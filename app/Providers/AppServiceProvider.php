<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Facades\View;
use App\Models\MenuOption;

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
                ->orderBy('id', 'asc')
                ->get();
            
            $view->with('quickOptions', $quickOptions);
        });
    }
}
