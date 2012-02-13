$(document).ready(function(){
	var checkall = false;
	$('#interval-add').click(function(e){
		e.preventDefault();
		var no = parseInt($('.date-range-div').size());
		++no;
		
		var picker1 = '<a href="void(0);" onclick="pickDate(\'active-from-' + no + '\');return false" title="" class="calendar"></a>&nbsp;';
		var picker2 = '<a href="void(0);" onclick="pickDate(\'active-to-' + no + '\');return false" title="" class="calendar"></a>&nbsp;';
		var html =
			'<div class="date-range-div">' +
			' from ' + picker1 + '<input type="text" name="active_from[]" id="active-from-' + no + '" value="" class="isDate" size="20" maxlength="20" />' +
			' till ' + picker2 + '<input type="text" name="active_to[]"   id="active-to-' + no + '"   value=""   class="isDate" size="20" maxlength="20" />' +
			' <a href="#" class="interval-remove" style="color:#DD0000;text-decoration:none;">x</a>' +
			'</div>';
			
		$('#explain-date').before(html);
	});
	$('.interval-remove').livequery('click', function(e) {
		e.preventDefault();
		$(this).parent().remove();
	});
	$('#add-banners').click(function(e){
		e.preventDefault();
		$('#frm-add-banners').toggle();
		window.scrollBy(0,400); // go to the bottom of the page
	});
	$('.settings').click(function(e){
		e.preventDefault();
		$(this).toggleClass('settings-active').next('.options').toggle();
	});
	$('.remove-item').click(function(e){
		var answer = confirm('Are you sure you want to remove this item?')
		if (!answer) e.preventDefault();
	});
	$('.check-all-sites').click(function(e){
		e.preventDefault();
		$(this).parent().parent().find('.item-site :input[type=checkbox]').each(function(){
			if ($(this).next().hasClass('site-inactive') === false) {
				this.checked = !checkall;
			}
		});
		checkall = !checkall;
	});
	
	// url target
	$('.addUrlTarget').click(function(e){
		e.preventDefault();
		var	self = $(this),
			pageId = self.attr('title'),
			textareaId = self.attr('href').substr(1),
			textarea = $('#' + textareaId),
			textareaVal = textarea.val(),
			textareaVal = (textareaVal != '') ? $.trim(textareaVal) + '\n' : '';
		if(pageId) textarea.val(textareaVal + pageId + '\n');
	});
	
	
});