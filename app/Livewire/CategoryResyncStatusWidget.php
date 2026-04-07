<?php

namespace App\Livewire;

use App\Services\Categories\CategoryResyncStatus as CategoryResyncStatusService;
use Livewire\Component;

class CategoryResyncStatusWidget extends Component
{
    public function render()
    {
        return view('livewire.category-resync-status-widget', [
            'run' => CategoryResyncStatusService::latest(),
            'status' => CategoryResyncStatusService::status(),
            'text' => CategoryResyncStatusService::text(),
            'progress' => CategoryResyncStatusService::progress(),
        ]);
    }
}
