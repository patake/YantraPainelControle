<?php

namespace App\Models\Concerns;

use App\Models\AuditLog;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;

/**
 * Grava a auditoria das ações dos usuários: quem, quando, o quê, valor antigo e
 * valor novo (R-SEG-4). Campos criptografados e ocultos aparecem só como
 * "[alterado]": a auditoria nunca grava senhas nem segredos (R-SEG-5).
 *
 * @mixin Model
 */
trait Auditable
{
    /**
     * Texto gravado no lugar de um valor secreto.
     */
    public const AUDIT_SECRET = '[alterado]';

    /**
     * Campos que a auditoria ignora em todos os modelos.
     *
     * @var list<string>
     */
    private static array $auditIgnored = [
        'created_at', 'updated_at', 'deleted_at', 'remember_token',
        'last_login_at', 'last_seen_at', 'two_factor_recovery_codes',
    ];

    public static function bootAuditable(): void
    {
        static::created(fn (Model $model) => $model->writeAudit('created', [], $model->auditValues($model->getAttributes())));

        static::updated(function (Model $model) {
            $changed = array_keys($model->getChanges());
            $new = $model->auditValues(array_intersect_key($model->getAttributes(), array_flip($changed)));

            if ($new === []) {
                return;
            }

            $old = $model->auditValues(array_intersect_key($model->getRawOriginal(), $new));

            $model->writeAudit('updated', $old, $new);
        });

        static::deleted(fn (Model $model) => $model->writeAudit('deleted', $model->auditValues($model->getRawOriginal()), []));
    }

    /**
     * Grava uma ação que o evento do modelo não cobre, como vincular clientes a um usuário.
     *
     * @param  array<string, mixed>  $old
     * @param  array<string, mixed>  $new
     */
    public function writeAudit(string $action, array $old, array $new): void
    {
        AuditLog::create([
            'user_id' => Auth::guard('web')->id(),
            'action' => $action,
            'auditable_type' => class_basename($this),
            'auditable_id' => (string) $this->getKey(),
            'old_values' => $old === [] ? null : $old,
            'new_values' => $new === [] ? null : $new,
            'ip' => app()->runningInConsole() ? null : request()->ip(),
            'created_at' => now(),
        ]);
    }

    /**
     * Remove os campos ignorados e troca os segredos por "[alterado]".
     *
     * @param  array<string, mixed>  $values
     * @return array<string, mixed>
     */
    private function auditValues(array $values): array
    {
        $result = [];

        foreach ($values as $key => $value) {
            if (in_array($key, self::$auditIgnored, true)) {
                continue;
            }

            $result[$key] = $this->isAuditSecret($key) && $value !== null ? self::AUDIT_SECRET : $value;
        }

        return $result;
    }

    private function isAuditSecret(string $key): bool
    {
        $cast = $this->getCasts()[$key] ?? '';

        return in_array($key, $this->getHidden(), true)
            || in_array($key, ['password', 'two_factor_secret'], true)
            || str_starts_with((string) $cast, 'encrypted');
    }
}
