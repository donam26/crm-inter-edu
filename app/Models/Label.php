<?php

namespace App\Models;

use App\Models\Scopes\BranchScope;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class Label extends Model
{
    protected $fillable = [
        'branch_id',
        'name',
        'color',
    ];

    protected static function booted(): void
    {
        static::addGlobalScope(new BranchScope);
    }

    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }

    public function tasks(): BelongsToMany
    {
        return $this->belongsToMany(Task::class, 'label_task');
    }

    /**
     * Dùng chung hệ badge với các enum (secondary/primary/success/warning/danger),
     * để view render <x-badge :variant="$label->badgeVariant()"> đồng nhất.
     */
    public function badgeVariant(): string
    {
        return $this->color;
    }
}
