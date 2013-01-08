/**
 * Days control
 */
(function(attachObj){
	function evAddDay(emptyDayControl_) {
		var s = emptyDayControl_;
		var nr = $('#eventDays .jsEventDayName').length + 1;
		s = s.replace(/\{nr\}/g, nr);
		s = s.replace(/\{merge_name\}/g, '');
		s = s.replace(/\{value\}/g, '');
		s = s.replace(/\{from_date\}/g, '');
		$('#eventDays tr:last').after(s);
		$("#eventDays .isDate:last").each(function () {
			var id = ['jsDateAutoId', Math.floor((Math.random()*10000)+1)].join('');
			jQuery(this).attr('id', id);
			$(this).before('<a href="void(0);" onclick="pickDate(\'' + id + '\');return false" title="" class="calendar"></a>&nbsp;');
		});
	}
	function onDemandEvAddDay(emptyDayControl_)
	{
		var addDay = true;
		$('#eventDays .jsEventDayName').each(function(){
			if ($(this).val() === '') {
				addDay = false;
			}
		});
		if (addDay) {
			evAddDay(emptyDayControl_);
		}
	}
	function isNumber(n) {
		return !isNaN(parseFloat(n)) && isFinite(n);
	}

	$(function(){
		if (!$('#eventDays').length)
			return ;
		// autofill day date from previous day
		$('#eventDays .jsEventDayName').livequery('focus', function(){
			var $context = $(this).closest('.jsEventDay');
			if ($('.jsEventDayDate', $context).val() !== '')
				return ;
			var $prevContext = $context.prev('.jsEventDay');
			if ($prevContext.length === 0)
				return ;
			var pdate = $('.jsEventDayDate', $prevContext).val().split(/-/);
			if (pdate.length == 3) {
				var ndate = new Date();
				ndate.setFullYear(pdate[0]);
				ndate.setMonth(pdate[1] - 1);
				ndate.setDate(pdate[2]);
				ndate.setTime(ndate.getTime() + 1000*3600*24);
				var m = ndate.getMonth() + 1;
				if (m < 10) {
					m = '0' + m;
				}
				var d = ndate.getDate();
				if (d < 10) {
					d = '0' + d;
				}
				$('.jsEventDayDate', $context).val(ndate.getFullYear() + '-' + m + '-' + d);
			}
		});
		// autofill first day date from event
		$('#eventDays .jsEventDayName:first').livequery('focus', function(){
			var date = $('.jsEventDayDate', $(this).closest('.jsEventDay'));
			if (date.val() === '') {
				date.val($('#from-date').val());
			}
		});
		// autofill day date from previous day
		$('#eventDays .jsEventDayName').livequery('blur', function(){
			var $context = $(this).closest('.jsEventDay');
			var day = $('.jsEventDayName', $context).val();
			if (!isNumber(day)) // no letter
				return ;
			var $prevContexts = $context.prevAll('.jsEventDay');
			$prevContexts.each(function(){
				var prevMerge = $('.jsEventDayMergeName', this);
				if (prevMerge.val() !== '')
					return ;
				var prevDay = $('.jsEventDayName', this).val();
				if (parseInt(prevDay, 10) + 1 == parseInt(day, 10)) { // m.b. with letter
					prevMerge.val(day).change(); // +trigger change event
				}
			});
		});
	});

	attachObj.lrEventDaysSetup = function(emptyDayControl_) {
		$(function(){
			evAddDay(emptyDayControl_);
			$('#eventDays .jsEventDayName').livequery('keyup',function(){
				onDemandEvAddDay(emptyDayControl_);
			});
		});
	};
})(window);

/**
 * Days graph preview
 */
$(function(){
	var daysEliminationPreview = $('#dayPreviewContainer');
	if (daysEliminationPreview.length === 0)
		return ;

	try {
		daysEliminationPreview.pnReportingDayGraph({
			width: 540,
			height: 150
		});
	} catch(e) {}

	function displayDaysPreview() {
		var days = [];
		$('#eventDays .jsEventDay').each(function(){
			days.push([
				$('input.jsEventDayName', this).val(),
				$('input.jsEventDayMergeName', this).val()
			]);
		});
		try {
			daysEliminationPreview.pnReportingDayGraph('set', days);
		} catch(e) {}
	}
	var drawTimer; // two events with slightly different input consequently - draw only last
	$('#eventDays .jsEventDayName, #eventDays .jsEventDayMergeName').livequery('change', function(){
		clearTimeout(drawTimer);
		drawTimer = setTimeout(displayDaysPreview, 10);
	});
	displayDaysPreview();
});

/**
 * "Is visible" checkbox, toggling other elements visibility.
 */
$(function(){
	function cToggleByCheckbox(ctrl, dep) {
		if ($(ctrl)[0].checked) {
			$(dep).show();
		} else {
			$(dep).hide();
		}
	}
	$('#isLiveTournament').click(function(){
		cToggleByCheckbox('#isLiveTournament', '.is_live_dep');
	});
	if ($('#isLiveTournament').length > 0)
		cToggleByCheckbox('#isLiveTournament', '.is_live_dep');
});

/**
 * Tournament skin preview
 */
$(function(){
	function skinPreview() {
		var v = $('#entry-skin').val();
		$('#entry-skin-preview').show()
			.css('background-image', 'url(/img/live_poker/default/' + v + '-thumb.png)')
			.css('background-position', 'center');
	}
	skinPreview();
	$('#entry-skin').change(skinPreview);
});

/**
 * Tournament map
 */
(function(){
	var map;
	var marker;
	function getLocation() {
		var savedLocation = $.trim($('#geoLocation').val()).split(/,/);
		return savedLocation.length == 2
			? new google.maps.LatLng(savedLocation[0], savedLocation[1])
			: new google.maps.LatLng(51.37255671615328, -1.847995542327908);
	}
	function setupGeoMap()
	{
		var initialLocation = getLocation();
		map = new google.maps.Map(document.getElementById("geoLocationMap"), {
			zoom: 17,
			mapTypeId: google.maps.MapTypeId.ROADMAP,
			mapTypeControlOptions: {
				mapTypeIds: [google.maps.MapTypeId.ROADMAP, google.maps.MapTypeId.HYBRID]
			},
			streetViewControl: false,
			scrollwheel: false
		});
		map.setCenter(initialLocation);
		marker = new google.maps.Marker({
			position: initialLocation,
			map: map,
			draggable:true
		});
		google.maps.event.addListener(marker, 'dragend', function(event) {
			var point = marker.getPosition();
			map.setCenter(point);
			$('#geoLocation').val(point.toString().replace(/[() ]/g,''));
		});
	}
	function updateGeoMap()
	{
		var location = getLocation();
		map.setCenter(location);
		marker.setPosition(location);
	}
	$(function(){
		$('#geoAddress').change(function(){
			function unlockUi() {
				$('#geoAddress')[0].disabled = false;
				$('#geoLocationHint').hide();
			}
			function lockUi() {
				$('#geoAddress')[0].disabled = true;
				$('#geoLocationHint').show();
			}
			var addr = $.trim(this.value);
			if ('' === addr) {
				$('#geoLocation').val('');
			} else {
				var geocoder = new google.maps.Geocoder();
				if (geocoder) {
					lockUi();
					geocoder.geocode({ 'address': addr }, function (results, status) {
						if (status == google.maps.GeocoderStatus.OK) {
							unlockUi();
							$('#geoLocation').val(
								[results[0].geometry.location.Pa, results[0].geometry.location.Qa].join(',')
							);
							updateGeoMap();
						} else {
							$('#geoLocation').val('');
						}
					});
				} else {
					$('#geoLocation').val('');
				}
			}
		});
		if ($('#geoLocationMap').length > 0) {
			setupGeoMap();
		}
		$('#geoLocation').change(function(){
			updateGeoMap();
		});
	});
})();

