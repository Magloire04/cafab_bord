<?php

namespace App\Imports;

use Maatwebsite\Excel\Concerns\ToCollection;
use Maatwebsite\Excel\Concerns\WithHeadingRow;

class FillesPreviewImport implements ToCollection, WithHeadingRow
{
    public array $rows = [];

    public function collection($rows): void
    {
        $this->rows = $rows->map(fn ($row) => [
            'nom' => trim((string) ($row['nom'] ?? '')),
            'prenom' => trim((string) ($row['prenom'] ?? '')),
            'contact' => trim((string) ($row['contact'] ?? '')) ?: null,
        ])->values()->all();
    }
}
