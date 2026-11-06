<?php

namespace App\Console\Commands;

use App\Models\Brand;
use App\Models\Product;
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
            'supplier_name' => 'AYGas',
            'supplier_branch' => 'Zorarganj Mirsarai branch',
            'supplier_code' => 'Cusc000419',
            'supplier_status' => 'Exclusive',
            'address_bn' => 'বিএস আরমের ২ কিলোমিটার দক্ষিণে, মামা ফকির আস্তানা, ভুইয়া মার্কেট, দক্ষিণ সোনাপাহাড়, জোরারগঞ্জ, মীরসরাই, চট্টগ্রাম।',
        ];
        foreach ($defaults as $key => $value) {
            if (! Setting::query()->where('key', $key)->exists()) {
                Setting::put($key, $value);
            }
        }

        if (! Brand::query()->exists()) {
            $names = ['Aygaz United', 'Bashundhara', 'Omera', 'Jamuna', 'Fresh', 'BM', 'Petromax', 'LAUGFS', 'JMI', 'Navana', 'Delta', 'SKS/Sena', 'Beximco', 'Totalgaz', 'Unigas', 'Orion', 'Bengal', 'Dubai Bangla', 'Universal', 'Promita', 'Green', 'Eurogaz', 'TMSS', 'Premier', 'Padma', 'City', 'G Gas', 'Super LPG'];
            foreach ($names as $i => $name) {
                $own = $i === 0;
                Brand::create([
                    'code' => 'BR-'.str_pad((string) ($i + 1), 2, '0', STR_PAD_LEFT),
                    'name' => $name,
                    'sizes' => $own ? [12, 35, 45] : [12],
                    'is_active' => $own,
                    'for_sale' => $own,
                    'empty_collection' => true,
                    'remarks' => $own ? 'Our brand · distributor' : null,
                    'position' => $i + 1,
                ]);
            }
            $this->info('Brand list created.');
        }

        if (! Product::query()->exists()) {
            $own = Brand::query()->where('for_sale', true)->orderBy('code')->first();
            if ($own) {
                $n = 1;
                foreach ([['refill', 'Refill', 'Customer returns an empty cylinder'], ['new_package', 'New package', 'Cylinder + gas, no empty needed']] as [$cat, $label, $note]) {
                    foreach ([12, 35, 45] as $size) {
                        Product::create(['code' => 'P-'.str_pad((string) $n++, 3, '0', STR_PAD_LEFT), 'name' => "{$label} {$size} kg", 'category' => $cat,
                            'brand_id' => $own->id, 'size_kg' => $size, 'note' => $note, 'is_active' => true]);
                    }
                }
                $this->info('Cylinder products created (prices to be set by the owner).');
            }
        }

        // One time: give every cylinder product without a photo its brand's picture (brand name and size on the body).
        // Runs once, so a photo the owner removes on purpose is not put back on the next update.
        if (Setting::get('cylinder_photos_v1') === null) {
            $n = 0;
            foreach (Product::query()->with('brand')->whereNull('image')->whereIn('category', ['refill', 'new_package'])->whereNotNull('brand_id')->get() as $p) {
                if ($img = $p->brand?->img($p->size_kg)) { $p->forceFill(['image' => $img])->save(); $n++; }
            }
            Setting::put('cylinder_photos_v1', now()->toDateTimeString());
            $this->info("Cylinder photos set on {$n} products.");
        }

        // One time, asked by the owner on 06-11-2026: every cylinder brand active (empty collection and exchange for all brands).
        if (Setting::get('brands_all_active_v1') === null) {
            $n = Brand::query()->where('is_active', false)->update(['is_active' => true]);
            Setting::put('brands_all_active_v1', now()->toDateTimeString());
            if ($n) \App\Models\AuditLog::record('brands_activated', "{$n} cylinder brands set Active (owner request)");
            $this->info("{$n} brands set Active.");
        }

        // Every active brand gets its Refill / New package products (with photo) in Products & prices.
        $made = 0;
        foreach (Brand::query()->where('is_active', true)->orderBy('code')->get() as $b) $made += Product::syncBrand($b);
        if ($made) $this->info("{$made} brand products added.");

        return self::SUCCESS;
    }
}
