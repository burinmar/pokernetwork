$(document).ready(function () {
	// manage posts list
	$('a.hide, a.unhide').click(function(){
		var self = $(this);
		var id = parseInt(self.attr('id').substring(11));
		var url = $('form[name=list-form]').attr('action');
		var currClass = self.attr('class');
		
		if(!id || !url) return false;
		$.ajax({
			type: 'POST',
			url: url+'ajax/toggle-hide.'+id+'.htm',
			data: 'ajax=1',
			success: function(resp) {
				self.removeClass(currClass).addClass(currClass=='hide'?'unhide':'hide');
				self.find('span:visible').hide().siblings().show();
			},
			error: function() {
				alert('Technical error. Unable to delete photo');
			}
		});
		return false;
	});
	$('form[name=list-form]').submit(function(){
		var list = $(this).find('input[name=it[]]:checked');
		if (list.size() > 0) {
			if (!confirm("Are you sure you want to delete?")) return false;
		} else {
			alert('You must select at least one entry.'); return false;
		}
        });
	$('.checkAll').click(function(){
		var checked = this.checked;
		$('form[name=list-form] :input[name=it[]]').each(function(){
			this.checked = checked;
		});
        });
	// post form
	$('a.addTag').click(function(){
		var tag = $(this).text();
		if (tag !== '') {
			var currentTags = new Array();
			var currentTagsLC = new Array();
			if ($('#i_tags').val() != '') {
				currentTags = $('#i_tags').val().split(',');
			}
			currentTagsLC = $('#i_tags').val().toLowerCase().split(',');
			if ($.inArray(tag.toLowerCase(), currentTagsLC) == -1) {
				currentTags.push(tag);
				$('#i_tags').val(currentTags.join(','));
			}
		}
		return false;
	});
	$('.previewLink').click(function(e){
		e.preventDefault();
		var form = $('#frm');
		var pid = form.find('input[name="id"]').val();
		pid = pid !== '' ? parseInt(pid) : 0;
		var data = {
			title : form.find('#i_title').val(),
			date : form.find('#i_created_on').val(),
			body : form.find('#i_body').val(),
			tags : form.find('#i_tags').val(),
			disableSmiles : form.find('#i_disable_smiles').attr('checked') ? 1 : 0,
			ajax : 1
		};
		if (typeof urlAjax !== 'undefined' && (data.title !== '' || data.body !== '')) {
			$.ajax({
				type: 'POST',
				url: urlAjax+'post-preview.'+ pid +'.htm',
				data: data,
				success: function(resp) {
					var win = new modalWindow(680,600);
					win.open(resp);
				},
				error: function() {
					//alert('Technical error.');
				}
			});
		}
	});
});