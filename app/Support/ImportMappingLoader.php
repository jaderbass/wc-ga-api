<?php

namespace App\Support;

use RuntimeException;

final class ImportMappingLoader
{
    public static function load(string $name): array
    {
        $path = resource_path("import_mappings/{$name}.php");

        if (! is_file($path)) {
            throw new RuntimeException("Import mapping '{$name}' not found at {$path}");
        }

        $mapping = require $path;

        if (! is_array($mapping)) {
            throw new RuntimeException("Import mapping {$path} must return an array.");
        }

        return $mapping;
    }
}
