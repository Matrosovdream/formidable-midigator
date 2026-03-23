<?php
/**
 * RDR DB Model
 * Table: {$wpdb->prefix}frm_midigator_rdr
 * DB Version: 1.2.0
 */
class FrmMidigatorRdrModel extends FrmMidigatorAbstractModel {

    public const DB_VERSION = '1.2.0';

    /** Whitelisted sortable columns */
    private const SORTABLE = [
        'id',
        'amount',
        'arn',
        'auth_code',
        'card_first_6',
        'card_last_4',
        'currency',
        'merchant_descriptor',
        'event_guid',
        'event_timestamp',
        'event_type',
        'rdr_guid',
        'rdr_case_number',
        'rdr_date',
        'rdr_resolution',
        'prevention_type',
        'transaction_date',
        'order_id',
        'is_resolved',
        'created_at',
        'updated_at',
    ];

    protected array $fillable = [
        'amount',
        'arn',
        'auth_code',
        'card_first_6',
        'card_last_4',
        'currency',
        'merchant_descriptor',
        'event_guid',
        'event_timestamp',
        'event_type',
        'rdr_guid',
        'rdr_case_number',
        'rdr_date',
        'rdr_resolution',
        'prevention_type',
        'transaction_date',
        'order_id',
        'is_resolved',
        'created_at',
        'updated_at',
    ];

    public function __construct() {
        parent::__construct();
        $this->table = $this->db->prefix . 'frm_midigator_rdr';
    }

    /**
     * Mark RDR as resolved/unresolved by rdr_guid.
     *
     * @return int|WP_Error
     */
    public function setResolved( string $guid, bool $resolved = true ) {
        return $this->updateByGuid( $guid, [ 'is_resolved' => $resolved ? 1 : 0 ], 'rdr_guid' );
    }

    /**
     * List query with filters.
     */
    public function getList( array $filter = [], array $opts = [] ) {

        $where  = [];
        $params = [];

        if ( isset( $filter['id'] ) && $filter['id'] !== '' )           { $where[] = 'id = %d'; $params[] = (int) $filter['id']; }
        if ( isset( $filter['is_resolved'] ) && $filter['is_resolved'] !== '' ) {
            $where[]  = 'is_resolved = %d';
            $params[] = (int) ( (bool) $filter['is_resolved'] ? 1 : 0 );
        }

        if ( ! empty( $filter['arn'] ) )              { $where[] = 'arn = %s';              $params[] = (string) $filter['arn']; }
        if ( ! empty( $filter['order_id'] ) )         { $where[] = 'order_id = %s';         $params[] = (string) $filter['order_id']; }
        if ( ! empty( $filter['rdr_guid'] ) )         { $where[] = 'rdr_guid = %s';         $params[] = (string) $filter['rdr_guid']; }
        if ( ! empty( $filter['rdr_case_number'] ) )  { $where[] = 'rdr_case_number = %s';  $params[] = (string) $filter['rdr_case_number']; }
        if ( ! empty( $filter['rdr_resolution'] ) )   { $where[] = 'rdr_resolution = %s';   $params[] = (string) $filter['rdr_resolution']; }
        if ( ! empty( $filter['prevention_type'] ) )  { $where[] = 'prevention_type = %s';  $params[] = (string) $filter['prevention_type']; }
        if ( ! empty( $filter['card_first_6'] ) )     { $where[] = 'card_first_6 = %s';     $params[] = (string) $filter['card_first_6']; }
        if ( ! empty( $filter['card_last_4'] ) )      { $where[] = 'card_last_4 = %s';      $params[] = (string) $filter['card_last_4']; }
        if ( ! empty( $filter['currency'] ) )         { $where[] = 'currency = %s';         $params[] = (string) $filter['currency']; }

        if ( isset( $filter['amount_from'] ) && $filter['amount_from'] !== '' ) { $where[] = 'amount >= %f'; $params[] = (float) $filter['amount_from']; }
        if ( isset( $filter['amount_to'] )   && $filter['amount_to']   !== '' ) { $where[] = 'amount <= %f'; $params[] = (float) $filter['amount_to']; }

        $addRange = function( string $col, string $fromKey, string $toKey ) use ( &$where, &$params, $filter ) {
            if ( isset( $filter[ $fromKey ] ) && $filter[ $fromKey ] !== '' ) { $where[] = "{$col} >= %s"; $params[] = (string) $filter[ $fromKey ]; }
            if ( isset( $filter[ $toKey ] )   && $filter[ $toKey ]   !== '' ) { $where[] = "{$col} <= %s"; $params[] = (string) $filter[ $toKey ]; }
        };

        $addRange( 'rdr_date',         'rdr_date_from',      'rdr_date_to' );
        $addRange( 'transaction_date', 'transaction_from',   'transaction_to' );
        $addRange( 'event_timestamp',  'event_ts_from',      'event_ts_to' );
        $addRange( 'created_at',       'created_from',       'created_to' );
        $addRange( 'updated_at',       'updated_from',       'updated_to' );

        if ( ! empty( $filter['search'] ) ) {
            $like = '%' . $this->db->esc_like( (string) $filter['search'] ) . '%';

            $where[] = '('
                . 'arn LIKE %s OR '
                . 'order_id LIKE %s OR '
                . 'rdr_guid LIKE %s OR '
                . 'rdr_case_number LIKE %s OR '
                . 'rdr_resolution LIKE %s OR '
                . 'card_first_6 LIKE %s OR '
                . 'card_last_4 LIKE %s OR '
                . 'merchant_descriptor LIKE %s'
                . ')';

            $params = array_merge( $params, array_fill( 0, 8, $like ) );
        }

        $whereSql = $where ? ( 'WHERE ' . implode( ' AND ', $where ) ) : '';

        $orderBy = ( isset( $opts['order_by'] ) && in_array( (string) $opts['order_by'], self::SORTABLE, true ) )
            ? (string) $opts['order_by']
            : 'id';

        $order = ( isset( $opts['order'] ) && strtoupper( (string) $opts['order'] ) === 'ASC' ) ? 'ASC' : 'DESC';

        $page     = isset( $opts['page'] )     ? max( 1, (int) $opts['page'] )     : 1;
        $per_page = isset( $opts['per_page'] ) ? max( 1, (int) $opts['per_page'] ) : ( isset( $opts['limit'] ) ? max( 1, (int) $opts['limit'] ) : 50 );
        $offset   = ( $page - 1 ) * $per_page;

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

        $total = (int) $this->db->get_var( 'SELECT FOUND_ROWS()' );

        return $this->finalizePaginatedResult( $rows ?: [], $page, $per_page, $total );
    }

}
