<?php

namespace App\Support;

use RuntimeException;

/**
 * Assina as respostas da API do agente com Ed25519 (R-SEG-2).
 *
 * A mensagem assinada é "<timestamp>.<corpo>". O timestamp vai no cabeçalho
 * X-Yantra-Timestamp, e a assinatura em base64 vai no cabeçalho X-Yantra-Signature.
 */
final class ResponseSigner
{
    public const HEADER_SIGNATURE = 'X-Yantra-Signature';

    public const HEADER_TIMESTAMP = 'X-Yantra-Timestamp';

    public const HEADER_KEY_ID = 'X-Yantra-Key-Id';

    private readonly string $secretKey;

    public function __construct(string $secretKeyBase64, public readonly string $keyId)
    {
        $secretKey = base64_decode($secretKeyBase64, true);

        if ($secretKey === false || strlen($secretKey) !== SODIUM_CRYPTO_SIGN_SECRETKEYBYTES) {
            throw new RuntimeException('YANTRA_SIGNING_SECRET_KEY inválida. Gere a chave com: php artisan yantra:signing-key --write');
        }

        $this->secretKey = $secretKey;
    }

    /**
     * Cria um par de chaves novo.
     *
     * @return array{secret_key: string, public_key: string}
     */
    public static function generateKeyPair(): array
    {
        $keyPair = sodium_crypto_sign_keypair();

        return [
            'secret_key' => base64_encode(sodium_crypto_sign_secretkey($keyPair)),
            'public_key' => base64_encode(sodium_crypto_sign_publickey($keyPair)),
        ];
    }

    public static function message(int $timestamp, string $body): string
    {
        return $timestamp.'.'.$body;
    }

    /**
     * Assina o corpo e devolve a assinatura em base64.
     */
    public function sign(int $timestamp, string $body): string
    {
        return base64_encode(sodium_crypto_sign_detached(self::message($timestamp, $body), $this->secretKey));
    }

    /**
     * Chave pública em base64, a mesma que vai no instalador do agente.
     */
    public function publicKey(): string
    {
        return base64_encode(sodium_crypto_sign_publickey_from_secretkey($this->secretKey));
    }

    /**
     * Confere uma assinatura, do mesmo jeito que o agente confere.
     */
    public static function verify(int $timestamp, string $body, string $signatureBase64, string $publicKeyBase64): bool
    {
        $signature = base64_decode($signatureBase64, true);
        $publicKey = base64_decode($publicKeyBase64, true);

        if ($signature === false || $publicKey === false
            || strlen($signature) !== SODIUM_CRYPTO_SIGN_BYTES
            || strlen($publicKey) !== SODIUM_CRYPTO_SIGN_PUBLICKEYBYTES) {
            return false;
        }

        return sodium_crypto_sign_verify_detached($signature, self::message($timestamp, $body), $publicKey);
    }
}
