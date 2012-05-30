var lrepSlider = function() {
	var entriesCnt,
		entriesPerView = 4,
		offset = 0, 
		entryWidth = 228,
		inTransition = false;
	function nextView() {
		if (inTransition) { return ; }
		inTransition = true;
		offset += entriesPerView;
		if (offset == entriesCnt) {
			offset = 0;
		}
		offset = Math.min(entriesCnt - entriesPerView, offset);
		$('#reportingSlider #slides ul').animate({
			left: -offset*entryWidth
		}, 500, function() {inTransition = false;});
	}
	function prevView() {
		if (inTransition) { return ; }
		inTransition = true;
		offset -= entriesPerView;
		if (offset == -entriesPerView) {
			offset = entriesCnt - entriesPerView;
		}
		offset = Math.max(0, offset);
		$('#reportingSlider #slides ul').animate({
			left: -offset*entryWidth
		}, 500, function() {inTransition = false;});
	}
	return {
		setup : function() {
			var $els = $('#reportingSlider #slides li');
			var entryWidth_ = $els.width();
			$els.css({ display: "block" }); // tmp IE8 fix
			if (entryWidth_ > 0) {
				entryWidth = entryWidth_;
			}
			entriesCnt = $els.length;
			$('#reportingSlider #slides .prev').click(function(e){
				e.preventDefault();
				prevView.call(this);

			});
			$('#reportingSlider #slides .next').click(function(e){
				e.preventDefault();
				nextView.call(this);
			});
		}
	};
}();
jQuery(document).ready(function(){
	lrepSlider.setup();
//	setTimeout('alert(1);lrepSlider.setup()', 20000);
});