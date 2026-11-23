<?php

namespace App\DataTransferObjects;

use App\Exceptions\InvalidFundPayloadException;

/**
 * An immutable, validated representation of one fund as fetched from the
 * AJ Bell CDN. This sits between the Connector (raw JSON arrays) and the
 * Action (persistence) so bad upstream data is caught before it reaches
 * Eloquent.
 */
final readonly class FundPayload
{
    /**
     * @param  array<array{type: string, external_id: ?string, url: string}>  $documents
     * @param  array<array{label: string, value: string}>  $assetAllocations
     * @param  array<array{position: int, name: string, weighting: string}>  $holdings
     */
    public function __construct(
        public string $sedol,
        public string $marketCode,
        public string $name,
        public ?string $sectorName,
        public ?string $currency,
        public ?string $lastPrice,
        public ?string $lastPriceDate,
        public ?string $ongoingCharge,
        public ?int $analystRating,
        public ?string $analystRatingLabel,
        public ?int $srri,
        public ?string $objective,
        public array $documents,
        public array $assetAllocations,
        public array $holdings,
    ) {}

    /**
     * Build a FundPayload from the upstream JSON array. Throws if the shape
     * doesn't match — upstream contract validation is the DTO's whole job.
     */
    public static function fromArray(array $raw, string $sedol): self
    {
        $data = $raw['data'] ?? throw self::invalid($sedol, 'missing "data" root');

        $quote = $data['quote'] ?? throw self::invalid($sedol, 'missing "data.quote"');

        if (! isset($quote['marketCode'], $quote['name'])) {
            throw self::invalid($sedol, 'quote missing marketCode or name');
        }

        $profile = $data['profile'] ?? [];
        $ratings = $data['ratings'] ?? [];
        $portfolio = $data['portfolio'] ?? [];

        return new self(
            sedol: $sedol,
            marketCode: (string) $quote['marketCode'],
            name: (string) $quote['name'],
            sectorName: isset($quote['sectorName']) ? (string) $quote['sectorName'] : null,
            currency: isset($quote['currency']) ? (string) $quote['currency'] : null,
            lastPrice: isset($quote['lastPrice']) ? (string) $quote['lastPrice'] : null,
            lastPriceDate: isset($quote['lastPriceDate']) ? (string) $quote['lastPriceDate'] : null,
            ongoingCharge: isset($quote['ongoingCharge']) ? (string) $quote['ongoingCharge'] : null,
            analystRating: isset($ratings['analystRating']) ? (int) $ratings['analystRating'] : null,
            analystRatingLabel: isset($ratings['analystRatingLabel']) ? (string) $ratings['analystRatingLabel'] : null,
            srri: isset($ratings['SRRI']) ? (int) $ratings['SRRI'] : null,
            objective: isset($profile['objective']) ? (string) $profile['objective'] : null,
            documents: self::mapDocuments($data['documents'] ?? []),
            assetAllocations: self::mapAllocations($portfolio['asset'] ?? []),
            holdings: self::mapHoldings($portfolio['top10Holdings'] ?? []),
        );
    }

    /**
     * @param  array<array<string, mixed>>  $documents
     * @return array<array{type: string, external_id: ?string, url: string}>
     */
    private static function mapDocuments(array $documents): array
    {
        return array_values(array_filter(array_map(
            fn (array $doc): ?array => isset($doc['type'], $doc['url']) ? [
                'type' => (string) $doc['type'],
                'external_id' => isset($doc['id']) ? (string) $doc['id'] : null,
                'url' => (string) $doc['url'],
            ] : null,
            $documents,
        )));
    }

    /**
     * @param  array<array<string, mixed>>  $allocations
     * @return array<array{label: string, value: string}>
     */
    private static function mapAllocations(array $allocations): array
    {
        return array_values(array_filter(array_map(
            fn (array $row): ?array => isset($row['label'], $row['value']) ? [
                'label' => (string) $row['label'],
                'value' => (string) $row['value'],
            ] : null,
            $allocations,
        )));
    }

    /**
     * @param  array<array<string, mixed>>  $holdings
     * @return array<array{position: int, name: string, weighting: string}>
     */
    private static function mapHoldings(array $holdings): array
    {
        $mapped = [];
        foreach (array_values($holdings) as $index => $holding) {
            if (! isset($holding['name'], $holding['weighting'])) {
                continue;
            }
            $mapped[] = [
                'position' => $index + 1,
                'name' => (string) $holding['name'],
                'weighting' => (string) $holding['weighting'],
            ];
        }

        return $mapped;
    }

    private static function invalid(string $sedol, string $reason): InvalidFundPayloadException
    {
        return new InvalidFundPayloadException($sedol, $reason);
    }
}
