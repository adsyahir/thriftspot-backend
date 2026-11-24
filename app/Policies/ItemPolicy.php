<?php

namespace App\Policies;

use App\Models\Item;
use App\Models\User;

class ItemPolicy
{
    public function before(User $user): ?bool
    {
        return $user->hasRole('admin') ? true : null;
    }

    public function view(User $user, Item $item): bool
    {
        return $this->owns($user, $item);
    }

    public function update(User $user, Item $item): bool
    {
        return $this->owns($user, $item);
    }

    public function delete(User $user, Item $item): bool
    {
        return $this->owns($user, $item);
    }

    private function owns(User $user, Item $item): bool
    {
        return $item->shop->user_id === $user->id;
    }
}
