<?php

namespace App\Http\Controllers;

use App\Models\AuditLog;
use App\Models\Setting;
use Illuminate\Http\Request;

class SettingController extends Controller
{
    public function edit()
    {
        $values = Setting::query()->pluck('value', 'key');
        return view('settings', ['fields' => Setting::FIELDS, 'values' => $values]);
    }

    public function update(Request $request)
    {
        $rules = [];
        foreach (array_keys(Setting::FIELDS) as $key) {
            $rules[$key] = ['nullable', 'string', 'max:300'];
        }
        $data = $request->validate($rules);

        $changed = [];
        foreach ($data as $key => $value) {
            $value = $value === null ? null : trim($value);
            if (Setting::get($key) !== $value) {
                Setting::put($key, $value);
                $changed[] = $key;
            }
        }
        if ($changed) {
            AuditLog::record('settings_changed', implode(', ', $changed));
        }

        return back()->with('ok', $changed ? 'Settings saved.' : 'Nothing changed.');
    }
}
