<?php
/***********************************************
 口コミプラグイン
/***********************************************/

class WP_Kckm {
    const VERSION = '1.1'; 
    const COMPANY_URL = 'https://side7.ms';
    const PLUGIN_NAME = 'WordPress Kuchikomi';
    const PLUGIN_SLUG = 'wp-kuchikomi';
    const PLUGIN_DIR = WP_PLUGIN_DIR.'/wp-kuchikomi';
    const PLUGIN_DOMAIN = 'wp-kuchikomi';
    const WP_OPTION_NAME = 'wp_kuchikomi_options';
    const WP_ITEM_OPTION_NAME = 'wp_kuchikomi_options_items';
    const COOKIE_PREFIX = 'wp_kckm';

    private $admin;
    private $frontend;
    private $widgets;
    private $shortcodes;

    private $plugin_info;

    /**
     *
     * @param WP_KckmAdmin $admin
     * @param WP_KckmFrontend $frontend
     */
    function __construct($admin, $frontend) {
        $this->admin = $admin;
        $this->frontend = $frontend;
        $this->plugin_info = array(
            "url" => plugins_url()."/".self::PLUGIN_DOMAIN
        );

        $this->init();
    }

    private function init() {

        // 国際化ファイル読込
        add_action('init', array($this, 'localization_init'));

        $this->admin->init(self::WP_OPTION_NAME, self::WP_ITEM_OPTION_NAME, self::PLUGIN_DOMAIN, self::PLUGIN_SLUG);
        $this->frontend->init(self::WP_OPTION_NAME, self::WP_ITEM_OPTION_NAME, self::PLUGIN_DOMAIN, self::PLUGIN_SLUG);

        // フロントエンド向けCSS読込処理
        add_action( 'wp_enqueue_scripts', array($this, 'wp_register_plugin_styles') );

        // 管理画面向けCSS読込処理
        add_action( 'admin_enqueue_scripts', array($this, 'admin_register_plugin_styles') );
    }

    /**
     * 国際化ファイル読込
     */
    public function localization_init() {
        $path = dirname(plugin_basename( __FILE__ )) . '/../assets/lang/';
        $loaded = load_plugin_textdomain( self::PLUGIN_DOMAIN, false, $path);

        // TODO: 国際化ファイルが見つからなかった時の処理
    }


    /**
     * フロントエンドに必要なCSSの読込
     */
     public function wp_register_plugin_styles() {
        wp_enqueue_style('fontawesome', 'http://maxcdn.bootstrapcdn.com/font-awesome/latest/css/font-awesome.min.css', array(), false, 'all' );
        wp_enqueue_style('fontawesome-stars-o', $this->plugin_info['url'].'/assets/css/fontawesome-stars-o.css', array('fontawesome'), false, 'all' );
        wp_enqueue_style('fontawesome-kuchikomi-style', $this->plugin_info['url'].'/assets/css/style.css', array(), false, 'all');
        wp_enqueue_script('jquery-barrating', $this->plugin_info['url'].'/assets/js/libs/jquery.barrating.min.js', array(), false, true);
     }

    /**
     * 管理画面に必要なCSSの読込
     */
     public function admin_register_plugin_styles() {
        wp_enqueue_style('fontawesome', 'http://maxcdn.bootstrapcdn.com/font-awesome/latest/css/font-awesome.min.css', array(), false, 'all' );
        wp_enqueue_style('fontawesome-stars-o', $this->plugin_info['url'].'/assets/css/fontawesome-stars-o.css', array('fontawesome'), false, 'all' );
        wp_enqueue_style('fontawesome-kuchikomi-style', $this->plugin_info['url'].'/assets/css/style.css', array(), false, 'all');
        wp_enqueue_script('jquery-barrating', $this->plugin_info['url'].'/assets/js/libs/jquery.barrating.min.js', array(), false, true);
     }

}
