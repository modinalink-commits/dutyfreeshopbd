<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/** The customer portal: only a customer who logged in with the SMS code. */
class CustomerSession
{
    public function handle(Request $request, Closure $next): Response
    {
        $id = $request->session()->get('cust_id');
        $c = $id ? \App\Models\Customer::find($id) : null;
        if (! $c || ! $c->is_active || ! $c->portal_enabled) {
            $request->session()->forget('cust_id');
            return redirect()->route('my.login');
        }
        return $next($request);
    }
}
