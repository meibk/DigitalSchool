define(function(require, exports, module) {

	var Notify = require('common/bootstrap-notify');

    module.exports = function($element, onSuccess) {
        $element.on('click', '[data-role=item-delete]', function() {
            var $btn = $(this),
                name = $btn.data('name'),
                message = $btn.data('message');


            if (!message) {
                message = '真的要删除该' + name + '吗？';
            }
            if($(this).data('parentid')>0 && $(this).data('structurechanged')==0){
                if (!confirm('删除'+name+'后课程相关内容将不再与模板课程同步信息，是否确认删除？')) {
                    return ;
                }
            }else{
                if (!confirm(message)) {
                    return ;
                }
            }


            $.post($btn.data('url'), function() {
                if ($.isFunction(onSuccess)) {
                    onSuccess.call($element, $item);
                } else {
                    $btn.parents('[data-role=item]').remove();
                    Notify.success('删除' + name + '成功');
                }
            });

        });


    };

});