<?php

namespace App\Models;

use App\Enums\StatutPersonne;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Coach extends Model
{
    use HasFactory;

    protected $fillable = ['user_id', 'pin', 'contact', 'statut', 'date_entree'];

    protected function casts(): array
    {
        return [
            'statut' => StatutPersonne::class,
            'date_entree' => 'date',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
