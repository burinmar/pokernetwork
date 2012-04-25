function st() {
	$('.articleSocialLinks li a').each(function(){
	if ('gplus' === this.parentNode.className) return;
	$(this).click(function(){
	_gaq.push(['_trackSocial', this.parentNode.className, 'Share']);
	});});
}

function floatHeader() {
	//overlayHeader
	if (!floatHeader.ready) {
		floatHeader.ready = true;
		var s = jQuery('#header .siteMenu').html();
		jQuery('#overlayHeader a.siteLogo').after('<ul class="siteMenu">'+s+'</ul>');
	}
	var y = jQuery(window).scrollTop();
	if (y>100) {
		jQuery('#overlayHeader').show();
	}
	else {
		jQuery('#overlayHeader').hide();
	}
}


new function($){
	$.fn.placeholder = function(settings) {
		settings = settings || {};
		var key = settings.dataKey || "placeholderValue";
		var attr = settings.attr || "placeholder";
		var className = settings.className || "placeholder";
		var values = settings.values || [];
		var block = settings.blockSubmit || false;
		var blank = settings.blankSubmit || false;
		var submit = settings.onSubmit || false;
		var value = settings.value || "";
		var position = settings.cursor_position || 0;

		
		return this.filter(":input[placeholder]").each(function(index) { 
			$.data(this, key, values[index] || $(this).attr(attr)); 
		}).each(function() {
			if (($.trim($(this).val()) === "") || ($.trim($(this).val()) === $.data(this, key)))
				$(this).addClass(className).val($.data(this, key));
		}).focus(function() {
			if ($.trim($(this).val()) === $.data(this, key)) {
				$(this).removeClass(className).val(value);
				if ($.fn.setCursorPosition) {
				  $(this).setCursorPosition(position);
				}
			}
		}).blur(function() {
			if ($.trim($(this).val()) === value)
				$(this).addClass(className).val($.data(this, key));
		}).each(function(index, elem) {
			new function(e) {
				$(e.form).bind("reset", function() {
					setTimeout(function() { $(e).trigger('blur'); }, 0);
				});
			}(elem);
			if (block)
				new function(e) {
					$(e.form).submit(function() {
						return $.trim($(e).val()) != $.data(e, key)
					});
				}(elem);
			else if (blank)
				new function(e) {
					$(e.form).submit(function() {
						if ($.trim($(e).val()) == $.data(e, key)) 
							$(e).removeClass(className).val("");
						return true;
					});
				}(elem);
			else if (submit)
				new function(e) { $(e.form).submit(submit); }(elem);
		});
	};
}(jQuery);

/* Only add placeholders if the browser does not support them */
function fixPlaceholders() {
	//pridedam placeholder (is title)
	$(':input.placeholder').each(function () {$(this).attr('placeholder', $(this).attr('title')).removeAttr('title').removeClass('placeholder')});
	//toliau aktyvuojam
	var i = document.createElement('input');
	if (!('placeholder' in i)) {
		// ignoruojam textarea elementus, nes jais tenka operuoti ir per JS.
		$('input[placeholder]').placeholder({blankSubmit: true});
	}
}


function trackOutgoing(){
	if (typeof(trackOutgoing.init) != 'undefined') {
		return ;
	}
	/*if (document.domain && document.domain == "www.pokernews.com") {
		// kad crazyegg patikrinti, isjungiam laikinai
		return;
	}*/
	trackOutgoing.init = 1;
	var trackFunc =function(e) {

		var clickedEl = e.srcElement ? e.srcElement : e.target;
		var nameEl = '';
		var go = true;
		while (clickedEl && clickedEl.nodeName && go) {
			nameEl = clickedEl.nodeName.toUpperCase();
			if (clickedEl.id == 'bg' && nameEl=='DIV') {
				nameEl = 'BODY';
			}
			switch (nameEl) {
				/*case 'A':
					var aHref = clickedEl.href.toLowerCase();

					if (aHref.indexOf("/ext/") > -1 || aHref.indexOf("/download/") > -1) { // quick check
						if (typeof reviewRooms != 'undefined' && reviewRooms.cookieClrDependent(aHref)) {
							e.preventDefault();
							reviewRooms.showInstructions(aHref);
						}
					}
					go = false;
					break;*/

				case 'BODY':
					if (typeof(bgURL) != "undefined" && bgURL) {
						var riba = Math.max(jQuery(document).width()/2, 900);
						var goto = bgURL;
						if (typeof(bgURL2) != "undefined" && e.clientX > riba) {
							goto = bgURL2;
						}
						var win = window.open(goto, 'promo');
						if (win) win.focus();
					}
				case 'DIV':

					go = false;
					break;
				default:
			}
			clickedEl = clickedEl.parentNode;
		}
	}
	jQuery(document.body).bind('click',trackFunc);
}

/* sidebar roombox mouseover*/
function roomsBox(e)
{
	var clickedEl = e.srcElement ? e.srcElement : e.target;
	var nameEl = '';
	var jEl;
	while (clickedEl && clickedEl.nodeName) {
		nameEl = clickedEl.nodeName.toUpperCase();
		switch (nameEl) {
			case 'TR':
				//var id = clickedEl.id.substring(4);
				jEl = jQuery(clickedEl);
				if (jEl.hasClass('r1') && !jEl.hasClass('active')) {
					jQuery('#topRooms TR.r2').hide();
					jQuery('#topRooms TR.r1.active').removeClass('active');
					jEl.addClass('active');
					jEl.next().show();
				}
				break;
			default:
		}
		clickedEl = clickedEl.parentNode;
	}
	return;
}

jQuery(document).ready(function(){
	//showOtherSites();
	jQuery('.article table').each(function(){
		jQuery('tr:even', this).addClass('even');
		jQuery('tr:odd', this).addClass('odd');
	});
	fixPlaceholders();
	jQuery(window).bind('scroll resize',floatHeader);
	$('#topRooms').mouseover(roomsBox);
	if (bgURL) trackOutgoing();
});