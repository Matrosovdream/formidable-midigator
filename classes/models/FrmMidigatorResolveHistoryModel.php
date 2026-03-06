<?php
/**
 * Resolve History DB Model
 * Table: {$wpdb->prefix}frm_midigator_resolve_history
 * DB Version: 1.1.0
 */
class FrmMidigatorResolveHistoryModel extends FrmMidigatorAbstractModel {

    public const DB_VERSION = '1.1.0';

    private const SORTABLE = [
        'id',
        'resolve_id',
        'prevention_id',
        'user_id',
        'prevention_guid',
        'resolution_type',
        'created_at',
        'updated_at',
    ];

    protected array $fillable = [
        'resolve_id',
        'prevention_id',
        'user_id',
        'prevention_guid',
        'resolution_type',
        'description',
        'created_at',
        'updated_at',
    ];

    public function __construct() {
        parent::__construct();
        $this->table = $this->db->prefix . 'frm_midigator_resolve_history';
    }

    /**
     * History "GUID" is also prevention_guid.
     */
    public function getByGuid( string $guid, string $guidCol = 'prevention_guid' ) {
        return parent::getByGuid( $guid, $guidCol );
    }

    public function getList( array $filter = [], array $opts = [] ) {

        $where  = [];
        $params = [];

        if ( isset( $filter['id'] ) && $filter['id'] !== '' ) { $where[] = 'id = %d'; $params[] = (int) $filter['id']; }

        if ( isset( $filter['resolve_id'] ) && $filter['resolve_id'] !== '' )     { $where[] = 'resolve_id = %d'; $params[] = (int) $filter['resolve_id']; }
        if ( isset( $filter['prevention_id'] ) && $filter['prevention_id'] !== '' ) { $where[] = 'prevention_id = %d'; $params[] = (int) $filter['prevention_id']; }

        if ( isset( $filter['user_id'] ) && $filter['user_id'] !== '' )          { $where[] = 'user_id = %d'; $params[] = (int) $filter['user_id']; }

        if ( ! empty( $filter['prevention_guid'] ) ) { $where[] = 'prevention_guid = %s'; $params[] = (string) $filter['prevention_guid']; }
        if ( ! empty( $filter['resolution_type'] ) ) { $where[] = 'resolution_type = %s'; $params[] = (string) $filter['resolution_type']; }

        if ( ! empty( $filter['search'] ) ) {
            $like = '%' . $this->db->esc_like( (string) $filter['search'] ) . '%';
            $where[] = '('
                . 'prevention_guid LIKE %s OR '
                . 'resolution_type LIKE %s OR '
                . 'description LIKE %s'
                . ')';
            $params = array_merge( $params, [ $like, $like, $like ] );
        }

        $whereSql = $where ? ( 'WHERE ' . implode( ' AND ', $where ) ) : '';

        $orderBy = ( isset( $opts['order_by'] ) && in_array( (string) $opts['order_by'], self::SORTABLE, true ) )
            ? (string) $opts['order_by']
            : 'id';

        $order = ( isset( $opts['order'] ) && strtoupper( (string) $opts['order'] ) === 'ASC' ) ? 'ASC' : 'DESC';

        $page     = isset( $opts['page'] ) ? max( 1, (int) $opts['page'] ) : 1;
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