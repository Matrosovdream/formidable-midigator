<?php
/**
 * Midigator DB Migrations
 * DB Version: 1.1.0
 */

if ( ! defined( 'ABSPATH' ) ) { exit; }

class FrmMidigatorMigrations {

    /** Bump when schema changes */
    public const DB_VERSION     = '1.1.0';

    /** Store installed db version in WP options */
    public const VERSION_OPTION = 'frm_midigator_db_version';

    /** Run on plugin activation */
    public static function install(): void {
        global $wpdb;

        require_once ABSPATH . 'wp-admin/includes/upgrade.php';

        $charset_collate = $wpdb->get_charset_collate();
        $prefix          = $wpdb->prefix;

        $sql = [];

        // Existing + altered table
        $sql[] = self::sql_midigator_preventions( $prefix, $charset_collate );

        // New tables
        $sql[] = self::sql_midigator_resolves( $prefix, $charset_collate );
        $sql[] = self::sql_midigator_resolve_history( $prefix, $charset_collate );

        foreach ( $sql as $statement ) {
            dbDelta( $statement );
        }

        update_option( self::VERSION_OPTION, self::DB_VERSION );
    }

    /** Optional: call this on 'plugins_loaded' to auto-upgrade when version changes */
    public static function maybe_upgrade(): void {
        $installed = get_option( self::VERSION_OPTION );
        if ( $installed !== self::DB_VERSION ) {
            self::install();
        }
    }

    /**
     * frm_midigator_preventions
     *
     * Added:
     *  - is_resolved tinyint(1) NOT NULL DEFAULT 0
     *  - created_at / updated_at auto-filling timestamps
     */
    private static function sql_midigator_preventions( string $prefix, string $collate ): string {
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

            created_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

            PRIMARY KEY  (id),

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

    /**
     * frm_midigator_resolves
     *
     * - prevention_id: points to frm_midigator_preventions.id (no FK enforced by dbDelta)
     * - prevention_guid: duplicated for quick lookup / resilience
     */
    private static function sql_midigator_resolves( string $prefix, string $collate ): string {
        $table = $prefix . 'frm_midigator_resolves';

        return "CREATE TABLE {$table} (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,

            prevention_id bigint(20) unsigned NOT NULL,
            prevention_guid varchar(64) NOT NULL,

            resolution_type varchar(80) NULL,
            description text NULL,

            created_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

            PRIMARY KEY (id),

            KEY idx_prevention_id (prevention_id),
            KEY idx_prevention_guid (prevention_guid),
            KEY idx_resolution_type (resolution_type),
            KEY idx_created_at (created_at),
            KEY idx_updated_at (updated_at)
        ) {$collate};";
    }

    /**
     * frm_midigator_resolve_history
     *
     * - resolve_id: points to frm_midigator_resolves.id
     * - prevention_id: points to frm_midigator_preventions.id
     * - user_id: WP user id who did the action
     *
     * Indexes:
     * - resolve_id, prevention_id, user_id, prevention_guid, created_at
     * - composite for common timelines/lookups
     */
    private static function sql_midigator_resolve_history( string $prefix, string $collate ): string {
        $table = $prefix . 'frm_midigator_resolve_history';

        return "CREATE TABLE {$table} (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,

            resolve_id bigint(20) unsigned NOT NULL,
            prevention_id bigint(20) unsigned NOT NULL,
            user_id bigint(20) unsigned NULL,

            prevention_guid varchar(64) NOT NULL,

            resolution_type varchar(80) NULL,
            description text NULL,

            created_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

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

// run maybe on plugins_loaded to auto-upgrade when version changes
add_action( 'plugins_loaded', [ FrmMidigatorMigrations::class, 'maybe_upgrade' ] );