<?php

namespace App\Filament\Components;

use Filament\Forms\Components\TextInput;

class EncryptedPassword extends TextInput
{
  protected string $encryptedField;

  public static function make(string $encryptedField): static
  {
    $component = parent::make($encryptedField . '_input');
    $component->encryptedField = $encryptedField;

    return $component
      ->password()
      ->dehydrated(false) // Verhindert automatisches Laden/Speichern
      ->default(null)
      ->helperText('Lass das Feld leer, um das bestehende Passwort beizubehalten.')
      ->saveRelationshipsUsing(function ($state, $record) use ($encryptedField) {
        if (!empty($state)) {
          $record->{$encryptedField} = safeEncrypt($state);
          $record->save();
        }
      });
  }
}
