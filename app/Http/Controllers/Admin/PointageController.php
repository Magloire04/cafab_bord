<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\CorrigerPointageRequest;
use App\Models\Pointage;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class PointageController extends Controller
{
    public function index(Request $request): View
    {
        $query = Pointage::with(['seance', 'pointable']);

        if ($request->filled('pointable_type') && $request->filled('pointable_id')) {
            $query->where('pointable_type', $request->string('pointable_type'))
                ->where('pointable_id', $request->integer('pointable_id'));
        }

        $pointages = $query->orderByDesc('pointe_a')->paginate(30);

        return view('admin.pointages.index', compact('pointages'));
    }

    public function corriger(CorrigerPointageRequest $request, Pointage $pointage): RedirectResponse
    {
        $pointage->update([
            'statut_ponctualite' => $request->validated('statut_ponctualite'),
            'corrige_par_user_id' => $request->user()->id,
            'motif_correction' => $request->validated('motif'),
        ]);

        return redirect()->route('admin.pointages.index')->with('message', 'Pointage corrigé.');
    }
}
