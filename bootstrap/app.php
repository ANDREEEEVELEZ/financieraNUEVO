<?php

use App\Http\Responses\ApiResponse;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

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

        // SecurityHeaders runs as the outermost global middleware so it adds headers
        // to ALL responses, including 429s from ThrottleRequests (filament-login).
        $middleware->prepend(\App\Http\Middleware\SecurityHeadersMiddleware::class);

        $middleware->web(append: [
            \App\Http\Middleware\CheckUserActive::class,
        ]);

        // CheckUserActive is applied per-route in routes/api.php (protected group only).
        // Do not add it here — the login route must remain unguarded.

        $middleware->alias([
            'role' => \Spatie\Permission\Middleware\RoleMiddleware::class,
            'permission' => \Spatie\Permission\Middleware\PermissionMiddleware::class,
            'role_or_permission' => \Spatie\Permission\Middleware\RoleOrPermissionMiddleware::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions) {
        // Render all exceptions as JSON for API routes and expectsJson requests.
        $exceptions->shouldRenderJsonWhen(
            fn (Request $request) => $request->is('api/*') || $request->expectsJson()
        );

        // 422 — Validation errors.
        $exceptions->render(function (ValidationException $e, Request $request) {
            return ApiResponse::error('Validation failed.', 422, $e->errors());
        });

        // 401 — Unauthenticated (API only; web falls through to Laravel's default login redirect).
        $exceptions->render(function (AuthenticationException $e, Request $request) {
            if (! ($request->is('api/*') || $request->expectsJson())) {
                return null;
            }
            return ApiResponse::error('Unauthenticated.', 401);
        });

        // 403 — Unauthorized (API only).
        $exceptions->render(function (AuthorizationException $e, Request $request) {
            if (! ($request->is('api/*') || $request->expectsJson())) {
                return null;
            }
            return ApiResponse::error('Unauthorized.', 403);
        });

        // 404 — Model not found (API only).
        $exceptions->render(function (ModelNotFoundException $e, Request $request) {
            if (! ($request->is('api/*') || $request->expectsJson())) {
                return null;
            }
            return ApiResponse::error('Resource not found.', 404);
        });

        // 404 — Route / URL not found (API only).
        $exceptions->render(function (NotFoundHttpException $e, Request $request) {
            if (! ($request->is('api/*') || $request->expectsJson())) {
                return null;
            }
            return ApiResponse::error('Resource not found.', 404);
        });

        // 500 — Catch-all for api/* routes only.
        // Plain \Exception from Pago::boot or domain code is mapped to 422.
        $exceptions->render(function (\Throwable $e, Request $request) {
            if ($request->is('api/*') && get_class($e) === \Exception::class) {
                \Illuminate\Support\Facades\Log::error('API domain exception', [
                    'message' => $e->getMessage(),
                    'trace'   => $e->getTraceAsString(),
                ]);

                return ApiResponse::error($e->getMessage(), 422);
            }
        });
    })->create();
