<?php

namespace App\Http\Controllers;

use App\Models\AuditLog;
use App\Models\CompanyEntry;
use App\Support\LedgerImport;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;

/** Import data (Super Admin): upload → validate → mapping → preview → duplicate check → confirm → log. The source file is kept unchanged. */
class ImportController extends Controller
{
    public function index(Request $request)
    {
        $path = session('import_file');
        $j = $path && Storage::disk('local')->exists($path) ? json_decode(Storage::disk('local')->get($path), true) : null;
        $a = $j ? LedgerImport::analyse($j) : null;
        $log = \App\Models\AuditLog::query()->with('user')->whereIn('action', ['company_import', 'import_uploaded'])->latest('id')->limit(10)->get();
        $step = ! $a ? 1 : 4;
        return view('imports', ['a' => $a, 'name' => session('import_name'), 'monthly' => LedgerImport::monthly(), 'log' => $log, 'step' => $step, 'hasEntries' => CompanyEntry::query()->exists()]);
    }

    public function upload(Request $request)
    {
        $request->validate(['file' => ['required', 'file', 'max:4096']]);
        $j = json_decode((string) file_get_contents($request->file('file')->getRealPath()), true);
        if (! is_array($j) || ! isset($j['entries'], $j['rates'])) return back()->withErrors(['file' => 'This is not a ledger export file (it needs "entries" and "rates").']);
        $path = $request->file('file')->storeAs('imports', now()->format('YmdHis').'-'.preg_replace('/[^A-Za-z0-9._-]/', '_', $request->file('file')->getClientOriginalName()), 'local');
        session(['import_file' => $path, 'import_name' => $request->file('file')->getClientOriginalName()]);
        AuditLog::record('import_uploaded', $request->file('file')->getClientOriginalName());
        return redirect()->route('imports.index')->with('ok', 'File read. Answer the questions below, then confirm.');
    }

    public function confirm(Request $request)
    {
        $path = session('import_file');
        abort_unless($path && Storage::disk('local')->exists($path), 404);
        $j = json_decode(Storage::disk('local')->get($path), true);
        $a = LedgerImport::analyse($j);
        $data = $request->validate([
            'dep_paid' => [$a['depPaid']->isNotEmpty() ? 'required' : 'nullable', Rule::in(['bill_payment', 'security'])],
            'comm' => [$a['comm']->isNotEmpty() ? 'required' : 'nullable', Rule::in(['incentive', 'commission'])],
            'opening_mode' => ['required', Rule::in(['zero', 'amount'])], 'opening' => ['required_if:opening_mode,amount', 'nullable', 'numeric'],
        ], ['dep_paid.required' => 'Say what the "Deposit paid" rows are.', 'comm.required' => 'Say whether the commission row is incentive or commission.']);
        if ($a['bad'] > 0) return back()->withErrors(['file' => $a['bad'].' rows have a bad date, size or no rate. Fix the source file first.']);
        $n = LedgerImport::run($j, $request->user()->id, ['dep_paid' => $data['dep_paid'] ?? 'bill_payment', 'comm' => $data['comm'] ?? 'incentive',
            'opening' => $data['opening_mode'] === 'amount' ? (float) $data['opening'] : 0, 'opening_date' => $a['from'] ?? now()->toDateString()]);
        AuditLog::record('company_import', "{$n} entries from ".session('import_name').' · deposit paid = '.($data['dep_paid'] ?? '—').' · commission row = '.($data['comm'] ?? '—').' · opening '.($data['opening_mode'] === 'amount' ? $data['opening'] : '0 confirmed'));
        session()->forget(['import_file', 'import_name']);
        return redirect()->route('imports.index')->with('ok', "{$n} entries imported. Duplicates were skipped. Check the month-by-month balance below.");
    }

    public function cancel()
    {
        session()->forget(['import_file', 'import_name']);
        return redirect()->route('imports.index');
    }
}
