<?php
if ( ! defined('ABSPATH') ) { exit; }

class FrmMidigatorEventWebhook {

    public function __construct() {
        add_action('rest_api_init', [$this, 'register_routes']);
    }

    /**
     * Register REST routes
     */
    public function register_routes(): void {

        register_rest_route('frm-midigator/v1', '/prevention', [
            'methods'  => 'POST',
            'callback' => [$this, 'handle_prevention'],
            'permission_callback' => '__return_true',
        ]);

        register_rest_route('frm-midigator/v1', '/prevention-new', [
            'methods'  => 'POST',
            'callback' => [$this, 'handle_prevention_new'],
            'permission_callback' => '__return_true',
        ]);

        register_rest_route('frm-midigator/v1', '/prevention-match', [
            'methods'  => 'POST',
            'callback' => [$this, 'handle_prevention_match'],
            'permission_callback' => '__return_true',
        ]);
    }

    /* ============================================================
     * Handlers
     * ============================================================ */

    public function handle_prevention(WP_REST_Request $request): WP_REST_Response {
        return $this->process_event('prevention', $request);
    }

    public function handle_prevention_new(WP_REST_Request $request): WP_REST_Response {
        return $this->process_event('prevention.new', $request);
    }

    public function handle_prevention_match(WP_REST_Request $request): WP_REST_Response {
        return $this->process_event('prevention.match', $request);
    }

    /**
     * Common processor
     */
    private function process_event(string $type, WP_REST_Request $request): WP_REST_Response {

        $body = $request->get_body();
        $data = json_decode($body, true);

        if (!is_array($data)) {
            $data = [
                'raw_body' => $body,
                'error'    => 'Invalid JSON',
            ];
        }

        // Log using your logger
        if (class_exists('FrmMidigatorLogger')) {
            $logger = new FrmMidigatorLogger();
            $logger->log($type, $data);
        }

        return new WP_REST_Response([
            'ok' => true,
            'received' => $type,
        ], 200);
    }
}

// On plugin load, instantiate the webhook handler to register routes
new FrmMidigatorEventWebhook();