<?php
if ( ! defined( 'ABSPATH' ) ) { exit; }

/**
 * Base DB Model
 */
abstract class FrmMidigatorAbstractModel {

    /** @var wpdb */
    protected $db;

    /** @var string Fully-qualified table name incl. prefix */
    protected string $table;

    /** @var array<string> */
    protected array $fillable = [];

    public function __construct() {
        global $wpdb;
        $this->db = $wpdb;
    }

    public function create( array $data ): int {
        $data = $this->filterData( $data );
        $this->db->insert( $this->table, $data );
        return (int) $this->db->insert_id;
    }

    /**
     * Update row by primary ID.
     *
     * @return int|WP_Error
     */
    public function update( int $id, array $data ) {
        $data = $this->filterData( $data );

        if ( $id <= 0 ) {
            return new WP_Error( 'invalid_id', __( 'Invalid ID.', 'frm-midigator' ) );
        }

        if ( empty( $data ) ) {
            return new WP_Error( 'empty_data', __( 'No data to update.', 'frm-midigator' ) );
        }

        $res = $this->db->update(
            $this->table,
            $data,
            [ 'id' => $id ]
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
     * Upsert by GUID column (prevention_guid).
     * - Updates if exists, otherwise inserts.
     *
     * @return int|WP_Error Updated rows count (0/1) OR inserted id, or WP_Error
     */
    public function updateCreateByGuid( string $guid, array $data, string $guidCol = 'prevention_guid' ) {
        $guid = trim( (string) $guid );
        if ( $guid === '' ) {
            return new WP_Error( 'invalid_guid', __( 'Invalid GUID.', 'frm-midigator' ) );
        }

        $data = $this->filterData( $data );
        if ( empty( $data ) ) {
            return new WP_Error( 'empty_data', __( 'No data to update.', 'frm-midigator' ) );
        }

        // If record exists -> update
        $existing = $this->getByGuid( $guid, $guidCol ); 
        if ( is_wp_error( $existing ) ) {
            return $existing;
        }

        if ( ! empty( $existing ) && isset( $existing['id'] ) ) {
            return $this->updateByGuid( $guid, $data, $guidCol );
        }

        // Otherwise -> insert (ensure guid is present)
        if ( ! isset( $data[ $guidCol ] ) ) {
            $data[ $guidCol ] = $guid;
        }

        $this->db->insert( $this->table, $data );
        if ( ! $this->db->insert_id ) {
            return new WP_Error(
                'db_insert_failed',
                __( 'Database insert failed.', 'frm-midigator' ),
                [ 'last_error' => $this->db->last_error ]
            );
        }

        return (int) $this->db->insert_id;
    }

    /**
     * Update row by GUID (generic).
     *
     * @return int|WP_Error
     */
    public function updateByGuid( string $guid, array $data, string $guidCol = 'prevention_guid' ) {
        $guid = trim( (string) $guid );
        if ( $guid === '' ) {
            return new WP_Error( 'invalid_guid', __( 'Invalid GUID.', 'frm-midigator' ) );
        }

        $data = $this->filterData( $data );
        if ( empty( $data ) ) {
            return new WP_Error( 'empty_data', __( 'No data to update.', 'frm-midigator' ) );
        }

        $res = $this->db->update(
            $this->table,
            $data,
            [ $guidCol => $guid ]
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
     * Get row by primary ID.
     */
    public function getById( int $id ) {
        $id = (int) $id;
        if ( $id <= 0 ) {
            return new WP_Error( 'invalid_id', __( 'Invalid ID.', 'frm-midigator' ) );
        }

        // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
        $sql = "SELECT * FROM {$this->table} WHERE id = %d LIMIT 1";
        $prepared = $this->db->prepare( $sql, $id );
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

    /**
     * Get row by GUID (generic).
     */
    public function getByGuid(string $guid, string $guidCol = 'prevention_guid') {

        $guid = trim((string) $guid);

        if ($guid === '') {
            return new WP_Error(
                'invalid_guid',
                __('Invalid GUID.', 'frm-midigator')
            );
        }

        // allow only safe column names
        $allowedColumns = ['prevention_guid', 'guid', 'id'];

        if (!in_array($guidCol, $allowedColumns, true)) {
            return new WP_Error(
                'invalid_column',
                __('Invalid GUID column.', 'frm-midigator')
            );
        }

        global $wpdb;

        $sql = $wpdb->prepare(
            "SELECT * FROM {$this->table} WHERE {$guidCol} = %s LIMIT 1",
            $guid
        );

        $row = $wpdb->get_row($sql, ARRAY_A);

        // real DB error
        if (!empty($wpdb->last_error)) {
            return new WP_Error(
                'db_query_failed',
                __('Database query failed.', 'frm-midigator'),
                [
                    'last_error' => $wpdb->last_error,
                    'query'      => $wpdb->last_query,
                ]
            );
        }

        // not found
        if (!$row) {
            return null;
        }

        return $row;
    }

    /**
     * Keep only allowed/known columns (model-defined $fillable).
     */
    protected function filterData( array $data ): array {
        if ( empty( $this->fillable ) ) {
            return [];
        }

        return array_intersect_key(
            $data,
            array_flip( $this->fillable )
        );
    }

    /**
     * Prepare final return structure for paginated list responses.
     */
    protected function finalizePaginatedResult( array $items, int $page, int $perPage, int $total ): array {
        $page    = max( 1, (int) $page );
        $perPage = max( 1, (int) $perPage );
        $total   = max( 0, (int) $total );

        $totalPages = ( $total === 0 ) ? 1 : (int) ceil( $total / $perPage );

        return [
            'data'       => $items,
            'pagination' => [
                'page'        => $page,
                'per_page'    => $perPage,
                'total'       => $total,
                'total_pages' => max( 1, $totalPages ),
            ],
        ];
    }

    protected function dateToMysql( string $in ): string {
        $t = strtotime( $in );
        if ( ! $t ) { return $in; }
        return gmdate( 'Y-m-d H:i:s', $t );
    }
}