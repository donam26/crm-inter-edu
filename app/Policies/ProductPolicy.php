<?php

namespace App\Policies;

use App\Models\Product;
use App\Models\User;
use App\Policies\Concerns\ChecksBranchOwnership;

class ProductPolicy
{
    use ChecksBranchOwnership;

    public function viewAny(User $user): bool
    {
        return $user->can('products.view');
    }

    public function view(User $user, Product $product): bool
    {
        // Catalog dùng chung trong branch — không có khái niệm "của mình".
        return $this->sameBranch($user, $product) && $user->can('products.view');
    }

    public function create(User $user): bool
    {
        return $user->can('products.create');
    }

    public function update(User $user, Product $product): bool
    {
        return $this->sameBranch($user, $product) && $user->can('products.update');
    }

    public function delete(User $user, Product $product): bool
    {
        return $this->sameBranch($user, $product) && $user->can('products.delete');
    }
}
