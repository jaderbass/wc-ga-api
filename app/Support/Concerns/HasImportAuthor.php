<?php

namespace App\Support\Concerns;

trait HasImportAuthor
{
  protected ?int $authorId = null;

  public function setAuthorId(?int $authorId): static
  {
    $this->authorId = $authorId;
    return $this;
  }

  protected function resolveAuthorId(int $fallbackUserId = 1): int
  {
    return $this->authorId ?? $fallbackUserId;
  }
}
