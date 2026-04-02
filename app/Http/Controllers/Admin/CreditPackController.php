<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreCreditPackRequest;
use App\Http\Requests\UpdateCreditPackRequest;
use App\Models\CreditPack;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;

class CreditPackController extends Controller
{
    public function index(): Response
    {
        return Inertia::render('Admin/CreditPacks/Index', [
            'packs' => CreditPack::query()
                ->ordered()
                ->get([
                    'id',
                    'name',
                    'description',
                    'credits',
                    'price_cents',
                    'currency',
                    'sort_order',
                    'is_active',
                    'created_at',
                    'updated_at',
                ]),
        ]);
    }

    public function store(StoreCreditPackRequest $request): RedirectResponse
    {
        CreditPack::query()->create($request->validated());

        return back()->with('success', 'Credit pack created.');
    }

    public function update(UpdateCreditPackRequest $request, CreditPack $pack): RedirectResponse
    {
        $pack->update($request->validated());

        return back()->with('success', 'Credit pack updated.');
    }

    public function destroy(CreditPack $pack): RedirectResponse
    {
        $pack->delete();

        return back()->with('success', 'Credit pack deleted.');
    }
}
