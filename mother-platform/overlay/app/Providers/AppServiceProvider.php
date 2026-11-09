<?php
namespace App\Providers;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider {
  public function register(): void {}
  public function boot(): void {
    // Send server errors to Sentry (no composer package; see App\Support\SentryReport).
    try { $h = $this->app->make(\Illuminate\Contracts\Debug\ExceptionHandler::class); if (method_exists($h, 'reportable')) $h->reportable(function (\Throwable $e) { \App\Support\SentryReport::report($e); }); } catch (\Throwable $e) {}
    // Real visitor IP when the site is proxied by Cloudflare (runs after Laravel's TrustProxies).
    if (!$this->app->runningInConsole()) $this->app->make(\Illuminate\Contracts\Http\Kernel::class)->pushMiddleware(\App\Http\Middleware\CloudflareRealIp::class);
    if (!$this->app->runningInConsole()) $this->app->make(\Illuminate\Contracts\Http\Kernel::class)->pushMiddleware(\App\Http\Middleware\InvoiceDisplay::class);
    if (!$this->app->runningInConsole()) $this->app->make(\Illuminate\Contracts\Http\Kernel::class)->pushMiddleware(\App\Http\Middleware\MinifyHtml::class);
    if (!$this->app->runningInConsole()) $this->app->make(\Illuminate\Contracts\Http\Kernel::class)->pushMiddleware(\App\Http\Middleware\CanonicalHost::class);
    if (!$this->app->runningInConsole()) $this->app->make(\Illuminate\Contracts\Http\Kernel::class)->pushMiddleware(\App\Http\Middleware\BrandPrint::class);
    if (!$this->app->runningInConsole()) $this->app->make(\Illuminate\Contracts\Http\Kernel::class)->pushMiddleware(\App\Http\Middleware\SessionGuard::class);
  }
}
