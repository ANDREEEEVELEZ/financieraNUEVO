<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware) {
        // Trusted proxies: use TRUSTED_PROXIES env var (comma-separated IPs or CIDR).
        // Set to null when the load balancer rewrites REMOTE_ADDR directly.
        // NEVER use '*' — it allows X-Forwarded-For spoofing.
        $middleware->trustProxies(
            at: env('TRUSTED_PROXIES', null)
        );
        
        // Trusted hosts — set TRUSTED_HOSTS in .env as a comma-separated list.
        // Keeping the hostname out of source code avoids exposing infrastructure details.
        $middleware->trustHosts(at: array_filter(
            explode(',', env('TRUSTED_HOSTS', 'localhost'))
        ));
        
        $middleware->web(append: [
            \App\Http\Middleware\SecurityHeadersMiddleware::class,
        ]);

        $middleware->alias([
            'role' => \Spatie\Permission\Middleware\RoleMiddleware::class,
            'permission' => \Spatie\Permission\Middleware\PermissionMiddleware::class,
            'role_or_permission' => \Spatie\Permission\Middleware\RoleOrPermissionMiddleware::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions) {
        //
    })->create();