<?php
if(!defined(WP_UNINSTALL_PLUGIN)){
    die();
}

function Borrar(){
    delete_option('wp_plugin_options');
}

resgister_activation_hook(__FILE__, 'Borrar');

add_action('admin_menu', 'Borrar');