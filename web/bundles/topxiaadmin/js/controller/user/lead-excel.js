define(function(require, exports, module) {
	var ClassChooser = require('../class/class-chooser');
    var Notify = require('common/bootstrap-notify');
    exports.run = function() {
    	var $form=$('#user-import-form');
        $("input[type=file]").change(function(){$(this).parents(".uploader").find(".filename").val($(this).val());});
        $("input[type=file]").each(function(){
            if($(this).val()==""){$(this).parents(".uploader").find(".filename").val("");}
        });
        $('#start-import-btn').on("click",function(){
            if($form.find('#className').length > 0 && ($form.find('#className').val()=="" || $form.find('#className').val()==null)){
                Notify.danger('请选择导入班级');
                return false;
            }

            if($('#start-import-btn').text() == "开始校验数据"){
                $('#start-import-btn').text("正在校验数据...");
            }else if($('#start-import-btn').text() == "确定导入"){
                $('#start-import-btn').text("正在导入...");
            }

            $('#start-import-btn').button('submiting').addClass('disabled');
        });

        //调用
        if($form.find('#className').length > 0) {
            var classChooser = new ClassChooser({
                element:'#className',
                modalTarget:$('#modal'),
                url:$form.find('#className').data().url
            });
            
            classChooser.on('choosed',function(id,name){
                $form.find('#classId').val(id);
                $form.find('#className').val(name);
            });

        }
     
    };

});