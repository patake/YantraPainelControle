<?php

namespace App\Console\Commands;

use App\Support\ResponseSigner;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

/**
 * Cria o par de chaves Ed25519 que assina as respostas da API do agente.
 */
#[Signature('yantra:signing-key {--write : Grava a chave privada no arquivo .env} {--force : Troca uma chave que já existe no .env}')]
#[Description('Cria o par de chaves Ed25519 que assina as respostas da API do agente')]
class GenerateSigningKey extends Command
{
    public function handle(): int
    {
        $keys = ResponseSigner::generateKeyPair();

        if (! $this->option('write')) {
            $this->line('YANTRA_SIGNING_SECRET_KEY='.$keys['secret_key']);
            $this->line('Chave pública (instalador do agente): '.$keys['public_key']);

            return self::SUCCESS;
        }

        $envPath = base_path('.env');
        $env = is_file($envPath) ? (string) file_get_contents($envPath) : '';
        $line = 'YANTRA_SIGNING_SECRET_KEY='.$keys['secret_key'];

        if (preg_match('/^YANTRA_SIGNING_SECRET_KEY=.+$/m', $env) === 1 && ! $this->option('force')) {
            $this->error('O .env já tem uma chave. Trocar a chave invalida a chave pública dos agentes instalados. Use --force para trocar.');

            return self::FAILURE;
        }

        $env = preg_match('/^YANTRA_SIGNING_SECRET_KEY=.*$/m', $env) === 1
            ? (string) preg_replace('/^YANTRA_SIGNING_SECRET_KEY=.*$/m', $line, $env)
            : rtrim($env, "\n")."\n\n".$line."\n";

        file_put_contents($envPath, $env);

        $this->info('Chave privada gravada no .env.');
        $this->line('Chave pública (instalador do agente): '.$keys['public_key']);

        return self::SUCCESS;
    }
}
