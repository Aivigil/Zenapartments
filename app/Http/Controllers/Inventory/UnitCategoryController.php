<?php

namespace App\Http\Controllers\Inventory;

use App\Http\Controllers\Controller;
use App\Http\Requests\Inventory\UnitCategoryRequest;
use App\Models\UnitCategory;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

class UnitCategoryController extends Controller
{
    public function index(): Response
    {
        Gate::authorize('viewAny', UnitCategory::class);
        return Inertia::render('Inventory/UnitCategories/Index', [
            'categories' => UnitCategory::withCount('units')->orderBy('name')->get(),
        ]);
    }

    public function create(): Response
    {
        Gate::authorize('create', UnitCategory::class);
        return Inertia::render('Inventory/UnitCategories/Form', ['category' => null]);
    }

    public function store(UnitCategoryRequest $request): RedirectResponse
    {
        UnitCategory::create($request->validated());
        return redirect()
            ->route('inventory.unit-categories.index')
            ->with('success', 'Category created.');
    }

    public function edit(UnitCategory $unitCategory): Response
    {
        Gate::authorize('update', $unitCategory);
        return Inertia::render('Inventory/UnitCategories/Form', ['category' => $unitCategory]);
    }

    public function update(UnitCategoryRequest $request, UnitCategory $unitCategory): RedirectResponse
    {
        $unitCategory->update($request->validated());
        return redirect()
            ->route('inventory.unit-categories.index')
            ->with('success', 'Category updated.');
    }

    public function destroy(UnitCategory $unitCategory): RedirectResponse
    {
        Gate::authorize('delete', $unitCategory);
        $unitCategory->delete();
        return redirect()
            ->route('inventory.unit-categories.index')
            ->with('success', 'Category archived.');
    }
}
