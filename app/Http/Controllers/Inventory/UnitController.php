<?php

namespace App\Http\Controllers\Inventory;

use App\Enums\UnitStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\Inventory\UnitRequest;
use App\Models\Block;
use App\Models\Project;
use App\Models\Unit;
use App\Models\UnitCategory;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

class UnitController extends Controller
{
    public function index(Request $request): Response
    {
        Gate::authorize('viewAny', Unit::class);

        $query = Unit::query()
            ->with(['project:id,code,name', 'block:id,code,name', 'category:id,code,name,kind']);

        if ($request->filled('project_id')) {
            $query->where('project_id', $request->integer('project_id'));
        }
        if ($request->filled('block_id')) {
            $query->where('block_id', $request->integer('block_id'));
        }
        if ($request->filled('status')) {
            $query->where('status', $request->string('status'));
        }
        if ($request->filled('q')) {
            $q = $request->string('q')->trim();
            $query->where(fn ($w) => $w
                ->where('code', 'like', "%{$q}%")
                ->orWhere('name', 'like', "%{$q}%")
            );
        }

        $units = $query->orderBy('code')
            ->paginate(25)
            ->withQueryString()
            ->through(fn ($u) => [
                'id' => $u->id,
                'code' => $u->code,
                'name' => $u->name,
                'project' => $u->project ? ['id' => $u->project->id, 'name' => $u->project->name] : null,
                'block'   => $u->block ? ['id' => $u->block->id, 'name' => $u->block->name] : null,
                'category' => $u->category ? ['id' => $u->category->id, 'name' => $u->category->name] : null,
                'size' => $u->size_value && $u->size_unit ? "{$u->size_value} {$u->size_unit}" : null,
                'base_price_minor' => $u->base_price_minor,
                'currency' => $u->currency,
                'status' => $u->status->value,
                'status_label' => $u->status->label(),
            ]);

        return Inertia::render('Inventory/Units/Index', [
            'units' => $units,
            'filters' => $request->only(['project_id', 'block_id', 'status', 'q']),
            'lookups' => [
                'projects' => Project::orderBy('name')->get(['id', 'name']),
                'statuses' => collect(UnitStatus::cases())->map(fn ($s) => [
                    'value' => $s->value,
                    'label' => $s->label(),
                ]),
            ],
        ]);
    }

    public function create(Request $request): Response
    {
        Gate::authorize('create', Unit::class);
        return Inertia::render('Inventory/Units/Form', [
            'unit' => null,
            'lookups' => $this->lookups($request->integer('project_id')),
        ]);
    }

    public function store(UnitRequest $request): RedirectResponse
    {
        $data = $request->validated();
        $data['base_price_minor'] = $request->input('base_price_minor');
        unset($data['base_price']);
        $unit = Unit::create($data);
        return redirect()
            ->route('inventory.units.show', $unit)
            ->with('success', "Unit {$unit->code} created.");
    }

    public function show(Unit $unit): Response
    {
        Gate::authorize('view', $unit);
        $unit->load(['project', 'block', 'category']);
        return Inertia::render('Inventory/Units/Show', [
            'unit' => [
                'id' => $unit->id,
                'code' => $unit->code,
                'name' => $unit->name,
                'project' => $unit->project->only(['id', 'name']),
                'block' => $unit->block?->only(['id', 'name']),
                'category' => $unit->category->only(['id', 'name', 'kind']),
                'size_value' => $unit->size_value,
                'size_unit' => $unit->size_unit,
                'base_price_minor' => $unit->base_price_minor,
                'currency' => $unit->currency,
                'status' => $unit->status->value,
                'status_label' => $unit->status->label(),
                'attributes' => $unit->attributes,
                'notes' => $unit->notes,
            ],
        ]);
    }

    public function edit(Unit $unit): Response
    {
        Gate::authorize('update', $unit);
        return Inertia::render('Inventory/Units/Form', [
            'unit' => [
                'id' => $unit->id,
                'project_id' => $unit->project_id,
                'block_id' => $unit->block_id,
                'unit_category_id' => $unit->unit_category_id,
                'code' => $unit->code,
                'name' => $unit->name,
                'size_value' => $unit->size_value,
                'size_unit' => $unit->size_unit,
                'base_price' => money_minor_to_major($unit->base_price_minor),
                'currency' => $unit->currency,
                'status' => $unit->status->value,
                'attributes' => $unit->attributes,
                'notes' => $unit->notes,
            ],
            'lookups' => $this->lookups($unit->project_id),
        ]);
    }

    public function update(UnitRequest $request, Unit $unit): RedirectResponse
    {
        $data = $request->validated();
        $data['base_price_minor'] = $request->input('base_price_minor');
        unset($data['base_price']);
        $unit->update($data);
        return redirect()
            ->route('inventory.units.show', $unit)
            ->with('success', "Unit {$unit->code} updated.");
    }

    public function destroy(Unit $unit): RedirectResponse
    {
        Gate::authorize('delete', $unit);
        $unit->delete();
        return redirect()
            ->route('inventory.units.index')
            ->with('success', 'Unit archived.');
    }

    private function lookups(?int $projectId): array
    {
        return [
            'projects' => Project::orderBy('name')->get(['id', 'name']),
            'blocks' => $projectId
                ? Block::where('project_id', $projectId)->orderBy('sort_order')->get(['id', 'name', 'project_id'])
                : Block::orderBy('name')->get(['id', 'name', 'project_id']),
            'categories' => UnitCategory::orderBy('name')->get(['id', 'name', 'kind']),
            'statuses' => collect(UnitStatus::cases())->map(fn ($s) => [
                'value' => $s->value, 'label' => $s->label(),
            ]),
            'size_units' => ['marla', 'kanal', 'sqft', 'sqm'],
            'currencies' => config('app.currency.accepted'),
        ];
    }
}
