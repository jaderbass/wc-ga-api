<?php

namespace App\Livewire\Dev;

use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Forms\Form;
use Filament\Forms\Components\Select;
use Livewire\Component;

class TomSelectDemo extends Component implements HasForms
{
  use InteractsWithForms;

  public ?array $data = [];

  public function mount(): void
  {
    $this->form->fill([
      'demo_shop_id' => null,
    ]);
  }

  public function form(Form $form): Form
  {
    return $form
      ->schema([
        Select::make('demo_shop_id')
          ->label('Shop Auswahl (Tom Select)')
          ->options([
            '1' => 'Shop 1',
            '2' => 'Shop 2',
            '3' => 'Shop 3',
          ])
          ->native(false)   // → Tom Select aktivieren
          ->searchable()
          ->preload()
          ->required(),
      ])
      ->statePath('data');
  }

  public function render()
  {
    return view('livewire.dev.tom-select-demo');
  }
}
