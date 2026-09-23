<?php

namespace App\Exports;

use App\Models\Cachet;
use Illuminate\Database\Eloquent\Builder;
use Maatwebsite\Excel\Concerns\FromQuery;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;
use Maatwebsite\Excel\Concerns\WithStyles;
use Maatwebsite\Excel\Concerns\WithTitle;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

class DepensesPrestationsExport implements FromQuery, WithHeadings, WithMapping, WithStyles, WithTitle
{
    /**
     * @param  array{date_debut: ?string, date_fin: ?string, fille_id: ?int}  $filtres
     */
    public function __construct(private readonly array $filtres) {}

    public function query(): Builder
    {
        return Cachet::validees()
            ->entrePeriode($this->filtres['date_debut'], $this->filtres['date_fin'])
            ->pourFille($this->filtres['fille_id'])
            ->with(['prestation', 'fille'])
            ->orderByDesc('validee_at');
    }

    public function headings(): array
    {
        return ['Date de validation', 'Prestation', 'Date de la prestation', 'Fille', 'Montant (FCFA)', 'Référence Caisse CAFAB'];
    }

    public function map($cachet): array
    {
        return [
            $cachet->validee_at?->format('d/m/Y'),
            $cachet->prestation->titre,
            $cachet->prestation->date->format('d/m/Y'),
            "{$cachet->fille->prenom} {$cachet->fille->nom}",
            number_format((float) $cachet->montant, 2, ',', ' '),
            $cachet->caisse_cafab_reference ?? '-',
        ];
    }

    public function styles(Worksheet $sheet): array
    {
        return [1 => ['font' => ['bold' => true]]];
    }

    public function title(): string
    {
        return 'Dépenses prestations';
    }
}
