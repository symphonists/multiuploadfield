(function($) {

	Symphony.Language.add({
		'Drop files': false,
		'In queue': false,
		'Remove file': false,
		'Upload failed': false
	});

	var Multiupload = function() {
		var fileAPI = !!window.FileReader;

	/*-------------------------------------------------------------------------
		Functions
	-------------------------------------------------------------------------*/

		function init() {
			var fields = $('div.field-multiupload'),
				files = fields.find('.multiupload-files');

			files.symphonyDuplicator({
				orderable: false,
				collapsible: false
			});

			if(fileAPI) {
				fields.each(createDroparea);
			}
		};

		function createDroparea(index, field) {
			var field = $(field),
				files = field.find('.multiupload-files').addClass('multiupload-drop'),
				controls = field.find('.apply').remove();

			// Append drop area
			$('<div />', {
				class: 'multiupload-droparea',
				html: '<span>' + Symphony.Language.get('Drop files') + '</span>',
				on: {
					dragover: drag,
					dragenter: drag,
					dragend: dragend,
					drop: drop
				}
			}).appendTo(field);
		};

		function drag(event) {
			stop(event);
			$(event.currentTarget).addClass('multiupload-drag');
		};

		function dragend(event) {
			$(event.currentTarget).removeClass('multiupload-drag');
		};

		function drop(event) {
			stop(event);

			var dragarea = $(event.currentTarget).removeClass('multiupload-drag'),
				field = dragarea.parents('.field-multiupload'),
				files = field.find('.multiupload-files'),
				list = field.find('ol');

			// Loop over files
			$.each(event.originalEvent.dataTransfer.files, function(index, file) {
				files.removeClass('empty');

				var item = $('<li />', {
					html: '<header><a>' + file.name + '</a><span class="multiupload-progress"></span><a class="destructor">' + Symphony.Language.get('In queue') + '</a></header>',
					class: 'instance queued'
				}).hide().appendTo(list).slideDown('fast');

				send(field, item, file);
			});
		};

		function stop(event) {
			event.stopPropagation();
			event.preventDefault();
		};

		function send(field, item, file) {
			var data = new FormData(),
				fieldId = field.attr('id').split('-')[1],
				entryId = Symphony.Context.get('env')['entry_id'];

			// Set data
			data.append('file', file);

			// Send data
			$.ajax({
				url: Symphony.Context.get('root') + '/extensions/multiuploadfield/lib/upload.php?field-id=' + fieldId + '&entry-id=' + entryId,
				data: data,
				cache: false,
				contentType: false,
				dataType: 'json',
				processData: false,
				type: 'POST',

				// Catch errors
				error: function(result){
					item.removeClass('queued').addClass('error');
					item.find('.destructor').text(Symphony.Language.get('Upload failed'));
				},

				// Add file
				success: function(result) {
					item.removeClass('queued');
					item.find('.multiupload-progress').css('width', '100%');
					item.find('.destructor').text(Symphony.Language.get('Remove file'));
					item.find('header a:first').attr('href', result.url);
					$('<input />', {
						type: 'hidden',
						val: result.url,
						name: field.attr('data-fieldname')
					}).appendTo(item);
				},

				// Upload progress
				xhrFields: {
					onprogress: function(progress) {
						item.find('.multiupload-progress').css('width', Math.floor(100 * progress.loaded / progress.total) + '%');
        			}
				}
			});
		};

	/*-------------------------------------------------------------------------
		API
	-------------------------------------------------------------------------*/

		return {
			'init': init
		};
	}();

	$(document).on('ready.multiupload', function() {
		Multiupload.init();
	});

})(window.jQuery);
