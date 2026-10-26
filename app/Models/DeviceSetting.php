<?php

namespace App\Models;

use App\Models\Concerns\Auditable;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Parametrização de um equipamento: pastas, credencial de rede, teclas e porta serial.
 */
#[Fillable(['device_id', 'capture_base_path', 'network_username', 'network_password', 'hotkey', 'serial_port'])]
#[Hidden(['network_password'])]
class DeviceSetting extends Model
{
    use Auditable;

    protected $primaryKey = 'device_id';

    protected $keyType = 'string';

    public $incrementing = false;

    protected function casts(): array
    {
        return [
            'network_password' => 'encrypted',
        ];
    }

    /**
     * @return BelongsTo<Device, $this>
     */
    public function device(): BelongsTo
    {
        return $this->belongsTo(Device::class);
    }
}
