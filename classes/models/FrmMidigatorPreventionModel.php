<?php
/**
 * Midigator Preventions DB Model
 * Table: {$wpdb->prefix}frm_midigator_preventions
 * DB Version: 1.0.0
 */

if ( ! defined( 'ABSPATH' ) ) { exit; }

class FrmMidigatorPreventionModel extends FrmMidigatorAbstractModel {

    /** Start db version from 1.0.0 */
    public const DB_VERSION = '1.0.0';

    /** @var wpdb */
    protected $db;

    /** @var string Fully-qualified table name incl. prefix */
    protected string $table;

    /** Whitelisted sortable columns for frm_midigator_preventions */
    private const SORTABLE = [
        'id',
        'amount',
        'arn',
        'card_brand',
        'card_first_6',
        'card_last_4',
        'currency',
        'merchant_descriptor',
        'mid',
        'order_guid',
        'order_id',
        'prevention_case_number',
        'prevention_guid',
        'prevention_timestamp',
        'prevention_type',
        'transaction_timestamp',
        'created_at',
        'updated_at',
    ];

    protected array $fillable = [
        'amount',
        'arn',
        'card_brand',
        'card_first_6',
        'card_last_4',
        'currency',
        'merchant_descriptor',
        'mid',
        'order_guid',
        'order_id',
        'prevention_case_number',
        'prevention_guid',
        'prevention_timestamp',
        'prevention_type',
        'transaction_timestamp',
        'created_at',
        'updated_at',
    ];

    public function __construct() {
        global $wpdb;
        $this->db    = $wpdb;
        $this->table = $this->db->prefix . 'frm_midigator_preventions';
    }

    /**
     * Update row by prevention_guid (unique).
     *
     * @return int|WP_Error Number of rows updated (0/1/...) or WP_Error on failure
     */
    public function updateByGuid( string $guid, array $data ) {
        $guid = trim( (string) $guid );
        if ( $guid === '' ) {
            return new WP_Error( 'invalid_guid', __( 'Invalid GUID.', 'frm-midigator' ) );
        }

        if ( empty( $data ) ) {
            return new WP_Error( 'empty_data', __( 'No data to update.', 'frm-midigator' ) );
        }

        $data = $this->filterData( $data );

        // Normalize timestamps if present
        if ( isset( $data['prevention_timestamp'] ) && $data['prevention_timestamp'] !== '' ) {
            $data['prevention_timestamp'] = $this->dateToMysql( (string) $data['prevention_timestamp'] );
        }
        if ( isset( $data['transaction_timestamp'] ) && $data['transaction_timestamp'] !== '' ) {
            $data['transaction_timestamp'] = $this->dateToMysql( (string) $data['transaction_timestamp'] );
        }
        if ( isset( $data['created_at'] ) && $data['created_at'] !== '' ) {
            $data['created_at'] = $this->dateToMysql( (string) $data['created_at'] );
        }
        if ( isset( $data['updated_at'] ) && $data['updated_at'] !== '' ) {
            $data['updated_at'] = $this->dateToMysql( (string) $data['updated_at'] );
        }

        if ( empty( $data ) ) {
            return new WP_Error( 'empty_data', __( 'No data to update.', 'frm-midigator' ) );
        }

        $res = $this->db->update(
            $this->table,
            $data,
            [ 'prevention_guid' => $guid ]
        );

        if ( false === $res ) {
            return new WP_Error(
                'db_update_failed',
                __( 'Database update failed.', 'frm-midigator' ),
                [ 'last_error' => $this->db->last_error ]
            );
        }

        return (int) $res;
    }

    /**
     * Base list query for frm_midigator_preventions.
     *
     * Returns:
     * [
     *   'items' => [...rows...],
     *   'pagination' => [
     *     'page' => (int),
     *     'per_page' => (int),
     *     'total' => (int),
     *     'total_pages' => (int),
     *   ]
     * ]
     *
     * NOTE: For pagination it uses ONE SQL call via SQL_CALC_FOUND_ROWS + FOUND_ROWS().
     */
    public function getList( array $filter = [], array $opts = [] ) {

        $where  = [];
        $params = [];

        // Exact filters
        if ( isset( $filter['id'] ) && $filter['id'] !== '' ) { $where[] = 'id = %d'; $params[] = (int) $filter['id']; }

        if ( ! empty( $filter['arn'] ) )                   { $where[] = 'arn = %s'; $params[] = (string) $filter['arn']; }
        if ( ! empty( $filter['mid'] ) )                   { $where[] = 'mid = %s'; $params[] = (string) $filter['mid']; }
        if ( ! empty( $filter['order_guid'] ) )            { $where[] = 'order_guid = %s'; $params[] = (string) $filter['order_guid']; }
        if ( ! empty( $filter['order_id'] ) )              { $where[] = 'order_id = %s'; $params[] = (string) $filter['order_id']; }
        if ( ! empty( $filter['prevention_case_number'] ) ){ $where[] = 'prevention_case_number = %s'; $params[] = (string) $filter['prevention_case_number']; }
        if ( ! empty( $filter['prevention_guid'] ) )       { $where[] = 'prevention_guid = %s'; $params[] = (string) $filter['prevention_guid']; }
        if ( ! empty( $filter['prevention_type'] ) )       { $where[] = 'prevention_type = %s'; $params[] = (string) $filter['prevention_type']; }

        if ( ! empty( $filter['card_brand'] ) )            { $where[] = 'card_brand = %s'; $params[] = (string) $filter['card_brand']; }
        if ( ! empty( $filter['card_first_6'] ) )          { $where[] = 'card_first_6 = %s'; $params[] = (string) $filter['card_first_6']; }
        if ( ! empty( $filter['card_last_4'] ) )           { $where[] = 'card_last_4 = %s'; $params[] = (string) $filter['card_last_4']; }
        if ( ! empty( $filter['currency'] ) )              { $where[] = 'currency = %s'; $params[] = (string) $filter['currency']; }

        // Ranged numeric filters
        if ( isset( $filter['amount_from'] ) && $filter['amount_from'] !== '' ) { $where[] = 'amount >= %f'; $params[] = (float) $filter['amount_from']; }
        if ( isset( $filter['amount_to'] ) && $filter['amount_to'] !== '' )     { $where[] = 'amount <= %f'; $params[] = (float) $filter['amount_to']; }

        // Helper for datetime-ish ranges
        $addRange = function( string $col, string $fromKey, string $toKey ) use ( &$where, &$params, $filter ) {
            if ( isset( $filter[ $fromKey ] ) && $filter[ $fromKey ] !== '' ) { $where[] = "{$col} >= %s"; $params[] = (string) $filter[ $fromKey ]; }
            if ( isset( $filter[ $toKey ] ) && $filter[ $toKey ] !== '' )     { $where[] = "{$col} <= %s"; $params[] = (string) $filter[ $toKey ]; }
        };

        $addRange( 'prevention_timestamp',   'prevention_ts_from',   'prevention_ts_to' );
        $addRange( 'transaction_timestamp',  'transaction_ts_from',  'transaction_ts_to' );
        $addRange( 'created_at',             'created_from',         'created_to' );
        $addRange( 'updated_at',             'updated_from',         'updated_to' );

        // Search (LIKE) across common columns
        if ( ! empty( $filter['search'] ) ) {
            $like = '%' . $this->db->esc_like( (string) $filter['search'] ) . '%';

            $where[] = '('
                . 'arn LIKE %s OR '
                . 'mid LIKE %s OR '
                . 'order_guid LIKE %s OR '
                . 'order_id LIKE %s OR '
                . 'prevention_case_number LIKE %s OR '
                . 'prevention_guid LIKE %s OR '
                . 'prevention_type LIKE %s OR '
                . 'card_brand LIKE %s OR '
                . 'card_first_6 LIKE %s OR '
                . 'card_last_4 LIKE %s OR '
                . 'currency LIKE %s'
                . ')';

            $params = array_merge( $params, array_fill( 0, 11, $like ) );
        }

        $whereSql = $where ? ( 'WHERE ' . implode( ' AND ', $where ) ) : '';

        $orderBy = ( isset( $opts['order_by'] ) && in_array( (string) $opts['order_by'], self::SORTABLE, true ) )
            ? (string) $opts['order_by']
            : 'id';

        $order = ( isset( $opts['order'] ) && strtoupper( (string) $opts['order'] ) === 'ASC' ) ? 'ASC' : 'DESC';

        // Resolve pagination
        $page     = isset( $opts['page'] ) ? max( 1, (int) $opts['page'] ) : 1;
        $per_page = isset( $opts['per_page'] ) ? max( 1, (int) $opts['per_page'] ) : ( isset( $opts['limit'] ) ? max( 1, (int) $opts['limit'] ) : 50 );
        $offset   = ( $page - 1 ) * $per_page;

        // One-call pagination: SQL_CALC_FOUND_ROWS + FOUND_ROWS()
        // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
        $sql  = "SELECT SQL_CALC_FOUND_ROWS * FROM {$this->table} {$whereSql} ORDER BY {$orderBy} {$order} LIMIT %d OFFSET %d";
        $args = array_merge( $params, [ $per_page, $offset ] );

        $prepared = $this->db->prepare( $sql, $args );
        if ( false === $prepared ) {
            return new WP_Error( 'db_prepare_failed', __( 'Failed to prepare query.', 'frm-midigator' ) );
        }

        $rows = $this->db->get_results( $prepared, ARRAY_A );
        if ( null === $rows ) {
            return new WP_Error(
                'db_query_failed',
                __( 'Database query failed.', 'frm-midigator' ),
                [ 'last_error' => $this->db->last_error ]
            );
        }

        // Total from the same call (MySQL session result)
        $total = (int) $this->db->get_var( 'SELECT FOUND_ROWS()' );

        return $this->finalizePaginatedResult( $rows, $page, $per_page, $total );
    }
}