<?php

namespace App\Console\Commands;

use App\Services\PreviewRegistrar;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Throwable;

#[Signature('previews:sync')]
#[Description('Give every preview environment its section domains and nudge pending ones to verify')]
class SyncPreviews extends Command
{
    /**
     * A safety net for the deploy-time registration: previews that never
     * called in (or called before their vanity domain existed) still get
     * their application's sections, and pending domains are re-verified.
     */
    public function handle(PreviewRegistrar $registrar): int
    {
        foreach ($registrar->previews() as $preview) {
            $environment = $preview['environment'];
            $label = $environment['attributes']['name'] ?? $environment['id'];

            try {
                if ($sections = $registrar->sections($preview)) {
                    $created = collect($registrar->register($environment, $sections))->where('created', true);

                    $created->each(fn ($domain) => $this->info("{$label}: added {$domain['hostname']}"));
                }

                if ($pending = $registrar->nudge($environment)) {
                    $this->line("{$label}: re-verifying {$pending} pending domain(s)");
                }
            } catch (Throwable $e) {
                $this->error("{$label}: {$e->getMessage()}");
            }
        }

        return self::SUCCESS;
    }
}
