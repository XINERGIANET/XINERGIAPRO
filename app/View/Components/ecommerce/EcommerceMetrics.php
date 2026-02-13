<?php

namespace App\View\Components\ecommerce;

use Closure;
use Illuminate\Contracts\View\View;
use Illuminate\View\Component;

class EcommerceMetrics extends Component
{
    public $dashboardData;

    public function __construct($dashboardData = [])
    {
        $this->dashboardData = $dashboardData;
    }

    public function render(): View|Closure|string
    {
        return view('components.ecommerce.ecommerce-metrics');
    }
}
