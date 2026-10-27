<?php

namespace App\Http\Controllers;

use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

/**
 * Selbst abgelegte Produktbilder ausliefern (storage/app/public/product-images/<id>/<datei>),
 * unabhängig vom öffentlichen storage-Link des Webservers.
 */
class ProductImageFileController extends Controller
{
    public function show(int $product, string $file): BinaryFileResponse
    {
        $path = 'product-images/'.$product.'/'.basename($file);

        abort_unless(Storage::disk('public')->exists($path), 404);

        return response()->file(Storage::disk('public')->path($path), [
            'Cache-Control' => 'public, max-age=86400',
            'X-Content-Type-Options' => 'nosniff',
        ]);
    }
}
