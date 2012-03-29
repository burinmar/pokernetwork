var alist = null; // override
$(document).ready(function(){
	function toggleDelete() {}
	$('#check-uncheck-entries').click(function(event){
		event.preventDefault();
		var allChecked = true;
		$('input.row-mark').each(function(){
			if (!this.checked) {
				allChecked = false;
			}
		});
		$('input.row-mark').each(function(){
			this.checked = !allChecked;
			if (this.checked) {
				$(this).parents().filter('.markable').addClass('marked');
			} else {
				$(this).parents().filter('.markable').removeClass('marked');
			}
		});
		toggleDelete();
	});
	$('input.row-mark').click(function(){
		toggleDelete();
		if (this.checked) {
			$(this).parents().filter('.markable').addClass('marked');
		} else {
			$(this).parents().filter('.markable').removeClass('marked');
		}
	});
	$('div#delete-level a, input#delete-level').click(function(event){
		event.preventDefault();
		if (!confirm('Are you sure to delete all the marked items?')) {
			return ;
		}
		var deleteIds = [];
		$('input.row-mark').each(function(){
			if (this.checked) {
				var id = this.id.replace(new RegExp('^.*:', 'g'), '');
				deleteIds[deleteIds.length] = id;
			}
		});
		$('#js-action-form-event').val(js_action_delete);
		$('#js-action-form-ids').val(deleteIds.join(','));
		$('#js-action-form').submit();
	});
});