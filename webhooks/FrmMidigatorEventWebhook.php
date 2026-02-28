<?php
if ( ! defined('ABSPATH') ) { exit; }

class FrmMidigatorEventWebhook {

    public function __construct() {
        add_action('rest_api_init', [$this, 'register_routes']);
    }

    /**
     * Register REST routes for all Midigator event types (10).
     */
    public function register_routes(): void {

        $events = defined('MIDIGATOR_EVENT_TYPES') && is_array(MIDIGATOR_EVENT_TYPES)
            ? MIDIGATOR_EVENT_TYPES
            : [];

        // Fallback safety (in case constants file not loaded for some reason)
        if (empty($events)) {
            // Return error
            register_rest_route('frm-midigator/v1', '/webhook', [
                'methods'  => 'POST, GET',
                'callback' => function() {
                    return new WP_REST_Response([
                        'ok'    => false,
                        'error' => 'No event types configured',
                    ], 500);
                },
                'permission_callback' => '__return_true',
            ]);
        }

        foreach ($events as $type) {
            $type = is_string($type) ? trim($type) : '';
            if ($type === '') { continue; }

            $route = '/' . $this->event_type_to_route_slug($type);

            register_rest_route('frm-midigator/v1', $route, [
                'methods'  => 'POST, GET',
                'callback' => function(WP_REST_Request $request) use ($type) {
                    return $this->process_event($type, $request);
                },
                'permission_callback' => '__return_true',
            ]);
        }
    }

    /**
     * Convert event type like "order_validation.new" into route slug "order-validation-new".
     *
     * Rules:
     * - "_" => "-"
     * - "." => "-"
     * - lowercase
     */
    private function event_type_to_route_slug(string $type): string {
        $slug = strtolower($type);
        $slug = str_replace(['_', '.'], '-', $slug);
        $slug = preg_replace('/[^a-z0-9\-]/', '', $slug);
        $slug = preg_replace('/-+/', '-', $slug);
        $slug = trim($slug, '-');
        return $slug;
    }

    /**
     * Common processor for all webhook event types.
     */
    private function process_event(string $type, WP_REST_Request $request): WP_REST_Response {

        $body = (string) $request->get_body();
        $data = json_decode($body, true);

        if (!is_array($data)) {
            $data = [
                'raw_body' => $body,
                'error'    => 'Invalid JSON',
            ];
        }

        // Add some context (useful in logs)
        $data['_meta'] = array_merge(
            isset($data['_meta']) && is_array($data['_meta']) ? $data['_meta'] : [],
            [
                'received_type' => $type,
                'method'        => $request->get_method(),
                'route'         => $request->get_route(),
                'ip'            => $this->get_client_ip(),
                'received_at'   => gmdate('Y-m-d H:i:s') . ' UTC',
            ]
        );

        // Log using your logger (if exists)
        if (class_exists('FrmMidigatorLogger')) {
            try {
                $logger = new FrmMidigatorLogger();
                $logger->log($type, $data);
            } catch (\Throwable $e) {
                
            }
        }

        return new WP_REST_Response([
            'ok'       => true,
            'received' => $type,
        ], 200);
    }

    private function get_client_ip(): string {
        // Best-effort IP (do not trust for security decisions)
        $keys = [
            'HTTP_CF_CONNECTING_IP',
            'HTTP_X_FORWARDED_FOR',
            'HTTP_X_REAL_IP',
            'REMOTE_ADDR',
        ];

        foreach ($keys as $k) {
            if (empty($_SERVER[$k])) { continue; }
            $v = (string) $_SERVER[$k];

            // X_FORWARDED_FOR can be "client, proxy1, proxy2"
            if ($k === 'HTTP_X_FORWARDED_FOR' && strpos($v, ',') !== false) {
                $parts = array_map('trim', explode(',', $v));
                $v = $parts[0] ?? $v;
            }

            $v = trim($v);
            if ($v !== '') return $v;
        }

        return '';
    }
}

// On plugin load, instantiate the webhook handler to register routes
new FrmMidigatorEventWebhook();