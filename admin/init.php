<?php

//import other admin stuff
include_once dirname(__FILE__)."/general.php";
include_once dirname(__FILE__)."/import.php";
include_once dirname(__FILE__)."/export.php";
include_once dirname(__FILE__)."/debug.php";


function emailSub_menu() {
	$page=array();

    $page[]=add_menu_page("Email Subscription", "Email Subscription",'manage_options', 'email-subscription', 'emailSub_admin_general');

    //parent, title, link, rights, url, function
    $page[]=add_submenu_page('email-subscription',"Email Subscription", "General", 'manage_options','email-subscription', 'emailSub_admin_general');
    $page[]=add_submenu_page('email-subscription',"Email Subscription - Import", "Import", 'manage_options','email-subscription-import', 'emailSub_admin_import');
    $page[]=add_submenu_page('email-subscription',"Email Subscription - Export", "Export", 'manage_options','email-subscription-export', 'emailSub_admin_export');
    $page[]=add_submenu_page('email-subscription',"Email Subscription - Debug", "Debug", 'manage_options','email-subscription-debug', 'emailSub_admin_debug');


    foreach($page as $p) {
  		add_action('admin_print_styles-' . $p, 'emailSub_admin_styles');
    }
}
add_action('admin_menu', 'emailSub_menu');
add_action('admin_init', 'emailSub_admin_init');


function emailSub_admin_init() {
	wp_register_style('emailSubStyleAdmin', WP_PLUGIN_URL . '/email-subscription/assets/admin.css');
}

function emailSub_admin_styles() {
	wp_enqueue_style('emailSubStyleAdmin');
}

