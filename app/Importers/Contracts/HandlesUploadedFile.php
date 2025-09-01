<?php

namespace App\Importers\Contracts;

use Livewire\Features\SupportFileUploads\TemporaryUploadedFile;

interface HandlesUploadedFile
{
  public function handleUploadedFile(TemporaryUploadedFile $file): void;
}
