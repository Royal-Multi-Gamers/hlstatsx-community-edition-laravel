<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

class EnsureNotInstalled
{
    public function handle(Request $request, Closure $next)
    {
        if (env('APP_INSTALLED') === 'true') {
            return redirect('/');
        }
        return $next($request);
    }
}
