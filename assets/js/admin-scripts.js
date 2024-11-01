/**
 * 管理画面のプラグイン設定用のjs処理
 */
jQuery(document).ready(function($) {

    $("#button_add_tite").click(function(){

        // イベントごとコピー
        var row = $(".item_list tr:last").clone(true); 
        var item_id = item_name = item_hide = "";
        
        if($(".item_list tr.item:last").length) {
            // 最新の要素のindexを+1して採番
            var last_row_id = $(".item_list tr.item:last").find('input.item_id').attr('name'); 
            var last_row_name = $(".item_list tr.item:last").find('input.item_name').attr('name'); 
            var last_row_hide = $(".item_list tr.item:last").find('input.item_default').attr('name'); 

            $(row).find('input.item_id').attr('name', function(index, name) {
                return last_row_id.replace(/(\d+)/, function(fullMatch, n) {
                    return Number(n) + 1;
                });
            });
            $(row).find('input.item_name').attr('name', function(index, name) {
                return last_row_name.replace(/(\d+)/, function(fullMatch, n) {
                    return Number(n) + 1;
                });
            });
            $(row).find('input.item_default').attr('name', function(index, name) {
                return last_row_hide.replace(/(\d+)/, function(fullMatch, n) {
                    return Number(n) + 1;
                });
            });
        } else {
            // データがない場合は0スタート
            item_id = $(row).find('input.item_id').attr('_name');
            item_name = $(row).find('input.item_name').attr('_name');
            item_hide = $(row).find('input.item_default').attr('_name');

            item_id = item_id.replace("[index]", "[0]");
            item_name = item_name.replace("[index]", "[0]");
            item_hide = item_hide.replace("[index]", "[0]");
            $(row).find('input.item_id').attr('name', item_id);
            $(row).find('input.item_name').attr('name', item_name);
            $(row).find('input.item_default').attr('name', item_hide);
        }

        // 表示要素の最下に追加
        $(row).addClass('item');
        $(row).insertBefore(".item_list tr:last").show();
    });

    // 削除
	$('.trash').click(function(){
		if (!confirm('delete this item?')) return false;
		$(this).parent().remove();
		return false;
	});
});
