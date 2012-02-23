var livePoker = function() {
	var ELPageNr = 0;
	var ELPager = {};
	function showELPage(page) {
		jQuery('#widgetEL .pg').hide();
		jQuery('#widgetEL .pgg' + ELPageNr + '').removeClass('current')
		ELPageNr = page;
		jQuery('#widgetEL .pgg' + ELPageNr + '').addClass('current')
		jQuery('#widgetEL .pg' + page).show();
	}
	function setupEL() {
		if (typeof jQuery('#widgetEL .pgga').attr('class') == 'undefined') {
			return;
		}
		ELPageNr = parseInt(jQuery('#widgetEL .pgga').attr('class').match(/pgg([0-9]+)/)[1]);
		jQuery('#widgetELShow').click(function(e) {
			e.preventDefault();
			showELPage(ELPageNr);
			jQuery('#widgetEL').show();
			jQuery('#widgetELHide').focus();
		});
		jQuery('#widgetELHide').click(function(e) {
			e.preventDefault();
			jQuery('#widgetEL').hide();
			jQuery('#widgetELShow').focus();
		});
		jQuery('#widgetEL .pgg').click(function(e) {
			e.preventDefault();
			var page = parseInt(jQuery(this).attr('class').match(/pgg([0-9]+)/)[1]);
			if (ELPageNr != page) {
				showELPage(page);
			}
		});
		jQuery('#widgetELNext').click(function(e) {
			e.preventDefault();
			if (jQuery('#widgetEL .pg' + (ELPageNr + 1)).length) {
				showELPage(ELPageNr + 1);
			}
		});
		jQuery('#widgetELPrev').click(function(e) {
			e.preventDefault();
			if (ELPageNr > 0) {
				showELPage(ELPageNr - 1);
			}
		});
	};
	function setupGalleryBoxes() {
		jQuery('ul.lightbox-gallery').each(function(){
			jQuery('a[rel*=lightbox]', this).click(function(e){
				e.preventDefault();
			}).lightBox();
		});
		jQuery('div.pnGallery').each(function(){
			$('.navi', this).removeClass('hide');
			$('.imageLink', this).eq(0).show();
			$('.imageInfo', this).eq(0).show();
			$(this).pnSlideshow({marginStep:93, visibleTabs:6})
		});
	};
	
	var miscAjaxInterval = 60000;
	var miscAjaxMultiplier = 1.2;
	var miscAjaxTimer;
	
	function reloadMiscAjax() {
		if (typeof miscAjaxUrl == 'undefined') {
			return;
		}
		var sendData = {};
		if (typeof launchShoutBox != 'undefined' && launchShoutBox != false) {
			sendData.shoutbox = 1;
			sendData.shoutbox_event_id = launchShoutBox.evt;
		}
		if (typeof launchLiveCount != 'undefined' && launchLiveCount != false) {
			sendData.liveupdate = 1;
			sendData.liveupdate_day = launchLiveCount.day;
			sendData.liveupdate_evt = launchLiveCount.evt;
			sendData.liveupdate_since = launchLiveCount.since;
		}

		jQuery.ajax({
			type: "POST",
			url: miscAjaxUrl,
			data : sendData,
			dataType: 'json',
			timeout: 30000,
			success: function(resp) {
				if (typeof resp.shoutbox != 'undefined') {
					jQuery('#shoutbox-ajax-content').html(resp.shoutbox);
				}
				if (typeof resp.liveupd != 'undefined') {
					if (resp.liveupd != 0) {
						jQuery('#lrAjaxLiveUpdate').html(' ' + resp.liveupd).show();
					} else {
						jQuery('#lrAjaxLiveUpdate').hide();
					}
				}
			}
		});
		
		miscAjaxInterval = Math.round(miscAjaxInterval * miscAjaxMultiplier);
		if (miscAjaxInterval > 600000) {
			miscAjaxInterval = 600000;
		}
		setTimeout(reloadMiscAjax, miscAjaxInterval);
	}
	function setupShoutBox() {
		jQuery('#iShoutbox').bind('keydown keyup focus blur mouseup', function(e){
			var defaultMsg = shoutBoxDefMsg;
			switch (e.type) {
				case 'blur':
					if(this.value == '') this.value=defaultMsg;
					jQuery('#counter').html('');
					break;
				case 'focus':
					if(this.value == defaultMsg) this.value='';
				default:
					var maxlimit = shoutBoxMaxLim;
					if (this.value.length > maxlimit) {
						this.value = this.value.substring(0, maxlimit);
					}
					jQuery('#counter').html( this.value.length + ' / ' + maxlimit);
			}
		});	
		if (typeof miscAjaxTimer == 'undefined') {
			miscAjaxTimer = setTimeout(reloadMiscAjax, miscAjaxInterval);
		}
	};
	function setupLiveCount() {
		if (typeof miscAjaxTimer == 'undefined') {
			miscAjaxTimer = setTimeout(reloadMiscAjax, miscAjaxInterval);
		}
	}
	return {
			setupEventsList : function() {
				setupEL();
			}, 
			setupGalleryBoxes : function() {
				setupGalleryBoxes();
			},
			setupShoutBox : function() {
				setupShoutBox();
			},
			setupLiveCount : function() {
				setupLiveCount();
			}
	};
}();

jQuery(document).ready(function(){
	livePoker.setupEventsList();
	livePoker.setupGalleryBoxes();
	jQuery('a#urlup').click(function(e){
		if (history.length > 1) {
			e.preventDefault();
			history.back();
		}
	})
	if (typeof launchShoutBox != 'undefined' && launchShoutBox != false) {
		livePoker.setupShoutBox();
	}
	if (typeof launchLiveCount != 'undefined' && launchLiveCount != false) {
		livePoker.setupLiveCount();
	}
});
	