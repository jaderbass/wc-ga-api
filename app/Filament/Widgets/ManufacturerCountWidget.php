<?php

namespace App\Filament\Widgets;

use App\Models\Manufacturer;
use Filament\Widgets\Widget;

class ManufacturerCountWidget extends Widget
{
    protected static string $view = 'filament.widgets.manufacturer-count-widget';

    public int $count;

    public function mount()
    {
        $this->count = Manufacturer::count();
    }
}
