<?php
if ( ! defined('ABSPATH') ) { exit; }

class MidigatorLib {

    /** @var array<string,mixed> */
    protected array $cfg;

    protected string $apiSecret;
    protected bool $sandbox;

    public function __construct(string $apiSecret, bool $sandbox = false, ?array $cfg = null) {
        $this->apiSecret = trim($apiSecret);
        $this->sandbox   = (bool) $sandbox;

        // If cfg not passed, try MIDIGATOR_SETTINGS const
        if ($cfg === null) {
            if (!defined('MIDIGATOR_SETTINGS') || !is_array(MIDIGATOR_SETTINGS)) {
                throw new RuntimeException('MIDIGATOR_SETTINGS const is not defined or not an array');
            }
            /** @var array $cfg */
            $cfg = MIDIGATOR_SETTINGS;
        }

        $this->cfg = $cfg;
    }

    /* ============================================================
     * Token
     * ============================================================ */

    protected function getBearerToken(): ?string {

        if ($this->apiSecret === '') {
            $this->log('Midigator: API Secret empty');
            return null;
        }

        $optToken = (string)($this->cfg['options']['token']  ?? 'frm_midigator_bearer_token');
        $optExp   = (string)($this->cfg['options']['exp_ts'] ?? 'frm_midigator_bearer_token_exp');

        $token = (string) get_option($optToken, '');
        $expTs = (int) get_option($optExp, 0);

        $buffer = (int)($this->cfg['token_refresh_buffer'] ?? 60);

        if ($token !== '' && $expTs > 0 && time() < ($expTs - $buffer)) {
            return $token;
        }

        $authBase = $this->getBaseUrl('auth');
        if (!$authBase) {
            $this->log('Midigator: auth base url missing in MIDIGATOR_SETTINGS');
            return null;
        }

        $url = rtrim($authBase, '/') . '/auth';

        // Docs:
        // POST /auth
        // Authorization: Bearer API_SECRET
        $res = wp_remote_request($url, [
            'method'  => 'POST',
            'timeout' => 30,
            'headers' => [
                'Authorization' => 'Bearer ' . $this->apiSecret,
                'Accept'        => 'application/json',
            ],
        ]);

        if (is_wp_error($res)) {
            $this->log('Midigator Auth WP_Error: ' . $res->get_error_message());
            return null;
        }

        $status = (int) wp_remote_retrieve_response_code($res);
        $raw    = (string) wp_remote_retrieve_body($res);
        $data   = json_decode($raw, true);

        if ($status < 200 || $status >= 300) {
            $this->log('Midigator Auth HTTP ' . $status . ': ' . $raw);
            return null;
        }

        $newToken = (is_array($data) && !empty($data['token'])) ? (string)$data['token'] : '';
        if ($newToken === '') {
            $this->log('Midigator Auth: token missing: ' . $raw);
            return null;
        }

        $exp = $this->jwtExpTimestamp($newToken);

        if ($exp <= 0) {
            $fallbackTtl = (int)($this->cfg['token_fallback_ttl'] ?? 300);
            $exp = time() + $fallbackTtl;
        }

        update_option($optToken, $newToken, false);
        update_option($optExp, $exp, false);

        return $newToken;
    }

    protected function jwtExpTimestamp(string $jwt): int {
        $parts = explode('.', $jwt);
        if (count($parts) < 2) return 0;

        $payloadJson = $this->base64UrlDecode($parts[1]);
        if ($payloadJson === '') return 0;

        $payload = json_decode($payloadJson, true);
        if (!is_array($payload)) return 0;

        $exp = isset($payload['exp']) ? (int)$payload['exp'] : 0;
        return $exp > 0 ? $exp : 0;
    }

    protected function base64UrlDecode(string $data): string {
        $data = strtr($data, '-_', '+/');
        $pad = strlen($data) % 4;
        if ($pad) $data .= str_repeat('=', 4 - $pad);

        $decoded = base64_decode($data, true);
        return ($decoded !== false) ? $decoded : '';
    }

    /* ============================================================
     * HTTP
     * ============================================================ */

    protected function request(string $method, string $url, ?array $body = null): array {

        $token = $this->getBearerToken();
        if (!$token) {
            return ['ok' => false, 'error' => 'Unable to obtain Midigator bearer token'];
        }

        $args = [
            'method'  => strtoupper($method),
            'timeout' => 30,
            'headers' => [
                'Authorization' => 'Bearer ' . $token,
                'Accept'        => 'application/json',
            ],
        ];

        if ($args['method'] !== 'GET') {
            $args['headers']['Content-Type'] = 'application/json';
            $args['body'] = ($body !== null) ? wp_json_encode($body) : '{}';
        }

        $res = wp_remote_request($url, $args);

        if (is_wp_error($res)) {
            return ['ok' => false, 'error' => $res->get_error_message()];
        }

        $status = (int) wp_remote_retrieve_response_code($res);
        $raw    = (string) wp_remote_retrieve_body($res);
        $data   = json_decode($raw, true);

        if ($status >= 200 && $status < 300) {
            return [
                'ok'     => true,
                'status' => $status,
                'data'   => $data,
            ];
        }

        return [
            'ok'     => false,
            'status' => $status,
            'error'  => (is_array($data) && isset($data['message'])) ? $data['message'] : $raw,
            'data'   => is_array($data) ? $data : null,
        ];
    }

    /* ============================================================
     * URLs
     * ============================================================ */

    protected function getBaseUrl(string $key): ?string {
        if (!isset($this->cfg[$key]) || !is_array($this->cfg[$key])) return null;

        $which = $this->sandbox ? 'sandbox' : 'prod';
        $url = $this->cfg[$key][$which] ?? '';
        $url = is_string($url) ? trim($url) : '';

        return $url !== '' ? $url : null;
    }

    protected function buildUrl(string $baseKey, string $path = '', array $query = []): string {
        $base = $this->getBaseUrl($baseKey) ?: '';
        $url  = rtrim($base, '/') . '/' . ltrim($path, '/');
        $url  = rtrim($url, '/');

        if (!empty($query)) {
            $url .= '?' . http_build_query($query);
        }

        return $url;
    }

    protected function log(string $message): void {
        error_log($message);
    }
}