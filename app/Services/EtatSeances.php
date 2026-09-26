<?php

namespace App\Services;

use App\Enums\StatutPersonne;
use App\Enums\StatutPonctualite;
use App\Enums\StatutSeance;
use App\Enums\UserRole;
use App\Models\Fille;
use App\Models\Seance;
use App\Models\User;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

/**
 * État « en direct » des séances, lu par le bandeau (admin, coach) et par
 * le kiosque. Les phrases sont construites ici pour que le premier rendu
 * Blade et les rafraîchissements JavaScript affichent exactement la même chose.
 */
class EtatSeances
{
    public const HORIZON_JOURS = 14;

    /**
     * @return array{lignes: list<array{id: int, type: string, texte: string}>}
     */
    public function pourUtilisateur(User $user): array
    {
        $estAdmin = $user->role === UserRole::Admin;
        // Un compte coach sans fiche Coach ne voit rien (id 0 ne correspond à aucune séance).
        $coachId = $estAdmin ? null : ($user->coach?->id ?? 0);

        $lignes = $this->enCours($coachId)
            ->map(fn (Seance $seance) => [
                'id' => $seance->id,
                'type' => 'en_cours',
                'texte' => $this->texteEnCours($seance, $estAdmin),
            ])
            ->values()
            ->all();

        if ($lignes === [] && ($prochaine = $this->prochaine($coachId)) !== null) {
            $lignes[] = [
                'id' => $prochaine->id,
                'type' => 'prochaine',
                'texte' => 'Prochaine répétition : '.$this->quand($prochaine).($estAdmin ? ' · '.$this->nomCoach($prochaine) : ''),
            ];
        }

        return ['lignes' => $lignes];
    }

    /**
     * Données publiques du kiosque : aucun nom, uniquement des identifiants et des horaires.
     *
     * @return array{en_cours_id: ?int, prochaine: ?array{id: int, texte: string}}
     */
    public function pourKiosque(): array
    {
        $enCours = Seance::where('statut', StatutSeance::EnCours)
            ->whereDate('date', today())
            ->latest('heure_prevue')
            ->first();
        $prochaine = $this->prochaine(null);

        return [
            'en_cours_id' => $enCours?->id,
            'prochaine' => $prochaine ? ['id' => $prochaine->id, 'texte' => 'Prochaine répétition : '.$this->quand($prochaine)] : null,
        ];
    }

    private function enCours(?int $coachId): Collection
    {
        return Seance::with('coach.user')
            ->where('statut', StatutSeance::EnCours)
            ->whereDate('date', today())
            ->when($coachId !== null, fn ($query) => $query->where('coach_id', $coachId))
            ->orderBy('heure_prevue')
            ->get();
    }

    private function prochaine(?int $coachId): ?Seance
    {
        return Seance::with('coach.user')
            ->where('statut', StatutSeance::AVenir)
            ->whereDate('date', '>=', today())
            ->whereDate('date', '<', today()->addDays(self::HORIZON_JOURS))
            ->when($coachId !== null, fn ($query) => $query->where('coach_id', $coachId))
            ->orderBy('date')
            ->orderBy('heure_prevue')
            ->first();
    }

    private function texteEnCours(Seance $seance, bool $avecCoach): string
    {
        $presentes = $seance->pointages()
            ->where('pointable_type', Fille::class)
            ->where('statut_ponctualite', '!=', StatutPonctualite::Absent)
            ->count();
        $total = Fille::where('statut', StatutPersonne::Actif)->count();

        $texte = sprintf(
            'Répétition en cours depuis %s · %d %s sur %d',
            $this->heure($seance),
            $presentes,
            $presentes > 1 ? 'présentes' : 'présente',
            $total,
        );

        return $avecCoach ? $texte.' · '.$this->nomCoach($seance) : $texte;
    }

    private function quand(Seance $seance): string
    {
        $jour = match (true) {
            $seance->date->isToday() => "aujourd'hui",
            $seance->date->isTomorrow() => 'demain',
            default => $seance->date->translatedFormat('l j F'),
        };

        return $jour.' à '.$this->heure($seance);
    }

    private function heure(Seance $seance): string
    {
        return Carbon::parse($seance->heure_prevue)->format('H:i');
    }

    private function nomCoach(Seance $seance): string
    {
        return $seance->coach?->user?->name ?? 'coach inconnu';
    }
}
