<?php

namespace App\Policies;

use App\Models\Client;
use App\Models\User;

class ClientPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('clients.view');
    }

    public function view(User $user, Client $client): bool
    {
        // A client (login user) can only view their own record.
        if ($user->isClient()) {
            return $user->client_id === $client->id;
        }
        return $user->can('clients.view');
    }

    public function create(User $user): bool
    {
        return $user->can('clients.manage');
    }

    public function update(User $user, Client $client): bool
    {
        return $user->can('clients.manage');
    }

    public function delete(User $user, Client $client): bool
    {
        return $user->can('clients.manage');
    }

    public function verifyKyc(User $user, Client $client): bool
    {
        return $user->can('clients.kyc');
    }
}
