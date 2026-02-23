<?php
if ( ! defined('ABSPATH') ) { exit; }

class FrmMidigatorEventHelper {

    protected FrmMidigatorApi $api;

    public function __construct(FrmMidigatorApi $api) {
        $this->api = $api;
    }

    /**
     * Create default subscriptions for:
     * prevention
     * prevention.new
     * prevention.match
     */
    public function createDefaultSubscriptions(): array {

        if (!defined('MIDIGATOR_WEBHOOK_URLS') || !is_array(MIDIGATOR_WEBHOOK_URLS)) {
            return [
                'ok' => false,
                'error' => 'MIDIGATOR_WEBHOOK_URLS not defined',
            ];
        }

        if (!defined('MIDIGATOR_WEBHOOK_EMAIL') || empty(MIDIGATOR_WEBHOOK_EMAIL)) {
            return [
                'ok' => false,
                'error' => 'MIDIGATOR_WEBHOOK_EMAIL not defined',
            ];
        }

        $results = [];

        foreach (MIDIGATOR_WEBHOOK_URLS as $eventType => $url) {

            if (empty($eventType) || empty($url)) {
                continue;
            }

            $res = $this->api->createSubscription(
                $eventType,
                $url,
                MIDIGATOR_WEBHOOK_EMAIL
            );

            $results[$eventType] = $res;
        }

        return [
            'ok' => true,
            'data' => $results,
        ];
    }
}