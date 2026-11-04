<?php

namespace App\Console\Commands;

use App\Models\Setting;
use App\Models\User;
use Illuminate\Console\Command;

class NkInstall extends Command
{
    protected $signature = 'nk:install';
    protected $description = 'Create the first Super Admin and the known company settings (runs safely on every boot).';

    public function handle(): int
    {
        $email = strtolower((string) env('NK_ADMIN_EMAIL'));
        $password = (string) env('NK_ADMIN_PASSWORD');

        if (! User::query()->where('role', 'super_admin')->exists()) {
            if ($email === '' || strlen($password) < 10) {
                $this->error('NK_ADMIN_EMAIL / NK_ADMIN_PASSWORD missing; no Super Admin created.');
                return self::FAILURE;
            }
            User::create([
                'name' => 'Mohammad Faruk',
                'email' => $email,
                'password' => $password,
                'role' => 'super_admin',
                'is_active' => true,
                'must_change_password' => true,
            ]);
            $this->info('Super Admin created: '.$email);
        }

        $defaults = [
            'company_name' => 'NK Corporation',
            'brand' => 'Aygaz United',
            'slogan' => 'Think Smart, Do Business',
            'phone' => '01901-168777',
            'whatsapp' => '01901-168777',
            'address_en' => 'Bhuiya Market, Mama Fakir Astana, South Sonapahar, Jorarganj, Mirsarai, Chattogram, Bangladesh',
            'address_bn' => 'বিএস আরমের ২ কিলোমিটার দক্ষিণে, মামা ফকির আস্তানা, ভুইয়া মার্কেট, দক্ষিণ সোনাপাহাড়, জোরারগঞ্জ, মীরসরাই, চট্টগ্রাম।',
        ];
        foreach ($defaults as $key => $value) {
            if (! Setting::query()->where('key', $key)->exists()) {
                Setting::put($key, $value);
            }
        }

        return self::SUCCESS;
    }
}
