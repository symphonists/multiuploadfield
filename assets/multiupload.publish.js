(function($) {

	$(document).on('ready.multiupload', function() {
		var multiupload = $('.multiupload-duplicator');

		// Initialise Duplicator
		multiupload.symphonyDuplicator({
			orderable: false,
			collapsible: false
  		});

  		// Select file
  		multiupload.on('constructshow.duplicator', 'li', function(event) {
  			$(this).find('input[type="file"]').click();
  		});
  		multiupload.on('change.multiupload', 'input[type="file"]', function(event) {
  			$(this).parents('.instance').find('span').text(this.value.replace(/^.*[\\\/]/, '')).addClass('file');
  		});
	});

})(window.jQuery);
