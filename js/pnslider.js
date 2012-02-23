/**
 * jQuery slider plugin for pokernews
 * @name pnslider.js
 * @author Aleksandras Kotovas
 * @version 1.0
 * @date May 11, 2011
 * @category jQuery plugin
 **/
(function($){
	$.fn.pnSlider = function(settings) {
		settings = $.extend({
			rotateInterval: 7000,
			// do not change
			tabIndex: null,
			tabsCnt: null,
			intervalId: null
		}, settings);
		var obj = $(this);
		
		function init()
		{
			var	tabIndex = settings.tabIndex = 0,
				tabsCnt = settings.tabsCnt || obj.find('.pnSliderPager').length;
			initRotation();
			obj.find('.pnSliderPager').click(function() {
				settings.tabIndex = obj.find('.pnSliderPager').index($(this));
				clearSliderInterval();
				rotate();
				initRotation();
				return false;
			});
			obj.find('.next').click(function() {
				clearSliderInterval();
				rotateTabs(1);
				initRotation();
				return false;
			});
			obj.find('.prev').click(function() {
				clearSliderInterval();
				rotateTabs(0);
				initRotation();
				return false;
			});
			obj.find('.pnSliderTab').mouseover(function() {
				clearSliderInterval();
			}).mouseout(function() {
				initRotation();
			});
		}
		function clearSliderInterval()
		{
			clearInterval(settings.intervalId);
		}
		function initRotation()
		{
			var self = this;
			settings.intervalId = setInterval(function() { rotateTabs(1); }, settings.rotateInterval);
		}
		function rotate()
		{
			var	tabIndex = settings.tabIndex,
				currPager = obj.find('.pnSliderPager:eq('+tabIndex+')'),
				currTab = obj.find('.pnSliderTab:eq('+tabIndex+')');
			obj.find('.pnSliderPager').removeClass('current');
			obj.find('.pnSliderTab:visible').css('display', 'none');
			currPager.addClass('current');
			currTab.css('display', 'block');
			obj.find('.pnSliderTabN').html(tabIndex+1);
		}
		function rotateTabs(forward)
		{
			var tabsTotal = settings.tabsCnt,idx = settings.tabIndex;
			(forward) ? ++idx : --idx;
			if (idx > tabsTotal-1) idx = 0;
			if (idx < 0) idx = tabsTotal-1;
			settings.tabIndex = idx;
			rotate();
		}
		return init();
	};
})(jQuery);