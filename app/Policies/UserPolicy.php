<?php

namespace App\Policies;

use App\Models\User;

/**
 * Benutzerverwaltung im Backend. Wer ins Backend darf, entscheidet
 * {@see User::canAccessPanel()}; hier geht es nur um geschützte Konten:
 * Die darf nur das Konto selbst ändern oder löschen.
 */
class UserPolicy
{
    public function viewAny(User $user): bool
    {
        return true;
    }

    public function view(User $user, User $model): bool
    {
        return true;
    }

    public function create(User $user): bool
    {
        return true;
    }

    public function update(User $user, User $model): bool
    {
        return $this->mayManage($user, $model);
    }

    public function delete(User $user, User $model): bool
    {
        return $this->mayManage($user, $model);
    }

    public function deleteAny(User $user): bool
    {
        return true;
    }

    public function restore(User $user, User $model): bool
    {
        return $this->mayManage($user, $model);
    }

    public function forceDelete(User $user, User $model): bool
    {
        return $this->mayManage($user, $model);
    }

    protected function mayManage(User $user, User $model): bool
    {
        return ! $model->isProtectedAccount() || $user->is($model);
    }
}
