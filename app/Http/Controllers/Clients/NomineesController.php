<?php

namespace App\Http\Controllers\Clients;

use App\Http\Controllers\Controller;
use App\Http\Requests\Clients\NomineeRequest;
use App\Models\Client;
use App\Models\Nominee;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Gate;

class NomineesController extends Controller
{
    public function store(NomineeRequest $request, Client $client): RedirectResponse
    {
        Gate::authorize('update', $client);

        $data = $request->validated();
        if (!empty($data['cnic'])) {
            $data['cnic_encrypted'] = $data['cnic'];
            $data['cnic_hash'] = cnic_hash($data['cnic']);
        }
        unset($data['cnic']);

        $client->nominees()->create($data);

        return back()->with('success', 'Nominee added.');
    }

    public function update(NomineeRequest $request, Client $client, Nominee $nominee): RedirectResponse
    {
        Gate::authorize('update', $client);
        abort_unless($nominee->client_id === $client->id, 404);

        $data = $request->validated();
        if (!empty($data['cnic'])) {
            $data['cnic_encrypted'] = $data['cnic'];
            $data['cnic_hash'] = cnic_hash($data['cnic']);
        }
        unset($data['cnic']);

        $nominee->update($data);
        return back()->with('success', 'Nominee updated.');
    }

    public function destroy(Client $client, Nominee $nominee): RedirectResponse
    {
        Gate::authorize('update', $client);
        abort_unless($nominee->client_id === $client->id, 404);

        $nominee->delete();
        return back()->with('success', 'Nominee removed.');
    }
}
