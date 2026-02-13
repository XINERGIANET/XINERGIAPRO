<?php

namespace App\View\Components\ecommerce;

use Closure;
use Illuminate\Contracts\View\View;
use Illuminate\View\Component;

class MonthlyTarget extends Component
{
    public $occupancyData;

    public function __construct($occupancyData = [])
    {
        $this->occupancyData = $occupancyData;
    }

    public function render(): View|Closure|string
    {
        return view('components.ecommerce.monthly-target');
    }
}




