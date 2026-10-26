<?php

namespace App\Models;

use App\Enums\DeviceStatus;
use App\Models\Concerns\Auditable;
use Database\Factories\LicenseFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Pacote de licenças de um cliente para um sistema.
 */
#[Fillable(['client_id', 'system_id', 'quantity', 'valid_until'])]
class License extends Model
{
    /** @use HasFactory<LicenseFactory> */
    use Auditable, HasFactory;

    protected function casts(): array
    {
        return [
            'quantity' => 'integer',
            'valid_until' => 'date',
        ];
    }

    /**
     * @return BelongsTo<Client, $this>
     */
    public function client(): BelongsTo
    {
        return $this->belongsTo(Client::class);
    }

    /**
     * @return BelongsTo<System, $this>
     */
    public function system(): BelongsTo
    {
        return $this->belongsTo(System::class);
    }

    /**
     * Equipamentos em trial ou ativos do cliente e do sistema (R-LIC-1).
     */
    public function usedCount(): int
    {
        return Device::query()
            ->where('client_id', $this->client_id)
            ->where('system_id', $this->system_id)
            ->whereIn('status', DeviceStatus::usingLicense())
            ->count();
    }

    /**
     * Licença livre para um registro novo (R-LIC-2).
     */
    public function hasAvailableSeat(): bool
    {
        if ($this->valid_until !== null && $this->valid_until->isPast()) {
            return false;
        }

        return $this->usedCount() < $this->quantity;
    }
}
