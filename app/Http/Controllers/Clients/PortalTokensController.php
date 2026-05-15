<?php

namespace App\Http\Controllers\Clients;

use App\Http\Controllers\Controller;
use App\Models\Client;
use App\Models\ClientPortalToken;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

class PortalTokensController extends Controller
{
    /**
     * Mint a new portal access token for a client. Staff copies the URL
     * and WhatsApps it to the client — no client passwords ever.
     */
    public function store(Request $request, Client $client): RedirectResponse
    {
        Gate::authorize('view', $client);

        $data = $request->validate([
            'booking_id' => ['nullable', 'integer', 'exists:bookings,id'],
            'label' => ['nullable', 'string', 'max:120'],
            'expires_in_days' => ['nullable', 'integer', 'min:1', 'max:365'],
        ]);

        // Validate that the booking, if specified, belongs to this client
        if (!empty($data['booking_id'])) {
            $owned = $client->bookings()->where('id', $data['booking_id'])->exists();
            abort_unless($owned, 422, 'That booking does not belong to this client.');
        }

        $token = ClientPortalToken::create([
            'client_id' => $client->id,
            'booking_id' => $data['booking_id'] ?? null,
            'token' => ClientPortalToken::generateToken(),
            'expires_at' => isset($data['expires_in_days'])
                ? now()->addDays((int) $data['expires_in_days'])
                : now()->addDays(180),
            'label' => $data['label'] ?? 'WhatsApp link ' . now()->format('M j'),
            'created_by' => $request->user()->id,
        ]);

        return back()->with('success', "Portal link created: {$token->label}");
    }

    /**
     * Revoke a previously-minted token.
     */
    public function destroy(Request $request, Client $client, ClientPortalToken $token): RedirectResponse
    {
        Gate::authorize('view', $client);
        abort_unless($token->client_id === $client->id, 404);

        $token->update([
            'revoked_at' => now(),
            'revoked_by' => $request->user()->id,
        ]);

        return back()->with('success', 'Portal link revoked.');
    }
}
