<?php
namespace App\Providers;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider {
  public function register(): void {}
  public function boot(): void {
    // Real visitor IP when the site is proxied by Cloudflare (runs after Laravel's TrustProxies).
    if (!$this->app->runningInConsole()) $this->app->make(\Illuminate\Contracts\Http\Kernel::class)->pushMiddleware(\App\Http\Middleware\CloudflareRealIp::class);
    if (!$this->app->runningInConsole()) $this->app->make(\Illuminate\Contracts\Http\Kernel::class)->pushMiddleware(\App\Http\Middleware\InvoiceDisplay::class);
  }
}
