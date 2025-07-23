<?php

namespace App\Filament\Widgets;

use Filament\Widgets\Widget;
use App\Models\Manufacturer;

class ManufacturerCountWidget extends Widget
{
    protected static string $view = 'filament.widgets.manufacturer-count-widget';
    public int $count;

    public function mount()
    {
        $this->count = Manufacturer::count();
    }
}
