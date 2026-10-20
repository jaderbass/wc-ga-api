<?php

namespace App\Support\Concerns;

use App\Models\User;
use Throwable;

trait HasImportAuthor
{
    protected ?int $authorId = null;

    public function setAuthorId(?int $authorId): static
    {
        $this->authorId = $authorId;

        return $this;
    }

    /**
     * Autor für importierte Produkte: der gesetzte Benutzer, sonst der erste Admin,
     * sonst der erste vorhandene Benutzer. Früher fest Benutzer-ID 1 – die muss es
     * nicht geben (gelöscht), dann scheiterte der Import am Foreign Key.
     */
    protected function resolveAuthorId(): ?int
    {
        if ($this->authorId !== null) {
            return $this->authorId;
        }

        try {
            $id = User::role('Admin')->orderBy('id')->value('id');
        } catch (Throwable) {
            // Rolle "Admin" (noch) nicht vorhanden
            $id = null;
        }

        return $this->authorId = $id ?? User::query()->orderBy('id')->value('id');
    }
}
