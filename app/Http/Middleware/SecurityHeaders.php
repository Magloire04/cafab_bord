<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class SecurityHeaders
{
    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);

        $response->headers->set('X-Content-Type-Options', 'nosniff');
        $response->headers->set('X-Frame-Options', 'DENY');
        $response->headers->set('Referrer-Policy', 'strict-origin-when-cross-origin');
        $response->headers->set(
            'Content-Security-Policy',
            // 'unsafe-eval' is required because the stock Alpine.js build (shipped via
            // Breeze's dropdown/modal components) evaluates directive expressions with
            // `new Function(...)`. Migrating to the @alpinejs/csp build was evaluated but
            // rejected here: it forbids any inline expression (assignments, ternaries,
            // even `open = ! open`) and would require rewriting dropdown.blade.php,
            // modal.blade.php (including its inline focus-trap methods) and
            // navigation.blade.php into Alpine.data()-registered components with no
            // remaining review round to catch regressions. See final-review-fix-report.md.
            "default-src 'self'; img-src 'self' data:; style-src 'self'; script-src 'self' 'unsafe-eval'; frame-ancestors 'none'"
        );

        return $response;
    }
}
