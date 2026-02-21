<?php
if ( ! defined('ABSPATH') ) { exit; }

class FrmMidigatorApi extends MidigatorLib {

    public function __construct(string $apiSecret, bool $sandbox = false, ?array $cfg = null) {
        parent::__construct($apiSecret, $sandbox, $cfg);
    }

    /* ============================================================
     * Orders
     * ============================================================ */

    public function getOrders(array $params = []): array {
        $url = $this->buildUrl('orders', '', $params);
        return $this->request('GET', $url);
    }

    public function getOrderById(string $orderId, array $params = []): array {
        $orderId = trim($orderId);
        if ($orderId === '') {
            return ['ok' => false, 'error' => 'Missing orderId'];
        }
        $url = $this->buildUrl('orders', rawurlencode($orderId), $params);
        return $this->request('GET', $url);
    }

    /* ============================================================
     * Prevention flow “list” (subscriptions)
     * ============================================================ */

    public function listSubscriptions(): array {
        $url = $this->buildUrl('events', 'subscribe');
        return $this->request('GET', $url);
    }

    public function listPreventionFlowList(): array {
        $res = $this->listSubscriptions();
        if (!$res['ok']) return $res;

        $rows = $res['data'];
        if (!is_array($rows)) {
            return ['ok' => true, 'status' => $res['status'] ?? 200, 'data' => []];
        }

        $out = [];
        foreach ($rows as $row) {
            if (!is_array($row)) continue;
            $eventType = (string)($row['event_type'] ?? '');
            if ($eventType !== '' && strpos($eventType, 'prevention.') === 0) {
                $out[] = $row;
            }
        }

        return ['ok' => true, 'status' => $res['status'] ?? 200, 'data' => $out];
    }

    public function createSubscription(string $eventType, string $url, string $email, array $auth = []): array {

        $eventType = trim($eventType);
        $url       = trim($url);
        $email     = trim($email);

        if ($eventType === '' || $url === '' || $email === '') {
            return ['ok' => false, 'error' => 'Missing eventType/url/email'];
        }

        $payload = [
            'email'      => $email,
            'url'        => $url,
            'event_type' => $eventType,
        ];

        if (!empty($auth['username']) || !empty($auth['password'])) {
            $payload['auth'] = [
                'username' => (string)($auth['username'] ?? ''),
                'password' => (string)($auth['password'] ?? ''),
            ];
        }

        $endpoint = $this->buildUrl('events', 'subscribe');
        return $this->request('POST', $endpoint, $payload);
    }

    public function getPreventionData(string $preventionGuid): array {
        $preventionGuid = trim($preventionGuid);
        if ($preventionGuid === '') {
            return ['ok' => false, 'error' => 'Missing preventionGuid'];
        }

        $url = $this->buildUrl('events', 'prevention/' . rawurlencode($preventionGuid));
        return $this->request('GET', $url);
    }

    public function resolvePreventionAlert(string $preventionGuid, string $resolutionType, string $otherDescription = ''): array {

        $preventionGuid = trim($preventionGuid);
        $resolutionType = trim($resolutionType);

        if ($preventionGuid === '' || $resolutionType === '') {
            return ['ok' => false, 'error' => 'Missing preventionGuid/resolutionType'];
        }

        $payload = [
            'resolution_type'   => $resolutionType,
            'other_description' => (string)$otherDescription,
        ];

        $url = $this->buildUrl(
            'prevention',
            'prevention/' . rawurlencode($preventionGuid) . '/resolution'
        );

        return $this->request('POST', $url, $payload);
    }
}