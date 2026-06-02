<?php

namespace App;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class UserRole extends Model
{
    public const MASTER_ADMIN = 1;

    public const SUPER_ADMIN = 2;

    public const ADMIN = 3;

    public const MODERATOR = 4;

    public const ACCOUNTANT = 5;

    public const TRANSPORTER = 6;

    public const CUSTOMER = 7;

    /**
     * @var array<int, string>
     */
    public const NAMES = [
        self::MASTER_ADMIN => 'master_admin',
        self::SUPER_ADMIN => 'super_admin',
        self::ADMIN => 'admin',
        self::MODERATOR => 'moderator',
        self::ACCOUNTANT => 'accountant',
        self::TRANSPORTER => 'transporter',
        self::CUSTOMER => 'customer',
    ];

    protected $fillable = [
        'name',
    ];

    public function users(): HasMany
    {
        return $this->hasMany(User::class);
    }

    public function permissions(): BelongsToMany
    {
        return $this->belongsToMany(Permission::class, 'permission_user_role');
    }

    /**
     * @var array<string, int>
     */
    private const ALIASES = [
        'fleet_manager' => self::ADMIN,
        'driver' => self::CUSTOMER,
    ];

    public static function idFor(string $name): ?int
    {
        $normalized = strtolower(str_replace('-', '_', $name));

        if (isset(self::ALIASES[$normalized])) {
            return self::ALIASES[$normalized];
        }

        $flipped = array_flip(self::NAMES);

        return $flipped[$normalized] ?? null;
    }
}
