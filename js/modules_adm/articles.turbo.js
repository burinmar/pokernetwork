function attachEvents () {
	$('.show-edit-form').click(function(){
		var self = $(this);
		var url = self.attr('href').replace(/edit-turbo/, 'ajax-edit-form');
		
		var formContainer = self.parent().prev();
		
		$.ajax({
			type: 'GET',
			url: url,
			dataType: 'text',
			success: function(resp) {
				if (resp != '') {
					formContainer.replaceWith(resp);
					
					// toolbar fix
					if (attachEvents.inst == undefined) {
						attachEvents.inst = new Object();
					}
					if (attachEvents.inst[rtf0.config.instance] == undefined) {
						attachEvents.inst[rtf0.config.instance] = 1;
					} else {
						rtf0.init(rtf0.config);
					}
				}
			},
			error: function() {
				alert('Error getting data');
			}
		});
		return false;
	});
	$('#add-new-story').click(function(){
		var self = $(this);
		var linkForm = $('#story-item-form');
		
		if (linkForm.css('display') == 'none') {
			linkForm.show();
                        window.scrollBy(0,300); // go to the bottom of the page
		}
		return false;
	});
	$('form').livequery('submit', function() {
		self = $(this);
		if (!self.find('input[name="title"]').val()) {
			alert('Title cannot be empty');
			return false;
		}
		if (!self.find('[name="content"]').val()) {
			alert('Text field cannot be empty');
			return false;
		}
		return TRUE;
	});
}
$(document).ready(function () {
	attachEvents();
});
function confirmDelete(url) {
	if (confirm("Are you sure you want to delete this story?")) {
		location.href = url;
	} else {
		return false;
	}
}