<?php

use Illuminate\Contracts\Encryption\DecryptException;
use Illuminate\Contracts\Encryption\EncryptException;

if (! function_exists('safeDecrypt')) {
  function safeDecrypt(?string $value, ?string $default = null): ?string
  {
    if (empty($value)) {
      return $default;
    }

    try {
      return decrypt($value);
    } catch (DecryptException $e) {
      \Log::warning('Decrypt fehlgeschlagen', [
        'value' => $value,
        'error' => $e->getMessage()
      ]);
      return $default;
    }
  }
}

if (! function_exists('safeEncrypt')) {
  function safeEncrypt(?string $value, ?string $default = null): ?string
  {
    if (empty($value)) {
      return $default;
    }

    try {
      return encrypt($value);
    } catch (EncryptException $e) {
      \Log::error('Encrypt fehlgeschlagen', [
        'value' => $value,
        'error' => $e->getMessage()
      ]);
      return $default;
    }
  }
}
