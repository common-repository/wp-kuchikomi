<?php
/*
 * Uninstall plugin
 */
if ( !defined( 'WP_UNINSTALL_PLUGIN' ) )
	exit ();



if (is_multisite()) {
    // 復数サイトの場合はサイト毎に消す
    $ms_sites = wp_get_sites();

    if(0 < sizeof($ms_sites)) {
        foreach ( $ms_sites as $ms_site ) {
            switch_to_blog( $ms_site['blog_id'] );
            plugin_uninstalled();
        }
    }
	restore_current_blog();
} else {
    plugin_uninstalled();
}

/**
 * 設定情報の削除
 */
function plugin_uninstalled() {
	global $wpdb;


    // コメント単位の情報の削除
    $wpdb->query( "DELETE FROM $wpdb->commentmeta WHERE meta_key LIKE 'wp_kuchikomi_user_rating%'" );


    // 記事単位の設定の削除
    $wpdb->query( "DELETE FROM $wpdb->postmeta WHERE meta_key = 'wp_kuchikomi_rating'" );
    $wpdb->query( "DELETE FROM $wpdb->postmeta WHERE meta_key LIKE 'wp_kuchikomi_user_rating%'" );
    $wpdb->query( "DELETE FROM $wpdb->postmeta WHERE meta_key LIKE 'wp_kuchikomi_user_rating%'" );

    // WPグローバルの設定削除
    $option_names = array(
    	'wp_kuchikomi_options'
    	, 'wp_kuchikomi_options_items'
    );
    foreach($option_names as $option_name) {
        delete_option($option_name);
    }
}
