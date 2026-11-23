<?php

return [

    /*
    |--------------------------------------------------------------------------
    | External HTTP endpoints
    |--------------------------------------------------------------------------
    |
    | Every external service we call lives here, keyed by a short identifier
    | that Connector classes reference via the `via` parameter of the
    | Http::request / Http::parallel macros (see AppServiceProvider).
    |
    */

    'aj_bell_funds' => env('AJ_BELL_FUNDS_URL', 'https://cdn.core3-dev.ajbbuild.uk/interview/'),

];
