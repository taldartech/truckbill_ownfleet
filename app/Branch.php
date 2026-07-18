<?php

namespace App;

use App\Enums\GstRegistrationType;
use App\Enums\TaxLiability;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class Branch extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'company_id',
        'branch_name',
        'branch_address',
        'branch_contact_number',
        'head_office',
        'inherit_company_gst',
        'registration_type',
        'tax_liability',
        'gst_number',
        'state_id',
        'gst_rate',
        'use_company_logo',
        'branch_logo',
    ];

    /**
     * @var array<string, mixed>
     */
    protected $attributes = [
        'inherit_company_gst' => true,
        'use_company_logo' => true,
    ];

    protected function casts(): array
    {
        return [
            'head_office' => 'boolean',
            'inherit_company_gst' => 'boolean',
            'use_company_logo' => 'boolean',
            'state_id' => 'integer',
            'gst_rate' => 'decimal:3',
            'registration_type' => GstRegistrationType::class,
            'tax_liability' => TaxLiability::class,
        ];
    }

    public function company(): BelongsTo
    {
        return $this->belongsTo(Tenant::class, 'company_id');
    }

    public function state(): BelongsTo
    {
        return $this->belongsTo(State::class);
    }

    public function users(): HasMany
    {
        return $this->hasMany(User::class);
    }

    public function settings(): HasOne
    {
        return $this->hasOne(BranchSetting::class);
    }

    public function storeLogo(UploadedFile $file): string
    {
        $extension = strtolower($file->getClientOriginalExtension() ?: $file->guessExtension() ?: 'png');
        $path = sprintf(
            'logos/branches/%s/%s/%s.%s',
            $this->company_id,
            $this->id,
            (string) Str::uuid(),
            $extension,
        );

        Storage::disk('public')->putFileAs(dirname($path), $file, basename($path));

        return $path;
    }

    public function deleteLogo(): void
    {
        if ($this->branch_logo) {
            Storage::disk('public')->delete($this->branch_logo);
            $this->branch_logo = null;
        }
    }

    public function logoPublicUrl(): ?string
    {
        $path = $this->logoPath();

        return $path ? Storage::disk('public')->url($path) : null;
    }

    public function logoFilesystemPathForPdf(): ?string
    {
        $path = $this->logoPath();

        if (! $path || ! Storage::disk('public')->exists($path)) {
            return null;
        }

        return Storage::disk('public')->path($path);
    }

    private function logoPath(): ?string
    {
        if ($this->use_company_logo) {
            $this->loadMissing('company');

            return $this->company?->company_logo;
        }

        return $this->branch_logo;
    }
}
