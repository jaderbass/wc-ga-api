<?php

namespace App\Livewire;

use App\Services\Categories\CategoryResyncStatus;
use Livewire\Component;

class CategoryResyncStatusWidget extends Component
{
    public function render()
    {
        return view('livewire.category-resync-status-widget', [
            'run' => CategoryResyncStatus::latest(),
            'status' => CategoryResyncStatus::status(),
            'text' => CategoryResyncStatus::text(),
            'progress' => CategoryResyncStatus::progress(),
        ]);
    }
}
