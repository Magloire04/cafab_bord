<?php

namespace App\Policies;

use App\Enums\UserRole;
use App\Models\PlanningRepetition;
use App\Models\User;

/**
 * Le coach voit tout le planning mais n'agit que sur ses propres créneaux.
 */
class PlanningRepetitionPolicy
{
    public function create(User $user): bool
    {
        return $user->role === UserRole::Admin || $user->coach !== null;
    }

    public function update(User $user, PlanningRepetition $planning): bool
    {
        if ($user->role === UserRole::Admin) {
            return true;
        }

        return $user->coach !== null && $planning->coach_id === $user->coach->id;
    }
}
