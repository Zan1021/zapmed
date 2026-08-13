<?php

/*
|--------------------------------------------------------------------------
| Country Configuration
|--------------------------------------------------------------------------
|
| This file defines the active country for this deployment.
| Each deployment (domain) has its own .env with APP_COUNTRY set.
|
| To launch in a new country:
| 1. Create config/countries/{code}.php
| 2. Set APP_COUNTRY={code} in that deployment's .env
| 3. Deploy same codebase to new domain
|
*/

$country = env('APP_COUNTRY', 'za');

$countryConfig = require __DIR__ . "/countries/{$country}.php";

return array_merge($countryConfig, [
    'active' => $country,
]);
