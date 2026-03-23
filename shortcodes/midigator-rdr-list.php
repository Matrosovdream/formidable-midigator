<?php
if ( ! defined('ABSPATH') ) { exit; }

final class MidigatorRdrListShortcode {

    public const SHORTCODE = 'midigator-rdr-list';
    private $shortcodeHelper;

    /** GET params */
    private const QP_PAGE        = 'mid_rdr_page';
    private const QP_Q           = 'mid_rdr_q';
    private const QP_CARD_FIRST6 = 'rdr_card_first_6';
    private const QP_CARD_LAST4  = 'rdr_card_last_4';

    private const ORDER_URL = '/orders/entry/';

    /** Assets */
    private const CSS_HANDLE = 'midigator-rdr-list-css';
    private const JS_HANDLE  = 'midigator-rdr-list-js';

    public function __construct() {
        $this->shortcodeHelper = new FrmMidigatorShortcodeHelper();
        add_shortcode( self::SHORTCODE, [ $this, 'render_shortcode' ] );
    }

    public function render_shortcode( $atts = [] ): string {

        $this->enqueue_assets();

        $page = isset( $_GET[ self::QP_PAGE ] ) ? max( 1, (int) $_GET[ self::QP_PAGE ] ) : 1;

        $card_first_6 = isset( $_GET[ self::QP_CARD_FIRST6 ] ) ? preg_replace( '/\D+/', '', (string) $_GET[ self::QP_CARD_FIRST6 ] ) : '';
        $card_last_4  = isset( $_GET[ self::QP_CARD_LAST4 ] )  ? preg_replace( '/\D+/', '', (string) $_GET[ self::QP_CARD_LAST4 ] )  : '';

        $filters = [];

        if ( $card_first_6 !== '' ) {
            $filters['card_first_6'] = substr( $card_first_6, 0, 6 );
        }
        if ( $card_last_4 !== '' ) {
            $filters['card_last_4'] = substr( $card_last_4, 0, 4 );
        }

        $per_page = 30;
        $opts = [
            'page'     => $page,
            'per_page' => $per_page,
            'order_by' => 'created_at',
            'order'    => 'DESC',
        ];

        $list = $this->safe_get_list( $filters, $opts );

        if ( isset( $_GET['log'] ) ) {
            echo '<pre>';
            print_r( $list );
            echo '</pre>';
        }

        $p           = isset( $list['pagination'] ) && is_array( $list['pagination'] ) ? $list['pagination'] : [];
        $cur_page    = isset( $p['page'] )        ? max( 1, (int) $p['page'] )        : $page;
        $total_pages = isset( $p['total_pages'] ) ? max( 1, (int) $p['total_pages'] ) : 1;

        $base_url = $this->shortcodeHelper->currentUrlWithout( [ self::QP_PAGE ] );

        ob_start();
        ?>
        <div class="mid-pre-wrap" id="mid-rdr">

            <div class="mid-pre-head">
                <div>
                    <div class="mid-pre-title">RDR New</div>
                </div>

                <!-- .mid-pre-actions hidden per design -->
                <div class="mid-pre-actions" style="display:none;"></div>
            </div>

            <form method="get" class="mid-pre-filters" id="midRdrFiltersForm">
                <?php
                foreach ( $_GET as $k => $v ) {
                    if ( in_array( $k, [ self::QP_PAGE, self::QP_Q, self::QP_CARD_FIRST6, self::QP_CARD_LAST4 ], true ) ) continue;
                    if ( is_array( $v ) ) continue;
                    echo '<input type="hidden" name="' . esc_attr( $k ) . '" value="' . esc_attr( (string) $v ) . '">';
                }
                ?>

                <div class="mid-pre-filter">
                    <label for="midRdrCardFirst6">BIN</label>
                    <input
                        id="midRdrCardFirst6"
                        name="<?php echo esc_attr( self::QP_CARD_FIRST6 ); ?>"
                        type="text"
                        inputmode="numeric"
                        pattern="[0-9]*"
                        value="<?php echo esc_attr( $card_first_6 ); ?>"
                        placeholder="e.g. 414720"
                        maxlength="6"
                    />
                </div>

                <div class="mid-pre-filter">
                    <label for="midRdrCardLast4">Last 4</label>
                    <input
                        id="midRdrCardLast4"
                        name="<?php echo esc_attr( self::QP_CARD_LAST4 ); ?>"
                        type="text"
                        inputmode="numeric"
                        pattern="[0-9]*"
                        value="<?php echo esc_attr( $card_last_4 ); ?>"
                        placeholder="e.g. 6636"
                        maxlength="4"
                    />
                </div>

                <div class="mid-pre-filter mid-pre-filter-actions">
                    <label>&nbsp;</label>
                    <div class="mid-pre-inline">
                        <button type="submit" class="mid-pre-btn mid-pre-btn-primary">Apply</button>
                        <a class="mid-pre-btn" href="<?php echo esc_url( $this->shortcodeHelper->currentUrlWithout( [ self::QP_PAGE, self::QP_Q, self::QP_CARD_FIRST6, self::QP_CARD_LAST4 ] ) ); ?>">Reset</a>
                    </div>
                </div>

                <input type="hidden" name="<?php echo esc_attr( self::QP_PAGE ); ?>" value="1">

            </form>

            <div class="mid-pre-statusbar" id="midRdrStatusbar">
                <?php
                if ( ! empty( $list['error'] ) ) {
                    echo '<span class="mid-pre-msg err">' . esc_html( (string) $list['error'] ) . '</span>';
                }
                ?>
            </div>

            <table class="table table-striped table-listing mid-pre-table" style="width:100%;">
                <thead>
                    <tr>
                        <th style="width:34px;"><input type="checkbox" id="midRdrCheckAll"></th>
                        <th style="width:160px;">Received on</th>
                        <th style="width:120px;">Amount</th>
                        <th style="width:90px;">BIN</th>
                        <th style="width:80px;">Last 4</th>
                        <th style="width:220px;">ARN</th>
                        <th style="width:160px;">Descriptor</th>
                        <th style="width:110px;">RDR Date</th>
                        <th style="width:130px;">Transaction Date</th>
                        <th style="width:140px;">Case #</th>
                        <th style="width:120px;">Resolution</th>
                        <th>Related Orders</th>
                    </tr>
                </thead>
                <tbody>
                    <?php echo $this->render_rows_html( $list ); ?>
                </tbody>
            </table>

            <?php
            echo $this->shortcodeHelper->renderPagination( [
                'current_page'  => $cur_page,
                'total_pages'   => $total_pages,
                'base_url'      => $base_url,
                'page_param'    => self::QP_PAGE,
                'wrapper_class' => 'mid-pre-footer',
                'pager_class'   => 'mid-pre-pager',
                'btn_class'     => 'mid-pre-btn',
                'page_class'    => 'mid-pre-page',
            ] );
            ?>

        </div>
        <?php

        return (string) ob_get_clean();
    }

    private function enqueue_assets(): void {

        wp_enqueue_style(
            self::CSS_HANDLE,
            esc_url( FRM_MDG_BASE_PATH . 'assets/midigator-list.css?time=' . time() ),
            [],
            '1.0.0'
        );

        wp_enqueue_script(
            self::JS_HANDLE,
            esc_url( FRM_MDG_BASE_PATH . 'assets/midigator-list.js?time=' . time() ),
            [ 'jquery' ],
            '1.0.0',
            true
        );

    }

    private function safe_get_list( array $filters, array $opts ): array {
        try {
            $model = new FrmMidigatorRdrModel();
            $rows  = $model->getList( $filters, $opts );
            if ( ! is_array( $rows ) ) $rows = [];
            return $rows;
        } catch ( \Throwable $e ) {
            return [
                'data'       => [],
                'pagination' => [
                    'page'        => $opts['page']     ?? 1,
                    'per_page'    => $opts['per_page']  ?? 30,
                    'total'       => 0,
                    'total_pages' => 1,
                ],
                'error' => $e->getMessage(),
            ];
        }
    }

    private function render_rows_html( array $list ): string {

        $items = isset( $list['data'] ) && is_array( $list['data'] ) ? $list['data'] : [];

        if ( empty( $items ) ) {
            return '<tr><td colspan="13" class="mid-pre-muted">No results.</td></tr>';
        }

        $fmt_date = static function( $raw ): string {
            $raw = (string) $raw;
            if ( $raw === '' ) return '';
            $ts = strtotime( $raw );
            if ( ! $ts ) return '';
            return date( 'Y-m-d', $ts );
        };

        $fmt_dt = static function( $raw ): string {
            $raw = (string) $raw;
            if ( $raw === '' ) return '';
            $ts = strtotime( $raw );
            if ( ! $ts ) return '';
            return date( 'Y-m-d H:i', $ts );
        };

        ob_start();

        foreach ( $items as $item ) {

            if ( is_object( $item ) ) $item = (array) $item;
            if ( ! is_array( $item ) ) continue;

            $id = isset( $item['id'] ) ? (int) $item['id'] : 0;

            $receivedOn  = $fmt_dt( $item['created_at'] ?? '' );
            $amount      = isset( $item['amount'] )    ? (string) $item['amount']    : '';
            $currency    = isset( $item['currency'] )  ? (string) $item['currency']  : '';
            $bin         = isset( $item['card_first_6'] ) ? (string) $item['card_first_6'] : '';
            $last4       = isset( $item['card_last_4'] )  ? (string) $item['card_last_4']  : '';
            $arn         = isset( $item['arn'] )        ? (string) $item['arn']        : '';
            $descriptor  = isset( $item['merchant_descriptor'] ) ? (string) $item['merchant_descriptor'] : '';
            $rdrDate     = $fmt_date( $item['rdr_date'] ?? '' );
            $txDate      = $fmt_date( $item['transaction_date'] ?? '' );
            $caseNumber  = isset( $item['rdr_case_number'] ) ? (string) $item['rdr_case_number'] : '';
            $resolution  = isset( $item['rdr_resolution'] )  ? (string) $item['rdr_resolution']  : '';
            $authCode    = isset( $item['auth_code'] )        ? (string) $item['auth_code']        : '';

            if ( $receivedOn === '' ) $receivedOn = '—';
            if ( $amount === '' )     $amount     = '—';
            if ( $currency === '' )   $currency   = '—';
            if ( $bin === '' )        $bin        = '—';
            if ( $last4 === '' )      $last4      = '—';
            if ( $arn === '' )        $arn        = '—';
            if ( $descriptor === '' ) $descriptor = '—';
            if ( $rdrDate === '' )    $rdrDate    = '—';
            if ( $txDate === '' )     $txDate     = '—';
            if ( $caseNumber === '' ) $caseNumber = '—';
            if ( $resolution === '' ) $resolution = '—';
            if ( $authCode === '' )   $authCode   = '—';

            // Related orders via card values
            $orders = $this->get_related_orders( $item );
            ?>
            <tr data-item-id="<?php echo esc_attr( (string) $id ); ?>">

                <td>
                    <input type="checkbox" data-item-id="<?php echo esc_attr( (string) $id ); ?>">
                </td>

                <td><?php echo esc_html( $receivedOn ); ?></td>

                <td><?php echo esc_html( $amount . ' ' . $currency ); ?></td>

                <td><?php echo esc_html( $bin ); ?></td>

                <td><?php echo esc_html( $last4 ); ?></td>

                <td><?php echo esc_html( $arn ); ?></td>

                <td class="mid-pre-normalized"><?php echo esc_html( $descriptor ); ?></td>

                <td><?php echo esc_html( $rdrDate ); ?></td>

                <td><?php echo esc_html( $txDate ); ?></td>

                <td><?php echo esc_html( $caseNumber ); ?></td>

                <td><?php echo esc_html( $resolution ); ?></td>

                <td><?php echo $this->render_orders_html( $orders ); ?></td>

            </tr>
            <?php
        }

        return (string) ob_get_clean();
    }

    private function get_related_orders( array $item ): array {
        $bin   = isset( $item['card_first_6'] ) ? (string) $item['card_first_6'] : '';
        $last4 = isset( $item['card_last_4'] )  ? (string) $item['card_last_4']  : '';

        if ( $bin === '' || $last4 === '' ) {
            return [];
        }

        try {
            $orderHelper = new DotFrmOrderHelper();
            $orders = $orderHelper->getItemsByCardValues( (int) $bin, (int) $last4 );
            return is_array( $orders ) ? $orders : [];
        } catch ( \Throwable $e ) {
            return [];
        }
    }

    private function render_orders_html( array $orders ): string {

        if ( empty( $orders ) ) {
            return '';
        }

        ob_start();
        ?>
        <div class="mid-pre-orders">
            <div class="mid-pre-orders-title">Related orders</div>

            <?php foreach ( $orders as $o ):
                if ( is_object( $o ) ) $o = (array) $o;
                if ( ! is_array( $o ) ) continue;

                $orderId = isset( $o['id'] ) ? (int) $o['id'] : ( isset( $o['item_id'] ) ? (int) $o['item_id'] : 0 );
                if ( $orderId <= 0 ) continue;

                $payment        = isset( $o['payment'] ) && is_array( $o['payment'] ) ? $o['payment'] : [];
                $paymentStatus  = isset( $payment['status'] )           ? (string) $payment['status']           : '';
                $fullAmount     = isset( $payment['full_amount'] )      ? (string) $payment['full_amount']      : '';
                $refundedAmount = isset( $payment['refunded_amount'] )  ? (string) $payment['refunded_amount']  : '';
                $createdAt      = isset( $o['created_at'] )             ? (string) $o['created_at']             : '';
                $userEmail      = isset( $o['field_values']['user_email'] ) ? (string) $o['field_values']['user_email'] : '';
                $isRefunded     = strtolower( $paymentStatus ) === 'refunded';
                $orderLink      = self::ORDER_URL . $orderId;

                if ( strtolower( $paymentStatus ) === 'failed' ) continue;
                ?>
                <div class="mid-pre-order-row" data-order-id="<?php echo esc_attr( (string) $orderId ); ?>">

                    <div class="mid-pre-order-top">
                        <div class="mid-pre-order-main">
                            <span class="mid-pre-order-id">#<?php echo esc_html( (string) $orderId ); ?></span>
                            <a href="<?php echo esc_url( $orderLink ); ?>" target="_blank">Open</a>
                        </div>

                        <div class="mid-pre-order-actions">
                            <?php if ( $isRefunded ): ?>
                                <span class="mid-pre-inline-msg ok">Refunded</span>
                            <?php endif; ?>
                        </div>
                    </div>

                    <div class="mid-pre-order-meta">
                        <div class="mid-pre-order-payment-status">
                            Payment status: <b><?php echo esc_html( $paymentStatus !== '' ? $paymentStatus : '—' ); ?></b>
                        </div>

                        <div class="mid-pre-order-payment-refund">
                            Payment date: <b><?php echo esc_html( $createdAt !== '' ? $createdAt : '—' ); ?></b>
                        </div>

                        <div class="mid-pre-order-payment-refund">
                            Refunded: <b><?php echo esc_html( ( $refundedAmount !== '' ? $refundedAmount : '0' ) . '/' . ( $fullAmount !== '' ? $fullAmount : '0' ) ); ?></b>
                        </div>

                        <?php if ( $userEmail !== '' ) : ?>
                        <div class="mid-pre-order-payment-refund">
                            Email: <b><?php echo esc_html( $userEmail ); ?></b>
                        </div>
                        <?php endif; ?>
                    </div>

                </div>
            <?php endforeach; ?>
        </div>
        <?php

        return (string) ob_get_clean();
    }

}

add_action( 'init', function () {
    if ( class_exists( 'MidigatorRdrListShortcode' ) ) {
        new MidigatorRdrListShortcode();
    }
} );
