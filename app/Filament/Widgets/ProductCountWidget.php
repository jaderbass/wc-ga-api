<?php

namespace App\Filament\Widgets;

use Filament\Widgets\Widget;
use App\Models\Product;

class ProductCountWidget extends Widget
{
    protected static string $view = 'filament.widgets.product-count-widget';
    public int $count;

    public function mount()
    {
        $this->count = Product::count();
    }
}
