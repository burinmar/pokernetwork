function cToggleByCheckbox(ctrl, dep) {
	if ($(ctrl)[0].checked) {
		$(dep).show();
	} else {
		$(dep).hide();
	}
}

function skinPreview() {
	var v = $('#entry-skin').val();
	$('#entry-skin-preview').show()
		.css('background-image', 'url(/img/live_poker/default/' + v + '-thumb.png)')
		.css('background-position', 'center');
}

function evAddDay() {
	var s = emptyDayControl;
	var nr = $('#event_days .evDName').length + 1;
	s = s.replace(/\{nr\}/g, nr);
	s = s.replace(/\{merge_name\}/g, '');
	s = s.replace(/\{value\}/g, '');
	s = s.replace(/\{from_date\}/g, '');
	$('#event_days tr:last').after(s);
	jQuery("#event_days .isDate:last").each(function () {
		var id = jQuery(this).attr('id');
		if (id) jQuery(this).before('<a href="void(0);" onclick="pickDate(\'' + id + '\');return false" title="" class="calendar"></a>&nbsp;');
	});
	if (nr > 1) {
		jQuery("#event_days .evDName:last").focus(function(){
			var nr = parseInt(this.id.match(/.*?([0-9]+)$/)[1], 10);
			if ($('#fromdate' + nr).val() === '') {
				var pdate = $('#fromdate' + (nr - 1)).val().split(/-/);
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
					$('#fromdate' + nr).val(ndate.getFullYear() + '-' + m + '-' + d);
				}
			}
		});
	}
}

function onDemandEvAddDay()
{
	var addDay= true;
	$('#event_days .evDName').each(function(){
		if ($(this).val() === '') {
			addDay = false;
		}
	});
	if (addDay) {
		evAddDay();
	}
}

$(document).ready(function(){
	if (jQuery('#isLiveTournament').length > 0) {
		jQuery('#isLiveTournament').click(function(){
			cToggleByCheckbox('#isLiveTournament', '.is_live_dep');
		});
		cToggleByCheckbox('#isLiveTournament', '.is_live_dep');
	}
	skinPreview();
	$('#entry-skin').change(skinPreview);
	
	if (typeof emptyDayControl != 'undefined') {
		evAddDay();
		$('#event_days .evDName').livequery('keyup',function(){
			onDemandEvAddDay();
		});
		if (isNewEvt || $('#event_days .evDName').length == 1) {
			$('#event_days #dayname1').focus(function(){
				if ($('#fromdate1').val() === '') {
					$('#fromdate1').val($('#from-date').val());
				}
			});
		}
	}
});

$(document).ready(function(){
	if (typeof emptyDayControl == 'undefined') {
		return ;
	}
	var daysEliminationPreview = $('#day-preview-holder');
	try {
		daysEliminationPreview.pnReportingDayGraph({
			width: 540,
			height: 150
		});
	} catch(e) {}

	function displayDaysPreview() {
		var days = [];
		$('#event_days input.evDName').each(function(){
			days.push([
				$(this).val(),
				$('input.evDMName', $(this).parents('.evDay')).val()
			]);
		});
		try {
			daysEliminationPreview.pnReportingDayGraph('set', days);
		} catch(e) {}
	}
	$('#event_days input.evDName').livequery('change', displayDaysPreview);
	$('#event_days input.evDMName').livequery('change', displayDaysPreview);
	displayDaysPreview();
});

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
$(document).ready(function(){
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
