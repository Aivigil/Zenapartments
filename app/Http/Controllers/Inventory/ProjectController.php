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

    /**
     * Visual grid view — color-coded inventory map per block.
     *
     * Each unit card carries enough context to drive tooltips without an
     * N+1 (status colour, category, active booking client, outstanding).
     */
    public function grid(Project $project): Response
    {
        $this->authorize('view', $project);

        $project->load([
            'blocks' => fn ($q) => $q->orderBy('sort_order')->orderBy('code'),
            'blocks.units' => fn ($q) => $q->with([
                'category:id,name,kind',
                'bookings' => fn ($b) => $b->where('status', 'active')
                    ->with('client:id,code,full_name'),
            ])->orderBy('code'),
        ]);

        $blocks = $project->blocks->map(function ($block) {
            $units = $block->units->map(function ($u) {
                $b = $u->bookings->first();
                $outstanding = $b ? $b->outstandingMinor() : null;
                return [
                    'id' => $u->id,
                    'code' => $u->code,
                    'name' => $u->name,
                    'status' => $u->status->value,
                    'status_label' => $u->status->label(),
                    'colour' => $u->status->colour(),
                    'category' => $u->category?->name,
                    'category_kind' => $u->category?->kind,
                    'base_price_minor' => (int) $u->base_price_minor,
                    'size' => $u->size_value ? rtrim(rtrim((string) $u->size_value, '0'), '.') . ' ' . $u->size_unit : null,
                    'booking_id' => $b?->id,
                    'booking_code' => $b?->code,
                    'client_id' => $b?->client?->id,
                    'client_code' => $b?->client?->code,
                    'client_name' => $b?->client?->full_name,
                    'outstanding_minor' => $outstanding,
                ];
            });

            $statusCounts = $units->groupBy('status')->map->count();

            return [
                'id' => $block->id,
                'code' => $block->code,
                'name' => $block->name,
                'block_type' => $block->block_type,
                'units' => $units->values(),
                'totals' => [
                    'count' => $units->count(),
                    'available' => $statusCounts->get('available', 0),
                    'sold' => $statusCounts->get('sold', 0),
                    'blocked' => $statusCounts->get('blocked', 0),
                    'possession' => $statusCounts->get('possession_transferred', 0),
                    'cancelled' => $statusCounts->get('cancelled', 0),
                ],
            ];
        });

        $allUnits = $blocks->pluck('units')->flatten(1);
        $totals = [
            'count' => $allUnits->count(),
            'available' => $allUnits->where('status', 'available')->count(),
            'sold' => $allUnits->where('status', 'sold')->count(),
            'blocked' => $allUnits->where('status', 'blocked')->count(),
            'possession' => $allUnits->where('status', 'possession_transferred')->count(),
            'cancelled' => $allUnits->where('status', 'cancelled')->count(),
            'outstanding_minor' => (int) $allUnits->sum(fn ($u) => $u['outstanding_minor'] ?? 0),
            'inventory_value_minor' => (int) $allUnits->sum('base_price_minor'),
        ];

        return Inertia::render('Inventory/Projects/Grid', [
            'project' => [
                'id' => $project->id,
                'code' => $project->code,
                'name' => $project->name,
                'location' => $project->location,
            ],
            'blocks' => $blocks,
            'totals' => $totals,
        ]);
    }

    private function authorize(string $ability, $arg): void
    {
        \Illuminate\Support\Facades\Gate::authorize($ability, $arg);
    }
}
