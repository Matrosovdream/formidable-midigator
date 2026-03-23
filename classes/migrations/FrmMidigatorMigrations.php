<?php
/**
 * Midigator DB Migrations
 * DB Version: 1.1.0
 */

if ( ! defined( 'ABSPATH' ) ) { exit; }

class FrmMidigatorMigrations {

    /** Bump when schema changes */
    public const DB_VERSION     = '1.2.0';

    /** Store installed db version in WP options */
    public const VERSION_OPTION = 'frm_midigator_db_version';

    /** Run on plugin activation */
    public static function install(): void {
        global $wpdb;

        require_once ABSPATH . 'wp-admin/includes/upgrade.php';

        $charset_collate = $wpdb->get_charset_collate();
        $prefix          = $wpdb->prefix;

        $sql = [];

        $sql[] = self::sql_midigator_preventions( $prefix, $charset_collate );
        $sql[] = self::sql_midigator_resolves( $prefix, $charset_collate );
        $sql[] = self::sql_midigator_resolve_history( $prefix, $charset_collate );
        $sql[] = self::sql_midigator_rdr( $prefix, $charset_collate );

        foreach ( $sql as $statement ) {
            dbDelta( $statement );
        }

        update_option( self::VERSION_OPTION, self::DB_VERSION );
    }

    /** Auto upgrade */
    public static function maybe_upgrade(): void {
        $installed = get_option( self::VERSION_OPTION );
        if ( $installed !== self::DB_VERSION ) {
            self::install();
        }
    }

    /**
     * EXPORT DB → JSON files
     */
    public static function exportData(bool $truncate = true): array {

        global $wpdb;
    
        $dir = self::get_import_dir();
        self::ensure_import_path();

        $result = [
            'success' => true,
            'dir'     => $dir,
            'tables'  => [],
            'errors'  => [],
        ];
    
        foreach ( self::get_table_names() as $table ) {
    
            $file = $dir . $table . '.json';
    
            if (!file_exists($file)) {
    
                file_put_contents($file, json_encode([
                    'table' => $table,
                    'rows'  => []
                ], JSON_PRETTY_PRINT));
    
                $result['tables'][$table] = [
                    'rows'          => 0,
                    'file'          => $file,
                    'created_empty' => true,
                ];
    
                continue;
            }
    
            $content = file_get_contents($file);
            $decoded = json_decode($content, true);
    
            if (!is_array($decoded) || !isset($decoded['rows']) || !is_array($decoded['rows'])) {
                $result['success'] = false;
                $result['errors'][$table] = 'Invalid JSON structure.';
                continue;
            }
    
            $rows = $decoded['rows'];
    
            if ($truncate) {
                $wpdb->query("TRUNCATE TABLE `{$table}`");
    
                if ($wpdb->last_error) {
                    $result['success'] = false;
                    $result['errors'][$table] = 'TRUNCATE failed: ' . $wpdb->last_error;
                    continue;
                }
            }
    
            $inserted = 0;
    
            foreach ($rows as $row) {
    
                if (!is_array($row) || empty($row)) {
                    continue;
                }
    
                if (array_key_exists('created_at', $row) && ($row['created_at'] === null || $row['created_at'] === '')) {
                    unset($row['created_at']);
                }
    
                if (array_key_exists('updated_at', $row) && ($row['updated_at'] === null || $row['updated_at'] === '')) {
                    unset($row['updated_at']);
                }

                echo "<pre>"; print_r($row); echo "</pre>";
    
                $formats = self::build_formats_from_row($row);
                $ok = $wpdb->insert($table, $row, $formats);
    
                if ($ok === false) {
                    $result['success'] = false;
                    //$result['errors'][$table][] = $wpdb->last_error;
                    continue;
                }
    
                $inserted++;
            }
    
            $result['tables'][$table] = [
                'rows' => $inserted,
                'file' => $file,
            ];
        }
    
        return $result;
    }

    /**
     * IMPORT JSON → DB
     */
    public static function importData(bool $truncate = true): array {

        global $wpdb;

        $dir = self::get_import_dir();
        self::ensure_import_path();

        $result = [
            'success' => true,
            'dir'     => $dir,
            'tables'  => [],
            'errors'  => [],
        ];

        foreach ( self::get_table_names() as $table ) {

            $file = $dir . $table . '.json';

            if (!file_exists($file)) {

                file_put_contents($file, json_encode([
                    'table'=>$table,
                    'rows'=>[]
                ], JSON_PRETTY_PRINT));

                $result['tables'][$table] = [
                    'rows'=>0,
                    'file'=>$file,
                    'created_empty'=>true
                ];

                continue;
            }

            $content = file_get_contents($file);

            $decoded = json_decode($content, true);

            if (!is_array($decoded) || empty($decoded['rows'])) {
                continue;
            }

            $rows = $decoded['rows'];

            if ($truncate) {
                $wpdb->query("TRUNCATE TABLE `{$table}`");
            }

            $inserted = 0;

            foreach ($rows as $row) {

                if (!is_array($row)) {
                    continue;
                }

                $formats = self::build_formats_from_row($row);

                $ok = $wpdb->insert($table, $row, $formats);

                // Show error
                $wpdb_error = $wpdb->last_error;
                if ($wpdb_error) {
                    $result['success'] = false;
                    $result['errors'][$table][] = $wpdb_error;
                }

                if ($ok !== false) {
                    $inserted++;
                }
            }

            $result['tables'][$table] = [
                'rows'=>$inserted,
                'file'=>$file
            ];
        }

        return $result;
    }

    /**
     * Ensure migrations/imports directory exists
     */
    private static function ensure_import_path(): void {

        $base = trailingslashit(FRM_MDG_BASE_URL);

        $dirs = [
            $base . 'migrations',
            $base . 'migrations/imports'
        ];

        foreach ($dirs as $dir) {

            if (!file_exists($dir)) {
                wp_mkdir_p($dir);
            }

            if (!is_dir($dir)) {
                throw new Exception("Cannot create directory: {$dir}");
            }

            if (!is_writable($dir)) {
                throw new Exception("Directory not writable: {$dir}");
            }
        }
    }

    /**
     * Import folder path
     */
    private static function get_import_dir(): string {
        return trailingslashit( FRM_MDG_BASE_URL ) . 'migrations/imports/';
    }

    /**
     * Tables list
     */
    private static function get_table_names(): array {

        global $wpdb;

        return [
            $wpdb->prefix . 'frm_midigator_preventions',
            $wpdb->prefix . 'frm_midigator_resolves',
            $wpdb->prefix . 'frm_midigator_resolve_history',
            $wpdb->prefix . 'frm_midigator_rdr',
        ];
    }

    /**
     * Detect formats for insert
     */
    private static function build_formats_from_row(array $row): array {

        $formats = [];

        foreach ($row as $value) {

            if (is_int($value)) {
                $formats[] = '%d';
            }
            elseif (is_float($value)) {
                $formats[] = '%f';
            }
            elseif (is_numeric($value) && (string)(int)$value === (string)$value) {
                $formats[] = '%d';
            }
            else {
                $formats[] = '%s';
            }
        }

        return $formats;
    }

    /**
     * SQL TABLES
     */

    private static function sql_midigator_preventions(string $prefix, string $collate): string {

        $table = $prefix . 'frm_midigator_preventions';

        return "CREATE TABLE {$table} (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,

            amount decimal(12,2) NULL,
            arn varchar(64) NULL,

            card_brand varchar(30) NULL,
            card_first_6 varchar(12) NULL,
            card_last_4 varchar(8) NULL,

            currency char(3) NULL,
            merchant_descriptor varchar(255) NULL,

            mid varchar(64) NULL,

            order_guid varchar(64) NULL,
            order_id varchar(64) NULL,

            prevention_case_number varchar(64) NULL,
            prevention_guid varchar(64) NOT NULL,
            prevention_timestamp datetime NULL,
            prevention_type varchar(50) NULL,

            transaction_timestamp datetime NULL,

            is_resolved tinyint(1) NOT NULL DEFAULT 0,

            created_at datetime NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at datetime NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

            PRIMARY KEY (id),

            UNIQUE KEY uniq_prevention_guid (prevention_guid),

            KEY idx_order_guid (order_guid),
            KEY idx_order_id (order_id),
            KEY idx_mid (mid),
            KEY idx_arn (arn),
            KEY idx_prevention_type (prevention_type),
            KEY idx_prevention_ts (prevention_timestamp),
            KEY idx_transaction_ts (transaction_timestamp),
            KEY idx_is_resolved (is_resolved),
            KEY idx_created_at (created_at),
            KEY idx_updated_at (updated_at)
        ) {$collate};";
    }

    private static function sql_midigator_rdr(string $prefix, string $collate): string {

        $table = $prefix . 'frm_midigator_rdr';

        return "CREATE TABLE {$table} (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,

            amount decimal(12,2) NULL,
            arn varchar(64) NULL,
            auth_code varchar(32) NULL,

            card_first_6 varchar(12) NULL,
            card_last_4 varchar(8) NULL,

            currency char(3) NULL,
            merchant_descriptor varchar(255) NULL,

            event_guid varchar(64) NULL,
            event_timestamp datetime NULL,
            event_type varchar(50) NULL,

            rdr_guid varchar(64) NOT NULL,
            rdr_case_number varchar(64) NULL,
            rdr_date date NULL,
            rdr_resolution varchar(80) NULL,
            prevention_type varchar(50) NULL,

            transaction_date date NULL,

            order_id varchar(64) NULL,
            is_resolved tinyint(1) NOT NULL DEFAULT 0,

            created_at datetime NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at datetime NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

            PRIMARY KEY (id),

            UNIQUE KEY uniq_rdr_guid (rdr_guid),

            KEY idx_order_id (order_id),
            KEY idx_arn (arn),
            KEY idx_rdr_case_number (rdr_case_number),
            KEY idx_rdr_date (rdr_date),
            KEY idx_rdr_resolution (rdr_resolution),
            KEY idx_prevention_type (prevention_type),
            KEY idx_event_timestamp (event_timestamp),
            KEY idx_transaction_date (transaction_date),
            KEY idx_is_resolved (is_resolved),
            KEY idx_created_at (created_at),
            KEY idx_updated_at (updated_at)
        ) {$collate};";
    }

    private static function sql_midigator_resolves(string $prefix, string $collate): string {

        $table = $prefix . 'frm_midigator_resolves';

        return "CREATE TABLE {$table} (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,

            prevention_id bigint(20) unsigned NOT NULL,
            prevention_guid varchar(64) NOT NULL,

            resolution_type varchar(80) NULL,
            description text NULL,

            created_at datetime NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at datetime NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

            PRIMARY KEY (id),

            KEY idx_prevention_id (prevention_id),
            KEY idx_prevention_guid (prevention_guid),
            KEY idx_resolution_type (resolution_type),
            KEY idx_created_at (created_at),
            KEY idx_updated_at (updated_at)
        ) {$collate};";
    }

    private static function sql_midigator_resolve_history(string $prefix, string $collate): string {

        $table = $prefix . 'frm_midigator_resolve_history';

        return "CREATE TABLE {$table} (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,

            resolve_id bigint(20) unsigned NOT NULL,
            prevention_id bigint(20) unsigned NOT NULL,
            user_id bigint(20) unsigned NULL,

            prevention_guid varchar(64) NOT NULL,

            resolution_type varchar(80) NULL,
            description text NULL,

            created_at datetime NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at datetime NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

            PRIMARY KEY (id),

            KEY idx_resolve_id (resolve_id),
            KEY idx_prevention_id (prevention_id),
            KEY idx_user_id (user_id),
            KEY idx_prevention_guid (prevention_guid),
            KEY idx_resolution_type (resolution_type),
            KEY idx_created_at (created_at),

            KEY idx_prevention_timeline (prevention_id, created_at),
            KEY idx_resolve_timeline (resolve_id, created_at),
            KEY idx_user_timeline (user_id, created_at)
        ) {$collate};";
    }
}

add_action('plugins_loaded', [FrmMidigatorMigrations::class, 'maybe_upgrade']);



// Init for calling import/export via URL param
add_action( 'init', function() {

    if ( isset( $_GET['midigator_import'] ) ) {
        $result = FrmMidigatorMigrations::importData();
        echo '<pre>'; print_r($result); echo '</pre>';
    }

    if ( isset( $_GET['midigator_export'] ) ) {
        $result = FrmMidigatorMigrations::exportData();
        echo '<pre>'; print_r($result); echo '</pre>';
    }

} );