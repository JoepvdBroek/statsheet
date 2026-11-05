<?php

use App\Http\Middleware\HandleInertiaRequests;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Middleware\AddLinkHeadersForPreloadedAssets;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Symfony\Component\HttpFoundation\Response;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->encryptCookies(except: ['sidebar_state']);

        $middleware->web(append: [
            HandleInertiaRequests::class,
            AddLinkHeadersForPreloadedAssets::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->shouldRenderJsonWhen(
            fn (Request $request) => $request->is('api/*') || $request->expectsJson(),
        );

        /*
         * In production, an Inertia visit that fails (status 400 or more) is answered as an Inertia response with
         * the same status and a `status` prop, so the app shows its in-page error state rather than Inertia's
         * raw-HTML modal. The client reads the status and keeps the page it's on, so the `error` component is
         * never drawn (resources/js/lib/failed-visits.ts). In development the HTML debug page stays, in the modal.
         */
        $exceptions->respond(function (Response $response, Throwable $exception, Request $request): Response {
            if (config('app.debug') || ! $request->inertia() || $response->getStatusCode() < 400) {
                return $response;
            }

            return Inertia::render('error', ['status' => $response->getStatusCode()])
                ->toResponse($request)
                ->setStatusCode($response->getStatusCode());
        });
    })->create();
