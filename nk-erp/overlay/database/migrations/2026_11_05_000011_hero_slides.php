<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('hero_slides', function (Blueprint $table) {
            $table->id();
            $table->unsignedSmallInteger('position')->default(0);
            $table->string('brand', 60)->default('Aygaz United');
            $table->string('size', 10)->default('12 KG');
            $table->boolean('enabled')->default(true);
            $table->string('heading', 120);
            $table->string('description', 300)->nullable();
            $table->string('description_bn', 300)->nullable();
            $table->string('button_text', 40)->nullable();
            $table->string('button_link', 20)->default('order'); // order, prices, app
            $table->string('image', 255)->nullable();
            $table->string('animation', 12)->default('slide'); // slide, fade-float, fade
            $table->unsignedTinyInteger('duration')->default(6);
            $table->string('background', 12)->default('#f7f4ec');
            $table->timestamps();
        });
        // The three slides already live on nklpgbd.com.
        $now = now();
        foreach ([
            ['12 KG', 'For family kitchens. Refills delivered across Jorarganj and Mirsarai.', 'বাসা-বাড়ির রান্নার জন্য — জোরারগঞ্জ ও মীরসরাইতে।', '/img/cyl-12.webp'],
            ['35 KG', 'For hotels, restaurants and tea stalls.', 'হোটেল, রেস্টুরেন্ট ও চায়ের দোকানের জন্য।', '/img/cyl-35.webp'],
            ['45 KG', 'For large kitchens and industry. Bulk and dealer orders welcome.', 'বড় রান্নাঘর ও কারখানার জন্য।', '/img/cyl-45.webp'],
        ] as $i => [$size, $en, $bn, $img]) {
            DB::table('hero_slides')->insert(['position' => $i + 1, 'size' => $size, 'heading' => $size.' LPG Cylinder', 'description' => $en, 'description_bn' => $bn,
                'button_text' => 'Order '.$size, 'image' => $img, 'created_at' => $now, 'updated_at' => $now]);
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('hero_slides');
    }
};
