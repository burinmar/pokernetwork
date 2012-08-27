var lrepSlider = function() {
	var entriesCnt,
		entriesPerView = 4,
		offset = 0, 
		entryWidth = 228,
		inTransition = false,
		$els, $next, $prev;
	function nextView() {
		if (inTransition || $(this).hasClass('disabled')) { return ; }
		inTransition = true;
		offset += entriesPerView;
		if (offset == entriesCnt) {
			offset = 0;
		}
		offset = Math.min(entriesCnt - entriesPerView, offset);
		renderCurrent();
		$('#reportingSlider #slides ul').animate({
			left: -offset*entryWidth
		}, 500, function() {
			renderForward();
			animateNextPrev();
			inTransition = false;
		});
	}
	function prevView() {
		if (inTransition || $(this).hasClass('disabled')) { return ; }
		inTransition = true;
		offset -= entriesPerView;
		if (offset == -entriesPerView) {
			offset = entriesCnt - entriesPerView;
		}
		offset = Math.max(0, offset);
		$('#reportingSlider #slides ul').animate({
			left: -offset*entryWidth
		}, 500, function() {
			animateNextPrev();
			inTransition = false;
		});
	}
	function loadBg(start, end) {
		var bg;
		start = Math.max(0, start);
		end = Math.min(Math.max(0, end), $els.length);
		for (var i = start; i < end; i++) {
			var $this = $('a', $els[i]);
			if (bg = $this.data('bg')) {
				$this.css('background-image', ['url(', bg, ')'].join(''))
				$this.data('bg', '');
			}
		}
	}
	function renderCurrent() {
		loadBg(offset, offset+entriesPerView);
	}
	function renderForward() {
		loadBg(offset+entriesPerView, offset+entriesPerView*2);
	}
	function animateNextPrev() {
		if (offset === 0)
			$prev.addClass('disabled');
		else
			$prev.removeClass('disabled');
		if (offset === entriesCnt - entriesPerView)
			$next.addClass('disabled');
		else
			$next.removeClass('disabled');
	}
	return {
		setup : function() {
			$els = $('#reportingSlider #slides li');
			$next = $('#reportingSlider #slides .next');
			$prev = $('#reportingSlider #slides .prev');
			$els.css({ display: "block" }); // tmp IE8 fix
			renderCurrent();
			animateNextPrev();
			$(window).load(function(){
				if (offset === 0)
					renderForward();
			});
			var entryWidth_ = $els.width();
			if (entryWidth_ > 0) {
				entryWidth = entryWidth_;
			}
			entriesCnt = $els.length;
			$prev.click(function(e){
				e.preventDefault();
				prevView.call(this);

			});
			$next.click(function(e){
				e.preventDefault();
				nextView.call(this);
			});
		}
	};
}();
jQuery(document).ready(function(){
	lrepSlider.setup();
});