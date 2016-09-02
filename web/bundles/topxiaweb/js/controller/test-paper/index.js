define(function(require, exports, module) {

    var Notify = require('common/bootstrap-notify');
    require("$");
    
    exports.run = function() {

        require('../course-manage/header').run();

        var $container = $('#quiz-table-container');
        require('../../util/batch-select')($container);
        require('../../util/batch-delete')($container);
        require('../../util/item-delete')($container);

        $('.test-paper-reset').on('click','',function(){
        	if (!confirm('重置会清空原先的题目,确定要继续吗？')) {
        	    return ;
        	}
            window.location.href=$(this).data('url');
        });



        var $table = $('#quiz-table');

        $table.on('click', '.open-testpaper, .close-testpaper', function() {
            var $trigger = $(this);
            var $oldTr = $trigger.parents('tr');

            if($(this).data('parentid')>0 && $(this).data('structurechanged')==0){
                if (!confirm($trigger.attr('title')+'后课程相关内容将不再与模板课程同步信息，是否确认'+$trigger.attr('title')+'？')) {
                    return ;
                }
            }else{
                if (!confirm('真的要' + $trigger.attr('title') + '吗？ 试卷发布后无论是否关闭都将无法修改或删除。')) {
                    return ;
                }
            }
            $.post($(this).data('url'), function(html){
                Notify.success($trigger.attr('title') + '成功！');

                var $tr = $(html);
                $oldTr.replaceWith($tr);
            }).error(function(){
                Notify.danger($trigger.attr('title') + '失败');
            });
        });



    };


});