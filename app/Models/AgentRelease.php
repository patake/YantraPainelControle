<?php

namespace App\Models;

use App\Enums\ReleaseChannel;
use App\Models\Concerns\Auditable;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

/**
 * Versão do instalador do agente, guardada no S3 em agent-releases/.
 */
#[Fillable(['version', 'channel', 'storage_path', 'sha256', 'size_bytes', 'notes', 'published_at'])]
class AgentRelease extends Model
{
    use Auditable;

    protected function casts(): array
    {
        return [
            'channel' => ReleaseChannel::class,
            'size_bytes' => 'integer',
            'published_at' => 'datetime',
        ];
    }

    /**
     * Caminho do instalador no bucket S3.
     */
    public static function storagePathFor(string $version): string
    {
        return "agent-releases/{$version}/YantraAgente-Setup-{$version}.exe";
    }

    /**
     * Versões que um equipamento do canal recebe: o canal piloto também recebe as versões estáveis.
     */
    #[Scope]
    protected function availableTo(Builder $query, ReleaseChannel $channel): void
    {
        $query->whereNotNull('published_at')
            ->when($channel === ReleaseChannel::Stable, fn (Builder $query) => $query->where('channel', ReleaseChannel::Stable));
    }

    /**
     * A versão mais nova do canal, pela ordem das versões (2.10.0 vem depois de 2.9.0).
     */
    public static function latestFor(ReleaseChannel $channel): ?self
    {
        return static::query()
            ->availableTo($channel)
            ->get()
            ->sort(fn (self $a, self $b) => version_compare($b->version, $a->version))
            ->first();
    }
}
