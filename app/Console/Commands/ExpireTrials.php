<?php

namespace App\Console\Commands;

use App\Enums\DeviceStatus;
use App\Models\Device;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

/**
 * Muda para expirado o equipamento em trial depois do prazo (R-LIC-3).
 */
#[Signature('devices:expire-trials')]
#[Description('Muda para expirado os equipamentos com o trial vencido')]
class ExpireTrials extends Command
{
    public function handle(): int
    {
        $count = 0;

        // Um a um, para a auditoria registrar cada mudança.
        Device::trialOverdue()->each(function (Device $device) use (&$count) {
            $device->update(['status' => DeviceStatus::Expired]);
            $count++;
        });

        $this->info("Trials expirados: {$count}.");

        return self::SUCCESS;
    }
}
