<?php

namespace App\Filament\Imports;

use Filament\Actions\Imports\Importer;
use Filament\Actions\Imports\Models\Import;
use Illuminate\Http\File;
use Illuminate\Support\Facades\Storage;
use App\Services\ImporterSelector;
use Livewire\Features\SupportFileUploads\TemporaryUploadedFile;

class ProductImporter extends Importer
{
    public static function getColumns(): array
    {
        // Diese Methode wird ignoriert – echtes Mapping passiert im Sub-Importer
        return [];
    }

    public function handleUploadedFile(TemporaryUploadedFile $file, array $formData): void
    {
        
        $manufacturerId = $formData['manufacturer_id'];

        $importer = ImporterSelector::forManufacturer($manufacturerId);
        $importer->handleUploadedFile($file);
    }

    public static function getCompletedNotificationBody(Import $import): string
    {
        return 'Der Import wurde gestartet. Sie erhalten eine Benachrichtigung nach Abschluss.';
    }
}
