<?php

namespace App\Filament\Widgets;

use App\Models\AssemblyGroupResyncRun;
use Filament\Widgets\Widget;

class AssemblyGroupResyncStatusWidget extends Widget
{
    protected static string $view = 'filament.widgets.assembly-group-resync-status';

    // protected static ?string $pollingInterval = '2s';

    protected int|string|array $columnSpan = 'full';

    public function getRun(): ?AssemblyGroupResyncRun
    {
        return AssemblyGroupResyncRun::latest()->first();
    }
}
