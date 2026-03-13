<?php
/*
Plugin Name: Formidable Midigator Extension
Description: 
Version: 1.0.0
Plugin URI: 
Author URI: 
Author: Stanislav Matrosov
*/

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}


// Variables
define('FRM_MDG_BASE_URL', __DIR__);
define('FRM_MDG_BASE_PATH', plugin_dir_url(__FILE__));

// References
require_once 'references.php';

// Initialize core
require_once 'classes/FrmMidigatorInit.php';



add_action('init', function() {
    
    if( isset( $_GET['midigator'] ) ) {

        midigatorShowSubs();
        exit();

    }

    if( isset( $_GET['midigator_create_subs'] ) ) {

        createDefaultSubs();
        exit();

    }

    if( isset( $_GET['ping_event'] ) ) {
        pingEvent();
        exit();
    }

    if( isset( $_GET['resolve_prevention'] ) ) {
        resolvePrevention();
        exit();
    }

    if( isset( $_GET['prevention'] ) ) {
        getPrevention();
        exit();
    }

    if( isset( $_GET['get_order'] ) ) {
        getOrder();
        exit();
    }

    if( isset( $_GET['refund_order'] ) ) {
        refundOrder();
        exit();
    }

});

function refundOrder() {

    $entryId = $_GET['refund_order'];

    $helper = new DotFrmOrderHelper();
    $res = $helper->runFullRefund($entryId);

    if (is_wp_error($res)) {
        echo 'Error: ' . $res->get_error_message();
    }

    echo '<pre>';
    print_r($res);
    echo '</pre>';

}

function getOrder() {

    $orderId = (int) $_GET['get_order'];
    $helper = new DotFrmOrderHelper();
    $order = $helper->getOrderById($orderId);

    echo '<pre>';
    print_r($order);
    echo '</pre>';

}

function getPrevention() {

    $preventionGuid = 'pre_540385dce935441baca73ae04d319c35';

    $prevHelper = new FrmMidigatorPreventionHelper();
    $res = $prevHelper->getPreventionData($preventionGuid);

    echo '<pre>';
    print_r($res);
    echo '</pre>';

}

function resolvePrevention() {

    $preventionGuid = 'pre_540385dce935441baca73ae04d319c35';

    $prevHelper = new FrmMidigatorPreventionHelper();
    $res = $prevHelper->resolvePreventionAlert($preventionGuid, 'could_not_find_order', 'Test resolve');

    echo '<pre>';
    print_r($res);
    echo '</pre>';

}

function midigatorShowSubs() {

    $api = new FrmMidigatorApi();
    $subs = $api->listSubscriptions();

    echo '<pre>';
    print_r($subs);
    echo '</pre>';

}

function createDefaultSubs() {

    $helper = new FrmMidigatorEventHelper();
    $res = $helper->createDefaultSubscriptions();

    echo '<pre>';
    print_r($res);
    echo '</pre>';

}

function pingEvent() {

    $api = new FrmMidigatorApi();
    $res = $api->pingEvent('chargeback.result');

    echo '<pre>';
    print_r($res);
    echo '</pre>';

}


