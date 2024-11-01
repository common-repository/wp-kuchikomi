<?php
/***********************************************
 フロントエンドの設定
/***********************************************/

// 直アクセスの防止
if (!class_exists('WP')) {
	header( 'Status: 403 Forbidden' );
	header( 'HTTP/1.1 403 Forbidden' );
	exit;
}

class WP_KckmFrontend {

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

        // 記事内に評価を表示する
        add_filter('the_content', array($this, 'show_post_rating'));

        // コメント投稿フォームの文言変更
        add_filter('comment_form_defaults', array($this, 'modify_comment_title'));
        // コメント投稿フォームに枠を追加
        add_action('wp_footer', array($this, 'add_user_rating_fields'));
        // コメントの保存
        add_action('comment_post', array($this, 'save_userrating_fields'));

        // frontendの場合に適用する
	    if (!is_admin()) {
            // コメント保存前のチェック
            add_filter('preprocess_comment', array($this, 'verify_comment'));

            // コメント表示に評価表示を付与
	    	add_filter('comment_text', array($this, 'display_user_rating_in_comment'), 99);
	    	add_filter('thesis_comment_text', array($this, 'display_user_rating_in_comment'), 99);
	    }
    }

    /**
     * 記事内に評価を表示する
     */
    public function show_post_rating($content){
        global $wp, $post;

	    if (!in_the_loop () || !is_main_query ()) {
            return $content;
        }

        // 投稿ページではないなら終了
	    if (!(is_single($post->ID) || is_page($post->ID))) return $content;


        // ==== 設定を取得
        $options = get_option($this->option_name);
	    $custom = get_post_custom($post->ID);

        // 自動表示しない設定なら終了
        if(isset($options['hide_rating']) && $options['hide_rating'] == "on") return $content;

        // 表示設定の投稿タイプかチェック
        $post_type = get_post_type($post->ID);
        if(!$post_type || !(isset($options['post_types_'.$post_type]) && $options['post_types_'.$post_type] == "on")) return $content;

        // 項目情報を取得
        $item_options = get_option($this->item_option_name);
        $items = isset($item_options['items']) ? $item_options['items'] : array();
        foreach($items as &$item) {
            // 記事固有の表示設定(on/off)を持っていなければデフォルト設定を使用する
            $default_viewable = isset($item['default']) ? $item['default'] : "off";
            $post_viewable = get_post_meta($post->ID, "wp_kuchikomi_item_".$item['id']."_viewable", true);
            $item['viewable'] = $post_viewable == "" ? $default_viewable : $post_viewable;

            // 評価の取得
            if(isset($options['display_type']) && $options['display_type'] == 'user') {
                // ユーザー平均点
                $item['rating'] = get_post_meta($post->ID, "wp_kuchikomi_user_rating_avg_item_".$item['id'], true);
            } else {
                // 運営評価
                $item['rating'] = get_post_meta($post->ID, "wp_kuchikomi_item_".$item['id'], true);
            }
        }
        unset($item);

        // 表示位置
        $position = isset($options['position']) ? $options['position']: 'top';

        if(isset($options['display_type']) && $options['display_type'] == 'user') {
            $rating = get_post_meta($post->ID, "wp_kuchikomi_user_rating_avg_total", true);
        } else {
            $rating = isset($custom["wp_kuchikomi_rating"][0]) ? $custom["wp_kuchikomi_rating"][0] : null;
        }

        // 総合評価がされてない場合は表示しない
        // FIXME: 設定項目につけるべき
        if(!$rating) return $content;

        // ==== 描画用HTMLの設定

        $html = "<div class='wp_kuchikomi_display_rating' itemtype='http://schema.org/Review' itemscope >";

        $html .= '<meta content="'.get_the_author_meta('display_name', $post->post_author).'" itemprop="author" />';
        $html .= '<div itemprop="itemreviewed" itemscope itemtype="http://schema.org/Thing">';
        $html .=    '<meta content="'.get_the_title().'" itemprop="name" />';
        $html .= '</div>';

        // 個別評価
        foreach($items as $item) {
            if($item['viewable'] !== "on") continue;

            $html .= "<div>";
            $html .=    "<span style='display: inline-block; min-width: 150px;'>".$item['name']."</span>";
            $class_rate = round($item['rating'] * 2) / 2;
            $class_rate = str_replace('.', '', $class_rate);
            $html .=    "<div style='display: inline-block;'>";
            $html .=        '<span class="rate-base rate-'.$class_rate.'"></span>';
            $html .=        '<span>('.$item['rating'].')</span>';
            $html .=    "</div>";
            $html .= "</div>";
        }
        
        // 総合評価
        $class_rate = round($rating * 2) / 2;
        $class_rate = str_replace('.', '', $class_rate);
        $html .= "<div>";
        $html .=    "<span style='display: inline-block; min-width: 150px;'><b>".__('Total Rating', $this->domain)."</b></span>";
        $html .=    "<div style='display: inline-block;'>";
        $html .=        '<div itemprop="reviewRating" itemscope itemtype="http://schema.org/Rating">';
        $html .=           '<meta itemprop="worstRating" content="0" ></meta>';
        $html .=           '<meta itemprop="ratingValue" content="'.$rating.'" ></meta>';
        $html .=           '<meta itemprop="bestRating" content="5" ></meta>';
        $html .=           '<span class="rate-base rate-'.$class_rate.'"></span>';
        $html .=           '<span>('.$rating.')</span>';
        $html .=        "</div>";
        $html .=    "</div>";
        $html .= "</div>";

        $html .= "</div>";

        // 表示位置の反映
        if($position == "top") {
            $content = $html.$content;
        } else {
            $content = $content.$html;
        }

        return $content;
    }

    /**
     * コメント投稿フォームの文言変更
     */
    public function modify_comment_title($arg) {
	    global $post;

        // 評価されてなければ拡張なし
        if (!WP_KckmUtils::is_review($post->ID)) return $arg;

        // 記事を書いたユーザーと一致しているなら拡張なし
	    if (WP_KckmUtils::user_is_author()) return $arg;

        // 「コメントを残す」部分の文言変更
	    $arg['title_reply'] = __('Add Review:', $this->domain);
	    return $arg;
    }

    /**
     * コメント投稿フォームの拡張
     */
    public function add_user_rating_fields() {
        global $post;

        $id = get_the_ID();

        // 評価されてなければ拡張しない
        if (!WP_KckmUtils::is_review($post->ID)) return;

        // 記事を書いたユーザーと一致しているなら拡張しない
	    if (WP_KckmUtils::user_is_author()) return;

        // 初期化
        $options = get_option($this->option_name);
        wp_reset_query();

        $mypost = get_post($id);
        $custom = get_post_custom($mypost->ID);

        // コメント欄の状態チェック
        if ($mypost->comment_status != 'open') return;

        // 表示設定の投稿タイプかチェック
        $post_type = get_post_type($post->ID);
        if(!$post_type || !(isset($options['post_types_'.$post_type]) && $options['post_types_'.$post_type] == "on")) return;

        // ユーザー評価可能設定での切り替え
        if( !(isset($options['user_rating']) && $options['user_rating'] == "on") ) return;

        // 項目情報を取得
        $item_options = get_option($this->item_option_name);
        $items = isset($item_options['items']) ? $item_options['items'] : array();
        $selectors = array('#wp_kuchikomi_user_rating'); // コメント欄での星評価入力用
        foreach($items as &$item) {
            // 記事固有の表示設定(on/off)を持っていなければデフォルト設定を使用する
            $default_viewable = isset($item['default']) ? $item['default'] : "off";
            $post_viewable = get_post_meta($post->ID, "wp_kuchikomi_item_".$item['id']."_viewable", true);
            $item['viewable'] = $post_viewable == "" ? $default_viewable : $post_viewable;

            $selectors[] = '#wp_kuchikomi_user_rating_item_'.$item['id'];
        }
        unset($item);

        $field = '';

        // 個別項目
        foreach($items as $item) {
            if($item['viewable'] !== "on") continue;

            $field .= "<div>";
            $field .= '<div style="display: inline-block; min-width: 180px;">'.$item['name'].' : </div>';
            $field .= '<div style="display: inline-block;">';
            $field .=   '<select id="wp_kuchikomi_user_rating_item_'.$item['id'].'" name="wp_kuchikomi_user_rating_item_'.$item['id'].'">';
            $field .=       '<option value=></option>';
            $field .=       '<option value=1>1</option>';
            $field .=       '<option value=2>2</option>';
            $field .=       '<option value=3>3</option>';
            $field .=       '<option value=4>4</option>';
            $field .=       '<option value=5>5</option>';
            $field .=   '</select>';
            $field .= '</div>';
            $field .= "</div>";
        }

        // 総合評価
        $field .= "<div>";
        $field .= '<div style="display: inline-block; min-width: 180px;">';
        if(isset($options['user_rating_required']) && $options['user_rating_required'] == "on") {
            $field .= '<span class="required">*</span>';
        }
            $field .= '<b>'.__('Total Rating', $this->domain).'</b> : ';
        $field .= '</div>';
        $field .= '<div style="display: inline-block;">';
        $field .=   '<select id="wp_kuchikomi_user_rating" name="wp_kuchikomi_user_rating">';
        $field .=     '<option value=></option>';
        $field .=     '<option value=1>1</option>';
        $field .=     '<option value=2>2</option>';
        $field .=     '<option value=3>3</option>';
        $field .=     '<option value=4>4</option>';
        $field .=     '<option value=5>5</option>';
        $field .=   '</select>';
        $field .= '</div>';
        $field .= "</div>";

    
        // 復数回評価のチェック
        $is_rated = false;
        if($options['check_multiple'] == "ip_address") {

            // IPでのチェック
            $ip = WP_KckmUtils::getClientIp();

            // 承認済みコメントを取得して重複チェック
            $args = array(
                'post_id' => $mypost->ID,
                'status' => 'approve',
                'number' => '',
                'meta_key' => 'wp_kuchikomi_user_rating',
                'comment_author_ip' => $ip,
            );
		    $comments = get_comments($args);
            foreach($comments as $comment) {
			    $userrating = get_comment_meta($comment->comment_ID, 'wp_kuchikomi_user_rating', true);
                // 既に評価を入れているなら
                if($comment->comment_author_IP == $ip && $userrating != '') {
                    $is_rated = true;
                    break;
                }
            }
        } else if($options['check_multiple'] == "cookie") {
            
            // Cookieでのチェック
            $cookie_name = WP_Kckm::COOKIE_PREFIX."_is_rated_".$post->ID;
            if (isset($_COOKIE[$cookie_name])) {
                // 既に評価済み
                $is_rated = true;
            }
        }

        if($is_rated) {
            // 既に評価済みの場合
            $field = "<span>";
            $field .= __('You have already rated.', $this->domain);
            $field .= "</span>";

        }

        // footer部分にjavascript記述してコメント欄の上部にフォームを追加する
        ?>
        <script type="text/javascript">
	    jQuery(document).ready(function($) {
            if ($('form#commentform textarea[name=comment]').length > 0) {
	    		var commentField = $('form#commentform textarea[name=comment]');
	    		var parentTagName = $(commentField).parent().get(0).tagName;

                // コメント欄の上部にhtml挿入
	    		if (parentTagName == 'P' || parentTagName == 'DIV' || parentTagName == 'LI') {
	    		    $(commentField).parent().before('<'+parentTagName+' class="wp-kuchikomi-field"><?php echo $field; ?></'+parentTagName+'>');
	    		} else {
	    		   $(commentField).before('<?php echo $field; ?>');
	    		}
	        }
            // コメント部分の星表示
            $('<?php echo join(',', $selectors); ?>').barrating({
                theme: 'fontawesome-stars-o'
            });
	    });
        </script>
        <?php

    }

    /**
     * コメント保存前のチェック
     * 
     * @param array $commentdata
     */
    public  function verify_comment($commentdata) {
        $options = get_option($this->option_name);

        // そもそもユーザー評価不能なら何もしない
        if( !(isset($options['user_rating']) && $options['user_rating'] == "on") ) return $commentdata;

        // 評価設定の入力必須チェック有無
        if( !(isset($options['user_rating_required']) && $options['user_rating_required'] == "on") ) return $commentdata;

        // 必須チェック
        if ( !isset($_POST['wp_kuchikomi_user_rating']) || ($_POST['wp_kuchikomi_user_rating'] == 0)) {
            wp_die( __( 'Rating required.', $this->domain) );
        }

        return $commentdata; 
    }


    /**
     * コメントの保存
     *
     * @param int $comment_id
     */
    public function save_userrating_fields($comment_id){
        global $wpdb;
    
        if (!isset($_POST['wp_kuchikomi_user_rating']) || $_POST['wp_kuchikomi_user_rating'] == 0) return;
    
        $comment = get_comment($comment_id);

        // 総合評価を更新
        add_comment_meta($comment_id, 'wp_kuchikomi_user_rating', $_POST[ 'wp_kuchikomi_user_rating' ] );
    
        // 個別評価を更新
        $item_options = get_option($this->item_option_name);
        $items = isset($item_options['items']) ? $item_options['items'] : array();
        foreach($items as $item){
            if(isset($_POST['wp_kuchikomi_user_rating_item_'.$item['id']])) {
                add_comment_meta($comment_id, 'wp_kuchikomi_user_rating_item_'.$item['id'], $_POST['wp_kuchikomi_user_rating_item_'.$item['id']]);
            }
        }
    
        // ユーザー評価の平均値を更新する
        WP_KckmUtils::update_rating_average($comment->comment_post_ID);
    
        // 頻繁な復数回答防止用にCOOKIEに評価情報を設定
        $cookie_name = WP_Kckm::COOKIE_PREFIX."_is_rated_".$comment->comment_post_ID;
        if (!isset($_COOKIE[$cookie_name])) {
            $userrating = $_POST['wp_kuchikomi_user_rating'];
            // FIXME: expireの値
    	    if($userrating) setcookie($cookie_name, $userrating, strtotime('+90 day'));
        }
    }

    /**
     * コメント表示に評価を表示
     * @param str $content
     */
    public function display_user_rating_in_comment($content) {
        global $post;

        // 評価情報の取得
        $comment_id = get_comment_ID();
        if(!$comment_id) {
            return $content;
        }
        $options = get_option($this->option_name);

        // 各項目名の取得
        $item_options = get_option($this->item_option_name);
        $items = isset($item_options['items']) ? $item_options['items'] : array();
        foreach($items as &$item) {
            // 記事固有の表示設定(on/off)を持っていなければデフォルト設定を使用する
            $default_viewable = isset($item['default']) ? $item['default'] : "off";
            $post_viewable = get_post_meta($post->ID, "wp_kuchikomi_item_".$item['id']."_viewable", true);
            $item['viewable'] = $post_viewable == "" ? $default_viewable : $post_viewable;

            // 評価取得
            $item['rating'] = get_comment_meta($comment_id, "wp_kuchikomi_user_rating_item_".$item['id'], true);
        }
        unset($item);

        // 表示設定の投稿タイプかチェック
        $post_type = get_post_type($post->ID);
        if(!$post_type || !(isset($options['post_types_'.$post_type]) && $options['post_types_'.$post_type] == "on")) return $content;

		$userrating = get_comment_meta( $comment_id, 'wp_kuchikomi_user_rating', true);
	    $custom = get_post_custom($post->ID);

        if(!$userrating) return $content;

        // ユーザー評価可能設定での切り替え
        if( !(isset($options['user_rating']) && $options['user_rating'] == "on") ) return $content;

        $box = "<div class='wp_kuchikomi_userrating_in_comment'>";
        $box .= "<div class='wp_kuchikomi_display_rating'>";

        // 個別評価
        foreach($items as $item) {
            if($item['viewable'] !== "on") continue;
            $box .= "<div>";
            $box .= "<span style='display: inline-block; min-width: 150px;'>".$item['name']."</span>";
            $class_rate = str_replace('.', '', $item['rating']);
            $box .= '<span class="rate-base rate-'.$class_rate.'"></span>';
            $box .= "</div>";
        }

        // 総合評価
        $class_rate = str_replace('.', '', $userrating);
        $box .=     "<div>";
        $box .=        "<span style='display: inline-block; min-width: 150px;'><b>".__('Total Rating', $this->domain)."</b></span>";
        $box .=        '<span class="rate-base rate-'.$class_rate.'"></span>';
        $box .=     "</div>";

        $box .= "</div>";

        $box .= "<div class='wp_kuchikomi_display_comment'>";
        $box .=     $content;
        $box .= "</div>";
        $box .= "</div>";

        return $box;
    }

}

