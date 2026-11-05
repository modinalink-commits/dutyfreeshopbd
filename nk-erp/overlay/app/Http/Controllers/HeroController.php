<?php

namespace App\Http\Controllers;

use App\Models\AuditLog;
use App\Models\Brand;
use App\Models\HeroSlide;
use App\Models\Setting;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/** Homepage hero slider. Edits are drafts; the website shows only what was last published. */
class HeroController extends Controller
{
    public function index(Request $request)
    {
        $slides = HeroSlide::query()->orderBy('position')->orderBy('id')->get();
        $cur = $request->query('new') ? null : ($slides->firstWhere('id', (int) $request->query('s')) ?? $slides->first());
        $published = json_decode((string) Setting::get('hero_published', ''), true);
        $draftNow = json_encode($slides->where('enabled', true)->map->publicData()->values());
        return view('hero', ['slides' => $slides, 'cur' => $cur, 'brands' => Brand::query()->where('for_sale', true)->pluck('name'),
            'publishedAt' => Setting::get('hero_published_at'), 'dirty' => $published === null || json_encode($published) !== $draftNow]);
    }

    public function save(Request $request)
    {
        $data = $request->validate([
            'id' => ['nullable', 'exists:hero_slides,id'], 'brand' => ['required', 'string', 'max:60'], 'size' => ['required', Rule::in(['12 KG', '35 KG', '45 KG'])],
            'enabled' => ['required', 'boolean'], 'heading' => ['required', 'string', 'max:120'], 'description' => ['nullable', 'string', 'max:300'], 'description_bn' => ['nullable', 'string', 'max:300'],
            'button_text' => ['nullable', 'string', 'max:40'], 'button_link' => ['required', Rule::in(array_keys(HeroSlide::LINKS))], 'animation' => ['required', Rule::in(array_keys(HeroSlide::ANIMS))],
            'duration' => ['required', Rule::in([4, 6, 8])], 'background' => ['required', Rule::in(array_keys(HeroSlide::BGS))],
            'image' => ['nullable', 'file', 'mimes:png,webp,jpg,jpeg', 'max:3072'],
        ]);
        $s = ! empty($data['id']) ? HeroSlide::findOrFail($data['id']) : new HeroSlide(['position' => (int) HeroSlide::query()->max('position') + 1]);
        unset($data['id'], $data['image']);
        if ($request->hasFile('image')) {
            $f = $request->file('image');
            $name = 'hero-'.now()->format('YmdHis');
            $dir = public_path('uploads');
            if (! is_dir($dir)) mkdir($dir, 0775, true);
            $src = null;
            if (function_exists('imagewebp')) {
                $src = match (strtolower($f->extension())) { 'png' => @imagecreatefrompng($f->getRealPath()), 'jpg', 'jpeg' => @imagecreatefromjpeg($f->getRealPath()), 'webp' => @imagecreatefromwebp($f->getRealPath()), default => null };
            }
            if ($src) {
                imagepalettetotruecolor($src); imagealphablending($src, true); imagesavealpha($src, true);
                imagewebp($src, $dir.'/'.$name.'.webp', 85);
                if (function_exists('imageavif')) @imageavif($src, $dir.'/'.$name.'.avif', 60);
                $data['image'] = '/uploads/'.$name.'.webp';
            } else {
                $f->move($dir, $name.'.'.$f->extension());
                $data['image'] = '/uploads/'.$name.'.'.$f->extension();
            }
        }
        $s->fill($data)->save();
        AuditLog::record('hero_slide_saved', "#{$s->id} {$s->heading}");
        return redirect()->route('hero.index', ['s' => $s->id])->with('ok', 'Slide saved as draft. Press Publish to put it on the website.');
    }

    public function move(Request $request, HeroSlide $slide)
    {
        $dir = $request->input('dir') === 'up' ? -1 : 1;
        $list = HeroSlide::query()->orderBy('position')->orderBy('id')->get()->values();
        $i = $list->search(fn ($x) => $x->id === $slide->id);
        $j = $i + $dir;
        if ($j >= 0 && $j < $list->count()) { $list[$i] = $list[$j]; $list[$j] = $slide; }
        foreach ($list as $k => $x) $x->update(['position' => $k + 1]);
        return back();
    }

    public function delete(HeroSlide $slide)
    {
        $slide->delete();
        AuditLog::record('hero_slide_deleted', $slide->heading);
        return redirect()->route('hero.index')->with('ok', 'Slide deleted from the draft. Publish to update the website.');
    }

    public function publish()
    {
        $data = HeroSlide::query()->where('enabled', true)->orderBy('position')->orderBy('id')->get()->map->publicData()->values();
        if ($data->isEmpty()) return back()->withErrors(['heading' => 'Turn on at least one slide before publishing.']);
        Setting::put('hero_published', json_encode($data, JSON_UNESCAPED_UNICODE));
        Setting::put('hero_published_at', now()->timezone('Asia/Dhaka')->format('d-m-Y H:i'));
        AuditLog::record('hero_published', $data->count().' slides');
        return back()->with('ok', 'Published. nklpgbd.com shows the new slider now.');
    }

    /** Public: the published slides for nklpgbd.com. */
    public function api()
    {
        $json = Setting::get('hero_published');
        return response($json ?: '[]', 200, ['Content-Type' => 'application/json', 'Cache-Control' => 'public, max-age=60']);
    }
}
