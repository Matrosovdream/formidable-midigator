<?php

define('MIDIGATOR_SETTINGS', [
    'auth' => [
        'prod'    => 'https://api.midigator.com/auth/v1',
        'sandbox' => 'https://api-sandbox.midigator.com/auth/v1',
    ],
    'orders' => [
        'prod'    => 'https://api.midigator.com/orders/v2/order',
        'sandbox' => 'https://api-sandbox.midigator.com/orders/v2/order',
    ],
    'events' => [
        'prod'    => 'https://api.midigator.com/events/v1',
        'sandbox' => 'https://api-sandbox.midigator.com/events/v1',
    ],
    'prevention' => [
        'prod'    => 'https://api.midigator.com/prevention/v1',
        'sandbox' => 'https://api-sandbox.midigator.com/prevention/v1',
    ],
    'options' => [
        'token'  => 'frm_midigator_bearer_token',
        'exp_ts' => 'frm_midigator_bearer_token_exp',
    ],
    'token_refresh_buffer' => 60,
    'token_fallback_ttl'   => 300,
]);

define('MIDIGATOR_LOG_FOLDER', FRM_MDG_BASE_URL . '/logs');

define('MIDIGATOR_WEBHOOK_URLS', 
[
    'prevention'       => home_url('/wp-json/frm-midigator/v1/prevention'),
    'prevention.new'   => home_url('/wp-json/frm-midigator/v1/prevention-new'),
    'prevention.match' => home_url('/wp-json/frm-midigator/v1/prevention-match'),
]);

define('MIDIGATOR_WEBHOOK_EMAIL', '');