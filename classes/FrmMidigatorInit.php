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

    }

    private function include_admin() {

        // Settings page
        require_once FRM_MDG_BASE_URL.'/classes/admin/FrmImageEnhancerSettings.php';

    }

    private function include_api() {

        // Gemini API client
        require_once FRM_MDG_BASE_URL.'/classes/api/FrmMidigatorApi.php';

    }

    private function include_libs() {

        // Gemini API client
        require_once FRM_MDG_BASE_URL.'/classes/libs/MidigatorLib.php';

    }

}

new FrmMidigatorInit();