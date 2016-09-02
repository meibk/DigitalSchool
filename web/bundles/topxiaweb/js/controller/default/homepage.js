define(function(require, exports, module) {

    require('jquery.cycle2');

    exports.run = function() {

    	$('.homepage-feature').cycle({
            fx:"scrollHorz",
            slides: "> a, > img",
            log: "false",
            pauseOnHover: "true",
    	});

    	$("#login_link").on("click", function(){
            toggleLoginBox();
    	});

        $("#login_float_panel .close").on("click", function(){
            toggleLoginBox();
        });

        $("#login-form button[type=submit]").on("click", function(){
            $(this).html("正在登录...");
        });

        var init = function(){
            if($("#login_float_panel").length > 0){
                $("#login_link").attr("href", "javascript: void(0);");
            }
        };

        var toggleLoginBox = function(){
            $(".statistics").fadeToggle(400);
            $("#login_float_panel").toggle(200);
        };
        
        init();
    };

});