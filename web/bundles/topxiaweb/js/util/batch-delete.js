define(function(require, exports, module) {

	var Notify = require('common/bootstrap-notify');

    module.exports = function($element) {

        $element.on('click', '[data-role=batch-delete]', function() {
        	var $btn = $(this);
        		name = $btn.data('name');

            var ids = [];
            $element.find('[data-role=batch-item]:checked').each(function(){
                ids.push(this.value);
            });

            if (ids.length == 0) {
                Notify.danger('未选中任何' + name);
                return ;
            }

            if($(this).data('parentid')>0 && $(this).data('structurechanged')==0){
                if (!confirm('删除'+name+'后课程相关内容将不再与模板课程同步信息，是否确认删除？')) {
                    return ;
                }
            }else{
                if (!confirm('确定要删除选中的' + ids.length + '条' + name + '吗？')) {
                    return ;
                }
            }
            $element.find('.btn').addClass('disabled');

            Notify.info('正在删除' + name + '，请稍等。', 60);
            
            $.post($btn.data('url'), {ids:ids}, function(response){
                window.location.reload();
            });

        });

    };

});