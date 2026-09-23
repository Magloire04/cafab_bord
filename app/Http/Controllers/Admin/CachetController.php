<?php

namespace App\Http\Controllers\Admin;

use App\Enums\StatutCachet;
use App\Exceptions\CachetException;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\AjusterMontantCachetRequest;
use App\Http\Requests\Admin\CorrigerCachetRequest;
use App\Models\Cachet;
use App\Services\CachetService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class CachetController extends Controller
{
    public function valider(Request $request, Cachet $cachet, CachetService $service): RedirectResponse
    {
        try {
            $service->valider($cachet, $request->user());
        } catch (CachetException $e) {
            return redirect()->route('admin.prestations.show', $cachet->prestation_id)->with('error', $e->getMessage());
        }

        return redirect()->route('admin.prestations.show', $cachet->prestation_id)->with('message', 'Paiement validé.');
    }

    public function corriger(CorrigerCachetRequest $request, Cachet $cachet, CachetService $service): RedirectResponse
    {
        try {
            $service->corriger(
                $cachet,
                StatutCachet::from($request->validated('statut')),
                $request->validated('motif'),
                $request->user()
            );
        } catch (CachetException $e) {
            return redirect()->route('admin.prestations.show', $cachet->prestation_id)->with('error', $e->getMessage());
        }

        return redirect()->route('admin.prestations.show', $cachet->prestation_id)->with('message', 'Déclaration corrigée.');
    }

    public function ajusterMontant(AjusterMontantCachetRequest $request, Cachet $cachet, CachetService $service): RedirectResponse
    {
        try {
            $service->ajusterMontant($cachet, (float) $request->validated('montant'));
        } catch (CachetException $e) {
            return redirect()->route('admin.prestations.show', $cachet->prestation_id)->with('error', $e->getMessage());
        }

        return redirect()->route('admin.prestations.show', $cachet->prestation_id)->with('message', 'Montant ajusté.');
    }
}
