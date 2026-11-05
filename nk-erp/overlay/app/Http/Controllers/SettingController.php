<?php

namespace App\Http\Controllers;

use App\Models\AuditLog;
use App\Models\Brand;
use App\Models\Setting;
use App\Models\Vehicle;
use Illuminate\Http\Request;

class SettingController extends Controller
{
    public function edit()
    {
        $values = Setting::query()->pluck('value', 'key');
        $log = AuditLog::query()->with('user')->whereIn('action', ['settings_changed', 'settings_field', 'settings_logo', 'settings_signature'])->latest('id')->limit(8)->get();
        return view('settings', ['fields' => Setting::FIELDS, 'values' => $values, 'log' => $log,
            'fleet' => Vehicle::query()->count(), 'sizes' => collect(Brand::SIZES)->map(fn ($s) => $s.' kg')->implode(', ')]);
    }

    public function update(Request $request)
    {
        $rules = ['logo' => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:2048'], 'signature' => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:1024']];
        foreach (array_keys(Setting::FIELDS) as $key) $rules[$key] = ['nullable', 'string', 'max:500'];
        $data = $request->validate($rules);

        $changed = 0;
        foreach (array_keys(Setting::FIELDS) as $key) {
            if (! $request->has($key)) continue;
            $value = $data[$key] === null ? null : trim($data[$key]);
            $old = Setting::get($key);
            if ($old !== $value) {
                Setting::put($key, $value);
                $changed++;
                AuditLog::record('settings_field', Setting::FIELDS[$key].($value === null || $value === '' ? ' cleared' : (mb_strlen($value) <= 40 ? ' set to "'.$value.'"' : ' updated')));
            }
        }
        foreach (['logo', 'signature'] as $f) {
            if (! $request->hasFile($f)) continue;
            $name = $f.'-'.now()->format('YmdHis').'.'.$request->file($f)->extension();
            $request->file($f)->move(public_path('uploads'), $name);
            Setting::put($f, '/uploads/'.$name);
            Setting::put($f.'_at', now()->timezone('Asia/Dhaka')->format('d-m-Y'));
            AuditLog::record('settings_'.$f, ucfirst($f).' uploaded');
            $changed++;
        }

        return back()->with('ok', $changed ? 'Settings saved. New documents use them from now on.' : 'Nothing changed.');
    }
}
