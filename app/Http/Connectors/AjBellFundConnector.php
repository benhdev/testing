<?php

namespace App\Http\Connectors;

use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Http;

/**
 * Talks to the AJ Bell fund-data CDN. Statically callable so no Action or
 * Service has to instantiate it or declare it as a constructor dependency —
 * matches the Connector pattern used across our Laravel projects.
 */
class AjBellFundConnector
{
    /**
     * Fetch every supplied SEDOL from the CDN in parallel. Returns a
     * Collection keyed by SEDOL where each value is the decoded JSON array.
     *
     * Throws (via the Http::parallel macro) if any individual fetch fails —
     * the Action decides how to react. All-or-nothing at this layer keeps
     * the error signal unambiguous: either every fund is up to date or the
     * refresh run visibly failed.
     *
     * @param  array<string>  $sedols
     * @return Collection<string, array>
     */
    public static function all(array $sedols): Collection
    {
        $requests = array_map(fn (string $sedol): array => [
            'key' => $sedol,
            'method' => 'get',
            'via' => 'aj_bell_funds',
            'uri' => $sedol.'.json',
            'body' => [],
        ], $sedols);

        /** @var Collection<string, array> $responses */
        $responses = Http::parallel($requests);

        return $responses;
    }
}
