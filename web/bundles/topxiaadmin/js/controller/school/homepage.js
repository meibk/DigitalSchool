define(function(require, exports, module) {

	var Notify = require('common/bootstrap-notify');

	exports.run = function() {

		var $checkboxes = $('input[type="checkbox"]');

		$checkboxes.on('change', function() {
			toggleRow($(this));
		});

		toggleRow = function(checkbox){
			if(! $(checkbox).is(':checked')){
				$(checkbox).parents(".row .form-group").find('input[type="text"]').each(function(index, element){
					$(element).prop( "disabled", true );
				});

				$(checkbox).parents(".row .form-group").find('#heroSlidesEditLink').each(function(index, element){
					$(element).addClass( "text-muted");
				});
			}else{
				$(checkbox).parents(".row .form-group").find('input[type="text"]').each(function(index, element){
					$(element).prop( "disabled", false );
				});

				$(checkbox).parents(".row .form-group").find('#heroSlidesEditLink').each(function(index, element){
					$(element).removeClass( "text-muted");
				});
			}
		};

		init = function(){
			$checkboxes.each(function(index, element){
				toggleRow($(element));
			});
		};

		init();
	};

});