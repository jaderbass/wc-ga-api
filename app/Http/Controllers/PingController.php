<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;

class PingController extends Controller
{
  public function __invoke(Request $request)
  {
    return response()->json(['ok' => true, 'ts' => now()->toIso8601String()]);
  }
}
