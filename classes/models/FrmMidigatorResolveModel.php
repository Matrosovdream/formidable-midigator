<?php
/**
 * Resolves DB Model
 * Table: {$wpdb->prefix}frm_midigator_resolves
 * DB Version: 1.1.0
 */
class FrmMidigatorResolveModel extends FrmMidigatorAbstractModel {

    public const DB_VERSION = '1.1.0';

    private const SORTABLE = [
        'id',
        'prevention_id',
        'prevention_guid',
        'resolution_type',
        'created_at',
        'updated_at',
    ];

    protected array $fillable = [
        'prevention_id',
        'prevention_guid',
        'resolution_type',
        'description',
        'created_at',
        'updated_at',
    ];

    public function __construct() {
        parent::__construct();
        $this->table = $this->db->prefix . 'frm_midigator_resolves';
    }

    /**
     * Resolve table GUID is prevention_guid.
     */
    public function getByGuid( string $guid, string $guidCol = 'prevention_guid' ) {
        return parent::getByGuid( $guid, $guidCol );
    }

    /**
     * Convenience: fetch by prevention_id
     */
    public function getByPreventionId( int $preventionId ) {
        $preventionId = (int) $preventionId;
        if ( $preventionId <= 0 ) {
            return new WP_Error( 'invalid_id', __( 'Invalid prevention ID.', 'frm-midigator' ) );
        }

        // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
        $sql = "SELECT * FROM {$this->table} WHERE prevention_id = %d ORDER BY id DESC LIMIT 1";
        $prepared = $this->db->prepare( $sql, $preventionId );
        if ( false === $prepared ) {
            return new WP_Error( 'db_prepare_failed', __( 'Failed to prepare query.', 'frm-midigator' ) );
        }

        $row = $this->db->get_row( $prepared, ARRAY_A );
        if ( null === $row ) {
            return new WP_Error(
                'db_query_failed',
                __( 'Database query failed.', 'frm-midigator' ),
                [ 'last_error' => $this->db->last_error ]
            );
        }

        return $row ?: null;
    }

    public function getList( array $filter = [], array $opts = [] ) {

        $where  = [];
        $params = [];

        if ( isset( $filter['id'] ) && $filter['id'] !== '' ) { $where[] = 'id = %d'; $params[] = (int) $filter['id']; }

        if ( isset( $filter['prevention_id'] ) && $filter['prevention_id'] !== '' ) { $where[] = 'prevention_id = %d'; $params[] = (int) $filter['prevention_id']; }
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