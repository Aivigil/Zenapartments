<?php

namespace App\Policies;

use App\Models\Block;
use App\Models\User;

class BlockPolicy
{
    public function viewAny(User $user): bool { return $user->can('blocks.view'); }
    public function view(User $user, Block $block): bool { return $user->can('blocks.view'); }
    public function create(User $user): bool { return $user->can('blocks.manage'); }
    public function update(User $user, Block $block): bool { return $user->can('blocks.manage'); }
    public function delete(User $user, Block $block): bool { return $user->can('blocks.manage'); }
}
