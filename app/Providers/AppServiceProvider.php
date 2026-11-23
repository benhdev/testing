<?php

namespace App\Providers;

use Illuminate\Http\Client\Pool;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        $this->registerHttpMacros();
    }

    /**
     * Register the Http::request and Http::parallel macros used by every
     * Connector class. The `via` parameter maps to a key in config/connectors.php
     * so endpoint URLs are centralised and never hard-coded in a Connector.
     */
    private function registerHttpMacros(): void
    {
        Http::macro('request', function (
            string $via,
            string $uri,
            string $method = 'get',
            array $body = [],
            bool $throw = true,
            array $headers = [],
        ): mixed {
            $response = Http::acceptJson()
                ->timeout(5)
                ->withHeaders($headers)
                ->$method(config('connectors.'.$via).$uri, $body);

            if ($throw) {
                $response->throw();
            }

            return $response->json();
        });

        Http::macro('parallel', function (
            array $requests,
            array $headers = [],
            bool $throw = true,
        ): Collection {
            $pool = Http::pool(function (Pool $pool) use ($requests, $headers) {
                foreach ($requests as $item) {
                    $pool->as($item['key'])
                        ->acceptJson()
                        ->timeout(5)
                        ->withHeaders($headers)
                        ->{$item['method']}(
                            config('connectors.'.$item['via']).$item['uri'],
                            $item['body'] ?? [],
                        );
                }
            });

            return collect($pool)->map(function (Response $response) use ($throw): mixed {
                if ($throw) {
                    $response->throw();
                }

                return $response->json();
            });
        });
    }
}
