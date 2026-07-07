<?php

namespace App;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class Permission extends Model
{
    protected $fillable = [
        'slug',
        'name',
        'group',
        'description',
        'display_order',
    ];

    public function userRoles(): BelongsToMany
    {
        return $this->belongsToMany(UserRole::class, 'permission_user_role');
    }
}
