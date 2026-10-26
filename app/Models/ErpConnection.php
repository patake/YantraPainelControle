<?php

namespace App\Models;

use App\Enums\DbType;
use App\Models\Concerns\Auditable;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Conexão com o banco do ERP do cliente. Cada cliente tem uma conexão.
 * Só o agente usa a conexão, dentro da rede do hospital.
 */
#[Fillable(['client_id', 'db_type', 'host', 'port', 'service_name', 'username', 'password', 'options'])]
#[Hidden(['password'])]
class ErpConnection extends Model
{
    use Auditable;

    protected function casts(): array
    {
        return [
            'db_type' => DbType::class,
            'port' => 'integer',
            'password' => 'encrypted',
            'options' => 'array',
        ];
    }

    /**
     * @return BelongsTo<Client, $this>
     */
    public function client(): BelongsTo
    {
        return $this->belongsTo(Client::class);
    }
}
