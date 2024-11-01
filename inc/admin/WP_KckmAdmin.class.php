<?php
/***********************************************
 管理画面での設定
/***********************************************/

// 直アクセスの防止
if (!class_exists('WP')) {
	header( 'Status: 403 Forbidden' );
	header( 'HTTP/1.1 403 Forbidden' );
	exit;
}

class WP_KckmAdmin {

    private $option_name;
    private $item_option_name;
    private $domain;
    private $plugin_slug;

    function __construct() {

    }

    /**
     * 初期化
     * @param str $option_name WPのオプション設定に保存する為の名前
     * @param str $item_option_name WPの評価項目オプション設定に保存する為の名前
     * @param str $doamin 翻訳ファイル識別用ドメイン
     * @param str $plugin_slug プラグインのスラッグ
     */
    public function init($option_name, $item_option_name, $domain, $plugin_slug) {
        $this->option_name = $option_name;
        $this->item_option_name = $item_option_name;
        $this->domain = $domain;
        $this->plugin_slug = $plugin_slug;

        add_action('admin_init', array($this, 'settings'));
        // メニューの追加
        add_action('init', array($this, 'addmenu'));

        add_filter('manage_edit-post_columns', array($this, 'add_post_header_columns'), 10, 1);
        add_action('manage_posts_custom_column', array($this, 'add_post_data_row'), 10, 2);

        // コメント承認時に平均点を算出
        add_action('wp_set_comment_status', array($this, 'update_rating_average'), 15, 2);

        // プラグインページ限定
        if(isset($_GET['page']) && strpos($_GET['page'], WP_Kckm::PLUGIN_SLUG) !== false) {

            // admin用スクリプトの読込
            add_action('admin_print_scripts', array($this, 'admin_scripts'));

            // helpページ用マークダウンCSS
            add_action('admin_print_styles', array($this, 'admin_css'));

            // フッターロゴの表示
            add_filter("admin_footer_text", array($this, 'footer'));
        }

    }

    /**
     * 設定
     */
    public function settings(){
        register_setting($this->option_name, $this->option_name, array($this, 'options_sanitize'));
        register_setting($this->item_option_name, $this->item_option_name, array($this, 'options_sanitize'));
    }

    /**
     * プラグイン設定画面で読み込むjavascriptなど
     */
    public function admin_scripts() {
        // デフォルト組み込みのjQueryスクリプトを読込
        wp_enqueue_script('jquery');
        wp_enqueue_script('jquery-ui-sortable');

        // 管理画面用スクリプトの読込
        wp_enqueue_script('wp-kuchikomi-admin-scripts', plugins_url().'/wp-kuchikomi/assets/js/admin-scripts.js');
    }

    /**
     * プラグイン設定画面で読み込むCSS
     */
     public function admin_css(){
        wp_enqueue_style('github-readme-style', plugins_url().'/wp-kuchikomi/assets/css/github-markdown.css', array(), false, 'all');
     }

    /**
     * 設定保存前のコールバック用
     */
    public function options_sanitize($input) {
        if(isset($_POST['reset'])){
		    delete_option($this->option_name);
	    }
	    return $input;
    }

    /**
     * 管理画面に設定項目の追加
     */
    public function addmenu() {
        add_action('admin_menu', array($this, 'addmenu_link'));
        add_action('admin_menu', array($this, 'add_box'));
        add_action('save_post',  array($this, 'save_postmeta_data'));
    }

    /**
     * プラグイン設定画面へのメニュー追加
     */
    public function addmenu_link() {
        if ( is_admin() && current_user_can('manage_options') ) {
            // リンクのプラグインアイコン画像
            add_menu_page(	__(WP_Kckm::PLUGIN_NAME, $this->domain),
							__('Kuchikomi', $this->domain),
							'manage_options', 
							$this->plugin_slug,
							array($this, 'show_setting_page'),
                            plugins_url()."/".WP_Kckm::PLUGIN_DOMAIN."/assets/img/menu_logo.png",
                            null
		    );
			add_submenu_page($this->plugin_slug, 
							__('Settings', $this->domain), 
							__('Settings', $this->domain),
							'manage_options', 
							$this->plugin_slug,
							array($this, 'show_setting_page')
            );
			add_submenu_page($this->plugin_slug, 
							__('Item Setting', $this->domain), 
							__('Item Setting', $this->domain),
							'manage_options', 
							$this->plugin_slug.'-item', 
							array($this, 'show_item_page')
            );
			add_submenu_page($this->plugin_slug, 
							__('Help', $this->domain), 
							__('Help', $this->domain),
							'manage_options', 
							$this->plugin_slug.'-help', 
							array($this, 'show_help_page')
            );
        }
    }

    /**
     * プラグインページのフッターロゴ
     */
    public function footer() {
        $url = WP_Kckm::COMPANY_URL;
        return "<span>".__('Kuchikomi', $this->domain)." is powered by </span><a href='".$url."' target='_blank'><img src='".plugins_url()."/".WP_Kckm::PLUGIN_DOMAIN."/assets/img/footer_logo.png' alt='side7' width='25px'/></a>";
    }

    /**
     * プラグイン設定画面の描画
     */
    public function show_setting_page() {

        // 設定を取得
        $options = get_option($this->option_name);

        // 投稿タイプを取得
        $post_types_default = array("post", "page");
        $post_types_custom = get_post_types( array(
            'public'   => true,
            '_builtin' => false
        ));
        $post_types = array_merge($post_types_default, $post_types_custom);

        // ==== プラグイン設定画面の描画
        // FIXME: 項目数が多くなってきたらテンプレート形式やフォーム生成するメソッドにデータ突っ込む形とか管理しやすい形式に変える
        ob_start();
    ?>
<div>
    <h1><?php _e(WP_Kckm::PLUGIN_NAME, $this->domain); ?>&nbsp;<?php _e('Settings', $this->domain); ?>&nbsp;<span style="font-size: 10px;"><?php echo 'ver '.WP_Kckm::VERSION; ?></span></h1>
</div> 
<form method="post" action="options.php">
    <?php
        settings_fields($this->option_name);
        settings_errors();
    ?>

  <table class="form-table" style="width: auto;">
    <tr>
        <th><label for="wp_kuchikomi_options[post_types]"><?php _e('Post Types', $this->domain); ?></label></th>
        <td>
        <?php
        foreach($post_types as $post_type) {
            $type = "post_types_".$post_type;
            echo '<input type="checkbox" id="wp_kuchikomi_options['.$type.']"  name="wp_kuchikomi_options['.$type.']" ';
            echo (isset($options[$type]) && $options[$type] == "on" ? 'checked' : '')."/>";
            echo '<span style="margin-right: 10px;">'.$post_type.'</span>';
        }
        ?>
        </td>
    </tr>
    <tr>
        <th><label for="wp_kuchikomi_options[check_multiple]"><?php _e('Check Multiple Ratings', $this->domain); ?></label></th>
        <td>
            <select id="wp_kuchikomi_options[check_multiple]" name="wp_kuchikomi_options[check_multiple]">
                <option value=""></option>
                <option value="ip_address" <?php echo isset($options['check_multiple']) && $options['check_multiple'] == 'ip_address' ? 'selected':''; ?> >IP Address</option>
                <option value="cookie" <?php echo isset($options['check_multiple']) && $options['check_multiple'] == 'cookie' ? 'selected':''; ?>>Cookie</option>
            </select>
        </td>
    </tr>
    <tr>
        <th><label for="wp_kuchikomi_options[user_rating]"><?php _e('User reviews', $this->domain); ?></th>
        <td><input type="checkbox" id="wp_kuchikomi_options[user_rating]" name="wp_kuchikomi_options[user_rating]" <?php echo isset($options['user_rating']) && $options['user_rating'] == 'on' ? 'checked' : ''; ?>></td>
    </tr>
    <tr>
        <th><label for="wp_kuchikomi_options[user_rating_required]"><?php _e('User reviews required', $this->domain); ?></th>
        <td><input type="checkbox" id="wp_kuchikomi_options[user_rating_required]" name="wp_kuchikomi_options[user_rating_required]" <?php echo isset($options['user_rating_required']) && $options['user_rating_required'] == 'on' ? 'checked' : ''; ?>></td>
    </tr>
    <tr>
        <th><label for="wp_kuchikomi_options[hide_rating]"><?php _e('Hide rating(use shortcode)', $this->domain); ?></th>
        <td><input type="checkbox" id="wp_kuchikomi_options[hide_rating]" name="wp_kuchikomi_options[hide_rating]" <?php echo isset($options['hide_rating']) && $options['hide_rating'] == 'on' ? 'checked' : ''; ?>></td>
    </tr>
    <tr>
        <th><label for="wp_kuchikomi_options[display_type]"><?php _e('Display type(when not checked "hide rating")', $this->domain); ?></label></th>
        <td>
            <select id="wp_kuchikomi_options[display_type]" name="wp_kuchikomi_options[display_type]">
                <option value="author" <?php echo isset($options['display_type']) && $options['display_type'] == 'author' ? 'selected':''; ?> ><?php _e('Author', $this->domain); ?></option>
                <option value="user" <?php echo isset($options['display_type']) && $options['display_type'] == 'user' ? 'selected':''; ?>><?php _e('User', $this->domain); ?></option>
            </select>
        </td>
    </tr>
    <tr>
      <th><label for="position"><?php _e('Position(when not checked "hide rating")', $this->domain); ?></label></th>
      <td>
        <select id="wp_kuchikomi_options[position]" name="wp_kuchikomi_options[position]">
            <option value="top" <?php if($options['position']=="top") echo "selected";?>><?php _e('Top', $this->domain); ?></option>
            <option value="bottom" <?php if($options['position']=="bottom") echo "selected";?>><?php _e('Bottom', $this->domain); ?></option>
        </select>
	  </td>
    </tr>
  </table>
  <input type="submit" class="button-primary lk_key" value="<?php _e('Save', $this->domain) ?>">
</form>
<?php

    }

    /**
     * 評価項目設定画面
     */
    public function show_item_page(){

        // 設定を取得
        $item_options = get_option($this->item_option_name);

        $items = array();
        if(isset($item_options['items'])) {
            $items = $item_options['items'];
        }

        // 項目は可変なので保存する時はシリアライズして1カラムにぶっこむ

        // ==== 評価項目設定画面の描画
?>
<div>
    <h1><?php _e(WP_Kckm::PLUGIN_NAME, $this->domain); ?>&nbsp;<?php _e('Item Setting', $this->domain); ?>&nbsp;<span style="font-size: 10px;"><?php echo 'ver '.WP_Kckm::VERSION; ?></span></h1>
</div> 
<form method="post" action="options.php">
    <?php
        settings_fields($this->item_option_name);
        settings_errors();
    ?>

  <table class="form-table" style="width: auto;">
    <tbody id="sortable" class="item_list">
    <?php 
    $num = 0;
    foreach($items as $item): ?>
    <tr class="item">
        <td>
            <input 
                type="text"
                class="item_id"
                name="<?php echo $this->item_option_name; ?>[items][<?php echo $num; ?>][id]"
                size="40"
                placeholder="<?php _e('Unique ID', $this->domain); ?>"
                value="<?php echo $item['id']; ?>"/>
        </td>
        <td>
            <input 
                class="regular-text item_name"
                type="text"
                placeholder="<?php _e('Item Name', $this->domain); ?>"
                name="<?php echo $this->item_option_name; ?>[items][<?php echo $num; ?>][name]"
                value="<?php echo $item['name']; ?>"/>
        </td>
        <td>
            <span><?php _e('default', $this->domain); ?></span>
            <input  
                name="<?php echo $this->item_option_name; ?>[items][<?php echo $num; ?>][default]"
                class="item_default"
                type="checkbox"
                <?php echo isset($item['default']) && $item['default'] == "on" ? "checked": ""; ?>>
        </td>
        <td class="trash">
          <i class="fa fa-trash-o" aria-hidden="true"></i>
        </td>
    </tr> 
    <?php
    $num++;
    endforeach; ?>
    <!-- コピー元 -->
    <tr style="display: none;">
        <td>
            <input 
                type="text"
                class="item_id"
                _name="<?php echo $this->item_option_name; ?>[items][<?php echo $num; ?>][id]"
                size="40"
                placeholder="<?php _e('Unique ID', $this->domain); ?>"
                value=""/>
        </td>
        <td>
            <input 
                type="text"
                class="regular-text item_name" 
                placeholder="<?php _e('Item Name', $this->domain); ?>"
                _name="<?php echo $this->item_option_name; ?>[items][index][name]"
                value=""/>
        </td>
        <td>
          <label for=""><?php _e('default', $this->domain); ?></label>
          <input 
            _name="<?php echo $this->item_option_name; ?>[items][index][default]"
            class="item_default"
            type="checkbox">
        </td>
        <td class="trash">
          <i class="fa fa-trash-o" aria-hidden="true"></i>
        </td>
    </tr>
    </tbody>
  </table>
  <p>
    <input id="button_add_tite" type="button" class="button-primary lk_key" value="<?php _e('Add Item', $this->domain) ?>">
  </p>
  <p>
    <input type="submit" class="button-primary lk_key" value="<?php _e('Save', $this->domain) ?>">
  </p>
</form>
<?php
    }

    /**
     * 使い方画面
     */
    public function show_help_page(){

        // ==== プラグイン使い方画面の描画
?>

<h1><?php _e(WP_Kckm::PLUGIN_NAME, $this->domain); ?>&nbsp;<?php _e('Help', $this->domain); ?>&nbsp;<span style="font-size: 10px;"><?php echo 'ver '.WP_Kckm::VERSION; ?></span></h1>
<div class="markdown-body">
<h3>簡単な使い方（投稿に口コミ評価を表示する）</h3>
<ol type="1">
    <li>口コミ機能 &gt; 基本設定 をクリック</li>
    <li>「投稿タイプ」の“post” にチェック</li>
    <li>「保存」ボタン を押す</li>
    <li>口コミ機能 &gt; 詳細項目設定 をクリック</li>
    <li>「評価項目追加」ボタン を押す</li>
    <li>「ユニークなID」に項目の識別子、「項目名」に項目の表示名を入れる</li>
        <ul>
            <li>例: ユニークなID =&gt; price, 項目名 =&gt; 値段</li>
        </ul>
    <li>「デフォルト表示」チェックボックス をチェック</li>
    <li>「保存」ボタン を押す</li>
    <li>各記事の投稿ページの「口コミ評価設定」で評価セレクトボックスから評価を設定する</li>
</ol>
<h3>設定画面の項目について</h3>
<ul>
    <li><b>基本設定</b>
        <ul>
            <li>投稿タイプ: 評価を設定する投稿タイプを設定します</li>
            <li>復数投稿チェック: ユーザーからの復数投稿を制限する方法を選択します</li>
            <li>コメントでユーザー評価の受付: ユーザーのコメント投稿時に評価を受付可能にします</li>
            <li>コメント時に評価を必須にする: ユーザーのコメント投稿時に評価入力を必須にします</li>
            <li>評価を自動表示しない（ショートコード使用時推奨）: チェックされた場合、評価の表示を行いません</li>
            <li>表示する評価種別(自動表示の場合): 運営評価、もしくはユーザー平均評価の点数を表示します</li>
            <li>表示位置(自動表示の場合): 評価の表示位置を設定します</li>
            <li>保存: 現在の設定を保存します</li>
        </ul>
    </li>
    <li><b>詳細項目設定</b>
        <ul>
            <li>評価項目の追加: 評価項目を追加します</li>
            <li>保存: 現在の評価項目の設定を保存します</li>
        </ul>
    </li>
    </ul>
<h3>ウィジェット</h3>
<ul>
    <li><b>[口コミ機能]新着口コミの表示</b>
        <ul>
            <li>新着のコメントと総合評価を併せて表示するウィジェットです</li>
        </ul>
    </li>
    <li><b>[口コミ機能]評価順での記事表示</b>
        <ul>
            <li>評価順で記事を一覧表示するウィジェットです</li>
        </ul>
    </li>
</ul>
<h3>ショートコード</h3>
<ul>
    <li><b>評価表示ショートコード</b>
        <ul>
            <li>ショートコードが記述された箇所にその記事の評価を表示するショートコード</li>
            <li>書式: [show_rating type={評価種別}]
                <ul>
                    <li>type: author or user</li>
                </ul>
            </li>
        </ul>
    </li>
    <li><b>ランキング</b>
        <ul>
            <li>総合評価、もしくは任意の評価軸でのランキングを表示するショートコードです</li>
            <li>書式: [top_rated_posts type={評価種別} num={表示数} posttype={投稿種別} item={評価項目}]
                <ul>
                    <li>type: author or user</li>
                    <li>num: 数値</li>
                    <li>posttype: post or page or {カスタムポストタイプのスラッグ}</li>
                    <li>item: ランキング基準となる項目のユニークID(未入力の場合は総合評価)</li>
                </ul>
            </li>
        </ul>
    </li>
</ul>
</div>

<?php
    }

    /**
     * 投稿画面のメタボックス登録
     */
    public function add_box() {
        $options = get_option($this->option_name);

        // FIXME: prefixどうする？
        $prefix = 'wp_kuchikomi_';
        $meta_box = array(
            'id' => $prefix."_meta_box",
            'title' => __('Kuchikomi Setting Box', $this->domain),
            'context' => 'advanced',
            'priority' => 'default'
        );

        // 固定ページやカスタム投稿タイプへの対応
        $post_types = WP_KckmUtils::get_post_types();
        foreach($post_types as $post_type){
            if(isset($options['post_types_'.$post_type]) && $options['post_types_'.$post_type] == 'on') {
                add_meta_box( $meta_box['id'],$meta_box['title'],array($this,'show_meta_box'), $post_type,$meta_box['context'],$meta_box['priority']);
            }
        }
        
    }

    /**
     * 投稿画面への設定枠表示
     */
    public function show_meta_box() {
	    global $post;

        // ==== 設定を取得
        $item_options = get_option($this->item_option_name);
        $items = isset($item_options['items']) ? $item_options['items'] : array();

        $form_fields = array();

        // 個別評価
        foreach($items as $item) {
            $form_fields[] = array(
                'name' => $item['name'],
                'desc' => $item['name'],
                'id'   => 'wp_kuchikomi_item_'.$item['id'],
                'type' => 'select&checkbox',
            	'options'	=> array(
                    '' => '', '1'=>'1', '1.5'=>'1.5', '2'=>'2', '2.5'=>'2.5', '3'=>'3', '3.5'=>'3.5', '4'=>'4', '4.5'=>'4.5', '5'=>'5'
            	),
                // 表示設定がない場合はデフォルト設定引き継ぐ
                'checkboxes' => array(
                    array(
                        'id' => 'wp_kuchikomi_item_'.$item['id'].'_viewable',
                        'label' => __('viewable', $this->domain), 
                        'checked' => isset($item['default']) ? $item['default'] : ""
                    ),
                )
            );
        }

        // 総合評価
        $form_fields[] = array(
            'name' => __('Total Rating', $this->domain),  
            'desc'  => '総合口コミ評価',  
            'id'    => 'wp_kuchikomi_rating',  
            'type'  => 'select',
            'options'	=> array(
                '' => '', '1'=>'1', '1.5'=>'1.5', '2'=>'2', '2.5'=>'2.5', '3'=>'3', '3.5'=>'3.5', '4'=>'4', '4.5'=>'4.5', '5'=>'5'
            ),
        );

        // ==== メタボックス描画処理 ここから

	    echo '<input type="hidden" name="wp_kuchikomi_nonce" value="', wp_create_nonce(basename(__FILE__)), '" />';
        foreach($form_fields as $field) {
            // 設定内容を読込
            $meta = get_post_meta($post->ID, $field['id'], true);

            switch($field['type']) {
            case 'select':
                echo '<dl">';
                echo   '<dd style="width:10%; margin: 5px; float: left;">'.$field['name'].'</dd>';
                echo   '<dt>';
                echo     '<select name="'.$field['id'].'" id="'.$field['id'].'" >';
                foreach($field['options'] as $key => $val) {
                    echo   '<option ', $meta == $key ? 'selected' : '', '>', $val, '</option>';
                }
                echo     '</select>';
                echo   '</dd>';
                echo '</dl>';

                break;

            case 'select&checkbox':
                echo '<dl>';
                echo   '<dd style="width:10%; margin: 5px; float: left;">'.$field['name'].'</dd>';
                echo   '<dt>';
                echo     '<select name="'.$field['id'].'" id="'.$field['id'].'" >';
                foreach($field['options'] as $key => $val) {
                    echo   '<option ', $meta == $key ? 'selected' : '', '>', $val, '</option>';
                }
                echo     '</select>';
                foreach($field['checkboxes'] as $checkbox) {
                    // 何もデータがなければデフォルト設定を使う
                    $meta_cb = get_post_meta($post->ID, $checkbox['id'], true);
                    $checked = $meta_cb ? $meta_cb : $checkbox['checked'];

                    echo "&nbsp;";
                    echo $checkbox['label'];
                    echo "&nbsp;";
                    echo '<input type="checkbox" name="'.$checkbox['id'].'" '.($checked == "on" ? "checked": "").'/>';
                    echo "&nbsp;";
                }
                echo   '</dt>';
                echo '</dl>';
                break;
            }
            
        }

        // ==== メタボックス描画処理 ここまで
    }

    /**
     * 投稿メタデータの保存
     * @param 
     */
    public function save_postmeta_data($post_id) {

        // ==== 入力値チェック
	    if (!isset($_POST['wp_kuchikomi_nonce']) ||
            !wp_verify_nonce($_POST['wp_kuchikomi_nonce'], basename(__FILE__))) return $post_id;

	    // 自動保存が働いてる場合は何もしない
	    if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) {
		    return $post_id;
	    }

	    if (!current_user_can( 'edit_post', $post_id ) ) return;


        // ==== 入力保存処理

        $item_options = get_option($this->item_option_name);
        $items = isset($item_options['items']) ? $item_options['items'] : array();


        // 設定からパラメータ名を取得
        $names = array('wp_kuchikomi_rating');
        foreach($items as $item) {
            $names[] = 'wp_kuchikomi_item_'.$item['id'];
            $names[] = 'wp_kuchikomi_item_'.$item['id']."_viewable";
        }

        foreach($names as $name) {
            $old = get_post_meta($post_id, $name, true);

            // 入力値の初期化
            $new = isset($_POST[$name]) ? $_POST[$name] : '';

            // WPのメタデータ返す君の戻り値仕様が存在しなくても空文字返すので対策
            if(preg_match("/^wp_kuchikomi_item_.*_viewable$/",$name) && $new == '') {
                $new = 'off';
            }

            // 設定の反映
            if ($new && $new != $old) {
                update_post_meta($post_id, $name, $new);
            } elseif ('' == $new && $old) {
                delete_post_meta($post_id, $name, $old);
            }
        }
    }


    /**
     * 投稿一覧の評価列の名前設定
     * @param array $columns
     */
    public function add_post_header_columns($columns) {
        if (!isset($columns['wp_kuchikomi_ratings'])) {
            $columns['wp_kuchikomi_ratings'] = __('Total Rating',$this->domain);    
        }
        return $columns;
    }

    /**
     * 投稿一覧の評価列の追加
     * @param array $column_name
     * @param int $post_id
     */
    public function add_post_data_row($column_name, $post_id) {

        switch($column_name) {
        case 'wp_kuchikomi_ratings':

            // Utilsメソッドのクラス化
            if(!WP_KckmUtils::is_review($post_id)) break;

            // レビュー設定を取得
            $custom = get_post_custom();
            $rating = isset($custom["wp_kuchikomi_rating"][0]) ? $custom["wp_kuchikomi_rating"][0] : '';
            echo '<div>'.__('Auhtor rating: ', $this->domain);
            if ($rating) {
                echo $rating;
            } else {
                echo __('None', $this->domain);
            }
            echo '</div>';

            // ユーザー評価平均点の表示
            $rating = isset($custom["wp_kuchikomi_user_rating_avg_total"][0]) ? $custom["wp_kuchikomi_user_rating_avg_total"][0] : '';
            echo '<div>'.__('User rating: ', $this->domain);
            if ($rating) {
                echo $rating;
            } else {
                echo __('None', $this->domain);
            }
            echo '</div>';
            break;
 
        default:
            break;
        }
    }

    /**
     * 投稿に対するユーザーの評価平均点と件数を設定する
     *
     * @param int $post_id 
     */
    public function update_rating_average($comment_id, $comment_status) {
        if ('approve' == $comment_status) {
            $comment = get_comment($comment_id);
            WP_KckmUtils::update_rating_average($comment->comment_post_ID);
        }
    }

}

