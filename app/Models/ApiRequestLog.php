<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Registro de uma requisição da API, sem corpo e sem cabeçalhos (R-SEG-3).
 */
#[Fillable(['device_id', 'user_id', 'method', 'route', 'status', 'duration_ms', 'ip', 'created_at'])]
class ApiRequestLog extends Model
{
    public const UPDATED_AT = null;

    protected function casts(): array
    {
        return [
            'status' => 'integer',
            'duration_ms' => 'integer',
        ];
    }

    /**
     * @return BelongsTo<Device, $this>
     */
    public function device(): BelongsTo
    {
        return $this->belongsTo(Device::class);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
