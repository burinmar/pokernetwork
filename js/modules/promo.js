$(function(){
$('#searchForm').parents('.search').show();

var searchInProcess = false;
$('#searchForm').submit(function (e) {
	e.preventDefault();
	var searchName = $('input[name=name]').val();
	if ('' === searchName || searchInProcess) {
		return false;
	}

	searchInProcess = true;
	$.ajax({
		url: $(this).attr('action'),
		data: {
			searchName: searchName
		}, complete: function() {
			searchInProcess = false;
		}, success: function(t) {
			var parent = $('#searchForm').parent();
			var i, r, s = '', l;
			r = parent.parent().children(); // td's
			for (i = 1; i < r.length; i++) {
				$(r[i]).remove();
			}
			r = parent.parent().prev().children(); // th's
			for (i = 1; i < r.length; i++) {
				$(r[i]).remove();
			}
			r = t.split('|');
			for (i in r) s += '<td>' + r[i] + '</td>';
			l = r.length;
			parent.after(s);
			r = $('table.results th');
			var l2 = Math.min(3,r.length);
			if (0 < l2) {
				s = '';
				for (i = 0; i < l2; i++) s += '<th>' + r[i].innerHTML + '</th>';
				$(parent.parent().prev().children()[0]).after(s);
				if (l < l2) $(parent.parent().children()[1]).attr('colspan', l2);
			} else {
				$(parent.parent().prev().children()[0]).attr('colspan', (l + 1));
			}
		}
	});
});
});