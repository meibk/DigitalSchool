define(function(require, exports, module) {
    var Validator = require('bootstrap.validator');
    require('common/validator-rules').inject(Validator);
    var Notify = require('common/bootstrap-notify');

    exports.run = function() {
        var $modal = $("#message-create-form").parents('.modal');

        var validator = new Validator({
            element: '#message-create-form',
            autoSubmit: false,
            onFormValidated: function(error, results, $form) {
                if (error) {
                    return false;
                }
                
                $('#message-submit').button('submiting').addClass('disabled');;
                $.post($form.attr('action'), $form.serialize(), function(html) {
                    $modal.modal('hide');
                    Notify.success('私信发送成功');
                }).error(function(e){
                    $modal.modal('hide');
                    Notify.danger('私信发送失败，'+ e.responseJSON.error.message);
                });
            }

        });

        validator.addItem({
            element: '[name="message_receiver"]',
            required: true,
            rule: 'chinese_alphanumeric'
        });

        validator.addItem({
            element: '[name="message_content"]',
            required: true,
            rule: 'maxlength{max:500}'
        });

        $('#modal').modal('show');
    }

});