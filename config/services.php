<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Customers API
    |--------------------------------------------------------------------------
    |
    | The upstream API (vzdeveloper/customers-api) that lists all customers.
    | It is unstable, so requests time out and are retried on connection
    | errors and 5xx responses. "attempts" is the total number of attempts.
    |
    */

    'customers_api' => [
        'base_url' => env('CUSTOMERS_API_URL', 'http://host.docker.internal:8080/api/v1'),
        'timeout' => (int) env('CUSTOMERS_API_TIMEOUT', 5),
        'attempts' => (int) env('CUSTOMERS_API_ATTEMPTS', 3),
        'retry_sleep_ms' => (int) env('CUSTOMERS_API_RETRY_SLEEP_MS', 300),
    ],

];
