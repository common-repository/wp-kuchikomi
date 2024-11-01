<?php
/***********************************************
 Utils
/***********************************************/

class WP_KckmUtils {


    /**
     * レビュー済み状態かチェック
     *
     * @param int $id post_id
     * @return boolean
     */
    public static function is_review($id) {
        return get_post_meta($id, "wp_kuchikomi_rating", TRUE) == null ? false : true;
    }

    /**
     * 投稿に対するユーザーの評価平均点と件数を設定する
     *
     * @param int $post_id
     */
    public static function update_rating_average($post_id) {
    	global $wpdb;	

        $prefix = $wpdb->prefix;

        // 各項目名の取得
        $item_options = get_option(WP_Kckm::WP_ITEM_OPTION_NAME);
        $items = isset($item_options['items']) ? $item_options['items'] : array();

        // 各項目の平均点を取得
        $sql = " SELECT ";
        $sql .= " avg(if(cmm.meta_key = 'wp_kuchikomi_user_rating', cmm.meta_value, NULL)) as avg_total,";
        foreach($items as $item) {
            $column = 'wp_kuchikomi_user_rating_item_'.$item['id'];
            $sql .= " avg(if(cmm.meta_key = '".$column."', cmm.meta_value, NULL)) as avg_item_".$item['id'].",";
        }
        $sql .= " count(IF(cmm.meta_key = 'wp_kuchikomi_user_rating', cmm.meta_value, NULL)) as cnt_rating ";
        $sql .= " FROM ".$prefix."comments as cm";
        $sql .= " LEFT JOIN ".$prefix."commentmeta as cmm ON cm.comment_ID = cmm.comment_id ";
        $sql .= " WHERE cm.comment_post_ID = %d ";
        $sql .= " AND cm.comment_approved = '1' ";
        $sql .= " AND cmm.meta_key LIKE %s ";
        $sql .= " AND cmm.meta_value != 0 ";
        $sql .= " GROUP BY cm.comment_post_ID ";
        
        $results = $wpdb->get_results( $wpdb->prepare( $sql, array($post_id, 'wp_kuchikomi_user_rating%'), null)); 

        // 取得した平均点を格納する
        // NOTE: UPDATE文一回でできそうな気もするけどWPのUPDATE処理に乗っかっておく
        if(isset($results[0])) {
            $params = $results[0];
            foreach($items as $item) {
                $column = 'wp_kuchikomi_user_rating_avg_item_'.$item['id'];
                $rating = $params->{'avg_item_'.$item['id']};
                if(is_numeric($rating)) $rating = round($rating, 1);
                update_post_meta($post_id, $column, $rating);
            }

            $total_rating = is_numeric($params->avg_total) ? round($params->avg_total, 1) : $params->avg_total;
            update_post_meta($post_id, 'wp_kuchikomi_user_rating_avg_total', $total_rating);
		    update_post_meta($post_id, 'wp_kuchikomi_user_rating_count', $params->cnt_rating);
        }
        return true;
    }

    /**
     * 記事閲覧ユーザーが著者かどうかチェック
     * 
     * @return boolean
     */
    public static function user_is_author() {
        global $post, $current_user;	
        $current_user = wp_get_current_user();
        return $post->post_author == $current_user->ID ? true :false;
    }

    /**
     * IPの取得
     */
    public static function getClientIp() {
        $ip_address = $_SERVER['REMOTE_ADDR'];
        return esc_attr($ip_address);
    }

    /**
     * カスタムを含め全ての投稿タイプを取得する
     */
    public static function get_post_types() {
        $post_types_builtin = array('post', 'page');
        $args = array(
            'public'   => true,
            '_builtin' => false
        );
        $output = 'names'; 
		$operator = 'and';
		$post_types_custom = get_post_types($args,$output,$operator);

        $post_types = array_merge($post_types_builtin, $post_types_custom);
        return $post_types;
    }
}

