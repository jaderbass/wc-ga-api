<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class AliensImageController extends Controller
{
  public function show(int $manufacturerId, int $productId, string $filename): BinaryFileResponse
  {
    // deine aktuelle Ablage (wie du sie hast)
    $path = public_path("storage/products/aliens/m{$manufacturerId}/p{$productId}/{$filename}");

    abort_unless(is_file($path), 404);

    return response()->file($path, [
      // optional: caching
      'Cache-Control' => 'public, max-age=31536000, immutable',
    ]);
  }
}
