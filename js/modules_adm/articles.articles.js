$(document).ready(function () {
	var checkLinks = true;
	$('#i_tags').focus(function(){
		$.ajax({
			type: 'POST',
			url: '/adm/articles-news/ajax-tags/',
			data: 'text=' + escape($('#i_title').val() + ' ' + $('#i_content').val()),
			success: function(resp) {
				$('#recommended-tags').html(resp).fadeIn(300);
			},
			error: function() {
				$('#recommended-tags').fadeOut(500);
			}
		});
	});
	if (typeof(articleId) != 'undefined' && articleId) {
		$.ajax({
			type: 'POST',
			url: '/adm/articles-news/ajax-check-broken-links/',
			data: 'id=' + articleId,
			success: function(resp) {
				if (resp) {
					$('#linksCheckResults1').html(resp).fadeIn(300);
				} else {
					// no broken links in existing article, don't check it again on submit
					checkLinks = false;
				}
			},
			error: function() {
				//$('#linksCheckResults1').html('Error occured');
			}
		});
		$('#frm').submit(function(e){
			if (checkLinks) {
				var content = $('#i_content').val();
				if (content) {
					$.ajax({
						type: 'POST',
						url: '/adm/articles-news/ajax-check-broken-links/',
						data: 'id=' + articleId + '&content=' + content
					});
					return true;
				}
			}
		});
	}
});
function addTag(tag) {
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