<?php
if ( ! defined('ABSPATH') ) { exit; }

/**
 * Links
 */
define('MIDIGATOR_RESOLVED_LIST_PAGE', '/midigator-preventions-list-resolved/');


/*
    * API Secret, Mode
*/
define('MIDIGATOR_API_SECRET', '');
define('MIDIGATOR_SANDBOX_MODE', false);

/**
 * Midigator endpoints + plugin settings
 */

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
    'ping' => [
        'prod'    => 'https://api.midigator.com/ping/v1',
        'sandbox' => 'https://api-sandbox.midigator.com/ping/v1',
    ],
    'options' => [
        'token'  => 'frm_midigator_bearer_token',
        'exp_ts' => 'frm_midigator_bearer_token_exp',
    ],
    'token_refresh_buffer' => 60,
    'token_fallback_ttl'   => 300,
]);

/**
 * Folder (URL) where you store logs.
 * Note: typically logs are better stored by PATH (not URL), but keeping your original style.
 */
define('MIDIGATOR_LOG_FOLDER', FRM_MDG_BASE_URL . '/logs');

/**
 * All Midigator event types available
 */
define('MIDIGATOR_EVENT_TYPES', [
    'chargeback.new',
    'prevention.new',
    'rdr.new',
    'rdr.match',
    'order_validation.new',
    'chargeback.match',
    'prevention.match',
    'order_validation.match',
    'chargeback.result',
    'chargeback.dnf',
]);

/**
 * Webhook URLs (type => URL)
 * Route naming rule here: dots become hyphens.
 * Example: chargeback.new => /chargeback-new
 */
define('MIDIGATOR_WEBHOOK_URLS', [
    'chargeback.new'         => home_url('/wp-json/frm-midigator/v1/chargeback-new'),
    'prevention.new'         => home_url('/wp-json/frm-midigator/v1/prevention-new'),
    'rdr.new'                => home_url('/wp-json/frm-midigator/v1/rdr-new'),
    'rdr.match'              => home_url('/wp-json/frm-midigator/v1/rdr-match'),
    'order_validation.new'   => home_url('/wp-json/frm-midigator/v1/order-validation-new'),
    'chargeback.match'       => home_url('/wp-json/frm-midigator/v1/chargeback-match'),
    'prevention.match'       => home_url('/wp-json/frm-midigator/v1/prevention-match'),
    'order_validation.match' => home_url('/wp-json/frm-midigator/v1/order-validation-match'),
    'chargeback.result'      => home_url('/wp-json/frm-midigator/v1/chargeback-result'),
    'chargeback.dnf'         => home_url('/wp-json/frm-midigator/v1/chargeback-dnf'),
]);

/**
 * Logs per event type (type => filename)
 */
define('MIDIGATOR_LOG_MAP', [
    'chargeback.new'         => 'chargeback_new.log',
    'prevention.new'         => 'prevention_new.log',
    'rdr.new'                => 'rdr_new.log',
    'rdr.match'              => 'rdr_match.log',
    'order_validation.new'   => 'order_validation_new.log',
    'chargeback.match'       => 'chargeback_match.log',
    'prevention.match'       => 'prevention_match.log',
    'order_validation.match' => 'order_validation_match.log',
    'chargeback.result'      => 'chargeback_result.log',
    'chargeback.dnf'         => 'chargeback_dnf.log',
]);

define('MIDIGATOR_WEBHOOK_EMAIL', 'matrosovdream@gmail.com');

/**
 * Resolve reasons
 */
define('MIDIGATOR_RESOLVE_PREVENTION_REASONS', [
    'could_not_find_order' => 'Order not found',
    'declined_or_canceled_nothing_to_do' => 'Order declined or canceled',
    'issued_full_refund' => 'Full refund issued',
    'issued_refund_for_remaining_amount' => 'Refund remaining amount',
    '3ds_authorized_successfully' => '3DS authorized successfully',
    'previously_refunded_nothing_to_do' => 'Already refunded',
    'unable_to_refund_merchant_account_closed' => 'Merchant account closed',
    'other' => 'Other action',
]);

