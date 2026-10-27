<?php

namespace App\Http\Controllers;

use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

/**
 * Hersteller-Logos ausliefern (unabhängig vom öffentlichen storage-Link des Webservers).
 */
class ManufacturerLogoController extends Controller
{
    public function show(string $file): BinaryFileResponse
    {
        $path = 'manufacturer-logos/'.basename($file);

        abort_unless(Storage::disk('public')->exists($path), 404);

        return response()->file(Storage::disk('public')->path($path), [
            'Cache-Control' => 'public, max-age=86400',
            // SVG nur als Bild: keine Skripte, falls die Datei direkt geöffnet wird
            'Content-Security-Policy' => "default-src 'none'; style-src 'unsafe-inline'",
            'X-Content-Type-Options' => 'nosniff',
        ]);
    }
}
