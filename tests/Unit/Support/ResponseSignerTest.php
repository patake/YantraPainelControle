<?php

namespace Tests\Unit\Support;

use App\Support\ResponseSigner;
use PHPUnit\Framework\TestCase;
use RuntimeException;

class ResponseSignerTest extends TestCase
{
    public function test_signature_verifies_with_the_public_key(): void
    {
        $keys = ResponseSigner::generateKeyPair();
        $signer = new ResponseSigner($keys['secret_key'], 'k1');

        $signature = $signer->sign(1792965000, '{"status":"active"}');

        $this->assertSame($keys['public_key'], $signer->publicKey());
        $this->assertTrue(ResponseSigner::verify(1792965000, '{"status":"active"}', $signature, $keys['public_key']));
    }

    public function test_signature_fails_when_the_body_changes(): void
    {
        $keys = ResponseSigner::generateKeyPair();
        $signature = (new ResponseSigner($keys['secret_key'], 'k1'))->sign(1792965000, '{"status":"active"}');

        $this->assertFalse(ResponseSigner::verify(1792965000, '{"status":"blocked"}', $signature, $keys['public_key']));
    }

    public function test_signature_fails_with_another_public_key(): void
    {
        $keys = ResponseSigner::generateKeyPair();
        $other = ResponseSigner::generateKeyPair();
        $signature = (new ResponseSigner($keys['secret_key'], 'k1'))->sign(1792965000, 'corpo');

        $this->assertFalse(ResponseSigner::verify(1792965000, 'corpo', $signature, $other['public_key']));
    }

    public function test_verify_rejects_malformed_input_without_errors(): void
    {
        $keys = ResponseSigner::generateKeyPair();

        $this->assertFalse(ResponseSigner::verify(1, 'corpo', 'não é base64', $keys['public_key']));
        $this->assertFalse(ResponseSigner::verify(1, 'corpo', base64_encode('curta'), $keys['public_key']));
    }

    public function test_rejects_a_missing_or_malformed_secret_key(): void
    {
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('YANTRA_SIGNING_SECRET_KEY inválida');

        new ResponseSigner('', 'k1');
    }
}
