define(function(require, exports, module) {

    var Notify = require('common/bootstrap-notify');
    var Uploader = require('upload');

    exports.run = function() {
        var $table=$('.eduMaterial-table');
        $table.popover({
            selector: '.material-selector',
            trigger: 'click',
            placement: 'bottom',
            html: true,
            delay: 30,
            viewport: { selector: '.eduMaterial-table', padding: 0 },
            content: function() {
                return $(this).find('.material-list').html();
            }
        });

        $table.on('click','.material-name',function(){
            $selectedMaterial = $(this);

            $selectedTd = $selectedMaterial.parents('.materialTd');

            if($(this).closest('.materialTd').find('.eduMaterial-name').html()==$(this).html()){
                $table.find('.popover').parent().find('.material-selector').popover('hide');
                return;
            }
            if (!confirm('确认更改教材为'+$(this).html()+'？')) {
                return ;
            }
            
            $table.find('.popover').parent().find('.material-selector').popover('hide');

            $.post(
                $(this).data('url'),
                { eduMaterialId:$(this).data('edumaterialid'),
                  materialId:$(this).data('materialid')
                }).success(
                function(data){
                    if(data){
                        // Update the page only when the updating succeed
                        $selectedTd.find('.eduMaterial-name').html($selectedMaterial.html());
                        
                        Notify.success('修改教材成功');
                    }else{
                        Notify.danger('修改教材失败');
                    }
                }).error( function(xhr, textStatus, errorThrown) {
                    Notify.danger('修改教材失败: ' + xhr.responseJSON.error.message);
                });
        });

        $('body').on('click', function () {
            var pops=$table.find('.popover');
            if(pops.length>0){
                pops.parent().find('.material-selector').popover('hide');
            }
        });


    }
});