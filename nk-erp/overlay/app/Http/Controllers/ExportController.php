<?php

namespace App\Http\Controllers;

use App\Models\AuditLog;
use App\Models\Brand;
use App\Models\Customer;
use App\Models\Export;
use App\Models\Route as RouteModel;
use App\Models\Sale;
use App\Models\Setting;
use App\Models\Trip;
use App\Models\Vehicle;
use App\Support\Reports;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\URL;
use Illuminate\Validation\Rule;

/** Document & export center: every report in PDF, Excel, CSV, Word, JSON or ZIP, with signed share links. */
class ExportController extends Controller
{
    public const FORMATS = ['PDF', 'Excel', 'CSV', 'Word', 'JSON', 'ZIP', 'PNG', 'A4 print', 'POS 80 mm'];

    private function filters(Request $r): array
    {
        $now = now()->timezone('Asia/Dhaka');
        return ['from' => $r->query('from', $r->input('from', $now->copy()->startOfMonth()->toDateString())), 'to' => $r->query('to', $r->input('to', $now->toDateString())),
            'brand' => $r->input('brand') ?: $r->query('brand'), 'customer' => $r->input('customer') ?: $r->query('customer'),
            'vehicle' => $r->input('vehicle') ?: $r->query('vehicle'), 'route' => $r->input('route') ?: $r->query('route'), 'page' => $r->input('page', $r->query('page', 'landscape'))];
    }

    public function index(Request $request)
    {
        $u = $request->user();
        $report = $request->query('report', 'route_vehicle');
        if (! Reports::allowed($report, $u->role)) $report = 'sales_list';
        $f = $this->filters($request);
        [$head, $rows, $total] = Reports::build($report, $f);
        Export::query()->where('status', 'ready')->where('expires_at', '<', now())->update(['status' => 'expired']);
        $hist = Export::query()->with('user')->when(! $u->isSuperAdmin(), fn ($q) => $q->where('user_id', $u->id))->latest('id')->limit(30)->get();
        $q = trim((string) $request->query('q', ''));
        $tab = $request->query('tab', 'all');
        $docs = collect();
        if (in_array($tab, ['all', 'invoices'], true)) {
            foreach (Sale::query()->with('customer')->when($q, fn ($x) => $x->where('invoice_no', 'ilike', "%$q%")->orWhereHas('customer', fn ($c) => $c->where('name', 'ilike', "%$q%")))->latest('id')->limit(12)->get() as $s) {
                $docs->push(['f' => 'PDF', 'n' => $s->invoice_no.' · '.$s->buyerName(), 'meta' => 'Sales invoice · '.$s->created_at->timezone('Asia/Dhaka')->format('d-m-Y').' · printed '.($s->pos_prints + $s->a4_prints).'×', 'v' => 1, 'url' => route('sales.print', $s)]);
            }
        }
        if (in_array($tab, ['all', 'statements'], true)) {
            foreach (Customer::query()->when($q, fn ($x) => $x->where('name', 'ilike', "%$q%"))->orderBy('name')->limit($tab === 'all' ? 4 : 30)->get() as $c) {
                $docs->push(['f' => 'PDF', 'n' => 'Statement · '.$c->name, 'meta' => 'Customer statement · today', 'v' => 1, 'url' => route('customers.statement', $c)]);
            }
        }
        if (in_array($tab, ['all', 'closing'], true)) {
            foreach (Trip::query()->with('vehicle')->where('status', 'closed')->when($q, fn ($x) => $x->where('trip_no', 'ilike', "%$q%"))->latest('id')->limit(10)->get() as $t) {
                $docs->push(['f' => 'PDF', 'n' => 'Daily closing · '.$t->vehicle->code.' · '.$t->trip_date->format('d-m'), 'meta' => 'Closing report · '.$t->trip_no, 'v' => 1, 'url' => route('trips.closing', ['trip' => $t->id])]);
            }
        }
        foreach ($hist->where('status', 'ready')->where('archived', $tab === 'archived') as $e) {
            if ($tab !== 'all' && $tab !== 'archived') break;
            $docs->push(['f' => ['Excel' => 'XLSX', 'ZIP' => 'ZIP', 'CSV' => 'CSV'][$e->format] ?? 'PDF', 'n' => (Reports::find($e->report)['label'] ?? $e->report).' · '.$e->filters['from'].' to '.$e->filters['to'],
                'meta' => $e->format.' export · '.$e->rows.' rows'.(Reports::find($e->report)['module'] === 'Super Admin (restricted)' ? ' · Super Admin only' : ''), 'v' => 1, 'url' => route('exports.download', $e)]);
        }
        return view('exports', ['report' => $report, 'f' => $f, 'head' => $head, 'rows' => $rows, 'total' => $total, 'hist' => $hist, 'docs' => $docs, 'q' => $q, 'tab' => $tab,
            'fmt' => in_array($request->query('fmt'), self::FORMATS, true) ? $request->query('fmt') : 'PDF', 'zip' => class_exists(\ZipArchive::class),
            'brands' => Brand::query()->orderBy('code')->get(), 'customers' => Customer::query()->orderBy('name')->get(['id', 'name']), 'vehicles' => Vehicle::query()->orderBy('code')->get(), 'routes' => RouteModel::query()->orderBy('name')->get()]);
    }

    public function generate(Request $request)
    {
        $data = $request->validate(['report' => ['required', 'string'], 'fmt' => ['required', Rule::in(self::FORMATS)], 'from' => ['required', 'date'], 'to' => ['required', 'date', 'after_or_equal:from']]);
        $u = $request->user();
        $f = $this->filters($request);
        if (! Reports::allowed($data['report'], $u->role)) {
            Export::create(['code' => Export::nextCode(), 'user_id' => $u->id, 'report' => $data['report'], 'format' => $data['fmt'], 'filters' => $f, 'status' => 'blocked']);
            AuditLog::record('export_blocked', $data['report']);
            abort(403, 'Your role may not export this report.');
        }
        if (in_array($data['fmt'], ['PDF', 'A4 print', 'POS 80 mm'], true)) {
            AuditLog::record('export_print', $data['report'].' '.$data['fmt']);
            return redirect()->route('exports.print', ['report' => $data['report'], 'size' => $data['fmt'] === 'POS 80 mm' ? '80' : 'a4', 'auto' => 1] + $f);
        }
        if ($data['fmt'] === 'PNG') return back()->withErrors(['fmt' => 'PNG: open the PDF preview and use your device\'s screenshot or "Save as image".']);
        [$head, $rows, $total] = Reports::build($data['report'], $f);
        $label = Reports::find($data['report'])['label'];
        $slug = \Illuminate\Support\Str::slug($label).'-'.$f['from'].'-'.$f['to'];
        $code = Export::nextCode();
        $all = array_merge([$head], $rows, $total ? [$total] : []);
        $make = [
            'CSV' => fn () => ["$slug.csv", "\xEF\xBB\xBF".collect($all)->map(fn ($r) => collect($r)->map(fn ($c) => is_numeric($c) ? $c : '"'.str_replace('"', '""', (string) $c).'"')->implode(','))->implode("\r\n")],
            'JSON' => fn () => ["$slug.json", json_encode(['report' => $label, 'from' => $f['from'], 'to' => $f['to'], 'columns' => $head, 'rows' => $rows, 'total' => $total], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE)],
            'Excel' => fn () => ["$slug.xls", $this->spreadsheetXml($label, $head, $rows, $total)],
            'Word' => fn () => ["$slug.doc", view('exports_print', ['label' => $label, 'f' => $f, 'head' => $head, 'rows' => $rows, 'total' => $total, 'size' => 'a4', 's' => Setting::snapshot(), 'auto' => false, 'word' => true])->render()],
        ];
        if ($data['fmt'] === 'ZIP') {
            if (! class_exists(\ZipArchive::class)) return back()->withErrors(['fmt' => 'ZIP is not available on this server yet.']);
            $tmp = tempnam(sys_get_temp_dir(), 'nkz');
            $z = new \ZipArchive(); $z->open($tmp, \ZipArchive::OVERWRITE);
            foreach (['CSV', 'Excel', 'JSON', 'Word'] as $k) { [$n, $c] = $make[$k](); $z->addFromString($n, $c); }
            $z->close();
            [$name, $content] = ["$slug.zip", file_get_contents($tmp)];
            @unlink($tmp);
        } else {
            [$name, $content] = $make[$data['fmt']]();
        }
        $path = 'exports/'.$code.'-'.$name;
        Storage::disk('local')->put($path, $content);
        $e = Export::create(['code' => $code, 'user_id' => $u->id, 'report' => $data['report'], 'format' => $data['fmt'], 'filters' => $f, 'rows' => count($rows), 'path' => $path, 'size' => strlen($content), 'status' => 'ready', 'expires_at' => now()->addDays(7)]);
        AuditLog::record('export', "{$code} {$label} {$data['fmt']}");
        if ($request->boolean('share')) {
            $url = URL::temporarySignedRoute('exports.shared', now()->addDays(7), ['export' => $e->id]);
            AuditLog::record('export_shared', $code);
            return back()->with('ok', "{$code} share link (valid 7 days): ".$url);
        }
        if ($request->boolean('stay')) return back()->with('ok', "{$code} ready.")->with('dl', route('exports.download', $e));
        return redirect()->route('exports.index', ['report' => $data['report'], 'fmt' => $data['fmt']] + $f)->with('ok', "{$code} ready.")->with('dl', route('exports.download', $e));
    }

    private function spreadsheetXml(string $label, array $head, array $rows, ?array $total): string
    {
        $cell = fn ($v, $style = null) => '<Cell'.($style ? ' ss:StyleID="'.$style.'"' : '').'><Data ss:Type="'.(is_int($v) || is_float($v) ? 'Number' : 'String').'">'.htmlspecialchars((string) $v, ENT_XML1).'</Data></Cell>';
        $x = '<?xml version="1.0" encoding="UTF-8"?><?mso-application progid="Excel.Sheet"?><Workbook xmlns="urn:schemas-microsoft-com:office:spreadsheet" xmlns:ss="urn:schemas-microsoft-com:office:spreadsheet" xmlns:x="urn:schemas-microsoft-com:office:excel">';
        $x .= '<Styles><Style ss:ID="h"><Font ss:Bold="1"/><Interior ss:Color="#F1F4F9" ss:Pattern="Solid"/></Style><Style ss:ID="t"><Font ss:Bold="1"/></Style></Styles>';
        $x .= '<Worksheet ss:Name="'.htmlspecialchars(mb_substr($label, 0, 30), ENT_XML1).'"><Table>';
        $x .= '<Row>'.implode('', array_map(fn ($h) => $cell($h, 'h'), $head)).'</Row>';
        foreach ($rows as $r) $x .= '<Row>'.implode('', array_map(fn ($c) => $cell($c), $r)).'</Row>';
        if ($total) $x .= '<Row>'.implode('', array_map(fn ($c) => $cell($c, 't'), $total)).'</Row>';
        $x .= '</Table><WorksheetOptions xmlns="urn:schemas-microsoft-com:office:excel"><FreezePanes/><FrozenNoSplit/><SplitHorizontal>1</SplitHorizontal><TopRowBottomPane>1</TopRowBottomPane><ActivePane>2</ActivePane></WorksheetOptions>';
        $x .= '<AutoFilter x:Range="R1C1:R1C'.count($head).'" xmlns="urn:schemas-microsoft-com:office:excel"/></Worksheet></Workbook>';
        return $x;
    }

    public function printView(Request $request)
    {
        $report = (string) $request->query('report');
        abort_unless(Reports::allowed($report, $request->user()->role), 403);
        $f = $this->filters($request);
        [$head, $rows, $total] = Reports::build($report, $f);
        return view('exports_print', ['label' => Reports::find($report)['label'], 'f' => $f, 'head' => $head, 'rows' => $rows, 'total' => $total,
            'size' => $request->query('size') === '80' ? '80' : 'a4', 's' => Setting::snapshot(), 'auto' => (bool) $request->query('auto'), 'word' => false]);
    }

    private function send(Export $export)
    {
        abort_unless($export->status === 'ready' && $export->path && Storage::disk('local')->exists($export->path), 404);
        $export->increment('downloads');
        return Storage::disk('local')->download($export->path, \Illuminate\Support\Str::after($export->path, $export->code.'-'));
    }

    public function download(Request $request, Export $export)
    {
        abort_unless($export->user_id === $request->user()->id || $request->user()->isSuperAdmin(), 403);
        abort_unless(Reports::allowed($export->report, $request->user()->role), 403);
        return $this->send($export);
    }

    public function share(Request $request, Export $export)
    {
        abort_unless($export->user_id === $request->user()->id || $request->user()->isSuperAdmin(), 403);
        $url = URL::temporarySignedRoute('exports.shared', now()->addDays(7), ['export' => $export->id]);
        AuditLog::record('export_shared', $export->code);
        return back()->with('ok', 'Share link (valid 7 days): '.$url);
    }

    public function shared(Export $export)
    {
        return $this->send($export);
    }

    public function archive(Request $request, Export $export)
    {
        abort_unless($export->user_id === $request->user()->id || $request->user()->isSuperAdmin(), 403);
        $export->update(['archived' => ! $export->archived]);
        return back();
    }

    public function bulkStatements(Request $request)
    {
        if (! class_exists(\ZipArchive::class)) return back()->withErrors(['fmt' => 'ZIP is not available on this server yet.']);
        $code = Export::nextCode();
        $tmp = tempnam(sys_get_temp_dir(), 'nkz');
        $z = new \ZipArchive(); $z->open($tmp, \ZipArchive::OVERWRITE);
        $n = 0;
        foreach (Customer::query()->orderBy('name')->get() as $c) {
            $rows = \App\Models\CustomerLedger::query()->where('customer_id', $c->id)->orderBy('id')->get();
            if ($rows->isEmpty()) continue;
            $run = 0; $rows->each(function ($r) use (&$run) { $run += (float) $r->debit - (float) $r->credit; $r->running = $run; });
            $z->addFromString(\Illuminate\Support\Str::slug($c->code.'-'.$c->name).'.html', view('statement_print', ['c' => $c, 'rows' => $rows, 's' => Setting::query()->pluck('value', 'key'), 'cyl' => $c->cylindersOut()])->render());
            $n++;
        }
        $z->close();
        $content = file_get_contents($tmp); @unlink($tmp);
        $path = 'exports/'.$code.'-customer-statements.zip';
        Storage::disk('local')->put($path, $content);
        Export::create(['code' => $code, 'user_id' => $request->user()->id, 'report' => 'customer_dues', 'format' => 'ZIP', 'filters' => $this->filters($request) + ['bulk' => 'statements'], 'rows' => $n, 'path' => $path, 'size' => strlen($content), 'status' => 'ready', 'expires_at' => now()->addDays(7)]);
        AuditLog::record('export_bulk', "{$code} {$n} customer statements");
        return back()->with('ok', "{$code}: {$n} customer statements zipped.");
    }
}
