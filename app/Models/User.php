<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use App\Enums\StatutPersonne;
use App\Enums\UserRole;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, Notifiable;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'name',
        'email',
        'password',
        'role',
        'must_change_password',
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var list<string>
     */
    protected $hidden = [
        'password',
        'remember_token',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'role' => UserRole::class,
            'must_change_password' => 'boolean',
        ];
    }

    public function coach(): HasOne
    {
        return $this->hasOne(Coach::class);
    }

    /**
     * Compte coach dont la fiche Coach est désactivée : il ne se connecte plus
     * et ses sessions ouvertes tombent. La fiche est relue à chaque appel pour
     * qu'une désactivation prenne effet dès la requête suivante. Un admin, ou
     * un compte coach sans fiche, n'est jamais concerné.
     */
    public function estCoachDesactive(): bool
    {
        return $this->role === UserRole::Coach
            && $this->coach()->where('statut', StatutPersonne::Inactif)->exists();
    }

    public function initials(): string
    {
        return collect(explode(' ', $this->name))
            ->filter()
            ->map(fn (string $word) => mb_strtoupper(mb_substr($word, 0, 1)))
            ->take(2)
            ->join('');
    }
}
