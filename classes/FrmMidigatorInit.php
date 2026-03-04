<?php

if ( ! defined('ABSPATH') ) { exit; }

class FrmMidigatorInit {

    public function __construct() {

        // Admin
        //$this->include_admin();

        // Libs
        $this->include_libs();

        // API class
        $this->include_api();

        // Loggers
        $this->include_loggers();

        // Webhooks
        $this->include_webhooks();

        // Helpers
        $this->include_helpers();

        // Migrations
        $this->include_migrations();

        // Models
        $this->include_models();

        // Include shortcodes
        $this->include_shortcodes();

    }

    private function include_admin() {

        // Settings page
        require_once FRM_MDG_BASE_URL.'/classes/admin/FrmImageEnhancerSettings.php';

    }

    private function include_helpers() {

        // Helpers
        require_once FRM_MDG_BASE_URL.'/classes/helpers/FrmMidigatorEventHelper.php';
        require_once FRM_MDG_BASE_URL.'/classes/helpers/FrmMidigatorPreventionHelper.php';

    }

    private function include_webhooks() {

        // Webhooks
        require_once FRM_MDG_BASE_URL.'/webhooks/FrmMidigatorEventWebhook.php';

    }

    private function include_loggers() {

        // Loggers
        require_once FRM_MDG_BASE_URL.'/classes/loggers/FrmMidigatorLogger.php';

    }

    private function include_api() {

        // Gemini API client
        require_once FRM_MDG_BASE_URL.'/classes/api/FrmMidigatorApi.php';

    }

    private function include_libs() {

        // Gemini API client
        require_once FRM_MDG_BASE_URL.'/classes/libs/MidigatorLib.php';

    }

    private function include_migrations() {

        // Migrations
        require_once FRM_MDG_BASE_URL.'/classes/migrations/FrmMidigatorMigrations.php';

    }

    private function include_models() {

        // Abstract model        
        require_once FRM_MDG_BASE_URL.'/classes/models/FrmMidigatorAbstractModel.php';

        // Models
        require_once FRM_MDG_BASE_URL.'/classes/models/FrmMidigatorPreventionModel.php';

    }

    private function include_shortcodes() {

        // Shortcodes
        require_once FRM_MDG_BASE_URL.'/shortcodes/midigator-preventions-list.php';

    }

}

new FrmMidigatorInit();