<?php

namespace App\Models;

use App\Enums\SystemFeature;
use App\Models\Concerns\Auditable;
use Database\Factories\SystemFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Sistema licenciado: YC PRT, YO PRT, YO SRC ou YO HUB.
 */
#[Fillable(['code', 'name', 'feature', 'branding'])]
class System extends Model
{
    /** @use HasFactory<SystemFactory> */
    use Auditable, HasFactory;

    protected function casts(): array
    {
        return [
            'feature' => SystemFeature::class,
            'branding' => 'array',
        ];
    }

    /**
     * @return HasMany<License, $this>
     */
    public function licenses(): HasMany
    {
        return $this->hasMany(License::class);
    }

    /**
     * @return HasMany<Device, $this>
     */
    public function devices(): HasMany
    {
        return $this->hasMany(Device::class);
    }
}
