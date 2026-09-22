<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Imports\FillesPreviewImport;
use App\Models\Fille;
use App\Services\PinGenerator;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Maatwebsite\Excel\Facades\Excel;

class FilleImportController extends Controller
{
    public function form(): View
    {
        return view('admin.filles.import');
    }

    public function preview(Request $request): View
    {
        $request->validate([
            'fichier' => ['required', 'file', 'mimes:xlsx,xls'],
        ]);

        $import = new FillesPreviewImport;
        Excel::import($import, $request->file('fichier'));

        $rows = collect($import->rows)->map(function (array $row) {
            $row['doublon'] = Fille::where('nom', $row['nom'])
                ->where('prenom', $row['prenom'])
                ->exists();

            return $row;
        })->all();

        $request->session()->put('import_filles_rows', $rows);

        return view('admin.filles.import-preview', ['rows' => $rows]);
    }

    public function confirm(Request $request, PinGenerator $pinGenerator): RedirectResponse
    {
        $validated = $request->validate([
            'lignes' => ['required', 'array'],
            'lignes.*' => ['integer'],
        ]);

        $rows = $request->session()->get('import_filles_rows', []);

        foreach ($validated['lignes'] as $index) {
            if (! isset($rows[$index])) {
                continue;
            }

            $row = $rows[$index];

            Fille::create([
                'nom' => $row['nom'],
                'prenom' => $row['prenom'],
                'contact' => $row['contact'],
                'pin' => $pinGenerator->generate(),
                'date_entree' => now()->toDateString(),
            ]);
        }

        $request->session()->forget('import_filles_rows');

        return redirect()->route('admin.filles.index')->with('message', 'Import terminé.');
    }
}
