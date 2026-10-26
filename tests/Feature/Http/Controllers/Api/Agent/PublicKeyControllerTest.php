<?php

namespace Tests\Feature\Http\Controllers\Api\Agent;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PublicKeyControllerTest extends TestCase
{
    use RefreshDatabase;

    public function test_returns_the_public_key_of_the_signing_key_without_authentication(): void
    {
        $secretKey = base64_decode((string) config('yantra.signing.secret_key'));
        $expected = base64_encode(sodium_crypto_sign_publickey_from_secretkey($secretKey));

        $this->getJson(route('agent.public-key'))
            ->assertOk()
            ->assertExactJson([
                'key_id' => 'test',
                'algorithm' => 'ed25519',
                'public_key' => $expected,
            ]);
    }
}
