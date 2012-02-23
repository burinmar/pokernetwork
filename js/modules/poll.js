jQuery(document).ready(function(){
	function pollInit(id) {
		var limit = null;
		if (typeof(id) != 'undefined') {
			limit = $('#' + id);
		}
		jQuery('form.poll input[type=submit]', limit).attr('disabled', 'disabled');
		jQuery('form.poll input', limit).change(function(){
			jQuery('input[type=submit]', $(this).parents('form')).removeAttr('disabled');
		});
		jQuery('form.poll input', limit).click(function(){
			jQuery('input[type=submit]', $(this).parents('form')).removeAttr('disabled');
		});
		jQuery('form.poll', limit).submit(function(e){
			e.preventDefault();
			if (jQuery('input:checked', this).length===0) {
				return ;
			}
			jQuery('.vote-submit',  this).hide();
			jQuery('.vote-loading', this).show();
			var d= ['vote=', jQuery('input:checked', this).val().substr(3),
				'&question=',jQuery('input[name=question]', this).val(),
				'&method=ajax',
				'&event=',jQuery('input[name=event]', this).val(),
				'&html_id=',this.id
			].join('');
			jQuery.ajax({
				type: "post",
				url: jQuery(this).attr('action'),
				data: d,
				dataType: 'json',
				success: function(resp) {
					var self = jQuery('#' + resp.html_id).parents('.data');
					self.html(resp.data);
					pollInit(resp.html_id);
					jQuery('.vote-loading', self).hide();
				},
				error: function() {
					alert('Error saving your vote!');
				}
			});
		});
		jQuery('form.poll .skipresults', limit).click(function(e){
			e.preventDefault();
			var self = jQuery(this).parents('form.poll');
			jQuery('.vote-submit',  self).hide();
			jQuery('.vote-loading', self).show();
			var d= ['question=',jQuery('input[name=question]', self).val(),
				'&method=ajax',
				'&html_id=',self[0].id
			].join('');
			jQuery.ajax({
				type: "get",
				url: jQuery(this).attr('href'),
				data: d,
				dataType: 'json',
				success: function(resp) {
					var self = jQuery('#' + resp.html_id).parents('.data');
					self.html(resp.data);
					pollInit(resp.html_id);
					jQuery('.vote-loading', self).hide();
				},
				error: function() {
					alert('Error loading vote results!');
				}
			});
		});
	}
	pollInit();
});