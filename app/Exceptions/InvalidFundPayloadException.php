<?php

namespace App\Exceptions;

use InvalidArgumentException;

/**
 * Thrown when a single fund's upstream JSON is structurally invalid (missing
 * required fields, wrong types, etc.). The refresh Action catches this per
 * fund, logs a warning, and continues — a bad fund doesn't poison the run.
 */
class InvalidFundPayloadException extends InvalidArgumentException
{
    public function __construct(
        public readonly string $sedol,
        string $message,
    ) {
        parent::__construct($message);
    }
}
