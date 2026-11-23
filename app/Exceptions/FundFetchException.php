<?php

namespace App\Exceptions;

use RuntimeException;

/**
 * Thrown when the AJ Bell CDN as a whole cannot be reached or returns a
 * non-success response. The refresh Action re-raises this so the scheduler
 * surfaces the failure rather than silently swallowing a bad run.
 */
class FundFetchException extends RuntimeException
{
    //
}
