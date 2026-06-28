<?php

namespace App\Models\Scopes;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Scope;
use Illuminate\Support\Facades\Auth;

class BranchScope implements Scope
{
    public function apply(Builder $builder, Model $model): void
    {
        $user = Auth::user();

        // Guest (CLI seed, queue worker, request chưa auth) → không filter
        if (! $user) {
            return;
        }

        // Super-admin bypass
        if ($user->hasRole('super-admin')) {
            return;
        }

        // User chưa có branch (data sai) → không trả gì
        if ($user->branch_id === null) {
            $builder->whereRaw('1 = 0');

            return;
        }

        $builder->where($model->qualifyColumn('branch_id'), $user->branch_id);
    }
}
