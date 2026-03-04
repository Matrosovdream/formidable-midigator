<?php

if ( ! defined( 'ABSPATH' ) ) { exit; }

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
     * @return int|WP_Error Number of rows updated (0/1/...) or WP_Error on failure
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
     *
     * @param array $items Rows
     * @param int   $page
     * @param int   $perPage
     * @param int   $total
     */
    protected function finalizePaginatedResult( array $items, int $page, int $perPage, int $total ): array {

        $page    = max( 1, (int) $page );
        $perPage = max( 1, (int) $perPage );
        $total   = max( 0, (int) $total );

        $totalPages = ( $total === 0 ) ? 1 : (int) ceil( $total / $perPage );

        return [
            'data'      => $items,
            'pagination' => [
                'page'        => $page,
                'per_page'    => $perPage,
                'total'       => $total,
                'total_pages' => max( 1, $totalPages ),
            ],
        ];
    }

    /** Escape a single value into SQL literal, respecting NULL and type */
    private function escapeValueForSql( string $format, $value ): string {
        if ( $value === null || $value === '' ) {
            return 'NULL';
        }
        switch ( $format ) {
            case '%d':
                return (string) (int) $value;
            case '%f':
                return (string) (float) $value;
            case '%s':
            default:
                return $this->db->prepare( '%s', (string) $value );
        }
    }

    protected function dateToMysql( string $in ): string {
        $t = strtotime( $in );
        if ( ! $t ) { return $in; }
        return gmdate( 'Y-m-d H:i:s', $t );
    }
}