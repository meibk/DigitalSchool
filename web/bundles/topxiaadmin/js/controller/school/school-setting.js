define(function(require, exports, module) {

    var DynamicCollection = require('../../../../topxiaweb/js/controller/widget/dynamic-collection4');

    var Notify = require('common/bootstrap-notify');
    var Uploader = require('upload');
    require('jquery.sortable');

    exports.run = function() {

        var $form = $("#school-form");
        var uploader = new Uploader({
            trigger: '#school-homepage-upload',
            name: 'homepagePicture',
            action: $('#school-homepage-upload').data('url'),
            data: {'_csrf_token': $('meta[name=csrf-token]').attr('content') },
            accept: 'image/*',
            error: function(file) {
                Notify.danger('上传图片失败，请重试！')
            },
            success: function(response) {
                response = $.parseJSON(response);
                $("#school-homepage-container").html('<img src="' + response.url + '?'+(new Date()).getTime()+'" style="max-width:400px;">');
                $form.find('[name=homepagePicture]').val(response.path);
                $("#school-homepage-remove").show();
                Notify.success('上传学校主页成功！');
            }
        });

        var dynamicCollections = new Object();

        $(".grade-list").each(function(i, item) {
            var schoolType = $(item).attr('id');

            dynamicCollections[schoolType] = new DynamicCollection({
                element: '#' + schoolType,
                onlyAddItemWithModel: true,
                beforeDeleteItem: function(e){
                    if (!confirm("删除年级会导致与该年级相关的数据不可用。\n\n 您确认要删除该年级吗？")) {
                        return false;
                    }

                    var gradeCounts=this.$('[data-role=list]').children("li").length;

                    if(gradeCounts <= 1){
                        Notify.danger("至少需要保留一个年级！");
                        return false;
                    }
                }
            });
        });

        $(".grade-list-group").sortable({
            'distance':20
        });

        // Add new grade when Add button is clicked.
        $('.btn-save-grade').on('click', function(){
            var newGradeName = $(this).parent().prev().val();
            var schoolType = $(this).parents('.grade-list').attr('id');
            var error = '';

            if(!newGradeName.trim()){
                error = "请输入年级名称！"
            }else{
                for(var key in dynamicCollections){
                    dynamicCollections[key].element.find('span.grade-name').each(function(i, item) {
                        if (newGradeName == $(item).text()) {
                            error = '该年级已存在，不能重复添加！';
                        }
                    });
                }
            }

            if (error) {
                Notify.danger(error);
            } else {
                var newGrade = new Object();
                newGrade.name = newGradeName;

                dynamicCollections[schoolType].addItemWithModel(newGrade);
            }
        });

        // Trigger the onclick event on the Add button when press Enter key in the input field.
        $('.custom-grade-input').on('keydown', function(event) {
            if(event.keyCode == 13){
                $(this).next().children('.btn-save-grade').trigger('click');
            }
        });

       $("#school-homepage-remove").on('click', function(){
            if (!confirm('确认要删除吗？')) return false;
            var $btn = $(this);
            $.post($btn.data('url'), function(){
                $("#school-homepage-container").html('');
                $form.find('[name=homepagePicture]').val('');
                $btn.hide();
                Notify.success('删除学校主页成功！');
            }).error(function(){
                Notify.danger('删除学校主页失败！');
            });
        });

       $('.school-type-container input[type=checkbox]').on('click', function(){
            if(! $(this).is(':checked')){
                var schoolName = $(this).parent().text().trim();
                if (!confirm('删除年级会导致与这些年级相关的数据不可用。\n\n确认要删除'+schoolName+'年级吗？')){
                    return false;
                }
            }

            showHideGradeSettings($(this));
        });
      
        $('.grade-type input[type=radio]').on('change', function(){
            showHideGradeList($(this).parents('.grade-type'));
        });

        initGradeSettings = function(){
            $('input[type=checkbox]', '.school-type-container').each(function(i, item) {
                showHideGradeSettings($(item));
            });
        };

        showHideGradeSettings = function(schoolTypeCheckbox){
            $schoolTypeChecked = $(schoolTypeCheckbox).is(':checked');
            $gradeSettingsContainer = $(schoolTypeCheckbox).parents('.sub-school-settings').children('.grade-settings');

            if($schoolTypeChecked){
                $gradeSettingsContainer.show();

                showHideGradeList($('.grade-type', $gradeSettingsContainer));
            }else{
                $gradeSettingsContainer.hide();
            }
        };

        showHideGradeList = function(gradeTypeRadios){
            $gradeType = $("input[type='radio']:checked", $(gradeTypeRadios)).val();
            $gradeListContainer = $(gradeTypeRadios).parents('.grade-settings').children('.grade-list');

            if($gradeType == 'custom'){
                $gradeListContainer.show();
            }else{
                $gradeListContainer.hide();
            }
        }

        initGradeSettings();
    }
});