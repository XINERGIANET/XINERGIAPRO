<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Facades\View;
use App\Models\MenuOption;
use Illuminate\Support\Str;

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
        // Compartir quickOptions con el header y el sidebar
        View::composer(['layouts.app-header', 'layouts.sidebar'], function ($view) {
            $quickOptions = MenuOption::where('status', 1)
                ->where('quick_access', 1)
                ->where(function ($query) {
                    $query->where('action', 'not like', 'areas.%')
                        ->where('action', 'not like', 'tables.%');
                })
                ->orderBy('id', 'asc')
                ->get();

            $quickOptions = $quickOptions->filter(function ($option) {
                $action = Str::lower((string) $option->action);
                return !Str::startsWith($action, [ '/areas', '/mesas']);
            })->values();

            $view->with('quickOptions', $quickOptions);
        });
    }
}
