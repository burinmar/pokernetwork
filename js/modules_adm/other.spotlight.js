$(document).ready(function(){
	$('#interval-add').click(function(e){
		e.preventDefault();
		var no = parseInt($('.date-range-div').size());
		++no;

		var picker1 = '<a href="void(0);" onclick="pickDate(\'active-from-' + no + '\');return false" title=""><img src="'+admImgDir+'_calendar.png" alt="Pick date" width="24" style="margin-bottom:-6px" /></a>&nbsp;';
		var picker2 = '<a href="void(0);" onclick="pickDate(\'active-to-' + no + '\');return false" title=""><img src="'+admImgDir+'_calendar.png" alt="Pick date" width="24" style="margin-bottom:-6px" /></a>&nbsp;';
		var html =
			'<div class="date-range-div">' +
			' from ' + picker1 + '<input name="active_from[]" id="active-from-' + no + '" value="" class="isDate" size="20" maxlength="20" />' +
			' till ' + picker2 + '<input name="active_to[]"   id="active-to-' + no + '"   value=""   class="isDate" size="20" maxlength="20" />' +
			' <a href="#" class="interval-remove" style="color:#DD0000;text-decoration:none;">x</a>' +
			'</div>';

		$('#explain-date').before(html);
	});

	$('.interval-remove').livequery('click', function(e) {
		e.preventDefault();
		$(this).parent().remove();
	});
});