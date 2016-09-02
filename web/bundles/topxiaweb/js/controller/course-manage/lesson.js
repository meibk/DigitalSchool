define(function(require, exports, module) {

    require('jquery.sortable');
    var Sticky = require('sticky');
    var Notify = require('common/bootstrap-notify');

    exports.run = function() {

        require('./header').run();

        var sortList = function($list) {
            if($list.data('parentid')>0 && !$list.data('structurechanged')){
                if (!confirm('修改章节结构后课程相关内容将不再与模板课程同步信息，是否确认修改？')) {
                    window.location.reload();
                    return ;
                }
            }
            var data = $list.sortable("serialize").get();
            $.post($list.data('sortUrl'), {ids:data}, function(response){
                var lessonNum = chapterNum = unitNum = 0;

                $list.find('.item-lesson, .item-chapter').each(function() {
                    var $item = $(this);
                    if ($item.hasClass('item-lesson')) {
                        lessonNum ++;
                        $item.find('.number').text(lessonNum);
                    } else if ($item.hasClass('item-chapter-unit')) {
                        unitNum ++;
                        $item.find('.number').text(unitNum);
                    } else if ($item.hasClass('item-chapter')) {
                        chapterNum ++;
                        unitNum = 0;
                        $item.find('.number').text(chapterNum);
                    }

                });
            });
        };

        var $list = $("#course-item-list").sortable({
            distance: 20,
            onDrop: function (item, container, _super) {
                _super(item, container);
                sortList($list);
            },
            serialize: function(parent, children, isContainer) {
                return isContainer ? children : parent.attr('id');
            },
            isValidTarget:function (item, container) {
                if(item.has('li').length){ 
                    return true;
                }else{
                    return false;
                }
            }
        });

        $('.lesson-manage-panel').on('click','.create-lesson,.create-chapter,.update-chapter',function(){
            if($list.data('parentid')>0 && !$list.data('structurechanged')){
                if (!confirm($(this).data('title')+$(this).data('name')+'后课程相关内容将不再与模板课程同步信息，是否确认'+$(this).data('title')+'？')) {
                    return ;
                }
            }
            $modal=$("#modal").modal({
                backdrop:"static",
                keyboard:false
            });
            $.get($(this).data('url'),function(html){
                $modal.html(html);
            });
        });

        $list.on('click','.update-lesson',function(){
            if($list.data('parentid')>0 && !$list.data('structurechanged') && !$(this).data('contentchanged')){
                if (!confirm('编辑后，本课时将不再从公有课程中同步更新，确定要继续编辑吗？')) {
                    return ;
                }
            }
            $modal=$("#modal").modal({
                backdrop:"static",
                keyboard:false
            });
            $.get($(this).data('url'),function(html){
                $modal.html(html);
            });
        });

        $list.on('click', '.delete-lesson-btn', function(e) {
            if($list.data('parentid')>0 && !$list.data('structurechanged')){
                if (!confirm('删除课时后课程相关内容将不再与模板课程同步信息，是否确认删除？')) {
                    return ;
                }
            }else{
                if (!confirm('删除课时的同时会删除课时的资料、测验。\n您真的要删除该课时吗？')) {
                    return ;
                }
            }

            var $btn = $(e.currentTarget);
            $.post($(this).data('url'), function(response) {
                $btn.parents('.item-lesson').remove();
                sortList($list);
                Notify.success('课时已删除！');
            }, 'json');
        });

        $list.on('click', '.delete-chapter-btn', function(e) {
            if($list.data('parentid')>0 && !$list.data('structurechanged')){
                if (!confirm('删除章节后课程相关内容将不再与模板课程同步信息，是否确认删除？')) {
                    return ;
                }
            }else{
                if (!confirm('您确认要删除该章节吗？')) {
                    return ;
                }
            }

            var $btn = $(e.currentTarget);
            $.post($(this).data('url'), function(response) {
                $btn.parents('.item-chapter').remove();
                sortList($list);
                Notify.success('章节已删除！');
            }, 'json');
        });

        $list.on('click', '.replay-lesson-btn', function(e) {
            if (!confirm('您真的要录制回放吗？')) {
                return ;
            }
            $.post($(this).data('url'), function(html) {
                if(html.error){
                    if(html.error.code == 10019)
                        Notify.danger("录制失败，直播时您没有进行录制！");
                    else
                        Notify.danger("录制失败！");
                }else{
                    var id = '#' + $(html).attr('id');
                    $(id).replaceWith(html);
                    Notify.success('课时已录制！');
                }
            });
        });

        $list.on('click', '.publish-lesson-btn', function(e) {
            if($list.data('parentid')>0 && !$list.data('structurechanged') && !$(this).data('contentchanged')){
                if (!confirm('发布课时后该课时相关内容将不再与模板课程同步信息，是否确认发布？')) {
                    return ;
                }
            }

            var $btn = $(e.currentTarget);
            $.post($(this).data('url'), function(html) {
                var id = '#' + $(html).attr('id');
                $(id).replaceWith(html);
                $(id).find('.btn-link').tooltip();
                Notify.success('课时发布成功！');
            });
        });

        $list.on('click', '.unpublish-lesson-btn', function(e) {
            if($list.data('parentid')>0 && !$list.data('structurechanged') && !$(this).data('contentchanged')){
                if (!confirm('取消发布课时后该课时相关内容将不再与模板课程同步信息，是否确认取消发布？')) {
                    return ;
                }
            }
            var $btn = $(e.currentTarget);
            $.post($(this).data('url'), function(html) {
                var id = '#' + $(html).attr('id');
                $(id).replaceWith(html);
                $(id).find('.btn-link').tooltip();
                Notify.success('课时已取消发布！');
            });
        });

        $list.on('click', '.delete-exercise-btn', function(e) {
             if($list.data('parentid')>0 && !$list.data('structurechanged') && !$(this).data('contentchanged')){
                if (!confirm('删除练习后该课时相关内容将不再与模板课程同步信息，是否确认删除练习？')) {
                    return ;
                }
            }else{
                if (!confirm('您真的要删除该课时练习吗？')) {
                    return ;
                }
            }

            var $btn = $(e.currentTarget);
            $.post($(this).data('url'), function(response) {
                Notify.success('练习已删除！');
                window.location.reload();
            }, 'json');
        });

        $list.on('click', '.delete-homework-btn', function(e) {
            if($list.data('parentid')>0 && !$list.data('structurechanged') && !$(this).data('contentchanged')){
                if (!confirm('删除作业后该课时相关内容将不再与模板课程同步信息，是否确认删除作业？')) {
                    return ;
                }
            }else{
                if (!confirm('您真的要删除该课时作业吗？')) {
                    return ;
                }
            }
            var $btn = $(e.currentTarget);
            $.post($(this).data('url'), function(response) {
                Notify.success('作业已删除！');
                window.location.reload();
            }, 'json');
        });

        Sticky('.lesson-manage-panel .panel-heading', 0, function(status){
            if (status) {
                var $elem = this.elem;
                $elem.addClass('sticky');
                $elem.width($elem.parent().width() - 10);
            } else {
                this.elem.removeClass('sticky');
                this.elem.width('auto');
            }
        });

        $("#course-item-list .item-actions .btn-link").tooltip();
        $("#course-item-list .fileDeletedLesson").tooltip();

        $('.dropdown-menu').parent().on('shown.bs.dropdown', function () {
            if ($(this).find('.dropdown-menu-more').css('display') == 'block') {
                $(this).parent().find('.dropdown-menu-more').mouseout(function(){
                    $(this).parent().find('.dropdown-menu-more').hide();
                });

                 $(this).parent().find('.dropdown-menu-more').mouseover(function(){
                    $(this).parent().find('.dropdown-menu-more').show();
                });

            } else {
                $(this).parent().find('.dropdown-menu-more').show();
            }
        });

        $('.dropdown-menu').parent().on('hide.bs.dropdown',function() {
            $(this).find('.dropdown-menu-more').show();
        });

    };

});