<?php

namespace App\Http\Controllers\Clients;

use App\Http\Controllers\Controller;
use App\Http\Requests\Clients\ClientRequest;
use App\Models\Client;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

class ClientsController extends Controller
{
    public function index(Request $request): Response
    {
        Gate::authorize('viewAny', Client::class);

        $query = Client::query()
            ->withCount('bookings');

        if ($request->filled('q')) {
            $q = trim((string) $request->string('q'));
            $query->where(function ($w) use ($q) {
                $w->where('full_name', 'like', "%{$q}%")
                  ->orWhere('code', 'like', "%{$q}%")
                  ->orWhere('primary_phone', 'like', "%{$q}%")
                  ->orWhere('email', 'like', "%{$q}%");
            });
        }

        if ($request->filled('kyc_status')) {
            $query->where('kyc_status', $request->string('kyc_status'));
        }

        $clients = $query->orderBy('full_name')
            ->paginate(25)
            ->withQueryString()
            ->through(fn ($c) => [
                'id' => $c->id,
                'code' => $c->code,
                'full_name' => $c->full_name,
                'primary_phone' => $c->primary_phone,
                'email' => $c->email,
                'city' => $c->city,
                'country_of_residence' => $c->country_of_residence,
                'kyc_status' => $c->kyc_status,
                'bookings_count' => $c->bookings_count,
            ]);

        return Inertia::render('Clients/Index', [
            'clients' => $clients,
            'filters' => $request->only(['q', 'kyc_status']),
            'lookups' => [
                'kyc_statuses' => [
                    ['value' => 'pending',  'label' => 'Pending'],
                    ['value' => 'verified', 'label' => 'Verified'],
                    ['value' => 'rejected', 'label' => 'Rejected'],
                ],
            ],
        ]);
    }

    public function create(): Response
    {
        Gate::authorize('create', Client::class);

        return Inertia::render('Clients/Form', [
            'client' => null,
            'lookups' => $this->lookups(),
        ]);
    }

    public function store(ClientRequest $request): RedirectResponse
    {
        $data = $this->prepareData($request);

        $client = DB::transaction(function () use ($data) {
            $client = Client::create($data);

            // Auto-generate the human-friendly code if not supplied.
            if (empty($client->code)) {
                $client->code = generate_code('ZR-C-', $client->id, 5);
                $client->save();
            }
            return $client;
        });

        return redirect()
            ->route('clients.show', $client)
            ->with('success', "Client {$client->code} — {$client->full_name} created.");
    }

    public function show(Client $client): Response
    {
        Gate::authorize('view', $client);

        $client->load([
            'nominees',
            'bookings' => fn ($q) => $q->with('unit:id,code,name')->latest('booking_date'),
        ]);

        $portalTokens = \App\Models\ClientPortalToken::where('client_id', $client->id)
            ->with('booking:id,code', 'creator:id,name')
            ->orderByDesc('id')
            ->get()
            ->map(fn ($t) => [
                'id' => $t->id,
                'token' => $t->token,
                'label' => $t->label,
                'url' => url("/c/{$t->token}"),
                'booking_id' => $t->booking_id,
                'booking_code' => $t->booking?->code,
                'expires_at' => $t->expires_at?->format('Y-m-d'),
                'last_used_at' => $t->last_used_at?->format('Y-m-d H:i'),
                'use_count' => $t->use_count,
                'revoked_at' => $t->revoked_at?->format('Y-m-d H:i'),
                'created_by' => $t->creator?->name,
                'is_active' => $t->isActive(),
            ]);

        return Inertia::render('Clients/Show', [
            'client' => [
                'id' => $client->id,
                'code' => $client->code,
                'full_name' => $client->full_name,
                'father_or_husband_name' => $client->father_or_husband_name,
                'date_of_birth' => $client->date_of_birth?->format('Y-m-d'),
                'nationality' => $client->nationality,
                'country_of_residence' => $client->country_of_residence,
                'cnic_masked' => $client->cnic_encrypted
                    ? '****-****-' . substr(preg_replace('/\D/', '', $client->cnic_encrypted), -4)
                    : null,
                'primary_phone' => $client->primary_phone,
                'alt_phone' => $client->alt_phone,
                'email' => $client->email,
                'address_line1' => $client->address_line1,
                'address_line2' => $client->address_line2,
                'city' => $client->city,
                'country' => $client->country,
                'kyc_status' => $client->kyc_status,
                'kyc_verified_at' => $client->kyc_verified_at?->format('Y-m-d H:i'),
                'notes' => $client->notes,
                'created_at' => $client->created_at?->format('Y-m-d H:i'),
                'nominees' => $client->nominees->map(fn ($n) => [
                    'id' => $n->id,
                    'full_name' => $n->full_name,
                    'relationship' => $n->relationship,
                    'phone' => $n->phone,
                ]),
                'bookings' => $client->bookings->map(fn ($b) => [
                    'id' => $b->id,
                    'code' => $b->code,
                    'unit_code' => $b->unit?->code,
                    'unit_name' => $b->unit?->name,
                    'booking_date' => $b->booking_date?->format('Y-m-d'),
                    'status' => $b->status,
                    'total_price_minor' => $b->total_price_minor,
                    'currency' => $b->currency,
                ]),
            ],
            'portal_tokens' => $portalTokens,
            'can' => [
                'edit' => request()->user()->can('clients.manage'),
                'verify_kyc' => request()->user()->can('clients.kyc'),
                'mint_portal_token' => request()->user()->can('clients.manage'),
            ],
        ]);
    }

    public function edit(Client $client): Response
    {
        Gate::authorize('update', $client);

        return Inertia::render('Clients/Form', [
            'client' => [
                'id' => $client->id,
                'code' => $client->code,
                'full_name' => $client->full_name,
                'father_or_husband_name' => $client->father_or_husband_name,
                'date_of_birth' => $client->date_of_birth?->format('Y-m-d'),
                'nationality' => $client->nationality,
                'country_of_residence' => $client->country_of_residence,
                'cnic' => '',     // intentionally empty — re-enter to change
                'passport' => '',
                'primary_phone' => $client->primary_phone,
                'alt_phone' => $client->alt_phone,
                'email' => $client->email,
                'address_line1' => $client->address_line1,
                'address_line2' => $client->address_line2,
                'city' => $client->city,
                'country' => $client->country,
                'kyc_status' => $client->kyc_status,
                'notes' => $client->notes,
            ],
            'lookups' => $this->lookups(),
        ]);
    }

    public function update(ClientRequest $request, Client $client): RedirectResponse
    {
        $data = $this->prepareData($request, $client);
        $client->update($data);

        return redirect()
            ->route('clients.show', $client)
            ->with('success', "Client {$client->code} — {$client->full_name} updated.");
    }

    public function destroy(Client $client): RedirectResponse
    {
        Gate::authorize('delete', $client);
        $client->delete();

        return redirect()
            ->route('clients.index')
            ->with('success', 'Client archived.');
    }

    /**
     * Build the payload to write. Strips CNIC if blank (so we don't overwrite
     * existing encrypted value with null on edit when user leaves the field blank).
     */
    private function prepareData(ClientRequest $request, ?Client $existing = null): array
    {
        $data = $request->validated();

        // CNIC: only set if user actually typed one (on edit, blank means "leave as-is").
        if (!empty($data['cnic'])) {
            $data['cnic_encrypted'] = $data['cnic'];
            $data['cnic_hash'] = cnic_hash($data['cnic']);
        }
        unset($data['cnic']);

        if (!empty($data['passport'])) {
            $data['passport_encrypted'] = $data['passport'];
        }
        unset($data['passport']);

        if ($data['kyc_status'] === 'verified' && (!$existing || $existing->kyc_status !== 'verified')) {
            $data['kyc_verified_at'] = now();
            $data['kyc_verified_by'] = $request->user()->id;
        }
        if ($data['kyc_status'] !== 'verified') {
            $data['kyc_verified_at'] = null;
            $data['kyc_verified_by'] = null;
        }

        return $data;
    }

    private function lookups(): array
    {
        return [
            'kyc_statuses' => [
                ['value' => 'pending',  'label' => 'Pending'],
                ['value' => 'verified', 'label' => 'Verified'],
                ['value' => 'rejected', 'label' => 'Rejected'],
            ],
            'countries' => [
                ['value' => 'PK', 'label' => 'Pakistan'],
                ['value' => 'AE', 'label' => 'UAE'],
                ['value' => 'SA', 'label' => 'Saudi Arabia'],
                ['value' => 'GB', 'label' => 'United Kingdom'],
                ['value' => 'US', 'label' => 'United States'],
                ['value' => 'CA', 'label' => 'Canada'],
                ['value' => 'AU', 'label' => 'Australia'],
            ],
        ];
    }
}
