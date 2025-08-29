<?php

namespace App\Filament\Imports;

use Filament\Actions\Imports\Importer;
use Filament\Actions\Imports\Models\Import;
use App\Services\ImporterSelector;
use Illuminate\Support\Facades\Log;
use Livewire\Features\SupportFileUploads\TemporaryUploadedFile;
use App\Importers\Contracts\CsvImporterContract;
use App\Importers\Contracts\HandlesUploadedFile;

class ProductImporter extends Importer
{
    public static function getColumns(): array
    {
        // Diese Methode wird ignoriert – echtes Mapping passiert im Sub-Importer
        return [];
    }

    public function handleUploadedFile(TemporaryUploadedFile $file, array $formData): void
    {
        $manufacturerId = (int) ($formData['manufacturer_id'] ?? 0);
        Log::debug('ProductImporter.handleUploadedFile ENTER', ['manufacturer_id' => $manufacturerId]);

        $importer = \App\Services\ImporterSelector::forManufacturer($manufacturerId);

        if ($importer instanceof HandlesUploadedFile) {
            Log::debug('ProductImporter using handleUploadedFile', ['class' => get_class($importer)]);
            $importer->handleUploadedFile($file);
            return;
        }

        if ($importer instanceof CsvImporterContract) {
            $path = $file->getRealPath() ?: $file->getPathname();
            Log::debug('ProductImporter using import($path)', ['class' => get_class($importer), 'path' => $path]);
            $importer->import($path);
            return;
        }

        throw new \RuntimeException(sprintf(
            '%s implementiert weder HandlesUploadedFile noch CsvImporterContract.',
            get_class($importer)
        ));
    }

    public static function getCompletedNotificationBody(Import $import): string
    {
        return 'Der Import wurde gestartet. Sie erhalten eine Benachrichtigung nach Abschluss.';
    }
}
