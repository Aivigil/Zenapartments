<?php

namespace App\Http\Controllers\Inventory;

use App\Http\Controllers\Controller;
use App\Http\Requests\Inventory\BlockRequest;
use App\Models\Block;
use App\Models\Project;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

class BlockController extends Controller
{
    public function create(Project $project): Response
    {
        Gate::authorize('create', Block::class);
        return Inertia::render('Inventory/Blocks/Form', [
            'project' => $project->only(['id', 'name']),
            'block' => null,
        ]);
    }

    public function store(BlockRequest $request, Project $project): RedirectResponse
    {
        Gate::authorize('create', Block::class);
        $block = $project->blocks()->create($request->validated());
        return redirect()
            ->route('inventory.projects.show', $project)
            ->with('success', "Block {$block->name} added.");
    }

    public function show(Block $block): Response
    {
        Gate::authorize('view', $block);

        $block->load(['project', 'units.category']);

        return Inertia::render('Inventory/Blocks/Show', [
            'block' => [
                'id' => $block->id,
                'code' => $block->code,
                'name' => $block->name,
                'block_type' => $block->block_type,
                'project' => $block->project->only(['id', 'name']),
                'units' => $block->units->map(fn ($u) => [
                    'id' => $u->id,
                    'code' => $u->code,
                    'name' => $u->name,
                    'category' => $u->category->name,
                    'size_value' => $u->size_value,
                    'size_unit' => $u->size_unit,
                    'base_price_minor' => $u->base_price_minor,
                    'currency' => $u->currency,
                    'status' => $u->status,
                ]),
            ],
        ]);
    }

    public function edit(Block $block): Response
    {
        Gate::authorize('update', $block);
        return Inertia::render('Inventory/Blocks/Form', [
            'project' => $block->project->only(['id', 'name']),
            'block' => $block->only(['id', 'code', 'name', 'block_type', 'sort_order', 'metadata']),
        ]);
    }

    public function update(BlockRequest $request, Block $block): RedirectResponse
    {
        Gate::authorize('update', $block);
        $block->update($request->validated());
        return redirect()
            ->route('inventory.projects.show', $block->project_id)
            ->with('success', "Block {$block->name} updated.");
    }

    public function destroy(Block $block): RedirectResponse
    {
        Gate::authorize('delete', $block);
        $projectId = $block->project_id;
        $block->delete();
        return redirect()
            ->route('inventory.projects.show', $projectId)
            ->with('success', 'Block archived.');
    }
}
