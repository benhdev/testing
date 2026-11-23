<?php

namespace App\Actions;

use App\DataTransferObjects\FundPayload;
use App\Exceptions\FundFetchException;
use App\Exceptions\InvalidFundPayloadException;
use App\Http\Connectors\AjBellFundConnector;
use App\Models\Fund;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * The orchestration behind `php artisan funds:refresh`:
 *
 *   1. Pull every seeded SEDOL's latest JSON from the AJ Bell CDN (in parallel).
 *   2. Validate each payload through the FundPayload DTO.
 *   3. Upsert the scalar fields on the matching Fund, then rewrite the
 *      three child collections (documents / holdings / asset allocations).
 *
 * The seeded columns (`category`, `risk_label`) are never touched here — they
 * are our local metadata and must survive every refresh.
 *
 * Partial failures (one bad SEDOL, others fine) are logged and the Action
 * completes. A total fetch failure (CDN unreachable) is re-raised so the
 * scheduler surfaces it.
 */
class RefreshFunds
{
    /**
     * @return array{ok: int, invalid: int}
     */
    public function handle(): array
    {
        $sedols = Fund::query()->pluck('sedol')->all();

        $responses = $this->fetch($sedols);

        $summary = ['ok' => 0, 'invalid' => 0];

        foreach ($responses as $sedol => $raw) {
            try {
                $payload = FundPayload::fromArray($raw, (string) $sedol);
            } catch (InvalidFundPayloadException $e) {
                Log::warning('Invalid fund payload', [
                    'sedol' => $e->sedol,
                    'reason' => $e->getMessage(),
                ]);
                $summary['invalid']++;

                continue;
            }

            $this->persist($payload);
            $summary['ok']++;
        }

        Log::info('Fund refresh complete', $summary);

        return $summary;
    }

    /**
     * @param  array<string>  $sedols
     */
    private function fetch(array $sedols): Collection
    {
        try {
            return AjBellFundConnector::all($sedols);
        } catch (Throwable $e) {
            Log::error('AJ Bell fund fetch failed', [
                'reason' => $e->getMessage(),
            ]);

            throw new FundFetchException(
                'Upstream fund fetch failed: '.$e->getMessage(),
                previous: $e,
            );
        }
    }

    /**
     * Apply the validated payload to the DB. Wrapped in a transaction so a
     * fund's scalar fields and its child collections can never desynchronise.
     */
    private function persist(FundPayload $payload): void
    {
        DB::transaction(function () use ($payload): void {
            $fund = Fund::query()->where('sedol', $payload->sedol)->firstOrFail();

            // Only upstream-sourced columns are listed here. `category` and
            // `risk_label` are intentionally omitted so the seeded values
            // persist.
            $fund->update([
                'market_code' => $payload->marketCode,
                'name' => $payload->name,
                'sector_name' => $payload->sectorName,
                'currency' => $payload->currency,
                'last_price' => $payload->lastPrice,
                'last_price_date' => $payload->lastPriceDate,
                'ongoing_charge' => $payload->ongoingCharge,
                'analyst_rating' => $payload->analystRating,
                'analyst_rating_label' => $payload->analystRatingLabel,
                'srri' => $payload->srri,
                'objective' => $payload->objective,
                'is_active' => true,
                'fetched_at' => now(),
            ]);

            // Child collections are delete-and-recreate: upstream is the
            // source of truth, and the sets are tiny (≤10 rows each), so a
            // diff-based upsert would be more complexity than value.
            $fund->documents()->delete();
            $fund->documents()->createMany($payload->documents);

            $fund->holdings()->delete();
            $fund->holdings()->createMany($payload->holdings);

            $fund->assetAllocations()->delete();
            $fund->assetAllocations()->createMany($payload->assetAllocations);
        });
    }
}
