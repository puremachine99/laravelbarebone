<?php

namespace FluxPro;

use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Facades\Blade;

use Illuminate\Support\Facades\Route;

class FluxProServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->alias(FluxProManager::class, 'flux-pro');

        $this->app->singleton(FluxProManager::class);

        $loader = \Illuminate\Foundation\AliasLoader::getInstance();
        $loader->alias('FluxPro', \Flux\FluxPro::class);
    }

    public function boot(): void
    {
        $this->bootComponentPath();
        $this->bootAssetRoutes();
    }

    public function bootComponentPath()
    {
        Blade::anonymousComponentPath(__DIR__.'/../stubs/resources/views/flux', 'flux');
    }

    public function bootAssetRoutes()
    {
        Route::get('/flux/flux.js', function () {
            return response()->file(base_path('package/flux-pro/dist/flux.js'), [
                'Content-Type' => 'text/javascript; charset=utf-8',
            ]);
        });

        Route::get('/flux/flux.min.js', function () {
            return response()->file(base_path('package/flux-pro/dist/flux.min.js'), [
                'Content-Type' => 'text/javascript; charset=utf-8',
            ]);
        });
    }
}
