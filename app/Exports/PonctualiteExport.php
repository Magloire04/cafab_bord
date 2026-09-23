<?php

namespace App\Exports;

use App\Services\PonctualiteRapportService;
use Illuminate\Support\Enumerable;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;
use Maatwebsite\Excel\Concerns\WithStyles;
use Maatwebsite\Excel\Concerns\WithTitle;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

class PonctualiteExport implements FromCollection, WithHeadings, WithMapping, WithStyles, WithTitle
{
    /**
     * @param  array{date_debut: ?string, date_fin: ?string, fille_id: ?int, coach_id: ?int}  $filtres
     */
    public function __construct(private readonly array $filtres) {}

    public function collection(): Enumerable
    {
        return (new PonctualiteRapportService)->generer(
            $this->filtres['date_debut'],
            $this->filtres['date_fin'],
            $this->filtres['fille_id'],
            $this->filtres['coach_id'],
        );
    }

    public function headings(): array
    {
        return ['Personne', 'Type', 'Présences', 'Retards', 'Retard cumulé (min)', 'Absences', 'Taux de présence (%)'];
    }

    public function map($ligne): array
    {
        return [
            $ligne['nom'],
            $ligne['type'],
            $ligne['presences'],
            $ligne['retards'],
            $ligne['retard_cumule'],
            $ligne['absences'],
            $ligne['taux_presence'],
        ];
    }

    public function styles(Worksheet $sheet): array
    {
        return [1 => ['font' => ['bold' => true]]];
    }

    public function title(): string
    {
        return 'Ponctualité';
    }
}
