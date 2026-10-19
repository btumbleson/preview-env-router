<?php

namespace App\Console\Commands;

use App\Services\PreviewRegistrar;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('previews:sync')]
#[Description('Give every preview environment its section domains and nudge pending ones to verify')]
class SyncPreviews extends Command
{
    public function handle(PreviewRegistrar $registrar): int
    {
        foreach ($registrar->sync() as $result) {
            foreach ($result['added'] as $hostname) {
                $this->info("{$result['preview']}: added {$hostname}");
            }

            if ($result['pending']) {
                $this->line("{$result['preview']}: re-verifying {$result['pending']} pending domain(s)");
            }

            if ($result['error']) {
                $this->error("{$result['preview']}: {$result['error']}");
            }
        }

        return self::SUCCESS;
    }
}
