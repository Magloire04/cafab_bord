<?php

namespace App\Policies;

use App\Enums\UserRole;
use App\Models\Fille;
use App\Models\User;

/**
 * Admin et coach gèrent les filles ensemble ; seule la désactivation reste à l'admin.
 */
class FillePolicy
{
    public function toggleStatut(User $user, Fille $fille): bool
    {
        return $user->role === UserRole::Admin;
    }
}
