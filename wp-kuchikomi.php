<?php
/*
Plugin Name: 口コミ機能
Plugin URI: http://side7.ms
Description: WordPressに口コミ機能を付与するプラグイン
Version: 1.1.1
Author: Seitaro Ohno
Author URI: http://side7.ms
Last Version update : 29 November 2016
*/


// 直アクセスの防止
if (!class_exists('WP')) {
	header( 'Status: 403 Forbidden' );
	header( 'HTTP/1.1 403 Forbidden' );
	exit;
}

if(!class_exists('WP_Kckm')) require_once('inc/WP_Kckm.class.php');
if(!class_exists('WP_KckmAdmin')) require_once('inc/admin/WP_KckmAdmin.class.php');
if(!class_exists('WP_KckmFrontend'))require_once ('inc/frontend/WP_KckmFrontend.class.php');
if(!class_exists('WP_KckmWidget_TopRated')) require_once ('inc/widgets/WP_KckmWidget_TopRated.class.php');
if(!class_exists('WP_KckmWidget_RecentRatings')) require_once ('inc/widgets/WP_KckmWidget_RecentRatings.class.php');
if(!class_exists('WP_KckmShortcodes')) require_once ('inc/shortcodes/WP_KckmShortcodes.class.php');
if(!class_exists('WP_KckmUtils')) require_once ('inc/WP_KckmUtils.class.php');

new WP_Kckm(new WP_KckmAdmin(), new WP_KckmFrontend());
new WP_KckmShortcodes();
