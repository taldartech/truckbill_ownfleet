<?php

namespace App;

use App\Concerns\BranchScoped;
use App\Concerns\TenantScoped;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DocumentAlertSetting extends Model
{
    use BranchScoped;
    use TenantScoped;

    protected $primaryKey = 'tenant_id';

    public $incrementing = false;

    protected $keyType = 'string';

    protected $fillable = [
        'tenant_id',
        'branch_id',
        'notify_live',
        'notify_email',
        'recipient_user_ids',
    ];

    protected function casts(): array
    {
        return [
            'notify_live' => 'boolean',
            'notify_email' => 'boolean',
            'recipient_user_ids' => 'array',
        ];
    }

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }
}
