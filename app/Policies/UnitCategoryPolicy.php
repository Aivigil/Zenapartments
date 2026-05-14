<?php

namespace App\Policies;

use App\Models\UnitCategory;
use App\Models\User;

class UnitCategoryPolicy
{
    public function viewAny(User $user): bool { return $user->can('unit_categories.view'); }
    public function view(User $user, UnitCategory $cat): bool { return $user->can('unit_categories.view'); }
    public function create(User $user): bool { return $user->can('unit_categories.manage'); }
    public function update(User $user, UnitCategory $cat): bool { return $user->can('unit_categories.manage'); }
    public function delete(User $user, UnitCategory $cat): bool { return $user->can('unit_categories.manage'); }
}
