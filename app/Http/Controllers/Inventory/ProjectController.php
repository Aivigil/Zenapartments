<?php

namespace App\Http\Controllers\Inventory;

use App\Http\Controllers\Controller;
use App\Http\Requests\Inventory\ProjectRequest;
use App\Models\Project;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;

class ProjectController extends Controller
{
    public function index(): Response
    {
        $this->authorize('viewAny', Project::class);

        $projects = Project::withCount(['units', 'blocks'])
            ->orderBy('name')
            ->get()
            ->map(fn ($p) => [
                'id' => $p->id,
                'code' => $p->code,
                'name' => $p->name,
                'location' => $p->location,
                'city' => $p->city,
                'country' => $p->country,
                'status' => $p->status,
                'units_count' => $p->units_count,
                'blocks_count' => $p->blocks_count,
            ]);

        return Inertia::render('Inventory/Projects/Index', [
            'projects' => $projects,
        ]);
    }

    public function create(): Response
    {
        $this->authorize('create', Project::class);
        return Inertia::render('Inventory/Projects/Form', [
            'project' => null,
        ]);
    }

    public function store(ProjectRequest $request): RedirectResponse
    {
        $project = Project::create($request->validated());
        return redirect()
            ->route('inventory.projects.show', $project)
            ->with('success', "Project {$project->name} created.");
    }

    public function show(Project $project): Response
    {
        $this->authorize('view', $project);

        $project->load(['blocks' => fn ($q) => $q->withCount('units')]);

        return Inertia::render('Inventory/Projects/Show', [
            'project' => [
                'id' => $project->id,
                'code' => $project->code,
                'name' => $project->name,
                'location' => $project->location,
                'city' => $project->city,
                'country' => $project->country,
                'status' => $project->status,
                'metadata' => $project->metadata,
                'blocks' => $project->blocks->map(fn ($b) => [
                    'id' => $b->id,
                    'code' => $b->code,
                    'name' => $b->name,
                    'block_type' => $b->block_type,
                    'units_count' => $b->units_count,
                ]),
            ],
        ]);
    }

    public function edit(Project $project): Response
    {
        $this->authorize('update', $project);
        return Inertia::render('Inventory/Projects/Form', [
            'project' => $project->only(['id', 'code', 'name', 'location', 'city', 'country', 'status', 'metadata']),
        ]);
    }

    public function update(ProjectRequest $request, Project $project): RedirectResponse
    {
        $project->update($request->validated());
        return redirect()
            ->route('inventory.projects.show', $project)
            ->with('success', "Project {$project->name} updated.");
    }

    public function destroy(Project $project): RedirectResponse
    {
        $this->authorize('delete', $project);
        $project->delete();
        return redirect()
            ->route('inventory.projects.index')
            ->with('success', "Project {$project->name} archived.");
    }

    private function authorize(string $ability, $arg): void
    {
        \Illuminate\Support\Facades\Gate::authorize($ability, $arg);
    }
}
