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
			var file = $(this).find('input[type="file"]').focus().trigger('click.multiupload');

			setTimeout(function() {
				$('body').one('mousemove.multiupload', function() {
					$(file).trigger('change.multiupload');
				});
			}, 500);
		});
		multiupload.on('change.multiupload', 'input[type="file"]', function(event) {
			var name = this.value,
				instance = $(this).parents('.instance');

			if(name != '') {
				instance.find('span').text(name.replace(/^.*[\\\/]/, '')).addClass('file');
			}
			else {
				instance.find('.destructor').trigger('click.duplicator');
			}
		});
	});

})(window.jQuery);
