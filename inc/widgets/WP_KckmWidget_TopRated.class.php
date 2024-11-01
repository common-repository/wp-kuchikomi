<?php
/***********************************************
 ランキング表示ウィジェット
/***********************************************/

class WP_KckmWidget_TopRated extends WP_Widget {

    private $cache_name;
    private $domain;

    function __construct() {
        $this->cache_name = 'kckm_widget_top_rated';
        $this->domain = WP_Kckm::PLUGIN_DOMAIN;
		$widget_ops = array(
            'classname' => 'widget_kckmwidget_toprated',
            'description' => '評価順に記事を並べて表示する'
        );
		parent::__construct('widget-kuchikomi-top-rated', '[口コミ機能]評価順での記事表示', $widget_ops);
		$this->alt_option_name = 'widget_kuchikomi_top_rated';

		add_action( 'save_post', array(&$this, 'flush_widget_cache'));
		add_action( 'deleted_post', array(&$this, 'flush_widget_cache'));
		add_action( 'switch_theme', array(&$this, 'flush_widget_cache'));
    }


    /**
     * フロントエンド側の表示
     */
    public function widget($args, $instance) {

		$cache = wp_cache_get($this->cache_name, 'widget');

		if(!is_array($cache)) $cache = array();

		if(isset($cache[$args['widget_id']])) {
			echo $cache[$args['widget_id']];
			return;
		}

		ob_start();
		extract($args);
		
		// 引数から取り出して値を設定

		$show_rating = '';

        // タイトル取得
        $title = apply_filters('widget_title', empty($instance['title']) ? '' : $instance['title'], $instance, $this->id_base);
        // 表示記事数
        if (!$number = absint($instance['number'])){
            $number = 10;
        }

        // 選択された投稿タイプを取得する
        $ptype = isset($instance['ptype']) ? $instance['ptype'] : 'post';

        // 評価種別
        $reviewtype = isset($instance['reviewtype']) ? $instance['reviewtype'] : 'author';
	
        // カテゴリ設定
        if (isset($instance['cat'])) {$cat = $instance['cat'];} else {$cat='';}
        
        // スクリーンショット設定
        if (isset($instance['width'])) {$width = $instance['width'];} else {$width = 200;}
        if (isset($instance['height'])) {$height = $instance['height'];} else {$height = 100;}

        // 記事情報の取得
		$order = 'meta_value_num';
        if ($reviewtype == 'author') {
            // 運営評価順
            $r = new WP_Query(array(	
                'post_type' => $ptype,
                'posts_per_page' => $number,
                'no_found_rows' => true,
                'cat' => $cat,
                'post_status' => 'publish',
                'meta_key'=> 'wp_kuchikomi_rating',
                'ignore_sticky_posts' => true,
                'orderby'=> $order,
                'order'=> 'DESC',
            ));
        } else {
            // ユーザー評価平均点順
            $r = new WP_Query(array(	
                'post_type' => $ptype,
                'posts_per_page' => $number,
                'no_found_rows' => true,
                'cat' => $cat,
                'post_status' => 'publish',
                'meta_key'=> 'wp_kuchikomi_user_rating_avg_total',
                'ignore_sticky_posts' => true,
                'orderby'=> $order,
                'order'=> 'DESC',
            ));

        }


		if ($r->have_posts()) :
?>
		<?php echo $before_widget; ?>
		<?php if ( $title ) echo $before_title . $title . $after_title; ?>

        <?php  $rank = 1; ?>

		<?php
        // 取得記事分ループ
        while ($r->have_posts()) : $r->the_post();
        ?>

        <?php
        
		$current_post_id = get_the_ID();
		$custom = get_post_custom();

        if(WP_KckmUtils::is_review($current_post_id)) {
            echo '<div class="wp_kuchikomi_widget_top_rated_item wp_kuchikomi_widget_top_rated_item_'.$reviewtype.' wp_kuchikomi_widget_rank_'.$rank.'">';
    
            $permalink = get_permalink();

            // 記事名を取得
            // FIXME: 評価用の別枠名作る？
            $post_title = get_the_title();
            echo    "<div >";
            echo        $rank.". <a href='".$permalink."' >".$post_title."</a>";
            echo    "</div>";


            // アイキャッチ表示
            if ($instance['screenshot'] && has_post_thumbnail($current_post_id)) {
                $image = get_the_post_thumbnail($current_post_id, array($width, $height), array('style'=> 'width: '.$width.'px; height: '.$height.'px;'));
                echo '<div class="wp_kuchikomi_widget_top_rated_screenshot">';
                echo "<a href='".$permalink."' >".$image."</a>";
                echo '</div>';

            }

            // 評価の表示
            if($reviewtype == 'author') {
                $rating = $custom['wp_kuchikomi_rating'][0];
                $class_rate = str_replace('.', '', $rating);
            } else {
                $rating = $custom['wp_kuchikomi_user_rating_avg_total'][0];
                // 数値を0.5単位で丸める
                $class_rate = round($rating * 2) / 2;
                $class_rate = str_replace('.', '', $class_rate);
                // TODO: 評価数の表示

            }
            echo    "<div style='font-family: FontAwesome !important;'>";
            echo      '<span class="rate-base rate-'.$class_rate.'"></span>';
            echo      '<span>('.$rating.')</span>';
            echo    "</div>";

            echo "</div>";

            echo "<hr style='margin: 15px auto 15px auto; border-width: 1px 0px 0px 0px;'>";

            // ランクをインクリメント
            $rank = $rank +1;
        }

        ?>


<?php
		endwhile;
		
		echo $after_widget;
		
        // postのスコープを戻す
		wp_reset_postdata();

		endif;

		$cache[$args['widget_id']] = ob_get_flush();
		wp_cache_set( $this->cache_name, $cache, 'widget');
    }
 
    /**
     * widget設定更新
     */
    public function update($new_instance, $old_instance) {
        $instance = $old_instance;
        $instance['title']		= strip_tags($new_instance['title']);
        $instance['ptype']		= strip_tags($new_instance['ptype']);
        $instance['reviewtype'] = strip_tags($new_instance['reviewtype']);
        $instance['cat']		= strip_tags($new_instance['cat']);
        $instance['number']		= (int) $new_instance['number'];
        $instance['screenshot']	= $new_instance['screenshot'];
        $instance['width']		= $new_instance['width'];
        $instance['height']		= $new_instance['height'];
        $this->flush_widget_cache();
        
        $alloptions = wp_cache_get( 'alloptions', 'options');
        if (isset($alloptions['widget_recent_entries'])) {
        	delete_option('widget_recent_entries');
        }
        
        return $instance;
    }

    /**
     * キャッシュクリアー
     */
	public function flush_widget_cache() {
		wp_cache_delete($this->cache_name, 'widget');
	}

    /**
     * 管理画面側のWidget設定
     */
    public function form($instance) {

        $title = isset($instance['title']) ? esc_attr($instance['title']) : '';
        // FIXME: 有償版はユーザー平均ランキング&固定ページも対応
        $reviewtype = isset($instance['reviewtype']) ? esc_attr($instance['reviewtype']) : 'author';
        $ptype = isset($instance['ptype']) ? esc_attr($instance['ptype']) : 'post';
        $cat = isset($instance['cat']) ? esc_attr($instance['cat']) : '';
        $number = isset($instance['number']) ? absint($instance['number']) : 5;
        $screenshot = isset($instance['screenshot']) ? $instance['screenshot'] : false;
        $width = isset($instance['width']) ? absint($instance['width']) : 100;
        $height = isset($instance['height']) ? absint($instance['height']) : 100;

        // 投稿タイプを取得
        $post_types = WP_KckmUtils::get_post_types();
        ?>
        <p>
            <label for="<?php echo $this->get_field_id('title'); ?>">タイトル</label>
            <input class="widefat" id="<?php echo $this->get_field_id('title'); ?>" name="<?php echo $this->get_field_name('title'); ?>" type="text" value="<?php echo $title; ?>" />
        </p>

        <p>
            <label for="<?php echo $this->get_field_id('ptype'); ?>"><select id="<?php echo $this->get_field_id('ptype'); ?>" name="<?php echo $this->get_field_name('ptype'); ?>" >
            <option value=""><?php _e('- post type -', $this->domain); ?></option>
            <?php foreach ( $post_types as $post_type ) { ?>
                <option value="<?php echo $post_type; ?>" <?php if( $ptype == $post_type) { echo 'selected="selected"'; } ?>><?php echo _e($post_type, $this->domain); ?></option>
            <?php }	?>
            </select></label>
        </p>

		<p>
            <label for="<?php echo $this->get_field_id('reviewtype'); ?>">
                <select id="<?php echo $this->get_field_id('reviewtype'); ?>" name="<?php echo $this->get_field_name('reviewtype'); ?>" >
                    <option <?php if( $reviewtype == 'author') { echo 'selected="selected"'; } ?> value="author"><?php _e('author rating', $this->domain); ?></option>
                    <option <?php if( $reviewtype == 'user') { echo 'selected="selected"'; } ?> value="user"><?php _e('user rating', $this->domain); ?></option>
                </select>
            </label>
        </p>

        <p>
            <label for="<?php echo esc_attr( $this->get_field_id( 'cat' ) ); ?>">カテゴリ</label>
            <?php wp_dropdown_categories( array('name'=>$this->get_field_name( 'cat' ), 'show_option_all' => '- 全カテゴリ -', 'hide_empty' => 1, 'hierarchical' => 1, 'selected' => $cat ) ); ?>
        </p>

        <p>
            <label for="<?php echo $this->get_field_id('number'); ?>">表示する記事数</label>
            <input id="<?php echo $this->get_field_id('number'); ?>" name="<?php echo $this->get_field_name('number'); ?>" type="text" value="<?php echo $number; ?>" size="3" />
        </p>

        <p>
            <label for="<?php echo $this->get_field_id('screenshot'); ?>">アイキャッチ表示</label>
            <input type="checkbox" class="checkbox" <?php checked( $instance['screenshot'], 'on' ); ?> id="<?php echo $this->get_field_id('screenshot'); ?>" name="<?php echo $this->get_field_name('screenshot'); ?>" />
        </p>

        <p>
            <label for="<?php echo $this->get_field_id('width'); ?>">アイキャッチの幅</label>
            <input id="<?php echo $this->get_field_id('width'); ?>" name="<?php echo $this->get_field_name('width'); ?>" type="text" value="<?php echo $width; ?>" size="3" /><span> px</span>
        </p>

        <p>
            <label for="<?php echo $this->get_field_id('height'); ?>">アイキャッチの高さ</label>
            <input id="<?php echo $this->get_field_id('height'); ?>" name="<?php echo $this->get_field_name('height'); ?>" type="text" value="<?php echo $height; ?>" size="3" /><span> px</span>
        </p>
        <?php
    }

}
add_action( 'widgets_init', create_function('', 'register_widget("WP_KckmWidget_TopRated");' ));
