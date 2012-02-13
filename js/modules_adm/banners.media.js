$(document).ready(function () {
	var displayall = false;
	
	$('.remove-item').click(function(e){
		var answer = confirm('Are you sure you want to remove this item?')
		if (!answer) e.preventDefault();
	});
	$('.settings').click(function(e){
		e.preventDefault();
		$(this).toggleClass('settings-active').next('.options').toggle();
	});
	$('#toggle-media-files').click(function(e){
		e.preventDefault();
		$('.media-file').each(function(){
			if ($(this).is(':hidden') && !displayall){
				$(this).toggle();
				$(this).next('.toggle-bn-media').toggleClass('settings-active');
			} else if ($(this).is(':visible') && displayall){
				$(this).toggle();
				$(this).next('.toggle-bn-media').toggleClass('settings-active');
			}
		});
		$(this).text($(this).text() == 'Show all banners' ? 'Hide all banners' : 'Show all banners');
		displayall = !displayall;
	});
	$('.toggle-bn-media').click(function(e){
		e.preventDefault();
		var self = $(this);
		self.prev('.media-file').toggle(function(){});
	});
});