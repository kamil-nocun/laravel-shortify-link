<?php

namespace App\Providers;

use App\Models\Link;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider;
use Illuminate\Validation\Rules\Password;
use Inertia\ExceptionResponse;
use Inertia\Inertia;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        $this->configureDefaults();
        $this->scopeAdminRouteBindings();
        $this->renderRefusedRedirectsAsNotFoundPage();
    }

    /**
     * Resolve user and link bindings within an administrator's scope on admin routes.
     *
     * Regular users are rejected by the admin middleware (403), so the binding must not turn that into a 404.
     */
    protected function scopeAdminRouteBindings(): void
    {
        foreach (['user' => User::class, 'link' => Link::class] as $key => $model) {
            Route::bind($key, function (string $value) use ($model): Model {
                $actor = request()->user();

                return request()->routeIs('admin.*') && $actor?->isAdmin()
                    ? $model::visibleTo($actor)->findOrFail($value)
                    : $model::findOrFail($value);
            });
        }
    }

    /**
     * Show the Inertia 404 page when a short link cannot be followed.
     */
    protected function renderRefusedRedirectsAsNotFoundPage(): void
    {
        Inertia::handleExceptionsUsing(fn (ExceptionResponse $response) => $response->request->routeIs('links.redirect')
            && ! $response->request->expectsJson()
            && $response->statusCode() === 404
                ? $response->render('errors/NotFound')
                : null);
    }

    /**
     * Configure default behaviors for production-ready applications.
     */
    protected function configureDefaults(): void
    {
        Date::use(CarbonImmutable::class);

        DB::prohibitDestructiveCommands(
            app()->isProduction(),
        );

        Password::defaults(fn (): ?Password => app()->isProduction()
            ? Password::min(12)
                ->mixedCase()
                ->letters()
                ->numbers()
                ->symbols()
                ->uncompromised()
            : null,
        );
    }
}
