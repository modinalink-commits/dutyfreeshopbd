<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
  public function up(): void {
    if (!Schema::hasTable('work_log')) Schema::create('work_log', function (Blueprint $t) {
      $t->id(); $t->string('title', 200); $t->string('status', 10)->default('pending'); $t->unsignedTinyInteger('percent')->default(0); $t->text('note')->nullable(); $t->timestamps();
    });
    if (DB::table('work_log')->exists()) return;
    $rows = [
      ['Deployment Center redesign (compact UI, history, filters)', 'done', 100, null],
      ['Product card Order Now icon → brand orange', 'done', 100, null],
      ['Flash Sale Order Now button (same standard + ⚡ accent)', 'done', 100, null],
      ['Product page Call / Message tiles upgrade', 'done', 100, null],
      ['Global admin design standard (typography, cards, tables, forms)', 'done', 100, null],
      ['Sidebar softer slate colour palette', 'done', 100, null],
      ['Referral / Affiliate system', 'partial', 90, 'Live, admin + customer UI done. Real customer end-to-end flow not tested yet.'],
      ['Date of Birth one-time entry in My Profile', 'partial', 85, 'Customer entry + lock done. Admin approval flow for later corrections not built (admin edits directly).'],
      ['Win-Back Offers / Customer Reactivation', 'partial', 90, 'Engine, settings, SMS/notification, coupon mode done. WhatsApp sending not implemented; automation is OFF until you enable it.'],
      ['Strict Bangladesh mobile validation (01XXXXXXXXX)', 'done', 100, 'Verified live: all invalid formats rejected with the 3 specified messages.'],
      ['Pages & SEO module', 'partial', 65, 'Done: pages manager, SEO score/audit, redirects, sitemap, settings, storefront meta, scheduling, revisions. Remaining: category/brand/product SEO UI, image alt audit, structured data, breadcrumbs, dynamic robots.txt, PDF/Word export.'],
      ['10 SEO blog posts', 'done', 100, 'All 10 live at /blog/… and in sitemap.'],
      ['Official favicon set (ICO, 16/32/48, 180, 192, 512, PWA)', 'done', 100, null],
      ['Delivery Charge module', 'partial', 90, 'Live: settings, zones, overrides, checkout + manual order, order/invoice breakdown, dashboard. Needs a real order test and your area zones.'],
      ['Fraud Check / Courier Reliability', 'partial', 85, 'Live. Needs your Steadfast / RedX / Pathao keys on /admin/fraud; live courier calls not tested yet.'],
      ['Order list readability (darker text, risk box, buttons)', 'done', 100, null],
      ['Work Log in Deployment Center (this list)', 'done', 100, null],
      ['Customer Login System — Email + Facebook', 'pending', 0, 'Spec received. Facebook App ID / Secret needed from you (entered in Settings, never in chat).'],
      ['Deployment Center — incremental file download (spec section 11+)', 'pending', 0, 'Spec was cut off; waiting for you to resend.'],
      ['Owner actions: change admin password, revoke leaked Meta tokens, delete test product ZZ TEST PRODUCT, review suspicious suppliers', 'pending', 0, 'Needs you.'],
    ];
    foreach ($rows as $i => $r) DB::table('work_log')->insert(['title' => $r[0], 'status' => $r[1], 'percent' => $r[2], 'note' => $r[3], 'created_at' => now()->addSeconds($i), 'updated_at' => now()->addSeconds($i)]);
  }
  public function down(): void {}
};
