/**
 * jQuery slideshow plugin for pokernews
 * @name pnslideshow.js
 * @author Aleksandras Kotovas
 * @version 1.0.3
 * @date February 17, 2011
 * @category jQuery plugin
 **/
(function($){
	var imgCache = [];
	$.fn.pnSlideshow = function(settings) {
		settings = $.extend({
			marginStep: 132,
			visibleTabs : 5,
			// do not change
			tabIndex: 0,
			tabsCnt: 0
		}, settings);
		var obj = $(this);
		
		function init()
		{
			settings.tabsCnt = obj.find('.pnSlideshowTabItem').length;
			obj.find('.status span:last').html(settings.tabsCnt);
			
			// load thumbnails
			$.each(obj.find('.pnSlideshowTabItem'), function(k, v) {
				var el = $(v), src = el.attr('href');
				if (typeof(src) == 'string') {
					el.attr('href', '#').html('<img alt="thumb" src="' + src + '">')
				}
			});

			// load first image
			loadImage(obj.find('.pnSlideshowImage:first'));
			
			// preload images
			$.each(obj.find('.pnSlideshowImage'), function(k, v) {
				var el = $(v), src = el.attr('href');
				if (typeof(src) == 'string') {
					var cImage = document.createElement('img');
					cImage.src = src;
					imgCache.push(cImage);
				}
			});

			obj.find('.pnSlideshowTabItem').click(function() {
				settings.tabIndex = obj.find('.pnSlideshowTabItem').index($(this));
				rotate();
				return false;
			});
			obj.find('.next').click(function() {
				rotateTabs(1);
				return false;
			});
			obj.find('.previous').click(function() {
				rotateTabs(0);
				return false;
			});
		}
		function loadImage(el)
		{
			var src = el.attr('href');
			if (typeof(src) == 'string' && src == '') { // IE
				src = $('img', el).attr('src');
			}
			if (typeof(src) == 'string') {
				el.removeAttr('href').html('<img alt="image" src="' + src + '">')
			}
		}
		function rotate()
		{
			var	tabIndex = settings.tabIndex, tabsCnt = settings.tabsCnt,marginStep = settings.marginStep,
				currPager = obj.find('.pnSlideshowTabItem:eq('+tabIndex+')'),
				currTab = obj.find('.pnSlideshowImage:eq('+tabIndex+')'),
				margin = parseInt(obj.find('.pnSlideshowTabItems').css('margin-left')),
				firstVisible = Math.abs(margin/marginStep),
				lastVisible = firstVisible + settings.visibleTabs,
				marginChange = 0,
				forward = null;
			
			obj.find('.pnSlideshowTabItem').removeClass('current');
			obj.find('.pnSlideshowImage:visible').css('display', 'none');
			obj.find('.pnSlideshowImageInfo:visible').css('display', 'none');
			obj.find('.status span:first').html(tabIndex+1);
			currPager.addClass('current');
			
			loadImage(currTab);
			currTab.fadeIn('fast').next('p').show();
			
			//show/hide nav buttons
			if (tabIndex == 0) obj.find('.previous').addClass('hide');
			else obj.find('.previous').removeClass('hide');
			if ((tabIndex+1) == tabsCnt) obj.find('.next').addClass('hide');
			else obj.find('.next').removeClass('hide');
			
			if (tabIndex == firstVisible && tabIndex != 0) forward = 0
			else if((tabIndex+1) == lastVisible && (tabIndex+1) != tabsCnt) forward = 1;
			
			if (typeof(forward) == 'number') {
				marginChange = (forward) ? '-='+marginStep : '+='+marginStep;
				obj.find('.pnSlideshowTabItems').animate({
					marginLeft: marginChange ? marginChange : margin
				}, 150);
			}
			return true;
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