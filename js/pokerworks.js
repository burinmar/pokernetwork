function st() {
	$('.articleSocialLinks li a').each(function(){
	if ('gplus' === this.parentNode.className) return;
	$(this).click(function(){
	_gaq.push(['_trackSocial', this.parentNode.className, 'Share']);
	});});
}