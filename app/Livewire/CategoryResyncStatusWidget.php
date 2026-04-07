<?php

namespace App\Livewire;

use App\Services\Categories\CategoryResyncStatus;
use Livewire\Component;

class CategoryResyncStatusWidget extends Component
{
    public function getRunProperty()
    {
        return CategoryResyncStatus::latest();
    }

    public function getStatusProperty(): ?string
    {
        return CategoryResyncStatus::status();
    }

    public function getTextProperty(): ?string
    {
        return CategoryResyncStatus::text();
    }

    public function getProgressProperty(): ?int
    {
        return CategoryResyncStatus::progress();
    }

    public function render()
    {
        return view('livewire.category-resync-status-widget');
    }
}
