<?php

namespace App\Models;

use App\Enums\ReleaseChannel;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

/**
 * Versão do instalador do agente, guardada no S3 em agent-releases/.
 */
#[Fillable(['version', 'channel', 'storage_path', 'sha256', 'size_bytes', 'notes', 'published_at'])]
class AgentRelease extends Model
{
    protected function casts(): array
    {
        return [
            'channel' => ReleaseChannel::class,
            'size_bytes' => 'integer',
            'published_at' => 'datetime',
        ];
    }
}
