/* pnVideo plugin */
window.onYouTubePlayerReady = function (playerId) {
	$('#' + playerId).pnVideo('videoPlayerReady');
};
(function($) {
var autoId = 1;
var contexts = {};

function attach(args) {
	var $video = $('.pnVideo', this);
	var $preroll = $('.pnPreroll', this);
	var $videoObjectContainer = $('.pnVideoObjectContainer', this);
	args = $.extend({
		instanceId: ['pnvideo-instance', autoId].join('-'),
		videoContainerId: ['pnvideo', autoId].join('-'),
		prerollContainerId: ['pnpreroll', autoId].join('-'),
		videoUri: $($video).data('video-uri'),
		videoLength: $($video).data('video-length'),
		videoDuration: $($video).data('duration'),
		videoIsLocal: false
	}, args);
	autoId++;
	if ('undefined' == typeof args.videoUri)
		throw "pnVideo: no videoUri";
	if (args.videoUri.length != 11) {
		args.videoIsLocal = true;
		$preroll.attr('href', args.videoUri);
	}
	$(this).attr('id',  args.instanceId);

	$video.attr('id',   args.videoContainerId);
	$preroll.attr('id', args.prerollContainerId);

	$('.toolbar', this).append(toolbarTemplate);
	var $videoToolbar = $('#toolabarTemplate').removeAttr('id');
	$('.toolbar', this).append(toolbarTemplate);
	var $prerollToolbar = $('#toolabarTemplate').removeAttr('id');

	contexts[args.instanceId] = {
		self: $(this),
		instanceId: args.instanceId,
		videoContainer: $video,
		videoToolbar: $videoToolbar,
		prerollContainer: $preroll,
		prerollToolbar: $prerollToolbar,
		videoUri: args.videoUri,
		videoLength: args.videoLength,
		videoDuration: args.videoDuration,
		videoAutostart: false,
		videoIsLocal: args.videoIsLocal
	};
	var context = contexts[args.instanceId];
	setupAndShowPreroll.call(this, context);
	setupPlaylist.call(this, context);

	return this;
}

function switchToPreroll(context) {
	context.prerollContainer.show();
	context.prerollToolbar.show();
	context.videoContainer.hide();
	context.videoToolbar.hide();
}

function switchToVideo(context) {
	context.prerollContainer.hide();
	context.prerollToolbar.hide();
	context.videoContainer.show();
	context.videoToolbar.show();
}

function timeFormat(seconds) {
	seconds = Number(seconds);

	var h = Math.floor(seconds / 3600),
		m = Math.floor(seconds % 3600 / 60),
		s = Math.floor(seconds % 3600 % 60);

	return ((h > 0 ? h + ':' : '') + (m > 0 ? (h > 0 && m < 10 ? '0' : '') + m + ':' : '0:') + (s < 10 ? '0' : '') + s);
}

function setupAndShowPreroll(context) {
	$controls = context.prerollToolbar;
	$controls.addClass('minimal').addClass('unmuted').addClass('paused').removeClass('playing').removeClass('hidden');
	$('.timeProgress', $controls).slider('destroy').slider({
		disabled: true
	});
	$('.time .total', $controls).html(context.videoLength
		? timeFormat(context.videoLength)
		: '- --');
	switchToPreroll(context);

	var prerollContainerId = context.prerollContainer.attr('id');
	if (typeof prerollContainerId == 'undefined') {
		return finishedPreroll(context, false);
	}

	// http://www.longtailvideo.com/support/open-video-ads/13048/ova-configuration-guide
	var prerollArgs = {
		position: "pre-roll",
		loadOnDemand: true,
		refreshOnReplay: true,
		notice: {
			message: "This preroll runs for _countdown_ seconds"
		}
	};
	var prerollAdditionalData = context.prerollContainer.data();
	for (var q in prerollAdditionalData) {
		prerollArgs[q] = prerollAdditionalData[q].toString();
	}

	flowplayer(prerollContainerId, {
			src: "/img/flowplayer-3.2.11.swf",
			wmode: "transparent"
		}, {
		key: '#$961f1dc9a905899b595',
		clip: {
			autoPlay: false,
			scaling: 'fit',
			onFinish: function(clip) {
				if (clip.ovaAd) {
					flowplayer(context.prerollContainer.attr('id')).getClip(1).update({
						autoPlay: context.videoIsLocal
					});
					finishedPreroll(context, true);
					// no localVideoPlayerStateChange(0) for ad, as long as just the play button is triggered
				} else {
					localVideoPlayerStateChange(context, 0);
				}
			},
			onBeforeBegin: function(clip) {
				if (clip.originalUrl == "javascript:;") {
					// finishedPreroll(context, realWatchedPreroll);
					// intercept stub video, if somehow we got here
					return false;
				}
			},
			onBegin: function() { localVideoPlayerStateChange(context, 1); },
			onResume: function() { localVideoPlayerStateChange(context, 1); },
			onPause: function() { localVideoPlayerStateChange(context, 0); },
			onStop: function() { localVideoPlayerStateChange(context, 0); }
		},
		onError: function(errorCode, errorMessage) {
			finishedPreroll(context, false);
		},
		onLoad: function() {
			context.prerollContainer = $('#' + prerollContainerId);
			prerollPlayerReady(context);
			var clip = flowplayer(prerollContainerId).getClip(0);
			if (!(clip.ovaAd || clip.originalUrl.match(/video_prerolls/))) {
				finishedPreroll(context, false);
			}
		},
		onBeforeFullscreen: function() {
			if (flowplayer(context.prerollContainer.attr('id')).getClip().ovaAd) {
				return false;
			}
		},
		canvas: {
			backgroundColor: '#000000',
			backgroundGradient: "none"
		},
		plugins: {
			controls: null,
			ova: {
				url: "/img/flowplayer-ova.swf",
				delayAdRequestUntilPlay: false, // !important
				ads: {
					linearScaling: "fit",
					clickSign: {
						verticalAlign: "top", horizontalAlign: "right",
						width: 10, height: 10, opacity: 0
					},
					servers: [{
						type: "OpenX",
						apiAddress: "http://ads.ibusmedia.com/www/delivery/fc.php"
					}],
					schedule: [
						prerollArgs
					]
				},
				debug: { "levels": "none" }
			}
		},
		play: {
			// url: '/img/muck-head.png'
		}
	});
}

function prerollPlayerReady(context) {
	var player = flowplayer(context.prerollContainer.attr('id'));
	var $controls = context.prerollToolbar;
	$controls.addClass('unmuted').removeClass('muted');
	player.unmute();

	if (context.prerollInitializedOnce) {
		return ;
	}
	context.prerollInitializedOnce = true;

	// play/pause - gui update on event
	$('.play', $controls).click(function(){
		player.play();
	});
	$('.pause', $controls).click(function(){
		player.stop();
	});

	// mute - there are no mute events, update in-place
	$('.mute', $controls).click(function(){
		player.mute();
		$controls.addClass('muted').removeClass('unmuted');
	});
	$('.unmute', $controls).click(function(){
		player.unmute();
		$controls.addClass('unmuted').removeClass('muted');
	});
}

function finishedPreroll(context, autostartVideo) {
	if (context.videoIsLocal) {
		// play everything in flowplayer
		localVideoPlayerReady(context);
		return ;
	}
	// switchToVideo not entirely sufficient
	context.prerollContainer.hide();
	if (context.videoInitializedOnce === true) {
		// should be coming from playlist.
		return ;
	}
	var videoContainerId = context.videoContainer.attr('id');
	// will wind up to videoPlayerReady()
	swfobject.embedSWF(
		'http://www.youtube.com/v/' + context.videoUri + '?enablejsapi=1&version=3&playerapiid=' + context.instanceId + '&modestbranding=1&rel=0&controls=0&showinfo=0',
		// 'http://www.youtube.com/apiplayer?version=3&enablejsapi=1&playerapiid=' + context.instanceId,
		videoContainerId, '100%', '100%', "10", null, null, {
			allowScriptAccess: 'always',
			allowFullScreen: 'true',
			wmode: "transparent"
		}, {
			id: videoContainerId
		}, function() {
			context.videoContainer = $('#' + videoContainerId);
			context.videoAutostart = autostartVideo;
		}
	);
}

function localVideoPlayerReady(context) {
	var player = flowplayer(context.prerollContainer.attr('id'));
	var $controls = context.prerollToolbar;
	$controls.addClass('unmuted').removeClass('muted').removeClass('minimal');
	player.unmute();
	player.setVolume(100);

	if (context.localVideoInitializedOnce) {
		return ;
	}
	context.localVideoInitializedOnce = true;

	// play already done
	// un/mute already done
	$('.pause', $controls).unbind('click');
	$('.pause', $controls).click(function(){
		player.pause();
	});

	// sound - there are no sound events, update in-place
	var $soundLevel = $('.sound', $controls);
	var soundLevelForAnim = 100;
	function soundLevelMark(level) {
		$('.pcnt', $soundLevel).each(function(){
			var buttonLevel = parseInt($(this).data('pcnt'), 10);
			if (buttonLevel <= level) {
				$(this).removeClass('inactive');
			} else {
				$(this).addClass('inactive');
			}
		});
	}
	$('.pcnt', $soundLevel).click(function(){
		var level = parseInt($(this).data('pcnt'), 10);
		var wasMuted = player.getStatus().muted;
		player.setVolume(level);
		if (wasMuted)
			player.mute();
		soundLevelForAnim = level;
		soundLevelMark.call(this, level);
	}).mouseover(function(){
		var level = parseInt($(this).data('pcnt'), 10);
		soundLevelMark.call(this, level);
	}).mouseout(function(){
		var level = soundLevelForAnim;
		soundLevelMark.call(this, level);
	});

	$('.timeProgress', $controls).slider('destroy').slider({
		disabled: !context.videoLength,
		start: function() {
			context.userSeekingTime = true;
			player.pause();
		},
		stop: function() {
			var seekToPosition = Math.round(context.videoLength * $(this).slider('value') / 100);
			context.userSeekingTime = false;
			player.seek(seekToPosition);
			player.play();
		},
		slide: function(event, ui) {
			$('.timeProgress .progress', $controls).css({
				width: ui.value + '%'
			});
		},
		change: function(event, ui) {
			$('.timeProgress .progress', $controls).css({
				width: ui.value + '%'
			});
		}
	});

	context.defaultHeight = $('.pnHeightControl', context.self).height();

	$('.fullscreen', $controls).click(function(){
		if (context.self.hasClass('fullScreenBox')) {
			$('.pnHeightControl', context.self).css({
				height: [context.defaultHeight, 'px'].join('')
			});
			context.self.removeClass('fullScreenBox');
		} else {
			context.self.addClass('fullScreenBox');
			$('.pnHeightControl', context.self).css({
				height: [context.self.height() - $controls.height(), 'px'].join('')
			});
		}
	});
	if ($.browser.mozilla && parseInt($.browser.version) < 13) {
		$('.fullscreen', $controls).unbind('click').addClass('disabled'); // plugin reframe bug
	}

	$(window).resize(function(){
		var height = context.self.hasClass('fullScreenBox')
			? context.self.height() - $controls.height()
			: context.defaultHeight;
		$('.pnHeightControl', context.self).css({
			height: [height, 'px'].join('')
		});
	});
}

function localVideoPlayerTimeChange(context, player) {
	var $controls = context.prerollToolbar;
	var currentTime = player.getTime();
	var duration = context.videoLength;

	$controls.addClass('withTime');
	$('.time .elapsed', $controls).html(
		timeFormat(currentTime)
	);
	$('.time .total', $controls).html(
		timeFormat(duration)
	);
	if (!context.userSeekingTime) {
		$('.timeProgress', $controls).slider('value', parseInt(currentTime / duration * 100, 10));
	}
}

function localVideoPlayerStateChange(context, newState) {
	switch (newState) {
	case 1:
		context.prerollToolbar.addClass('playing').removeClass('paused');
		clearInterval(context.timer);
		var player = flowplayer(context.prerollContainer.attr('id'));
		context.timer = setInterval((function(self, context, player){
			return function () {
				(!context.videoLength || player.getClip().ovaAd)
					? clearInterval(context.timer)
					: localVideoPlayerTimeChange.call(self, context, player);
			};
		})(this, context, player), 250);
		break;
	case 0:
		clearInterval(context.timer);
		context.prerollToolbar.addClass('paused').removeClass('playing');
		break;
	}
}

function videoPlayerReady() {
	var instanceId = this.attr('id');
	var context = contexts[instanceId];
	var player = context.videoContainer[0];
	var $controls = context.videoToolbar;
	// setup additional context
	context.userSeekingTime = false;

	// bind videoPlayerStateChange
	player.addEventListener('onStateChange', '(function(state) { return jQuery("#' + context.instanceId + '").pnVideo("videoPlayerStateChange", state); })' );
	// player.addEventListener('onPlaybackQualityChange', '(function(q) {window.console && console.log("quality:" + q)} )');

	if (context.videoInitializedOnce === true) {
		// stoopid, stoopid, stoopid
		$('.timeProgress', $controls).slider('value', 0);
		videoPlayerStateChange.call(this, -1);
		return ;
	}

	context.videoInitializedOnce = true;
	context.defaultHeight = $('.pnHeightControl', context.self).height();

	// load video
	player.loadVideoById(context.videoUri);
	if (!context.videoAutostart) {
		player.stopVideo();
		player.clearVideo();
	}

	// play/pause - gui update on event
	$('.play', $controls).click(function(){
		player.playVideo();
	});
	$('.pause', $controls).click(function(){
		player.pauseVideo();
	});
	$controls.addClass('paused');

	// mute - there are no mute events, update in-place
	$('.mute', $controls).click(function(){
		player.mute();
		$controls.addClass('muted').removeClass('unmuted');
	});
	$('.unmute', $controls).click(function(){
		player.unMute();
		$controls.addClass('unmuted').removeClass('muted');
	});
	$controls.addClass('unmuted');

	// sound - there are no sound events, update in-place
	var $soundLevel = $('.sound', $controls);
	var soundLevelForAnim = 100;
	function soundLevelMark(level) {
		$('.pcnt', $soundLevel).each(function(){
			var buttonLevel = parseInt($(this).data('pcnt'), 10);
			if (buttonLevel <= level) {
				$(this).removeClass('inactive');
			} else {
				$(this).addClass('inactive');
			}
		});
	}
	$('.pcnt', $soundLevel).click(function(){
		var level = parseInt($(this).data('pcnt'), 10);
		var wasMuted = player.isMuted();
		player.setVolume(level);
		if (wasMuted)
			player.mute();
		soundLevelForAnim = level;
		soundLevelMark.call(this, level);
	}).mouseover(function(){
		var level = parseInt($(this).data('pcnt'), 10);
		soundLevelMark.call(this, level);
	}).mouseout(function(){
		var level = soundLevelForAnim;
		soundLevelMark.call(this, level);
	});

	$('.timeProgress', $controls).slider('destroy').slider({
		// disabled: true,
		start: function() {
			context.userSeekingTime = true;
			player.pauseVideo(); // maybe
		},
		stop: function() {
			var seekToPosition = Math.round(player.getDuration() * $(this).slider('value') / 100);
			context.userSeekingTime = false;
			player.seekTo(seekToPosition, true);
			player.playVideo(); // maybe
		},
		slide: function(event, ui) {
			$('.timeProgress .progress', $controls).css({
				width: ui.value + '%'
			});
		},
		change: function(event, ui) {
			$('.timeProgress .progress', $controls).css({
				width: ui.value + '%'
			});
		}
	});

	$('.fullscreen', $controls).click(function(){
		if (context.self.hasClass('fullScreenBox')) {
			$('.pnHeightControl', context.self).css({
				height: [context.defaultHeight, 'px'].join('')
			});
			context.self.removeClass('fullScreenBox');
		} else {
			context.self.addClass('fullScreenBox');
			$('.pnHeightControl', context.self).css({
				height: [context.self.height() - $controls.height(), 'px'].join('')
			});
		}
	});

	if ($.browser.mozilla && parseInt($.browser.version) < 13) {
		$('.fullscreen', $controls).unbind('click').addClass('disabled'); // plugin reframe bug
	}

	$(window).resize(function(){
		var height = context.self.hasClass('fullScreenBox')
			? context.self.height() - $controls.height()
			: context.defaultHeight;
		$('.pnHeightControl', context.self).css({
			height: [height, 'px'].join('')
		});
	});

	$controls.removeClass('minimal');
	switchToVideo(context);

	return this;
}

function videoPlayerTimeChange(context, player) {
	var $controls = context.videoToolbar;
	var currentTime = player.getCurrentTime();
	var duration = player.getDuration();

	$controls.addClass('withTime');
	$('.time .elapsed', $controls).html(
		timeFormat(currentTime)
	);
	$('.time .total', $controls).html(
		timeFormat(duration)
	);
	if (!context.userSeekingTime) {
		$('.timeProgress', $controls).slider('value', parseInt(currentTime / duration * 100, 10));
	}

	$('.timeProgress .buffer', $controls).css({
		width: [Math.ceil((player.getVideoBytesLoaded() + player.getVideoStartBytes()) / player.getVideoBytesTotal() * 100), '%'].join('')
	});
}

function videoPlayerStateChange(newState) {
	var instanceId = this.attr('id');
	var context = contexts[instanceId];
	var player = context.videoContainer[0];
	var $controls = context.videoToolbar;
	switch(newState) {
		case -1: // unstarted
		case 0: // ended
		case 2: // paused
			if (newState === -1) {
				$('.timeProgress', $controls).slider('value', 0);
				$('.timeProgress .buffer', $controls).css({width: '0%'});
				$('.time .elapsed', $controls).html('- --');
				$('.time .total', $controls).html(context.videoLength
					? timeFormat(context.videoLength)
					: '- --');
			} else if (newState === 0)
				$('.timeProgress', $controls).slider('value', 100);
			$controls.addClass('paused').removeClass('playing');
			clearInterval(context.timer);
			break;
		case 1: // playing
			$controls.addClass('playing').removeClass('paused');
			clearInterval(context.timer); // rare but possible condition
			context.timer = setInterval((function(self, context, player){
				return function () {
					(!player.getCurrentTime)
						? clearInterval(context.timer)
						: videoPlayerTimeChange.call(self, context, player);
				};
			})(this, context, player), 250);
			break;
		case 3: // buffering
			player.setPlaybackQuality('large');
		case 5: // video cued
			break;
	}
}

function setupPlaylist(context) {
	function markItem(videoUri) {
		$('.playlist a[data-video-uri]', context.self).each(function(){
			if (videoUri == $(this).data('video-uri'))
				$(this).parent().addClass('active');
			else
				$(this).parent().removeClass('active');
		});

	}
	markItem(context.videoUri);
	$('.playlist a[data-video-uri]', context.self).click(function(e){
		e.preventDefault();
		var videoUri = $(this).data('video-uri');
		var videoLength = $(this).data('video-length');
		var oldVideoUri = context.videoUri;

		markItem(videoUri);
		if (context.videoIsLocal) // first video is local -> no tube -> no playlist
			return ;
		context.videoUri = videoUri;
		context.videoLength = videoLength;

		if (!context.videoInitializedOnce) {
			// try loop through preroll
			var $controls = context.prerollToolbar;
			$('.time .total', $controls).html(videoLength
				? timeFormat(videoLength)
				: '- --');
			if (context.prerollInitializedOnce) {
				var player = flowplayer(context.prerollContainer.attr('id'));
				if (player.isPlaying())
					return;
				player.play();
			}
			return ;
		}

		var player = context.videoContainer[0];
		if (videoUri == oldVideoUri) {
			// if not playing -- play
			if (player.getPlayerState() != 1) {
				player.playVideo();
			}
			return ;
		}
		
		player.stopVideo();
		player.loadVideoById(context.videoUri);
		return ;

	});
}

$.fn.pnVideo = function(args)
{
	var methods = {
		videoPlayerReady: videoPlayerReady, // onYouTubePlayerReady
		videoPlayerStateChange: videoPlayerStateChange // player.onStateChange
	};
	if (methods[args]) {
		return methods[args].apply(this, Array.prototype.slice.call(arguments, 1));
	} else if ( typeof args === 'object' || ! args ) {
		this.each(function(){
			attach.call($(this), args);
			$('.playlist', this).pnVideoSlider();
		});
	}

	return this;
};

var toolbarTemplate = '' +
'<div class="controls hidden" id="toolabarTemplate">' +
'	<div class="colPlay">' +
'		<span class="play"></span>' +
'		<span class="pause"></span>' +
'	</div>' +
'	<div class="colTime">' +
'		<span class="time">' +
'			<span class="elapsed"> - --</span>/<span class="total"> - --</span>' +
'		</span>' +
'	</div>' +
'	<div class="colProgress">' +
'		<div class="timeProgressContainer">' +
'			<div class="timeProgress">' +
'				<div class="buffer" style="width: 0%;"></div>' +
'				<div class="progress" style="width: 0%;"></div>' +
'			</div>' +
'		</div>' +
'	</div>' +
'	<div class="colSound">' +
'		<span class="mute"></span>' +
'		<span class="unmute hidden"></span>' +
'		<span class="sound">' +
'			<span class="pcnt" data-pcnt="20"></span>' +
'			<span class="pcnt" data-pcnt="40"></span>' +
'			<span class="pcnt" data-pcnt="60"></span>' +
'			<span class="pcnt" data-pcnt="80"></span>' +
'			<span class="pcnt" data-pcnt="100"></span>' +
'		</span>' +
'	</div>' +
'	<div class="colFullscreen">' +
'		<span class="fullscreen"></span>' +
'	</div>' +
'</div>';
})(jQuery);
/* pnVideo plugin end */


(function($) {
$.fn.pnVideoSlider = function(args)
{
	this.each(function(){
		var $self = this,
			entriesCnt = $('li', this).length,
			entryWidth,
			entriesPerView,
			offset = 0,
			inTransition = false;
		entryWidth = $('li', this).width();
		entriesPerView = Math.floor($(this).width() / entryWidth - 0.4);
	
		$('.prev', this).click(function(e){
			e.preventDefault();
			prevView.call($self);

		});
		$('.next', this).click(function(e){
			e.preventDefault();
			nextView.call($self);
		});
		function lockAnimation() {
			if (inTransition)
				return false;
			inTransition = true;
			return true;
		}
		function animate() {
			$('ul', this).animate({
				left: -offset*entryWidth
			}, 500, function() {inTransition = false;});
		}
		function nextView() {
			if (!lockAnimation())
				return;
			offset += entriesPerView;
			if (offset == entriesCnt)
				offset = 0;
			offset = Math.min(entriesCnt - entriesPerView, offset);
			animate.call(this);
		}
		function prevView() {
			if (!lockAnimation())
				return;
			offset -= entriesPerView;
			if (offset == -entriesPerView)
				offset = entriesCnt - entriesPerView;
			offset = Math.max(0, offset);
			animate.call(this);
		}
	});
	return this;
};
})(jQuery);

/*! jQuery UI - v1.8.20 - 2012-04-30
* https://github.com/jquery/jquery-ui
* Includes: jquery.ui.core.js
* Copyright (c) 2012 AUTHORS.txt; Licensed MIT, GPL */
(function(a,b){function c(b,c){var e=b.nodeName.toLowerCase();if("area"===e){var f=b.parentNode,g=f.name,h;return!b.href||!g||f.nodeName.toLowerCase()!=="map"?!1:(h=a("img[usemap=#"+g+"]")[0],!!h&&d(h))}return(/input|select|textarea|button|object/.test(e)?!b.disabled:"a"==e?b.href||c:c)&&d(b)}function d(b){return!a(b).parents().andSelf().filter(function(){return a.curCSS(this,"visibility")==="hidden"||a.expr.filters.hidden(this)}).length}a.ui=a.ui||{};if(a.ui.version)return;a.extend(a.ui,{version:"1.8.20",keyCode:{ALT:18,BACKSPACE:8,CAPS_LOCK:20,COMMA:188,COMMAND:91,COMMAND_LEFT:91,COMMAND_RIGHT:93,CONTROL:17,DELETE:46,DOWN:40,END:35,ENTER:13,ESCAPE:27,HOME:36,INSERT:45,LEFT:37,MENU:93,NUMPAD_ADD:107,NUMPAD_DECIMAL:110,NUMPAD_DIVIDE:111,NUMPAD_ENTER:108,NUMPAD_MULTIPLY:106,NUMPAD_SUBTRACT:109,PAGE_DOWN:34,PAGE_UP:33,PERIOD:190,RIGHT:39,SHIFT:16,SPACE:32,TAB:9,UP:38,WINDOWS:91}}),a.fn.extend({propAttr:a.fn.prop||a.fn.attr,_focus:a.fn.focus,focus:function(b,c){return typeof b=="number"?this.each(function(){var d=this;setTimeout(function(){a(d).focus(),c&&c.call(d)},b)}):this._focus.apply(this,arguments)},scrollParent:function(){var b;return a.browser.msie&&/(static|relative)/.test(this.css("position"))||/absolute/.test(this.css("position"))?b=this.parents().filter(function(){return/(relative|absolute|fixed)/.test(a.curCSS(this,"position",1))&&/(auto|scroll)/.test(a.curCSS(this,"overflow",1)+a.curCSS(this,"overflow-y",1)+a.curCSS(this,"overflow-x",1))}).eq(0):b=this.parents().filter(function(){return/(auto|scroll)/.test(a.curCSS(this,"overflow",1)+a.curCSS(this,"overflow-y",1)+a.curCSS(this,"overflow-x",1))}).eq(0),/fixed/.test(this.css("position"))||!b.length?a(document):b},zIndex:function(c){if(c!==b)return this.css("zIndex",c);if(this.length){var d=a(this[0]),e,f;while(d.length&&d[0]!==document){e=d.css("position");if(e==="absolute"||e==="relative"||e==="fixed"){f=parseInt(d.css("zIndex"),10);if(!isNaN(f)&&f!==0)return f}d=d.parent()}}return 0},disableSelection:function(){return this.bind((a.support.selectstart?"selectstart":"mousedown")+".ui-disableSelection",function(a){a.preventDefault()})},enableSelection:function(){return this.unbind(".ui-disableSelection")}}),a.each(["Width","Height"],function(c,d){function h(b,c,d,f){return a.each(e,function(){c-=parseFloat(a.curCSS(b,"padding"+this,!0))||0,d&&(c-=parseFloat(a.curCSS(b,"border"+this+"Width",!0))||0),f&&(c-=parseFloat(a.curCSS(b,"margin"+this,!0))||0)}),c}var e=d==="Width"?["Left","Right"]:["Top","Bottom"],f=d.toLowerCase(),g={innerWidth:a.fn.innerWidth,innerHeight:a.fn.innerHeight,outerWidth:a.fn.outerWidth,outerHeight:a.fn.outerHeight};a.fn["inner"+d]=function(c){return c===b?g["inner"+d].call(this):this.each(function(){a(this).css(f,h(this,c)+"px")})},a.fn["outer"+d]=function(b,c){return typeof b!="number"?g["outer"+d].call(this,b):this.each(function(){a(this).css(f,h(this,b,!0,c)+"px")})}}),a.extend(a.expr[":"],{data:function(b,c,d){return!!a.data(b,d[3])},focusable:function(b){return c(b,!isNaN(a.attr(b,"tabindex")))},tabbable:function(b){var d=a.attr(b,"tabindex"),e=isNaN(d);return(e||d>=0)&&c(b,!e)}}),a(function(){var b=document.body,c=b.appendChild(c=document.createElement("div"));c.offsetHeight,a.extend(c.style,{minHeight:"100px",height:"auto",padding:0,borderWidth:0}),a.support.minHeight=c.offsetHeight===100,a.support.selectstart="onselectstart"in c,b.removeChild(c).style.display="none"}),a.extend(a.ui,{plugin:{add:function(b,c,d){var e=a.ui[b].prototype;for(var f in d)e.plugins[f]=e.plugins[f]||[],e.plugins[f].push([c,d[f]])},call:function(a,b,c){var d=a.plugins[b];if(!d||!a.element[0].parentNode)return;for(var e=0;e<d.length;e++)a.options[d[e][0]]&&d[e][1].apply(a.element,c)}},contains:function(a,b){return document.compareDocumentPosition?a.compareDocumentPosition(b)&16:a!==b&&a.contains(b)},hasScroll:function(b,c){if(a(b).css("overflow")==="hidden")return!1;var d=c&&c==="left"?"scrollLeft":"scrollTop",e=!1;return b[d]>0?!0:(b[d]=1,e=b[d]>0,b[d]=0,e)},isOverAxis:function(a,b,c){return a>b&&a<b+c},isOver:function(b,c,d,e,f,g){return a.ui.isOverAxis(b,d,f)&&a.ui.isOverAxis(c,e,g)}})})(jQuery);;/*! jQuery UI - v1.8.20 - 2012-04-30
* Includes: jquery.ui.widget.js*/
(function(a,b){if(a.cleanData){var c=a.cleanData;a.cleanData=function(b){for(var d=0,e;(e=b[d])!=null;d++)try{a(e).triggerHandler("remove")}catch(f){}c(b)}}else{var d=a.fn.remove;a.fn.remove=function(b,c){return this.each(function(){return c||(!b||a.filter(b,[this]).length)&&a("*",this).add([this]).each(function(){try{a(this).triggerHandler("remove")}catch(b){}}),d.call(a(this),b,c)})}}a.widget=function(b,c,d){var e=b.split(".")[0],f;b=b.split(".")[1],f=e+"-"+b,d||(d=c,c=a.Widget),a.expr[":"][f]=function(c){return!!a.data(c,b)},a[e]=a[e]||{},a[e][b]=function(a,b){arguments.length&&this._createWidget(a,b)};var g=new c;g.options=a.extend(!0,{},g.options),a[e][b].prototype=a.extend(!0,g,{namespace:e,widgetName:b,widgetEventPrefix:a[e][b].prototype.widgetEventPrefix||b,widgetBaseClass:f},d),a.widget.bridge(b,a[e][b])},a.widget.bridge=function(c,d){a.fn[c]=function(e){var f=typeof e=="string",g=Array.prototype.slice.call(arguments,1),h=this;return e=!f&&g.length?a.extend.apply(null,[!0,e].concat(g)):e,f&&e.charAt(0)==="_"?h:(f?this.each(function(){var d=a.data(this,c),f=d&&a.isFunction(d[e])?d[e].apply(d,g):d;if(f!==d&&f!==b)return h=f,!1}):this.each(function(){var b=a.data(this,c);b?b.option(e||{})._init():a.data(this,c,new d(e,this))}),h)}},a.Widget=function(a,b){arguments.length&&this._createWidget(a,b)},a.Widget.prototype={widgetName:"widget",widgetEventPrefix:"",options:{disabled:!1},_createWidget:function(b,c){a.data(c,this.widgetName,this),this.element=a(c),this.options=a.extend(!0,{},this.options,this._getCreateOptions(),b);var d=this;this.element.bind("remove."+this.widgetName,function(){d.destroy()}),this._create(),this._trigger("create"),this._init()},_getCreateOptions:function(){return a.metadata&&a.metadata.get(this.element[0])[this.widgetName]},_create:function(){},_init:function(){},destroy:function(){this.element.unbind("."+this.widgetName).removeData(this.widgetName),this.widget().unbind("."+this.widgetName).removeAttr("aria-disabled").removeClass(this.widgetBaseClass+"-disabled "+"ui-state-disabled")},widget:function(){return this.element},option:function(c,d){var e=c;if(arguments.length===0)return a.extend({},this.options);if(typeof c=="string"){if(d===b)return this.options[c];e={},e[c]=d}return this._setOptions(e),this},_setOptions:function(b){var c=this;return a.each(b,function(a,b){c._setOption(a,b)}),this},_setOption:function(a,b){return this.options[a]=b,a==="disabled"&&this.widget()[b?"addClass":"removeClass"](this.widgetBaseClass+"-disabled"+" "+"ui-state-disabled").attr("aria-disabled",b),this},enable:function(){return this._setOption("disabled",!1)},disable:function(){return this._setOption("disabled",!0)},_trigger:function(b,c,d){var e,f,g=this.options[b];d=d||{},c=a.Event(c),c.type=(b===this.widgetEventPrefix?b:this.widgetEventPrefix+b).toLowerCase(),c.target=this.element[0],f=c.originalEvent;if(f)for(e in f)e in c||(c[e]=f[e]);return this.element.trigger(c,d),!(a.isFunction(g)&&g.call(this.element[0],c,d)===!1||c.isDefaultPrevented())}}})(jQuery);;/*! jQuery UI - v1.8.20 - 2012-04-30
* Includes: jquery.ui.mouse.js*/
(function(a,b){var c=!1;a(document).mouseup(function(a){c=!1}),a.widget("ui.mouse",{options:{cancel:":input,option",distance:1,delay:0},_mouseInit:function(){var b=this;this.element.bind("mousedown."+this.widgetName,function(a){return b._mouseDown(a)}).bind("click."+this.widgetName,function(c){if(!0===a.data(c.target,b.widgetName+".preventClickEvent"))return a.removeData(c.target,b.widgetName+".preventClickEvent"),c.stopImmediatePropagation(),!1}),this.started=!1},_mouseDestroy:function(){this.element.unbind("."+this.widgetName),a(document).unbind("mousemove."+this.widgetName,this._mouseMoveDelegate).unbind("mouseup."+this.widgetName,this._mouseUpDelegate)},_mouseDown:function(b){if(c)return;this._mouseStarted&&this._mouseUp(b),this._mouseDownEvent=b;var d=this,e=b.which==1,f=typeof this.options.cancel=="string"&&b.target.nodeName?a(b.target).closest(this.options.cancel).length:!1;if(!e||f||!this._mouseCapture(b))return!0;this.mouseDelayMet=!this.options.delay,this.mouseDelayMet||(this._mouseDelayTimer=setTimeout(function(){d.mouseDelayMet=!0},this.options.delay));if(this._mouseDistanceMet(b)&&this._mouseDelayMet(b)){this._mouseStarted=this._mouseStart(b)!==!1;if(!this._mouseStarted)return b.preventDefault(),!0}return!0===a.data(b.target,this.widgetName+".preventClickEvent")&&a.removeData(b.target,this.widgetName+".preventClickEvent"),this._mouseMoveDelegate=function(a){return d._mouseMove(a)},this._mouseUpDelegate=function(a){return d._mouseUp(a)},a(document).bind("mousemove."+this.widgetName,this._mouseMoveDelegate).bind("mouseup."+this.widgetName,this._mouseUpDelegate),b.preventDefault(),c=!0,!0},_mouseMove:function(b){return!a.browser.msie||document.documentMode>=9||!!b.button?this._mouseStarted?(this._mouseDrag(b),b.preventDefault()):(this._mouseDistanceMet(b)&&this._mouseDelayMet(b)&&(this._mouseStarted=this._mouseStart(this._mouseDownEvent,b)!==!1,this._mouseStarted?this._mouseDrag(b):this._mouseUp(b)),!this._mouseStarted):this._mouseUp(b)},_mouseUp:function(b){return a(document).unbind("mousemove."+this.widgetName,this._mouseMoveDelegate).unbind("mouseup."+this.widgetName,this._mouseUpDelegate),this._mouseStarted&&(this._mouseStarted=!1,b.target==this._mouseDownEvent.target&&a.data(b.target,this.widgetName+".preventClickEvent",!0),this._mouseStop(b)),!1},_mouseDistanceMet:function(a){return Math.max(Math.abs(this._mouseDownEvent.pageX-a.pageX),Math.abs(this._mouseDownEvent.pageY-a.pageY))>=this.options.distance},_mouseDelayMet:function(a){return this.mouseDelayMet},_mouseStart:function(a){},_mouseDrag:function(a){},_mouseStop:function(a){},_mouseCapture:function(a){return!0}})})(jQuery);;/*! jQuery UI - v1.8.20 - 2012-04-30
* Includes: jquery.ui.slider.js*/
(function(a,b){var c=5;a.widget("ui.slider",a.ui.mouse,{widgetEventPrefix:"slide",options:{animate:!1,distance:0,max:100,min:0,orientation:"horizontal",range:!1,step:1,value:0,values:null},_create:function(){var b=this,d=this.options,e=this.element.find(".ui-slider-handle").addClass("ui-state-default ui-corner-all"),f="<a class='ui-slider-handle ui-state-default ui-corner-all' href='#'></a>",g=d.values&&d.values.length||1,h=[];this._keySliding=!1,this._mouseSliding=!1,this._animateOff=!0,this._handleIndex=null,this._detectOrientation(),this._mouseInit(),this.element.addClass("ui-slider ui-slider-"+this.orientation+" ui-widget"+" ui-widget-content"+" ui-corner-all"+(d.disabled?" ui-slider-disabled ui-disabled":"")),this.range=a([]),d.range&&(d.range===!0&&(d.values||(d.values=[this._valueMin(),this._valueMin()]),d.values.length&&d.values.length!==2&&(d.values=[d.values[0],d.values[0]])),this.range=a("<div></div>").appendTo(this.element).addClass("ui-slider-range ui-widget-header"+(d.range==="min"||d.range==="max"?" ui-slider-range-"+d.range:"")));for(var i=e.length;i<g;i+=1)h.push(f);this.handles=e.add(a(h.join("")).appendTo(b.element)),this.handle=this.handles.eq(0),this.handles.add(this.range).filter("a").click(function(a){a.preventDefault()}).hover(function(){d.disabled||a(this).addClass("ui-state-hover")},function(){a(this).removeClass("ui-state-hover")}).focus(function(){d.disabled?a(this).blur():(a(".ui-slider .ui-state-focus").removeClass("ui-state-focus"),a(this).addClass("ui-state-focus"))}).blur(function(){a(this).removeClass("ui-state-focus")}),this.handles.each(function(b){a(this).data("index.ui-slider-handle",b)}),this.handles.keydown(function(d){var e=a(this).data("index.ui-slider-handle"),f,g,h,i;if(b.options.disabled)return;switch(d.keyCode){case a.ui.keyCode.HOME:case a.ui.keyCode.END:case a.ui.keyCode.PAGE_UP:case a.ui.keyCode.PAGE_DOWN:case a.ui.keyCode.UP:case a.ui.keyCode.RIGHT:case a.ui.keyCode.DOWN:case a.ui.keyCode.LEFT:d.preventDefault();if(!b._keySliding){b._keySliding=!0,a(this).addClass("ui-state-active"),f=b._start(d,e);if(f===!1)return}}i=b.options.step,b.options.values&&b.options.values.length?g=h=b.values(e):g=h=b.value();switch(d.keyCode){case a.ui.keyCode.HOME:h=b._valueMin();break;case a.ui.keyCode.END:h=b._valueMax();break;case a.ui.keyCode.PAGE_UP:h=b._trimAlignValue(g+(b._valueMax()-b._valueMin())/c);break;case a.ui.keyCode.PAGE_DOWN:h=b._trimAlignValue(g-(b._valueMax()-b._valueMin())/c);break;case a.ui.keyCode.UP:case a.ui.keyCode.RIGHT:if(g===b._valueMax())return;h=b._trimAlignValue(g+i);break;case a.ui.keyCode.DOWN:case a.ui.keyCode.LEFT:if(g===b._valueMin())return;h=b._trimAlignValue(g-i)}b._slide(d,e,h)}).keyup(function(c){var d=a(this).data("index.ui-slider-handle");b._keySliding&&(b._keySliding=!1,b._stop(c,d),b._change(c,d),a(this).removeClass("ui-state-active"))}),this._refreshValue(),this._animateOff=!1},destroy:function(){return this.handles.remove(),this.range.remove(),this.element.removeClass("ui-slider ui-slider-horizontal ui-slider-vertical ui-slider-disabled ui-widget ui-widget-content ui-corner-all").removeData("slider").unbind(".slider"),this._mouseDestroy(),this},_mouseCapture:function(b){var c=this.options,d,e,f,g,h,i,j,k,l;return c.disabled?!1:(this.elementSize={width:this.element.outerWidth(),height:this.element.outerHeight()},this.elementOffset=this.element.offset(),d={x:b.pageX,y:b.pageY},e=this._normValueFromMouse(d),f=this._valueMax()-this._valueMin()+1,h=this,this.handles.each(function(b){var c=Math.abs(e-h.values(b));f>c&&(f=c,g=a(this),i=b)}),c.range===!0&&this.values(1)===c.min&&(i+=1,g=a(this.handles[i])),j=this._start(b,i),j===!1?!1:(this._mouseSliding=!0,h._handleIndex=i,g.addClass("ui-state-active").focus(),k=g.offset(),l=!a(b.target).parents().andSelf().is(".ui-slider-handle"),this._clickOffset=l?{left:0,top:0}:{left:b.pageX-k.left-g.width()/2,top:b.pageY-k.top-g.height()/2-(parseInt(g.css("borderTopWidth"),10)||0)-(parseInt(g.css("borderBottomWidth"),10)||0)+(parseInt(g.css("marginTop"),10)||0)},this.handles.hasClass("ui-state-hover")||this._slide(b,i,e),this._animateOff=!0,!0))},_mouseStart:function(a){return!0},_mouseDrag:function(a){var b={x:a.pageX,y:a.pageY},c=this._normValueFromMouse(b);return this._slide(a,this._handleIndex,c),!1},_mouseStop:function(a){return this.handles.removeClass("ui-state-active"),this._mouseSliding=!1,this._stop(a,this._handleIndex),this._change(a,this._handleIndex),this._handleIndex=null,this._clickOffset=null,this._animateOff=!1,!1},_detectOrientation:function(){this.orientation=this.options.orientation==="vertical"?"vertical":"horizontal"},_normValueFromMouse:function(a){var b,c,d,e,f;return this.orientation==="horizontal"?(b=this.elementSize.width,c=a.x-this.elementOffset.left-(this._clickOffset?this._clickOffset.left:0)):(b=this.elementSize.height,c=a.y-this.elementOffset.top-(this._clickOffset?this._clickOffset.top:0)),d=c/b,d>1&&(d=1),d<0&&(d=0),this.orientation==="vertical"&&(d=1-d),e=this._valueMax()-this._valueMin(),f=this._valueMin()+d*e,this._trimAlignValue(f)},_start:function(a,b){var c={handle:this.handles[b],value:this.value()};return this.options.values&&this.options.values.length&&(c.value=this.values(b),c.values=this.values()),this._trigger("start",a,c)},_slide:function(a,b,c){var d,e,f;this.options.values&&this.options.values.length?(d=this.values(b?0:1),this.options.values.length===2&&this.options.range===!0&&(b===0&&c>d||b===1&&c<d)&&(c=d),c!==this.values(b)&&(e=this.values(),e[b]=c,f=this._trigger("slide",a,{handle:this.handles[b],value:c,values:e}),d=this.values(b?0:1),f!==!1&&this.values(b,c,!0))):c!==this.value()&&(f=this._trigger("slide",a,{handle:this.handles[b],value:c}),f!==!1&&this.value(c))},_stop:function(a,b){var c={handle:this.handles[b],value:this.value()};this.options.values&&this.options.values.length&&(c.value=this.values(b),c.values=this.values()),this._trigger("stop",a,c)},_change:function(a,b){if(!this._keySliding&&!this._mouseSliding){var c={handle:this.handles[b],value:this.value()};this.options.values&&this.options.values.length&&(c.value=this.values(b),c.values=this.values()),this._trigger("change",a,c)}},value:function(a){if(arguments.length){this.options.value=this._trimAlignValue(a),this._refreshValue(),this._change(null,0);return}return this._value()},values:function(b,c){var d,e,f;if(arguments.length>1){this.options.values[b]=this._trimAlignValue(c),this._refreshValue(),this._change(null,b);return}if(!arguments.length)return this._values();if(!a.isArray(arguments[0]))return this.options.values&&this.options.values.length?this._values(b):this.value();d=this.options.values,e=arguments[0];for(f=0;f<d.length;f+=1)d[f]=this._trimAlignValue(e[f]),this._change(null,f);this._refreshValue()},_setOption:function(b,c){var d,e=0;a.isArray(this.options.values)&&(e=this.options.values.length),a.Widget.prototype._setOption.apply(this,arguments);switch(b){case"disabled":c?(this.handles.filter(".ui-state-focus").blur(),this.handles.removeClass("ui-state-hover"),this.handles.propAttr("disabled",!0),this.element.addClass("ui-disabled")):(this.handles.propAttr("disabled",!1),this.element.removeClass("ui-disabled"));break;case"orientation":this._detectOrientation(),this.element.removeClass("ui-slider-horizontal ui-slider-vertical").addClass("ui-slider-"+this.orientation),this._refreshValue();break;case"value":this._animateOff=!0,this._refreshValue(),this._change(null,0),this._animateOff=!1;break;case"values":this._animateOff=!0,this._refreshValue();for(d=0;d<e;d+=1)this._change(null,d);this._animateOff=!1}},_value:function(){var a=this.options.value;return a=this._trimAlignValue(a),a},_values:function(a){var b,c,d;if(arguments.length)return b=this.options.values[a],b=this._trimAlignValue(b),b;c=this.options.values.slice();for(d=0;d<c.length;d+=1)c[d]=this._trimAlignValue(c[d]);return c},_trimAlignValue:function(a){if(a<=this._valueMin())return this._valueMin();if(a>=this._valueMax())return this._valueMax();var b=this.options.step>0?this.options.step:1,c=(a-this._valueMin())%b,d=a-c;return Math.abs(c)*2>=b&&(d+=c>0?b:-b),parseFloat(d.toFixed(5))},_valueMin:function(){return this.options.min},_valueMax:function(){return this.options.max},_refreshValue:function(){var b=this.options.range,c=this.options,d=this,e=this._animateOff?!1:c.animate,f,g={},h,i,j,k;this.options.values&&this.options.values.length?this.handles.each(function(b,i){f=(d.values(b)-d._valueMin())/(d._valueMax()-d._valueMin())*100,g[d.orientation==="horizontal"?"left":"bottom"]=f+"%",a(this).stop(1,1)[e?"animate":"css"](g,c.animate),d.options.range===!0&&(d.orientation==="horizontal"?(b===0&&d.range.stop(1,1)[e?"animate":"css"]({left:f+"%"},c.animate),b===1&&d.range[e?"animate":"css"]({width:f-h+"%"},{queue:!1,duration:c.animate})):(b===0&&d.range.stop(1,1)[e?"animate":"css"]({bottom:f+"%"},c.animate),b===1&&d.range[e?"animate":"css"]({height:f-h+"%"},{queue:!1,duration:c.animate}))),h=f}):(i=this.value(),j=this._valueMin(),k=this._valueMax(),f=k!==j?(i-j)/(k-j)*100:0,g[d.orientation==="horizontal"?"left":"bottom"]=f+"%",this.handle.stop(1,1)[e?"animate":"css"](g,c.animate),b==="min"&&this.orientation==="horizontal"&&this.range.stop(1,1)[e?"animate":"css"]({width:f+"%"},c.animate),b==="max"&&this.orientation==="horizontal"&&this.range[e?"animate":"css"]({width:100-f+"%"},{queue:!1,duration:c.animate}),b==="min"&&this.orientation==="vertical"&&this.range.stop(1,1)[e?"animate":"css"]({height:f+"%"},c.animate),b==="max"&&this.orientation==="vertical"&&this.range[e?"animate":"css"]({height:100-f+"%"},{queue:!1,duration:c.animate}))}}),a.extend(a.ui.slider,{version:"1.8.20"})})(jQuery);;
/*! end jQuery UI */

/* flowplayer.js 3.2.10. The Flowplayer API
 * Copyright 2009-2011 Flowplayer Oy
 */
(function(){function g(o){console.log("$f.fireEvent",[].slice.call(o))}function k(q){if(!q||typeof q!="object"){return q}var o=new q.constructor();for(var p in q){if(q.hasOwnProperty(p)){o[p]=k(q[p])}}return o}function m(t,q){if(!t){return}var o,p=0,r=t.length;if(r===undefined){for(o in t){if(q.call(t[o],o,t[o])===false){break}}}else{for(var s=t[0];p<r&&q.call(s,p,s)!==false;s=t[++p]){}}return t}function c(o){return document.getElementById(o)}function i(q,p,o){if(typeof p!="object"){return q}if(q&&p){m(p,function(r,s){if(!o||typeof s!="function"){q[r]=s}})}return q}function n(s){var q=s.indexOf(".");if(q!=-1){var p=s.slice(0,q)||"*";var o=s.slice(q+1,s.length);var r=[];m(document.getElementsByTagName(p),function(){if(this.className&&this.className.indexOf(o)!=-1){r.push(this)}});return r}}function f(o){o=o||window.event;if(o.preventDefault){o.stopPropagation();o.preventDefault()}else{o.returnValue=false;o.cancelBubble=true}return false}function j(q,o,p){q[o]=q[o]||[];q[o].push(p)}function e(){return"_"+(""+Math.random()).slice(2,10)}var h=function(t,r,s){var q=this,p={},u={};q.index=r;if(typeof t=="string"){t={url:t}}i(this,t,true);m(("Begin*,Start,Pause*,Resume*,Seek*,Stop*,Finish*,LastSecond,Update,BufferFull,BufferEmpty,BufferStop").split(","),function(){var v="on"+this;if(v.indexOf("*")!=-1){v=v.slice(0,v.length-1);var w="onBefore"+v.slice(2);q[w]=function(x){j(u,w,x);return q}}q[v]=function(x){j(u,v,x);return q};if(r==-1){if(q[w]){s[w]=q[w]}if(q[v]){s[v]=q[v]}}});i(this,{onCuepoint:function(x,w){if(arguments.length==1){p.embedded=[null,x];return q}if(typeof x=="number"){x=[x]}var v=e();p[v]=[x,w];if(s.isLoaded()){s._api().fp_addCuepoints(x,r,v)}return q},update:function(w){i(q,w);if(s.isLoaded()){s._api().fp_updateClip(w,r)}var v=s.getConfig();var x=(r==-1)?v.clip:v.playlist[r];i(x,w,true)},_fireEvent:function(v,y,w,A){if(v=="onLoad"){m(p,function(B,C){if(C[0]){s._api().fp_addCuepoints(C[0],r,B)}});return false}A=A||q;if(v=="onCuepoint"){var z=p[y];if(z){return z[1].call(s,A,w)}}if(y&&"onBeforeBegin,onMetaData,onStart,onUpdate,onResume".indexOf(v)!=-1){i(A,y);if(y.metaData){if(!A.duration){A.duration=y.metaData.duration}else{A.fullDuration=y.metaData.duration}}}var x=true;m(u[v],function(){x=this.call(s,A,y,w)});return x}});if(t.onCuepoint){var o=t.onCuepoint;q.onCuepoint.apply(q,typeof o=="function"?[o]:o);delete t.onCuepoint}m(t,function(v,w){if(typeof w=="function"){j(u,v,w);delete t[v]}});if(r==-1){s.onCuepoint=this.onCuepoint}};var l=function(p,r,q,t){var o=this,s={},u=false;if(t){i(s,t)}m(r,function(v,w){if(typeof w=="function"){s[v]=w;delete r[v]}});i(this,{animate:function(y,z,x){if(!y){return o}if(typeof z=="function"){x=z;z=500}if(typeof y=="string"){var w=y;y={};y[w]=z;z=500}if(x){var v=e();s[v]=x}if(z===undefined){z=500}r=q._api().fp_animate(p,y,z,v);return o},css:function(w,x){if(x!==undefined){var v={};v[w]=x;w=v}r=q._api().fp_css(p,w);i(o,r);return o},show:function(){this.display="block";q._api().fp_showPlugin(p);return o},hide:function(){this.display="none";q._api().fp_hidePlugin(p);return o},toggle:function(){this.display=q._api().fp_togglePlugin(p);return o},fadeTo:function(y,x,w){if(typeof x=="function"){w=x;x=500}if(w){var v=e();s[v]=w}this.display=q._api().fp_fadeTo(p,y,x,v);this.opacity=y;return o},fadeIn:function(w,v){return o.fadeTo(1,w,v)},fadeOut:function(w,v){return o.fadeTo(0,w,v)},getName:function(){return p},getPlayer:function(){return q},_fireEvent:function(w,v,x){if(w=="onUpdate"){var z=q._api().fp_getPlugin(p);if(!z){return}i(o,z);delete o.methods;if(!u){m(z.methods,function(){var B=""+this;o[B]=function(){var C=[].slice.call(arguments);var D=q._api().fp_invoke(p,B,C);return D==="undefined"||D===undefined?o:D}});u=true}}var A=s[w];if(A){var y=A.apply(o,v);if(w.slice(0,1)=="_"){delete s[w]}return y}return o}})};function b(q,G,t){var w=this,v=null,D=false,u,s,F=[],y={},x={},E,r,p,C,o,A;i(w,{id:function(){return E},isLoaded:function(){return(v!==null&&v.fp_play!==undefined&&!D)},getParent:function(){return q},hide:function(H){if(H){q.style.height="0px"}if(w.isLoaded()){v.style.height="0px"}return w},show:function(){q.style.height=A+"px";if(w.isLoaded()){v.style.height=o+"px"}return w},isHidden:function(){return w.isLoaded()&&parseInt(v.style.height,10)===0},load:function(J){if(!w.isLoaded()&&w._fireEvent("onBeforeLoad")!==false){var H=function(){if(u&&!flashembed.isSupported(G.version)){q.innerHTML=""}if(J){J.cached=true;j(x,"onLoad",J)}flashembed(q,G,{config:t})};var I=0;m(a,function(){this.unload(function(K){if(++I==a.length){H()}})})}return w},unload:function(J){if(u.replace(/\s/g,"")!==""){if(w._fireEvent("onBeforeUnload")===false){if(J){J(false)}return w}D=true;try{if(v){v.fp_close();w._fireEvent("onUnload")}}catch(H){}var I=function(){v=null;q.innerHTML=u;D=false;if(J){J(true)}};if(/WebKit/i.test(navigator.userAgent)&&!/Chrome/i.test(navigator.userAgent)){setTimeout(I,0)}else{I()}}else{if(J){J(false)}}return w},getClip:function(H){if(H===undefined){H=C}return F[H]},getCommonClip:function(){return s},getPlaylist:function(){return F},getPlugin:function(H){var J=y[H];if(!J&&w.isLoaded()){var I=w._api().fp_getPlugin(H);if(I){J=new l(H,I,w);y[H]=J}}return J},getScreen:function(){return w.getPlugin("screen")},getControls:function(){return w.getPlugin("controls")._fireEvent("onUpdate")},getLogo:function(){try{return w.getPlugin("logo")._fireEvent("onUpdate")}catch(H){}},getPlay:function(){return w.getPlugin("play")._fireEvent("onUpdate")},getConfig:function(H){return H?k(t):t},getFlashParams:function(){return G},loadPlugin:function(K,J,M,L){if(typeof M=="function"){L=M;M={}}var I=L?e():"_";w._api().fp_loadPlugin(K,J,M,I);var H={};H[I]=L;var N=new l(K,null,w,H);y[K]=N;return N},getState:function(){return w.isLoaded()?v.fp_getState():-1},play:function(I,H){var J=function(){if(I!==undefined){w._api().fp_play(I,H)}else{w._api().fp_play()}};if(w.isLoaded()){J()}else{if(D){setTimeout(function(){w.play(I,H)},50)}else{w.load(function(){J()})}}return w},getVersion:function(){var I="flowplayer.js 3.2.10";if(w.isLoaded()){var H=v.fp_getVersion();H.push(I);return H}return I},_api:function(){if(!w.isLoaded()){throw"Flowplayer "+w.id()+" not loaded when calling an API method"}return v},setClip:function(H){m(H,function(I,J){if(typeof J=="function"){j(x,I,J);delete H[I]}else{if(I=="onCuepoint"){$f(q).getCommonClip().onCuepoint(H[I][0],H[I][1])}}});w.setPlaylist([H]);return w},getIndex:function(){return p},bufferAnimate:function(H){v.fp_bufferAnimate(H===undefined||H);return w},_swfHeight:function(){return v.clientHeight}});m(("Click*,Load*,Unload*,Keypress*,Volume*,Mute*,Unmute*,PlaylistReplace,ClipAdd,Fullscreen*,FullscreenExit,Error,MouseOver,MouseOut").split(","),function(){var H="on"+this;if(H.indexOf("*")!=-1){H=H.slice(0,H.length-1);var I="onBefore"+H.slice(2);w[I]=function(J){j(x,I,J);return w}}w[H]=function(J){j(x,H,J);return w}});m(("pause,resume,mute,unmute,stop,toggle,seek,getStatus,getVolume,setVolume,getTime,isPaused,isPlaying,startBuffering,stopBuffering,isFullscreen,toggleFullscreen,reset,close,setPlaylist,addClip,playFeed,setKeyboardShortcutsEnabled,isKeyboardShortcutsEnabled").split(","),function(){var H=this;w[H]=function(J,I){if(!w.isLoaded()){return w}var K=null;if(J!==undefined&&I!==undefined){K=v["fp_"+H](J,I)}else{K=(J===undefined)?v["fp_"+H]():v["fp_"+H](J)}return K==="undefined"||K===undefined?w:K}});w._fireEvent=function(Q){if(typeof Q=="string"){Q=[Q]}var R=Q[0],O=Q[1],M=Q[2],L=Q[3],K=0;if(t.debug){g(Q)}if(!w.isLoaded()&&R=="onLoad"&&O=="player"){v=v||c(r);o=w._swfHeight();m(F,function(){this._fireEvent("onLoad")});m(y,function(S,T){T._fireEvent("onUpdate")});s._fireEvent("onLoad")}if(R=="onLoad"&&O!="player"){return}if(R=="onError"){if(typeof O=="string"||(typeof O=="number"&&typeof M=="number")){O=M;M=L}}if(R=="onContextMenu"){m(t.contextMenu[O],function(S,T){T.call(w)});return}if(R=="onPluginEvent"||R=="onBeforePluginEvent"){var H=O.name||O;var I=y[H];if(I){I._fireEvent("onUpdate",O);return I._fireEvent(M,Q.slice(3))}return}if(R=="onPlaylistReplace"){F=[];var N=0;m(O,function(){F.push(new h(this,N++,w))})}if(R=="onClipAdd"){if(O.isInStream){return}O=new h(O,M,w);F.splice(M,0,O);for(K=M+1;K<F.length;K++){F[K].index++}}var P=true;if(typeof O=="number"&&O<F.length){C=O;var J=F[O];if(J){P=J._fireEvent(R,M,L)}if(!J||P!==false){P=s._fireEvent(R,M,L,J)}}m(x[R],function(){P=this.call(w,O,M);if(this.cached){x[R].splice(K,1)}if(P===false){return false}K++});return P};function B(){if($f(q)){$f(q).getParent().innerHTML="";p=$f(q).getIndex();a[p]=w}else{a.push(w);p=a.length-1}A=parseInt(q.style.height,10)||q.clientHeight;E=q.id||"fp"+e();r=G.id||E+"_api";G.id=r;u=q.innerHTML;if(typeof t=="string"){t={clip:{url:t}}}t.playerId=E;t.clip=t.clip||{};if(q.getAttribute("href",2)&&!t.clip.url){t.clip.url=q.getAttribute("href",2)}s=new h(t.clip,-1,w);t.playlist=t.playlist||[t.clip];var I=0;m(t.playlist,function(){var L=this;if(typeof L=="object"&&L.length){L={url:""+L}}m(t.clip,function(M,N){if(N!==undefined&&L[M]===undefined&&typeof N!="function"){L[M]=N}});t.playlist[I]=L;L=new h(L,I,w);F.push(L);I++});m(t,function(L,M){if(typeof M=="function"){if(s[L]){s[L](M)}else{j(x,L,M)}delete t[L]}});m(t.plugins,function(L,M){if(M){y[L]=new l(L,M,w)}});if(!t.plugins||t.plugins.controls===undefined){y.controls=new l("controls",null,w)}y.canvas=new l("canvas",null,w);u=q.innerHTML;function K(L){if(/iPad|iPhone|iPod/i.test(navigator.userAgent)&&!/.flv$/i.test(F[0].url)&&!J()){return true}if(!w.isLoaded()&&w._fireEvent("onBeforeClick")!==false){w.load()}return f(L)}function J(){return w.hasiPadSupport&&w.hasiPadSupport()}function H(){if(u.replace(/\s/g,"")!==""){if(q.addEventListener){q.addEventListener("click",K,false)}else{if(q.attachEvent){q.attachEvent("onclick",K)}}}else{if(q.addEventListener&&!J()){q.addEventListener("click",f,false)}w.load()}}setTimeout(H,0)}if(typeof q=="string"){var z=c(q);if(!z){throw"Flowplayer cannot access element: "+q}q=z;B()}else{B()}}var a=[];function d(o){this.length=o.length;this.each=function(q){m(o,q)};this.size=function(){return o.length};var p=this;for(name in b.prototype){p[name]=function(){var q=arguments;p.each(function(){this[name].apply(this,q)})}}}window.flowplayer=window.$f=function(){var p=null;var o=arguments[0];if(!arguments.length){m(a,function(){if(this.isLoaded()){p=this;return false}});return p||a[0]}if(arguments.length==1){if(typeof o=="number"){return a[o]}else{if(o=="*"){return new d(a)}m(a,function(){if(this.id()==o.id||this.id()==o||this.getParent()==o){p=this;return false}});return p}}if(arguments.length>1){var t=arguments[1],q=(arguments.length==3)?arguments[2]:{};if(typeof t=="string"){t={src:t}}t=i({bgcolor:"#000000",version:[10,1],expressInstall:"http://releases.flowplayer.org/swf/expressinstall.swf",cachebusting:false},t);if(typeof o=="string"){if(o.indexOf(".")!=-1){var s=[];m(n(o),function(){s.push(new b(this,k(t),k(q)))});return new d(s)}else{var r=c(o);return new b(r!==null?r:k(o),k(t),k(q))}}else{if(o){return new b(o,k(t),k(q))}}}return null};i(window.$f,{fireEvent:function(){var o=[].slice.call(arguments);var q=$f(o[0]);return q?q._fireEvent(o.slice(1)):null},addPlugin:function(o,p){b.prototype[o]=p;return $f},each:m,extend:i});if(typeof jQuery=="function"){jQuery.fn.flowplayer=function(q,p){if(!arguments.length||typeof arguments[0]=="number"){var o=[];this.each(function(){var r=$f(this);if(r){o.push(r)}});return arguments.length?o[arguments[0]]:new d(o)}return this.each(function(){$f(this,k(q),p?k(p):{})})}}})();(function(){var h=document.all,j="http://www.adobe.com/go/getflashplayer",c=typeof jQuery=="function",e=/(\d+)[^\d]+(\d+)[^\d]*(\d*)/,b={width:"100%",height:"100%",id:"_"+(""+Math.random()).slice(9),allowfullscreen:true,allowscriptaccess:"always",quality:"high",version:[3,0],onFail:null,expressInstall:null,w3c:false,cachebusting:false};if(window.attachEvent){window.attachEvent("onbeforeunload",function(){__flash_unloadHandler=function(){};__flash_savedUnloadHandler=function(){}})}function i(m,l){if(l){for(var f in l){if(l.hasOwnProperty(f)){m[f]=l[f]}}}return m}function a(f,n){var m=[];for(var l in f){if(f.hasOwnProperty(l)){m[l]=n(f[l])}}return m}window.flashembed=function(f,m,l){if(typeof f=="string"){f=document.getElementById(f.replace("#",""))}if(!f){return}if(typeof m=="string"){m={src:m}}return new d(f,i(i({},b),m),l)};var g=i(window.flashembed,{conf:b,getVersion:function(){var m,f;try{f=navigator.plugins["Shockwave Flash"].description.slice(16)}catch(o){try{m=new ActiveXObject("ShockwaveFlash.ShockwaveFlash.7");f=m&&m.GetVariable("$version")}catch(n){try{m=new ActiveXObject("ShockwaveFlash.ShockwaveFlash.6");f=m&&m.GetVariable("$version")}catch(l){}}}f=e.exec(f);return f?[1*f[1],1*f[(f[1]*1>9?2:3)]*1]:[0,0]},asString:function(l){if(l===null||l===undefined){return null}var f=typeof l;if(f=="object"&&l.push){f="array"}switch(f){case"string":l=l.replace(new RegExp('(["\\\\])',"g"),"\\$1");l=l.replace(/^\s?(\d+\.?\d*)%/,"$1pct");return'"'+l+'"';case"array":return"["+a(l,function(o){return g.asString(o)}).join(",")+"]";case"function":return'"function()"';case"object":var m=[];for(var n in l){if(l.hasOwnProperty(n)){m.push('"'+n+'":'+g.asString(l[n]))}}return"{"+m.join(",")+"}"}return String(l).replace(/\s/g," ").replace(/\'/g,'"')},getHTML:function(o,l){o=i({},o);var n='<object width="'+o.width+'" height="'+o.height+'" id="'+o.id+'" name="'+o.id+'"';if(o.cachebusting){o.src+=((o.src.indexOf("?")!=-1?"&":"?")+Math.random())}if(o.w3c||!h){n+=' data="'+o.src+'" type="application/x-shockwave-flash"'}else{n+=' classid="clsid:D27CDB6E-AE6D-11cf-96B8-444553540000"'}n+=">";if(o.w3c||h){n+='<param name="movie" value="'+o.src+'" />'}o.width=o.height=o.id=o.w3c=o.src=null;o.onFail=o.version=o.expressInstall=null;for(var m in o){if(o[m]){n+='<param name="'+m+'" value="'+o[m]+'" />'}}var p="";if(l){for(var f in l){if(l[f]){var q=l[f];p+=f+"="+(/function|object/.test(typeof q)?g.asString(q):q)+"&"}}p=p.slice(0,-1);n+='<param name="flashvars" value=\''+p+"' />"}n+="</object>";return n},isSupported:function(f){return k[0]>f[0]||k[0]==f[0]&&k[1]>=f[1]}});var k=g.getVersion();function d(f,n,m){if(g.isSupported(n.version)){f.innerHTML=g.getHTML(n,m)}else{if(n.expressInstall&&g.isSupported([6,65])){f.innerHTML=g.getHTML(i(n,{src:n.expressInstall}),{MMredirectURL:encodeURIComponent(location.href),MMplayerType:"PlugIn",MMdoctitle:document.title})}else{if(!f.innerHTML.replace(/\s/g,"")){f.innerHTML="<h2>Flash version "+n.version+" or greater is required</h2><h3>"+(k[0]>0?"Your version is "+k:"You have no flash plugin installed")+"</h3>"+(f.tagName=="A"?"<p>Click here to download latest version</p>":"<p>Download latest version from <a href='"+j+"'>here</a></p>");if(f.tagName=="A"||f.tagName=="DIV"){f.onclick=function(){location.href=j}}}if(n.onFail){var l=n.onFail.call(this);if(typeof l=="string"){f.innerHTML=l}}}}if(h){window[n.id]=document.getElementById(n.id)}i(this,{getRoot:function(){return f},getOptions:function(){return n},getConf:function(){return m},getApi:function(){return f.firstChild}})}if(c){jQuery.tools=jQuery.tools||{version:"3.2.10"};jQuery.tools.flashembed={conf:b};jQuery.fn.flashembed=function(l,f){return this.each(function(){$(this).data("flashembed",flashembed(this,l,f))})}}})();
/*! end flowplayer */

jQuery(function(){
	$('.pnVideoContainer').pnVideo();
});