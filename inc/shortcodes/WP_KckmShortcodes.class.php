<?php
/***********************************************
 ショートコード群
/***********************************************/

class WP_KckmShortcodes {

    private $option_name;
    private $item_option_name;

    function __construct() {

        $this->option_name = WP_Kckm::WP_OPTION_NAME;
        $this->item_option_name = WP_Kckm::WP_ITEM_OPTION_NAME;
        $this->domain = WP_Kckm::PLUGIN_DOMAIN;

        // ショートコードを一括登録
        $ref = new ReflectionClass( $this );
        foreach( $ref->getMethods( ReflectionMethod::IS_PUBLIC ) as $method ){
            $funcname = $method->name;
            if( 0 === strpos($funcname,'shortcode_') ) {
                $name = substr($funcname, 10);
                add_shortcode($name, array($this, $funcname));
            }
        }
    }

    /**
     * 記事内に評価を表示するショートコード
     */
    public function shortcode_show_rating($atts, $content = null) {

        global $wp, $post;

        $html = "";

        /**
         ショートコードパラメータ
         @param str type author/user 運営評価 or ユーザー評価
         */
        extract(shortcode_atts(array(
            "id" => null,
            "type" => 'author',
        ), $atts));

        if(!$id) {
            $id = $post->ID;
        }

        // 投稿ページではないなら終了
	    if (!(is_single($id) || is_page($id))) return $html;


        // ==== 設定を取得
        $options = get_option($this->option_name);
	    $custom = get_post_custom($id);

        // 項目情報を取得
        $item_options = get_option($this->item_option_name);
        $items = isset($item_options['items']) ? $item_options['items'] : array();

        // 表示設定の投稿タイプかチェック
        $post_type = get_post_type($id);
        if(!$post_type || !(isset($options['post_types_'.$post_type]) && $options['post_types_'.$post_type] == "on")) return $html;

        // 評価点の取得
        foreach($items as &$item) {
            // 記事固有の表示設定(on/off)を持っていなければデフォルト設定を使用する
            $default_viewable = isset($item['default']) ? $item['default'] : "off";
            $post_viewable = get_post_meta($post->ID, "wp_kuchikomi_item_".$item['id']."_viewable", true);
            $item['viewable'] = $post_viewable == "" ? $default_viewable : $post_viewable;

            // 評価の取得
            if($type == 'user') {
                // ユーザー平均点
                $item['rating'] = get_post_meta($post->ID, "wp_kuchikomi_user_rating_avg_item_".$item['id'], true);
            } else {
                // 運営評価
                $item['rating'] = get_post_meta($post->ID, "wp_kuchikomi_item_".$item['id'], true);
            }
        }
        unset($item);
        if($type == "author") {
            $key_total = 'wp_kuchikomi_rating';
        } else {
            $key_total = 'wp_kuchikomi_user_rating_avg_total';
        }
        $rating = isset($custom[$key_total][0]) ? $custom[$key_total][0] : null;

        // 総合評価がされてない場合は表示しない
        // FIXME: 設定項目につけるべき
        if(!$rating) return $content;

        // FIXME: 同じ表示だから描画用クラス分けるべきかもしれんね

        $html = "<div class='wp_kuchikomi_display_rating wp_kuchikomi_display_rating_".$type."' itemtype='http://schema.org/Thing' itemscope >";

        $html .= '<meta content="'.get_the_author_meta('display_name', $post->post_author).'" itemprop="author">';
        $html .= '<div itemprop="itemreviewed" itemscope itemtype="http://schema.org/Thing">';
        $html .=    '<meta content="'.get_the_title().'" itemprop="name">';
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
        // 数値を0.5単位で丸める
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

        return $html;
    }

    /**
     * 評価順で記事一覧を表示するショートコード
     */
    public function shortcode_top_rated_posts($atts, $content = null) {
        global $wp, $post;

        /**
         ショートコードパラメータ
         @param str type author/user 運営評価 or ユーザー評価
         @param int num 表示記事数
         @param str posttype 表示する投稿タイプ
         @param str item 項目のID
         @param str template 使用するテンプレートファイル名(*非公開パラメーター)
         */
        extract(shortcode_atts(array(
                "type" => 'author',
                "num" => '5',
                //"cat" => '',
                "posttype" => '',
                "item" => '',
                "template" => '',
        ), $atts));

        // テンプレート指定がなければデフォルトを読み込みにいく
        $template_dir = dirname( __FILE__ ).'/templates/';
        if(!$template) {
            $template_filepath = $template_dir.'top_rated_posts_default.php';
        } else {
            // 使用中のテーマ配下から指定のファイルを探す
            $template_filepath = get_stylesheet_directory()."/".$template;
        }

        // 記事の取得
        $order = 'meta_value_num';
        if($type == 'author') {
            // 運営評価順
            $meta_key = $item != '' ? 'wp_kuchikomi_item_'.$item : 'wp_kuchikomi_rating';
            $r = new WP_Query(array(
                'post_type' => $posttype,
                'posts_per_page' => $num,
                'no_found_rows' => true,
                'post_status' => 'publish',
                'meta_key'=> $meta_key,
                //'category' => $cat,
                'ignore_sticky_posts' => true,
                'orderby'=> $order,
                'order'=> 'DESC',
            ));
    
        } else {
            // ユーザー評価平均点順
            $meta_key = $item != '' ? 'wp_kuchikomi_user_rating_avg_item_'.$item : 'wp_kuchikomi_user_rating_avg_total';
            $r = new WP_Query(array(
                'post_type' => $posttype,
                'posts_per_page' => $num,
                'no_found_rows' => true,
                'post_status' => 'publish',
                //'category' => $cat,
                'meta_key'=> $meta_key,
                'ignore_sticky_posts' => true,
                'orderby'=> $order,
                'order'=> 'DESC',
            ));
    
        }
        $rank = 1;

        ob_start();
        if ($r->have_posts()) :
            
            echo '<div style="clear: both;"></div>';
            echo '<div>';
            
            
            while ($r->have_posts()) : $r->the_post();

                $custom = get_post_custom();

                // 記事IDの取得
                $id = get_the_ID();
        
                // 記事タイトルの取得
                $title = esc_attr(get_the_title() ? get_the_title() : get_the_ID());

                // 記事のURLを取得
                $permalink = get_permalink($post->ID);

                // 口コミ数を取得
                $userrating_count = 0;
                if(isset($custom["wp_kuchikomi_user_rating_count"][0])){
                    $userrating_count = $custom["wp_kuchikomi_user_rating_count"][0];
                }

                // 評価の取得
                if($type == 'author') {
                    $rating = $item != '' ? 
                        $custom['wp_kuchikomi_item_'.$item][0] :
                        $custom['wp_kuchikomi_rating'][0];
                    $class_rate = str_replace('.', '', $rating);
                } else {
                    $rating = $item != '' ? 
                        $custom['wp_kuchikomi_user_rating_avg_item_'.$item][0] :
                        $custom['wp_kuchikomi_user_rating_avg_total'][0];
                    // 数値を0.5単位で丸める
                    $class_rate = round($rating * 2) / 2;
                    $class_rate = str_replace('.', '', $class_rate);
                }

                // テンプレートファイルの読込
                include($template_filepath);

                // ランクをインクリメント
                $rank = $rank +1;
        
            endwhile;

            echo '</div>';
            echo '<div style="clear: both;"></div>';

        endif;

        wp_reset_postdata();
    
        return ob_get_clean();      
    }

}
