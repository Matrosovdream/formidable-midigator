<?php
/**
 * Preventions DB Model
 * Table: {$wpdb->prefix}frm_midigator_preventions
 * DB Version: 1.1.0
 */
class FrmMidigatorPreventionModel extends FrmMidigatorAbstractModel {

    public const DB_VERSION = '1.1.0';

    /** Whitelisted sortable columns */
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
        'is_resolved',
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
        'is_resolved',
        // created_at / updated_at are auto, but allow manual set if you want
        'created_at',
        'updated_at',
    ];

    public function __construct() {
        parent::__construct();
        $this->table = $this->db->prefix . 'frm_midigator_preventions';
    }

    /**
     * Mark prevention as resolved/unresolved by guid.
     *
     * @return int|WP_Error
     */
    public function setResolved( string $guid, bool $resolved = true ) {
        return $this->updateByGuid( $guid, [ 'is_resolved' => $resolved ? 1 : 0 ], 'prevention_guid' );
    }

    /**
     * Create (or update/create) resolve row for a prevention GUID.
     *
     * - Loads prevention by guid
     * - Ensures prevention_id + prevention_guid are set
     * - Saves into frm_midigator_resolves using updateCreateByGuid (guid column = prevention_guid)
     *
     * @return int|WP_Error inserted id OR updated rows count (from updateCreateByGuid)
     */
    public function createResolve( string $guid, array $data ) {
        $guid = trim( (string) $guid );
        if ( $guid === '' ) {
            return WP_error( 'invalid_guid', __( 'Invalid GUID.', 'frm-midigator' ) );
        }

        // 1) Find prevention
        $prev = $this->getByGuid( $guid, 'prevention_guid' );

        if ( empty( $prev ) || empty( $prev['id'] ) ) {
            return WP_error( 'prevention_not_found', __( 'Prevention not found.', 'frm-midigator' ) );
        }

        $preventionId = (int) $prev['id'];

        // 2) Ensure required foreign fields
        $data['prevention_id']   = $preventionId;
        $data['prevention_guid'] = $guid;

        // 3) Save resolve (upsert by prevention_guid)
        $resolveModel = new FrmMidigatorResolveModel();
        return $resolveModel->updateCreateByGuid( $guid, $data, 'prevention_guid' );
    }

    /**
     * Get entities (resolve + history) for a prevention row id.
     *
     * resolve         -> one row via ResolveModel::getByPreventionId($preventionId)
     * resolve_history -> ALL rows via HistoryModel filtered by prevention_id (not resolve_id)
     *
     * @param int   $preventionId
     * @param array $entities ['resolve','resolve_history'] or ['resolve'=>true,'resolve_history'=>true]
     * @return array|WP_Error
     */
    public function getEntitiesById( int $preventionId, array $entities = [ 'resolve', 'resolve_history' ] ) {
        $preventionId = (int) $preventionId;
        if ( $preventionId <= 0 ) {
            return new WP_Error( 'invalid_id', __( 'Invalid prevention ID.', 'frm-midigator' ) );
        }

        $wantResolve = in_array( 'resolve', $entities, true ) || ( isset( $entities['resolve'] ) && $entities['resolve'] );
        $wantHist    = in_array( 'resolve_history', $entities, true ) || ( isset( $entities['resolve_history'] ) && $entities['resolve_history'] );

        $out = [
            'resolve'         => null,
            'resolve_history' => [],
        ];

        $resolveModel = new FrmMidigatorResolveModel();
        $histModel    = new FrmMidigatorResolveHistoryModel();

        if ( $wantResolve ) {
            $resolve = $resolveModel->getByPreventionId( $preventionId );
            if ( is_wp_error( $resolve ) ) { return $resolve; }
            $out['resolve'] = $resolve;
        }

        if ( $wantHist ) {
            // IMPORTANT: history by prevention_id (ALL rows), not resolve_id
            $history = $histModel->getList(
                [ 'prevention_id' => $preventionId ],
                [ 'page' => 1, 'per_page' => 2000, 'order_by' => 'id', 'order' => 'DESC' ]
            );
            if ( is_wp_error( $history ) ) { return $history; }
            $out['resolve_history'] = $history['data'] ?? [];
        }

        return $out;
    }

    /**
     * List query with optional includeEntities injection.
     *
     * $opts['includeEntities'] = ['resolve','resolve_history']
     */
    public function getList( array $filter = [], array $opts = [] ) {

        $where  = [];
        $params = [];

        // Exact filters
        if ( isset( $filter['id'] ) && $filter['id'] !== '' ) { $where[] = 'id = %d'; $params[] = (int) $filter['id']; }

        if ( isset( $filter['is_resolved'] ) && $filter['is_resolved'] !== '' ) {
            $where[]  = 'is_resolved = %d';
            $params[] = (int) ( (bool) $filter['is_resolved'] ? 1 : 0 );
        }

        if ( ! empty( $filter['arn'] ) )                    { $where[] = 'arn = %s'; $params[] = (string) $filter['arn']; }
        if ( ! empty( $filter['mid'] ) )                    { $where[] = 'mid = %s'; $params[] = (string) $filter['mid']; }
        if ( ! empty( $filter['order_guid'] ) )             { $where[] = 'order_guid = %s'; $params[] = (string) $filter['order_guid']; }
        if ( ! empty( $filter['order_id'] ) )               { $where[] = 'order_id = %s'; $params[] = (string) $filter['order_id']; }
        if ( ! empty( $filter['prevention_case_number'] ) ) { $where[] = 'prevention_case_number = %s'; $params[] = (string) $filter['prevention_case_number']; }
        if ( ! empty( $filter['prevention_guid'] ) )        { $where[] = 'prevention_guid = %s'; $params[] = (string) $filter['prevention_guid']; }
        if ( ! empty( $filter['prevention_type'] ) )        { $where[] = 'prevention_type = %s'; $params[] = (string) $filter['prevention_type']; }

        if ( ! empty( $filter['card_brand'] ) )             { $where[] = 'card_brand = %s'; $params[] = (string) $filter['card_brand']; }
        if ( ! empty( $filter['card_first_6'] ) )           { $where[] = 'card_first_6 = %s'; $params[] = (string) $filter['card_first_6']; }
        if ( ! empty( $filter['card_last_4'] ) )            { $where[] = 'card_last_4 = %s'; $params[] = (string) $filter['card_last_4']; }
        if ( ! empty( $filter['currency'] ) )               { $where[] = 'currency = %s'; $params[] = (string) $filter['currency']; }

        // Ranged numeric filters
        if ( isset( $filter['amount_from'] ) && $filter['amount_from'] !== '' ) { $where[] = 'amount >= %f'; $params[] = (float) $filter['amount_from']; }
        if ( isset( $filter['amount_to'] ) && $filter['amount_to'] !== '' )     { $where[] = 'amount <= %f'; $params[] = (float) $filter['amount_to']; }

        // Datetime-ish ranges
        $addRange = function( string $col, string $fromKey, string $toKey ) use ( &$where, &$params, $filter ) {
            if ( isset( $filter[ $fromKey ] ) && $filter[ $fromKey ] !== '' ) { $where[] = "{$col} >= %s"; $params[] = (string) $filter[ $fromKey ]; }
            if ( isset( $filter[ $toKey ] ) && $filter[ $toKey ] !== '' )     { $where[] = "{$col} <= %s"; $params[] = (string) $filter[ $toKey ]; }
        };

        $addRange( 'prevention_timestamp',   'prevention_ts_from',   'prevention_ts_to' );
        $addRange( 'transaction_timestamp',  'transaction_ts_from',  'transaction_ts_to' );
        $addRange( 'created_at',             'created_from',         'created_to' );
        $addRange( 'updated_at',             'updated_from',         'updated_to' );

        // Search (LIKE)
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

        $result = $this->finalizePaginatedResult( $rows ?: [], $page, $per_page, $total );

        // Inject entities if requested
        $includeEntities = $opts['includeEntities'] ?? []; 
        if ( ! empty( $includeEntities ) && ! empty( $result['data'] ) ) {
            foreach ( $result['data'] as &$row ) {
                $pid = isset( $row['id'] ) ? (int) $row['id'] : 0;
                if ( $pid > 0 ) {
                    $entities = $this->getEntitiesById( $pid, (array) $includeEntities ); 
                    if ( ! is_wp_error( $entities ) ) {
                        $row['_entities'] = $entities;
                    }
                }
            }
            unset( $row );
        }

        return $result;
    }
}