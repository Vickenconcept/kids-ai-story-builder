<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreStoryPlanRequest;
use App\Http\Requests\UpdateStoryPlanRequest;
use App\Models\StoryPlan;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;

class StoryPlanController extends Controller
{
    public function index(): Response
    {
        return Inertia::render('Admin/Plans/Index', [
            'plans' => StoryPlan::query()
                ->ordered()
                ->get([
                    'id',
                    'name',
                    'description',
                    'tier',
                    'included_credits',
                    'price_cents',
                    'currency',
                    'sort_order',
                    'is_active',
                    'is_featured',
                    'feature_list',
                    'created_at',
                    'updated_at',
                ]),
        ]);
    }

    public function store(StoreStoryPlanRequest $request): RedirectResponse
    {
        StoryPlan::query()->create($request->validated());

        return back()->with('success', 'Plan created.');
    }

    public function update(UpdateStoryPlanRequest $request, StoryPlan $plan): RedirectResponse
    {
        $plan->update($request->validated());

        return back()->with('success', 'Plan updated.');
    }

    public function destroy(StoryPlan $plan): RedirectResponse
    {
        $plan->delete();

        return back()->with('success', 'Plan deleted.');
    }
}
