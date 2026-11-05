<?php

/*
|--------------------------------------------------------------------------
| Question Bank feature flag
|--------------------------------------------------------------------------
|
| Treatment slugs whose assessment flow is served from the DATABASE question
| bank (via AssessmentService) instead of config/assessment-questions.php.
|
| A slug only actually switches over if it ALSO has a LIVE category manifest in
| the bank — otherwise the flow falls back to the legacy config automatically.
| This lets us cut Weight Management over first and migrate the rest one at a
| time, with zero risk to categories still on the config.
|
| Set via QUESTIONBANK_SLUGS (comma-separated) or edit the default below.
|
*/

return [
    'enabled_slugs' => array_filter(array_map(
        'trim',
        explode(',', (string) env('QUESTIONBANK_SLUGS', 'weight-loss'))
    )),
];
