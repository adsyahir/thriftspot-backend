<?php

namespace App\Policies;

use App\Models\Shop;
use App\Models\User;

class ShopPolicy
{
    public function before(User $user): ?bool
    {
        return $user->hasRole('admin') ? true : null;
    }

    public function update(User $user, Shop $shop): bool
    {
        return $shop->user_id === $user->id;
    }

    public function delete(User $user, Shop $shop): bool
    {
        return $shop->user_id === $user->id;
    }
}
