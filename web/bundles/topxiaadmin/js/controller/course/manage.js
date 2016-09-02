define(function(require, exports, module) {
	var Notify = require('common/bootstrap-notify');
	var ClassChooser = require('../class/class-chooser');
	
	exports.run = function(options) {

		var $table = $('#course-table');
		var $form = $('#message-search-form');
		$table.on('click', '.cancel-recommend-course', function(){
			$.post($(this).data('url'), function(html){
				var $tr = $(html);
				$table.find('#' + $tr.attr('id')).replaceWith(html);
				Notify.success('课程推荐已取消！');
			});
		});

		$table.on('click', '.close-course', function(){
			var confirmMsg = "课程关闭后，还在有效期内的学生，仍然可以继续学习。\n\n 您确认要关闭此课程吗？";

			if($(this).data('parentid')>0 && $(this).data('structurechanged')==0){
				confirmMsg = "课程关闭后将不再与公有课程同步信息。\n" + confirmMsg;
            }else if ($(this).data('parentid')==0){
				confirmMsg = "公有课程关闭后，所有关联的复制课程将同时被关闭。\n" + confirmMsg;
            }

            if (!confirm(confirmMsg)) {
                return ;
            }

			$.post($(this).data('url'), function(html){
				var $tr = $(html);
				$table.find('#' + $tr.attr('id')).replaceWith(html);
				Notify.success('课程关闭成功！');
			});
		});

		$table.on('click', '.publish-course', function(){
			if($(this).data('parentid')>0 && $(this).data('structurechanged')==0){
                if (!confirm('发布课程后将不再与模板课程同步信息，是否确认发布？')) {
                    return ;
                }
            }else{
                if (!confirm('您确认要发布此课程吗？')) {
                    return ;
                }
            }
			$.post($(this).data('url'), function(html){
				var $tr = $(html);
				$table.find('#' + $tr.attr('id')).replaceWith(html);
				Notify.success('课程发布成功！');
			});
		});

		$table.on('click', '.delete-course', function() {
			var confirmMsg = "删除课程将删除该课程的所有章节、课时和学生信息。\n\n 您确认要删除该课程吗？";

			if($(this).data('parentid')==0){
				confirmMsg = "公有课程删除后，所有关联的复制课程将同时被删除。\n" + confirmMsg;
            }

            if (!confirm(confirmMsg)) {
                return ;
            }

			var $tr = $(this).parents('tr');
			$.post($(this).data('url'), function(){
				window.location.reload();
			});

		});

		//调用
        var classChooser = new ClassChooser({
            element:'#class_name',
            modalTarget:$('#modal'),
            url:$form.find('#class_name').data().url
        });
        
        classChooser.on('choosed',function(id,name){
            $form.find('#class_id').val(id);
            $form.find('#class_name').val(name);
        });

		$table.find('.copy-course[data-type="live"]').tooltip();

		$table.on('click', '.copy-course[data-type="live"]', function(e) {
			e.stopPropagation();
		});
	};

});
