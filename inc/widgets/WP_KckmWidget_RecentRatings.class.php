<?php
/***********************************************
 新着口コミ表示ウィジェット
/***********************************************/

class WP_KckmWidget_RecentRatings extends WP_Widget {

    function __construct() {
		$widget_ops = array(
            'classname' => 'widget_kckmwidget_recentratings',
            'description' => '新着口コミを表示する'
        );
		parent::__construct('widget-kuchikomi-recent-ratings', '[口コミ機能]新着口コミの表示', $widget_ops);
		$this->alt_option_name = 'widget_kuchikomi_recent_ratings';
    }

    /**
     * フロントエンドに新着口コミを表示
     */
    public function widget($args, $instance) {

        extract($args);

        //タイトル名を取得
        $title = apply_filters( 'widget_recent_comment_title', empty($instance['title']) ? "最近のコメント" : $instance['title'] );
        //コメント表示数
        $count = apply_filters( 'widget_recent_comment_count', empty($instance['count']) ? 5 : absint( $instance['count'] ) );
        //コメント文字数
        $str_count = apply_filters( 'widget_recent_comment_str_count', empty($instance['str_count']) ? 100 : absint( $instance['str_count'] ) );
        //管理者コメント除外設定
        $author_not_in = apply_filters( 'widget_recent_comment_author_not_in', empty($instance['author_not_in']) ? false : true );

        // ==== Widgetの描画部分 ここから
        ?>

        <?php echo $args['before_widget']; ?>

        <?php
        echo $args['before_title'];
        echo $title ? $title : '最近の口コミ';
        echo $args['after_title'];
        ?>
        <div class="recent-comments">
        <?php
            $comments_args = array(
                'author__not_in' => $author_not_in ? 1 : 0, // 管理者は除外
                'number' => $count, // 取得するコメント数
                'status' => 'approve', // 承認済みコメントのみ取得
                'type' => 'comment' // 取得タイプを指定。トラックバックとピンバックは除外
            );
            //クエリの取得
            $comments_query = new WP_Comment_Query;
            $comments = $comments_query->query( $comments_args );
            //コメントループ
            if ( $comments ) {
                foreach ( $comments as $comment ) {
                    $url = get_permalink($comment->comment_post_ID);
                    $rating = get_comment_meta($comment->comment_ID, 'wp_kuchikomi_user_rating', true);
                    $rating_display = str_replace('.', '', $rating);

                    echo '<div class="recent_review">';
                    
                    // 記事タイトル
                    echo    '<div class="recent-comment-title">';
                    echo        '<a href="'.get_permalink($comment->comment_post_ID).'#comment-'.$comment->comment_ID.'">'.$comment->post_title.'</a>';
                    echo    '</div>';
                    
                    // 評価表示
                    echo    "<div style='font-family: FontAwesome !important;'>";
                    echo        '<span class="rate-base rate-'.$rating_display.'"></span>';
                    echo        '<span>('.$rating.')</span>';
                    echo    "</div>";
                    
                    // コメントの表示
                    echo    '<div class="wp_kuchikomi_recent_comment_content" style="font-family: FontAwesome !important;">';
                    echo        '<span class="fa fa-comment-o"></span>&nbsp;';
                    $my_pre_comment_content = strip_tags($comment->comment_content);
                    if(mb_strlen($my_pre_comment_content,"UTF-8") > $str_count) {
                        $my_comment_content = mb_substr($my_pre_comment_content, 0, $str_count) ; echo $my_comment_content. '...' ;
                    } else {
                        echo $comment->comment_content;
                    }
                    echo    "</div>";

                    // アバター表示
                    echo    '<div class="wp_kuchikomi_recent_comment_author">';
                    echo        '<span class="recent-comment-avatar">';
                    echo        get_avatar( $comment, '18', null );
                    echo        '</span>';
                    comment_author($comment->comment_ID);
                    echo        "(";
                    echo        comment_date( get_theme_text_date_format(), $comment->comment_ID);
                    echo        ")";
                    echo     '</div>';

                    echo '</div>';
                }
            } else {
                echo 'コメントなし';
            }
          ?>
            </div>
            <?php echo $args['after_widget']; ?>
    <?php
        // ==== Widgetの描画部分 ここまで
    }

    /**
     * 設定更新処理
     */
    public function update($new_instance, $old_instance) {
        $instance = $old_instance;
        $instance['title'] = strip_tags($new_instance['title']);
        $instance['count'] = $new_instance['count'];
        $instance['str_count'] = $new_instance['str_count'];
        $instance['author_not_in'] = $new_instance['author_not_in'];
        return $instance;
    }

    /**
     * 管理画面での設定枠
     */
    public function form($instance) {

        // 初期値設定
        if(empty($instance)){
            $instance = array(
                'title' => null,
                'count' => 5,
                'str_count' => 100,
                'author_not_in' => false,
            );
        }

        $title = esc_attr($instance['title']);
        $count = esc_attr($instance['count']);
        $str_count = esc_attr($instance['str_count']);
        $author_not_in = esc_attr($instance['author_not_in']);

        // ==== 設定枠描画部分 ここから
    ?>
    <?php //タイトル入力フォーム ?>
    <p>
      <label for="<?php echo $this->get_field_id('title'); ?>">
      タイトル
      </label>
      <input class="widefat" id="<?php echo $this->get_field_id('title'); ?>" name="<?php echo $this->get_field_name('title'); ?>" type="text" value="<?php echo $title; ?>" />
    </p>
    <?php //表示するコメント数 ?>
    <p>
      <label for="<?php echo $this->get_field_id('count'); ?>">
        表示するコメント数
      </label>
      <input class="widefat" id="<?php echo $this->get_field_id('count'); ?>" name="<?php echo $this->get_field_name('count'); ?>" type="number" min="3" max="30" value="<?php echo $count; ?>" />
    </p>
    <?php //コメント文字数 ?>
    <p>
      <label for="<?php echo $this->get_field_id('str_count'); ?>">
        コメント文字数
      </label>
      <input class="widefat" id="<?php echo $this->get_field_id('str_count'); ?>" name="<?php echo $this->get_field_name('str_count'); ?>" type="number" min="30" value="<?php echo $str_count; ?>" />
    </p>
    <?php //管理者の除外 ?>
    <p>
      <label for="<?php echo $this->get_field_id('author_not_in'); ?>">
        管理者の除外
      </label><br />
      <input class="widefat" id="<?php echo $this->get_field_id('author_not_in'); ?>" name="<?php echo $this->get_field_name('author_not_in'); ?>" type="checkbox" value="on"<?php echo ($author_not_in ? ' checked="checked"' : ''); ?> />管理者のコメントを表示しない
    </p>
    <?php
        // ==== 設定枠描画部分 ここまで
    }
}
add_action( 'widgets_init', create_function('', 'register_widget("WP_KckmWidget_RecentRatings");' ));
