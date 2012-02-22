$(document).ready(function () {
	var bannerId = $('#bannerId').val();
	var phpSessionId = $('#sessionId').val();
	//alert(phpSessionId);
	var settings_object = {
		upload_url : 'media.php', // flash session cookie fix
		flash_url : '/img/adm/swfupload.swf',
		post_params: {'files-upload':1,'banner_id':bannerId,'SI':phpSessionId},
		
		// File Upload Settings
		file_size_limit : '20 MB',
		file_types : '*.jpg;*.jpeg;*.gif;*.png;*.swf',//"*.*",
		file_types_description : "All Files",
		file_upload_limit : '60',
		file_queue_limit : '60',
		
		// Event Handler Settings (all my handlers are in the Handler.js file)
		file_dialog_start_handler : fileDialogStart,
		file_queued_handler : fileQueued,
		file_queue_error_handler : fileQueueError,
		file_dialog_complete_handler : fileDialogComplete,
		upload_start_handler : uploadStart,
		upload_progress_handler : uploadProgress,
		upload_error_handler : uploadErrorHandler,
		upload_success_handler : uploadSuccessHandler,
		upload_complete_handler : uploadCompleteHandler,
		
		// Button Settings
		button_image_url : "/img/adm/button_browse.png",
		button_placeholder_id : "spanButtonPlaceholder1",
		button_width: 61,
		button_height: 22,
		button_action : SWFUpload.BUTTON_ACTION.SELECT_FILES,
		
		swfupload_element_id : "flashUI2",	// Setting from graceful degradation plugin
		degraded_element_id : "degradedUI2",	// Setting from graceful degradation plugin
		
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
var uploadCompleteHandler = function () {
	var filesLeft = swfu.getStats().files_queued;
	if (!filesLeft) {
		document.location.href = document.location.href;
	}
};
var uploadSuccessHandler = function (file, server_data, receivedResponse) {
	if (server_data) {
		alert('Error uploading files. Server response: ' + server_data);
	}
	uploadSuccess(file, server_data, 'fsUploadProgress1');
	//alert("The file " + file.name + " has been delivered to the server. The server responded with " + server_data);
};
var uploadErrorHandler = function (file, server_data, receivedResponse) {
	if (server_data) {
		alert('Error uploading files. Server response: ' + server_data);
	}
	//uploadSuccess(file, server_data, 'fsUploadProgress1');
	//alert("The file " + file.name + " has been delivered to the server. The server responded with " + server_data);
};