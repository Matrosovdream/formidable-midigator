<?php
if ( ! defined('ABSPATH') ) { exit; }

final class MidigatorPreventionsListShortcode {

    public const SHORTCODE = 'midigator-preventions-list';

    /** GET params */
    private const QP_PAGE        = 'mid_pre_page';
    private const QP_Q           = 'mid_pre_q';
    private const QP_SORT        = 'mid_pre_sort';
    private const QP_DIR         = 'mid_pre_dir';

    private const QP_CARD_FIRST6 = 'card_first_6';
    private const QP_CARD_LAST4  = 'card_last_4';

    private const ALERT_EXPIRATION_HOURS = 72;

    /** AJAX */
    private const NONCE_ACTION             = 'midigator_preventions_nonce';
    private const AJAX_ACTION_RESOLVE      = 'midigator_resolve_prevention';
    private const AJAX_ACTION_BULK_RESOLVE = 'midigator_bulk_resolve_prevention';
    private const AJAX_ACTION_BULK_REFUND  = 'midigator_bulk_full_refund_prevention';

    /** Assets */
    private const CSS_HANDLE = 'midigator-preventions-list-css';
    private const JS_HANDLE  = 'midigator-preventions-list-js';

    public function __construct() {
        add_shortcode(self::SHORTCODE, [ $this, 'render_shortcode' ]);

        add_action('wp_ajax_' . self::AJAX_ACTION_RESOLVE, [ $this, 'ajax_resolve_prevention' ]);
        add_action('wp_ajax_' . self::AJAX_ACTION_BULK_RESOLVE, [ $this, 'ajax_bulk_resolve_prevention' ]);
        add_action('wp_ajax_' . self::AJAX_ACTION_BULK_REFUND, [ $this, 'ajax_bulk_full_refund_prevention' ]);
    }

    public function render_shortcode($atts = []): string {

        $this->enqueue_assets();

        $atts = shortcode_atts([
            'show-resolved'       => 'false',
            'default-bulk-reason' => '',
        ], (array) $atts, self::SHORTCODE);

        $showResolved      = filter_var($atts['show-resolved'], FILTER_VALIDATE_BOOLEAN);
        $defaultBulkReason = sanitize_text_field((string) $atts['default-bulk-reason']);

        $page = isset($_GET[self::QP_PAGE]) ? max(1, (int) $_GET[self::QP_PAGE]) : 1;
        $q    = isset($_GET[self::QP_Q]) ? sanitize_text_field((string) $_GET[self::QP_Q]) : '';
        $sort = isset($_GET[self::QP_SORT]) ? sanitize_key((string) $_GET[self::QP_SORT]) : '';
        $dir  = isset($_GET[self::QP_DIR]) ? sanitize_key((string) $_GET[self::QP_DIR]) : '';

        if (!in_array($sort, ['arn','case','date'], true)) $sort = '';
        if (!in_array($dir, ['asc','desc'], true)) $dir = '';

        $card_first_6 = isset($_GET[self::QP_CARD_FIRST6]) ? preg_replace('/\D+/', '', (string) $_GET[self::QP_CARD_FIRST6]) : '';
        $card_last_4  = isset($_GET[self::QP_CARD_LAST4])  ? preg_replace('/\D+/', '', (string) $_GET[self::QP_CARD_LAST4])  : '';

        $filters = [];
        $filters['is_resolved'] = $showResolved ? true : false;

        if ($card_first_6 !== '') {
            $filters['card_first_6'] = substr($card_first_6, 0, 6);
        }
        if ($card_last_4 !== '') {
            $filters['card_last_4'] = substr($card_last_4, 0, 4);
        }

        $per_page = 30;
        $opts = [
            'page' => $page,
            'per_page' => $per_page,
            'includeEntities' => [ 'resolve', 'resolve_history', 'orders' ],
        ];

        $list = $this->safe_get_list($filters, $opts);

        if (isset($_GET['log'])) {
            echo '<pre>';
            print_r($list);
            echo '</pre>';
        }

        $p = isset($list['pagination']) && is_array($list['pagination']) ? $list['pagination'] : [];
        $cur_page    = isset($p['page']) ? max(1, (int) $p['page']) : $page;
        $total_pages = isset($p['total_pages']) ? max(1, (int) $p['total_pages']) : 1;

        $base_url = $this->current_url_without([ self::QP_PAGE ]);

        $reasons = (defined('MIDIGATOR_RESOLVE_PREVENTION_REASONS') && is_array(MIDIGATOR_RESOLVE_PREVENTION_REASONS))
            ? MIDIGATOR_RESOLVE_PREVENTION_REASONS
            : [];

        ob_start();
        ?>
        <div class="mid-pre-wrap" id="mid-pre">

            <div class="mid-pre-head">
                <div>
                    <div class="mid-pre-title">
                        <?php echo $showResolved ? 'Prevention Flow - Resolved' : 'Prevention Flow'; ?>
                    </div>
                </div>

                <div class="mid-pre-actions">
                    <select id="midPreBulkReason" class="mid-pre-bulk-select">
                        <option value="">— Select reason —</option>
                        <?php foreach ($reasons as $value => $label): ?>
                            <option
                                value="<?php echo esc_attr((string) $value); ?>"
                                <?php selected((string) $defaultBulkReason, (string) $value); ?>
                            >
                                <?php echo esc_html((string) $label); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>

                    <button class="faip-btn faip-btn-primary" id="midPreBulkResolve" type="button" disabled>Resolve</button>
                    <button class="faip-btn faip-btn-success" id="midPreBulkRefund" type="button" disabled>Full refund</button>
                </div>
            </div>

            <form method="get" class="mid-pre-filters" id="midPreFiltersForm">
                <?php
                foreach ($_GET as $k => $v) {
                    if (in_array($k, [self::QP_PAGE, self::QP_Q, self::QP_CARD_FIRST6, self::QP_CARD_LAST4], true)) continue;
                    if (is_array($v)) continue;
                    echo '<input type="hidden" name="' . esc_attr($k) . '" value="' . esc_attr((string) $v) . '">';
                }
                ?>

                <input type="hidden" name="<?php echo esc_attr(self::QP_Q); ?>" value="<?php echo esc_attr($q); ?>">

                <div class="mid-pre-filter">
                    <label for="midPreCardFirst6">BIN</label>
                    <input
                        id="midPreCardFirst6"
                        name="<?php echo esc_attr(self::QP_CARD_FIRST6); ?>"
                        type="text"
                        inputmode="numeric"
                        pattern="[0-9]*"
                        value="<?php echo esc_attr($card_first_6); ?>"
                        placeholder="e.g. 551306"
                        maxlength="6"
                    />
                </div>

                <div class="mid-pre-filter">
                    <label for="midPreCardLast4">Last 4</label>
                    <input
                        id="midPreCardLast4"
                        name="<?php echo esc_attr(self::QP_CARD_LAST4); ?>"
                        type="text"
                        inputmode="numeric"
                        pattern="[0-9]*"
                        value="<?php echo esc_attr($card_last_4); ?>"
                        placeholder="e.g. 9157"
                        maxlength="4"
                    />
                </div>

                <div class="mid-pre-filter mid-pre-filter-actions">
                    <label>&nbsp;</label>
                    <div class="mid-pre-inline">
                        <button type="submit" class="mid-pre-btn mid-pre-btn-primary">Apply</button>
                        <a class="mid-pre-btn" href="<?php echo esc_url($this->current_url_without([self::QP_PAGE, self::QP_Q, self::QP_CARD_FIRST6, self::QP_CARD_LAST4])); ?>">Reset</a>
                    </div>
                </div>

                <input type="hidden" name="<?php echo esc_attr(self::QP_PAGE); ?>" value="1">
            </form>

            <div class="mid-pre-statusbar" id="midPreBulkStatusbar"></div>

            <div class="mid-pre-statusbar" id="midPreStatusbar">
                <?php
                if (!empty($list['error'])) {
                    echo '<span class="mid-pre-msg err">' . esc_html((string) $list['error']) . '</span>';
                }
                ?>
            </div>

            <table class="table table-striped table-listing mid-pre-table" style="width:100%;">
                <thead>
                    <tr>
                        <th style="width:34px;"><input type="checkbox" id="midPreCheckAll"></th>
                        <th style="width:160px;">Received on</th>
                        <th style="width:160px;">Transaction date</th>
                        <th style="width:170px;">Alert expiration</th>
                        <th style="width:120px;">Amount</th>
                        <th style="width:90px;">BIN</th>
                        <th style="width:80px;">Last 4</th>
                        <th style="width:220px;">ARN</th>
                        <th style="width:260px;">Descriptor</th>
                        <th style="width:260px;">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php echo $this->render_rows_html($list); ?>
                </tbody>
            </table>

            <div class="mid-pre-footer">
                <div class="mid-pre-pager">
                    <?php
                    $prev_disabled = ($cur_page <= 1);
                    $next_disabled = ($cur_page >= $total_pages);

                    $prev_url = $this->add_query_arg_safe($base_url, self::QP_PAGE, (string) max(1, $cur_page - 1));
                    $next_url = $this->add_query_arg_safe($base_url, self::QP_PAGE, (string) min($total_pages, $cur_page + 1));
                    ?>
                    <a class="mid-pre-btn <?php echo $prev_disabled ? 'is-disabled' : ''; ?>"
                       href="<?php echo $prev_disabled ? '#' : esc_url($prev_url); ?>"
                       <?php echo $prev_disabled ? 'aria-disabled="true"' : ''; ?>
                    >Prev</a>

                    <span class="mid-pre-page"><?php echo esc_html("Page {$cur_page} / {$total_pages}"); ?></span>

                    <a class="mid-pre-btn <?php echo $next_disabled ? 'is-disabled' : ''; ?>"
                       href="<?php echo $next_disabled ? '#' : esc_url($next_url); ?>"
                       <?php echo $next_disabled ? 'aria-disabled="true"' : ''; ?>
                    >Next</a>
                </div>
            </div>

        </div>

        <div class="mid-pre-backdrop" id="midPreResolveModal" aria-hidden="true">
            <div class="mid-pre-modal">
                <button type="button" class="mid-pre-modal-close" id="midPreResolveClose" aria-label="Close">×</button>

                <div class="mid-pre-modal-title" id="midPreResolveTitle">Resolve</div>

                <div class="mid-pre-modal-body">
                    <div class="mid-pre-field">
                        <div class="mid-pre-field-label">Resolve reason</div>

                        <select id="midPreResolveReason" style="width:100%;">
                            <option value="">— Select —</option>
                            <?php foreach ($reasons as $value => $label): ?>
                                <option value="<?php echo esc_attr((string) $value); ?>">
                                    <?php echo esc_html((string) $label); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>

                        <div class="mid-pre-field" id="midPreOtherWrap" style="margin-top:10px; display:none;">
                            <div class="mid-pre-field-label">Other note</div>
                            <textarea id="midPreResolveNote" rows="4" style="width:100%; resize:vertical;" placeholder="Write note..."></textarea>
                        </div>

                        <div class="mid-pre-modal-statusbar" id="midPreResolveStatusbar"></div>
                    </div>
                </div>

                <div class="mid-pre-modal-actions">
                    <button class="mid-pre-btn" type="button" id="midPreResolveCancel">Cancel</button>
                    <button class="mid-pre-btn mid-pre-btn-success" type="button" id="midPreResolveConfirm" disabled>Resolve</button>
                </div>
            </div>
        </div>
        <?php

        return (string) ob_get_clean();
    }

    private function enqueue_assets(): void {

        wp_register_style(self::CSS_HANDLE, false, [], '1.0.1');
        wp_enqueue_style(self::CSS_HANDLE);

        wp_enqueue_style(
            'dotfiler-ai-photos-page-css',
            esc_url(FRM_MDG_BASE_PATH . 'assets/midigator-list.css?time=' . time()),
            [],
            '1.0.0'
        );

        wp_enqueue_script(
            'dotfiler-ai-photos-page-js',
            esc_url(FRM_MDG_BASE_PATH . 'assets/midigator-list.js?time=' . time()),
            [],
            '1.0.0',
            true
        );

        wp_localize_script('dotfiler-ai-photos-page-js', 'MID_PRE_AJAX', [
            'ajax_url'            => admin_url('admin-ajax.php'),
            'nonce'               => wp_create_nonce(self::NONCE_ACTION),
            'action_resolve'      => self::AJAX_ACTION_RESOLVE,
            'action_bulk_resolve' => self::AJAX_ACTION_BULK_RESOLVE,
            'action_bulk_refund'  => self::AJAX_ACTION_BULK_REFUND,
        ]);
    }

    public function ajax_resolve_prevention(): void {
        check_ajax_referer(self::NONCE_ACTION, '_ajax_nonce');

        $preventionGuid = isset($_POST['prevention_guid']) ? sanitize_text_field((string) $_POST['prevention_guid']) : '';
        $reason         = isset($_POST['resolve_reason']) ? sanitize_text_field((string) $_POST['resolve_reason']) : '';
        $note           = isset($_POST['note']) ? sanitize_textarea_field((string) $_POST['note']) : '';

        if ($preventionGuid === '') {
            wp_send_json_error([ 'message' => 'Missing prevention_guid' ], 400);
        }
        if ($reason === '') {
            wp_send_json_error([ 'message' => 'Missing resolve_reason' ], 400);
        }
        if ($reason === 'other' && trim($note) === '') {
            wp_send_json_error([ 'message' => 'Note is required for "Other action"' ], 400);
        }

        try {
            $prevHelper = new FrmMidigatorPreventionHelper();
            $res = $prevHelper->resolvePreventionAlert($preventionGuid, $reason, $note);

            $ok = false;
            if (is_array($res) && array_key_exists('ok', $res)) {
                $ok = (bool) $res['ok'];
            } elseif (is_array($res) && array_key_exists('success', $res)) {
                $ok = (bool) $res['success'];
            } elseif (is_object($res) && isset($res->ok)) {
                $ok = (bool) $res->ok;
            }

            if (!$ok) {
                $msg = 'Resolve failed';
                if (is_array($res)) {
                    if (!empty($res['error']) && is_string($res['error'])) $msg = $res['error'];
                    elseif (!empty($res['message']) && is_string($res['message'])) $msg = $res['message'];
                    elseif (!empty($res['data']['message']) && is_string($res['data']['message'])) $msg = $res['data']['message'];
                } elseif (is_object($res)) {
                    if (!empty($res->message) && is_string($res->message)) $msg = $res->message;
                }

                wp_send_json_error([ 'message' => $msg ], 400);
            }

            wp_send_json_success([
                'ok' => true,
                'prevention_guid' => $preventionGuid,
                'resolve_reason'  => $reason,
            ]);

        } catch (\Throwable $e) {
            wp_send_json_error([ 'message' => $e->getMessage() ], 500);
        }
    }

    public function ajax_bulk_resolve_prevention(): void {
        check_ajax_referer(self::NONCE_ACTION, '_ajax_nonce');

        $id             = isset($_POST['id']) ? (int) $_POST['id'] : 0;
        $preventionGuid = isset($_POST['prevention_guid']) ? sanitize_text_field((string) $_POST['prevention_guid']) : '';
        $reason         = isset($_POST['resolve_reason']) ? sanitize_text_field((string) $_POST['resolve_reason']) : '';

        if ($id <= 0) {
            wp_send_json_error([ 'message' => 'Missing row id' ], 400);
        }

        if ($preventionGuid === '') {
            wp_send_json_error([ 'message' => 'Missing prevention_guid' ], 400);
        }

        if ($reason === '') {
            wp_send_json_error([ 'message' => 'Missing resolve_reason' ], 400);
        }

        wp_send_json_success([
            'ok'              => true,
            'id'              => $id,
            'prevention_guid' => $preventionGuid,
            'resolve_reason'  => $reason,
        ]);
    }

    public function ajax_bulk_full_refund_prevention(): void {
        check_ajax_referer(self::NONCE_ACTION, '_ajax_nonce');

        $id             = isset($_POST['id']) ? (int) $_POST['id'] : 0;
        $preventionGuid = isset($_POST['prevention_guid']) ? sanitize_text_field((string) $_POST['prevention_guid']) : '';

        if ($id <= 0) {
            wp_send_json_error([ 'message' => 'Missing row id' ], 400);
        }

        if ($preventionGuid === '') {
            wp_send_json_error([ 'message' => 'Missing prevention_guid' ], 400);
        }

        wp_send_json_success([
            'ok'              => true,
            'id'              => $id,
            'prevention_guid' => $preventionGuid,
        ]);
    }

    private function safe_get_list(array $filters, array $opts): array {
        try {
            $model = new FrmMidigatorPreventionModel();
            $rows = $model->getList($filters, $opts);
            if (!is_array($rows)) $rows = [];

            return $rows;

        } catch (\Throwable $e) {
            return [
                'data' => [],
                'pagination' => [
                    'page' => $opts['page'] ?? 1,
                    'per_page' => $opts['per_page'] ?? 30,
                    'total' => 0,
                    'total_pages' => 1,
                ],
                'error' => $e->getMessage(),
            ];
        }
    }

    private function render_rows_html(array $list): string {

        $items = isset($list['data']) && is_array($list['data']) ? $list['data'] : [];

        if (empty($items)) {
            return '<tr><td colspan="14" class="mid-pre-muted">No results.</td></tr>';
        }

        $fmt_dt = static function($raw): string {
            $raw = (string) $raw;
            if ($raw === '') return '';
            $ts = strtotime($raw);
            if (!$ts) return '';
            return date('Y-m-d H:i', $ts);
        };

        $calc_exp = static function($preventionTsRaw): string {
            $preventionTsRaw = (string) $preventionTsRaw;
            if ($preventionTsRaw === '') return '';
            $ts = strtotime($preventionTsRaw);
            if (!$ts) return '';
            $ts += (int) MidigatorPreventionsListShortcode::ALERT_EXPIRATION_HOURS * 3600;
            return date('Y-m-d H:i', $ts);
        };

        ob_start();

        foreach ($items as $item) {

            if (is_object($item)) $item = (array) $item;
            if (!is_array($item)) continue;

            $id   = isset($item['id']) ? (int) $item['id'] : 0;
            $guid = isset($item['prevention_guid']) ? (string) $item['prevention_guid'] : '';

            $receivedOn = $fmt_dt($item['created_at'] ?? ($item['prevention_timestamp'] ?? ''));
            $txDate     = $fmt_dt($item['transaction_timestamp'] ?? '');
            $prevTsRaw  = (string) ($item['prevention_timestamp'] ?? '');
            $expiration = $calc_exp($prevTsRaw);

            $amount   = isset($item['amount']) ? (string) $item['amount'] : '';
            $currency = isset($item['currency']) ? (string) $item['currency'] : '';

            $bin   = isset($item['card_first_6']) ? (string) $item['card_first_6'] : '';
            $last4 = isset($item['card_last_4']) ? (string) $item['card_last_4'] : '';

            $arn        = isset($item['arn']) ? (string) $item['arn'] : '';
            $descriptor = isset($item['merchant_descriptor']) ? (string) $item['merchant_descriptor'] : '';

            $rowResolved = !empty($item['is_resolved']);

            $orders = [];
            if (isset($item['_entities']['orders']) && is_array($item['_entities']['orders'])) {
                $orders = $item['_entities']['orders'];
            } elseif (isset($item['orders']) && is_array($item['orders'])) {
                $orders = $item['orders'];
            }

            if ($receivedOn === '') $receivedOn = '—';
            if ($txDate === '') $txDate = '—';
            if ($expiration === '') $expiration = '—';
            if ($amount === '') $amount = '—';
            if ($currency === '') $currency = '—';
            if ($bin === '') $bin = '—';
            if ($last4 === '') $last4 = '—';
            if ($arn === '') $arn = '—';
            if ($descriptor === '') $descriptor = '—';

            $btnDisabled = ($guid === '');
            ?>
            <tr data-item-id="<?php echo esc_attr((string) $id); ?>" data-guid="<?php echo esc_attr($guid); ?>">
                <td>
                    <input
                        type="checkbox"
                        data-guid="<?php echo esc_attr($guid); ?>"
                        data-item-id="<?php echo esc_attr((string) $id); ?>"
                        <?php echo $btnDisabled ? 'disabled' : ''; ?>
                    >
                </td>

                <td><?php echo esc_html($receivedOn); ?></td>
                <td><?php echo esc_html($txDate); ?></td>
                <td><?php echo esc_html($expiration); ?></td>
                <td><?php echo esc_html($amount . ' ' . $currency); ?></td>
                <td><?php echo esc_html($bin); ?></td>
                <td><?php echo esc_html($last4); ?></td>
                <td><?php echo esc_html($arn); ?></td>
                <td class="mid-pre-normalized"><?php echo esc_html($descriptor); ?></td>

                <td>
                    <?php if (!$rowResolved): ?>
                        
                    <?php else: ?>
                        <span class="mid-pre-inline-msg ok" data-result="resolved">Resolved</span>
                    <?php endif; ?>

                    <button
                            class="faip-btn faip-btn-success"
                            data-action="approve_row"
                            data-guid="<?php echo esc_attr($guid); ?>"
                            type="button"
                            <?php echo $btnDisabled ? 'disabled' : ''; ?>
                        >Resolve</button>

                    <?php if (!empty($orders)): ?>
                        <div class="mid-pre-orders">
                            <div class="mid-pre-orders-title">Related orders</div>
                            <?php foreach ($orders as $o):
                                if (is_object($o)) $o = (array) $o;
                                if (!is_array($o)) continue;

                                $orderId = isset($o['item_id']) ? (int) $o['item_id'] : 0;
                                if ($orderId <= 0) continue;

                                $refundUrl = add_query_arg(['id' => $orderId], '/orders/payment-refund/');
                                ?>
                                <div class="mid-pre-order-row">
                                    <span class="mid-pre-order-id">#<?php echo esc_html((string) $orderId); ?></span>
                                    <a class="mid-pre-order-link" href="<?php echo esc_url($refundUrl); ?>" target="_blank" rel="noopener noreferrer">Refund</a>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                </td>
            </tr>
            <?php
        }

        return (string) ob_get_clean();
    }

    private function current_url_without(array $remove_keys): string {
        $scheme = is_ssl() ? 'https' : 'http';
        $host   = $_SERVER['HTTP_HOST'] ?? '';
        $uri    = $_SERVER['REQUEST_URI'] ?? '';
        $url    = $scheme . '://' . $host . $uri;

        $parts = wp_parse_url($url);
        $path  = $parts['path'] ?? '';
        $query = [];

        if (!empty($parts['query'])) {
            parse_str($parts['query'], $query);
        }

        foreach ($remove_keys as $k) {
            unset($query[$k]);
        }

        $base = $scheme . '://' . $host . $path;
        if (!empty($query)) {
            $base .= '?' . http_build_query($query);
        }

        return $base;
    }

    private function add_query_arg_safe(string $url, string $key, string $value): string {
        return add_query_arg([ $key => $value ], $url);
    }
}

add_action('init', function(){
    if (class_exists('MidigatorPreventionsListShortcode')) {
        new MidigatorPreventionsListShortcode();
    }
});