<?php

namespace App\Console\Commands;

use App\Actions\RefreshFunds;
use App\Exceptions\FundFetchException;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('funds:refresh')]
#[Description('Fetch the latest fund data from AJ Bell and update the local store.')]
class FundsRefreshCommand extends Command
{
    public function handle(RefreshFunds $action): int
    {
        try {
            $summary = $action->handle();
        } catch (FundFetchException $e) {
            $this->error('Upstream fetch failed: '.$e->getMessage());

            return self::FAILURE;
        }

        $this->info(sprintf(
            'Refresh complete. Successful: %d. Invalid: %d.',
            $summary['ok'],
            $summary['invalid'],
        ));

        return self::SUCCESS;
    }
}
