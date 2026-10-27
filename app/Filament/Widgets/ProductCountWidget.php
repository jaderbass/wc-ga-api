<?php

namespace App\Filament\Widgets;

use App\Models\Product;
use Filament\Widgets\Widget;

class ProductCountWidget extends Widget
{
    protected static string $view = 'filament.widgets.product-count-widget';

    public int $count;

    public function mount()
    {
        $this->count = Product::count();
    }
}
