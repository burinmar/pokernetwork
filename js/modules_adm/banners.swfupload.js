$(document).ready(function () {
	var bannerId = $('#bannerId').val();
	var swfkey = $('#swfkey').val();
	var settings_object = {
		upload_url : 'adm.php', // flash session cookie fix
		flash_url : '/js/swfupload/swfupload.swf',
		post_params: {'files-upload':1, 'event':'banners.media#', 'banner_id':bannerId, 'swfkey': swfkey},
		
		// File Upload Settings
		file_size_limit : '20 MB',
		file_types : '*.jpg;*.jpeg;*.gif;*.png;*.swf;*.flv;*.mp4',//"*.*",
		file_types_description : "All Files",
		file_upload_limit : '60',
		file_queue_limit : '60',
		
		// The event handler functions are defined in handlers.js
		file_queued_handler : fileQueued,
		file_queue_error_handler : fileQueueError,
		file_dialog_complete_handler : fileDialogComplete,
		upload_start_handler : uploadStart,
		upload_progress_handler : uploadProgress,
		upload_error_handler : uploadError,
		upload_success_handler : uploadSuccess,
		upload_complete_handler : uploadCompleteHandler,
		queue_complete_handler : queueComplete,	// Queue plugin event
		// Button Settings
		button_image_url : "/img/adm/button_browse.png",
		button_placeholder_id : "spanButtonPlaceholder1",
		button_width: 61,
		button_height: 22,
		custom_settings : {
			progressTarget : "fsUploadProgress1",
			cancelButtonId : "btnUpload" // for enable/disable effect
		}
	};
	swfu = new SWFUpload(settings_object);
	$('#btnUpload').click(function(e){
		swfu.startUpload();
		e.preventDefault();
		//window.location.href('');
	});
});

var swfu;
var uploadCompleteHandler = function (file) {
	uploadComplete.call(this, file);
	var filesLeft = swfu.getStats().files_queued;
	if (!filesLeft) {
		setTimeout(function (){document.location.href = document.location.href;},1000)
	}

};
