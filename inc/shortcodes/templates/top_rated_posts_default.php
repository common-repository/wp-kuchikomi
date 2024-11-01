<?php
$id;
$rank;
$title;
$permalink;
$userrating_count;
$rating;
$class_rate;
?>

<div>
    <div>
     <span><?php echo $rank; ?>.&nbsp;</span><a href="<?php echo $permalink; ?>"><strong><?php echo $title; ?></strong></a>
    </div>

    <div>
    <?php if ( is_create_date_visible() ): // 投稿日 ?>
        <span>投稿日:<?php the_time( get_theme_text_date_format() ) ;?></span>
    <?php endif; //is_category_visible?>

    <?php if($userrating_count): // 口コミ数 ?>
        <span>口コミ数:<?php echo $userrating_count; ?>件</span>
    <?php endif; ?>
    </div>

    <div style='font-family: FontAwesome !important;'>
        <span class="rate-base rate-<?php echo $class_rate; ?>"></span>
        <span>(<?php echo $rating; ?>)</span>
    </div>

    <?php // 記事抜粋もしくは冒頭表示 ?>
    <p style="margin: 0 0 5px 0; font-size: 14px;color: #555;"><?php echo get_the_excerpt(); ?></p>
</div>
<hr style="margin: 10px auto 10px auto; border-width: 1px 0px 0px 0px;">
