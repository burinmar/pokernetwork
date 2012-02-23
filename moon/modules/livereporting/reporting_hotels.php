<?php
/**
 * @package livereporting
 */
/**
 * @package livereporting
 */
class reporting_hotels extends moon_com
{
	const remoteFailedDecode = 1;
	const remoteAmbiguousAddress = 2;
	const remoteOtherError = 3;
	const remoteHotelList = 4;
	const remoteRoomList = 5;
	const remoteHotelInfo = 6;
	
	function events()
	{
		if (_SITE_ID_ != 'com') {
			moon::page()->page404();
		}
		$this->use_page('LiveReporting1col');
		if (isset($_GET['cancelationPolicy'])) {
			header('content-type: text/html; charset=utf-8');
			$this->forget();
			$this->renderRoomDetails(@$_GET['hotelId'], @$_GET['rateCode'], @$_GET['roomTypeCode'], @$_GET['supplierType']);
			moon_close();
			exit;
		}
		if (isset($_GET['hotelId'])) {
			$this->set_var('render', 'entry');
			$this->set_var('id', $_GET['hotelId']);
			$this->set_var('rateKey', isset($_GET['rateKey']) ? $_GET['rateKey'] : '');
		} else {
			$this->set_var('render', 'list');
		}
	}
	
	function main($argv)
	{
		switch ($argv['render']) {
		case 'entry':
			return $this->renderHotel($argv);
		case 'widget':
			return $this->renderWidget();
		default:
			return $this->renderSearch();
		}
	}
	
	private function renderSearch()
	{
		$tpl = $this->load_template();
		$page = moon::page();
		$page->js('http://ajax.googleapis.com/ajax/libs/jqueryui/1.8.12/jquery-ui.min.js');
		$page->js('/js/modules/live_reporting_hotels.js');
		$page->js('/js/modal.window.js');
		$page->css('/css/live_poker_hotels.css');
		$page->css('http://ajax.googleapis.com/ajax/libs/jqueryui/1.8.12/themes/base/jquery-ui.css');

		$searchArgv = $this->eGetSearchArgv();
		$activeTournaments = $this->getActiveTournaments();
		if (0 == count($activeTournaments)) {
			$searchArgv['active_tab'] = 'location';
		}

		$mainArgv = array(
			'event.self' => $this->my('fullname'),
			'active_tab_tournament' => $searchArgv['active_tab'] == 'tournament',
			'active_tab_location'   => $searchArgv['active_tab'] == 'location',
			'url.tab_tournament' => $this->linkas('#'),
			'url.tab_location' => $this->linkas('#', '', array(
				'active_tab' => 'location'
			)),
			'block.list.results' => $this->partialRenderSearchResults($tpl, $searchArgv),
			'block.rooms' => $this->partialRenderSearchFormRooms($tpl, $searchArgv), // after partialRenderSearchResults
		);

		$optionValues = $tpl->parse_array('list:form.values');
		foreach (array('nights') as $key) {
			$values = explode(',', $optionValues[$key]);
			$mainArgv['list.' . $key] = '';
			foreach ($values as $nr => $value) {
				$mainArgv['list.' . $key] .= $tpl->parse('list:optionShort.item', array(
					'value' => intval($value),
					'name' => htmlspecialchars($value),
					'selected' => $searchArgv[$key] == intval($value)
				));
			}
			unset($searchArgv[$key]);
		}

 		foreach ($searchArgv as $key => $value) {
			$mainArgv['arg.' . $key] = htmlspecialchars($value);
		}
		$mainArgv['arg.check_in_loc'] = trim($mainArgv['arg.check_in']);
		
		if (0 != count($activeTournaments)) {
			$mainArgv += array(
				'active_tournament_exists' => true,
				'list.tournaments' => '',
				'js.tournaments' => array()
			);
			$tours = poker_tours();
			foreach ($activeTournaments as $row) {
				$mainArgv['list.tournaments'] .= $tpl->parse('list:form.tournaments.item', array(
					'id' => $row['id'],
					'name' => htmlspecialchars($row['name']),
					'selected' => intval($row['id']) == $searchArgv['tournament']
				));
				$mainArgv['js.tournaments'][$row['id']] = array(
					'date' => max($row['from_date'], time()+24*3600),
					'bg' => isset($tours[$row['tour']])
						? $tours[$row['tour']]['img1']
						: '',
				);
			}
			$mainArgv['js.tournaments'] = json_encode($mainArgv['js.tournaments']);
		}
		
		return $tpl->parse('list:main', $mainArgv);
	}

	private function renderWidget()
	{
		$tpl = $this->load_template();
		$tplArgv = array(
			'url.action' => $this->linkas('#'),
			'event.search' => $this->my('fullname'),
			'arg.active_tab' => 'tournament',
			'list.tournaments' => '',
		);

		$page = moon::page();
		$page->js('http://ajax.googleapis.com/ajax/libs/jqueryui/1.8.12/jquery-ui.min.js');
		$page->css('http://ajax.googleapis.com/ajax/libs/jqueryui/1.8.12/themes/base/jquery-ui.css');
		$page->js('/js/modules/live_reporting_hotels.js');

		$activeTournaments = $this->getActiveTournaments();
		if (0 == count($activeTournaments)) {
			return '';
		}

		$tours = poker_tours();
		foreach ($activeTournaments as $row) {
			$tplArgv['list.tournaments'] .= $tpl->parse('list:form.tournaments.item', array(
				'id' => $row['id'],
				'name' => htmlspecialchars($row['name']),
			));
			$tplArgv['js.tournaments'][$row['id']] = array(
				'date' => max($row['from_date'], time()+24*3600),
			);
		}
		$tplArgv['js.tournaments'] = json_encode($tplArgv['js.tournaments']);

		return $tpl->parse('widget:main', $tplArgv);
	}

	private function renderHotel($argv)
	{
		$tpl = $this->load_template();
		$page = moon::page();
		$page->js('http://ajax.googleapis.com/ajax/libs/jqueryui/1.8.12/jquery-ui.min.js');
		$page->js('http://maps.google.com/maps/api/js?sensor=false&amp;language=en');
		$page->js('/js/modules/live_reporting_hotels.js');
		$page->js('/js/pnslideshow.js');
		$page->js('/js/modal.window.js');
		$page->css('/css/live_poker_hotels.css');

		$searchArgv = $this->eGetSearchArgv();
		$requestArgv = array(
			'hotelId' => $argv['id']
		);
		if (!empty($argv['rateKey'])) {
			$requestArgv['rateKey'] = $argv['rateKey'];
		}
		$this->helperRequestArgvTimed($requestArgv, $searchArgv);
		
		$cacheId = $requestArgv;
		$cacheId[] = moon::user()->id();
		$cacheId = md5(serialize($cacheId));		
		
		$memcObj = moon_memcache::getInstance();
		$memcObjPrefix = moon_memcache::getRecommendedPrefix();
		if (
			//1 ||
			FALSE == ($cachedResult = $memcObj->get($memcObjPrefix . 'hotel.result' . $cacheId)) ||
			!is_array($cachedResult)
		) { 
			// fetch remote data
			list ($resultCode, $data) = $this->getHotelRemote($requestArgv);
			if (self::remoteOtherError == $resultCode) {
				// retry
				$this->helperSetRemoteSessId('');
				list ($resultCode, $data) = $this->getHotelRemote($requestArgv);
			}
			// cache if got hotel info, AND either not requested rooms availability or got it
			if (self::remoteHotelInfo == $resultCode && (!isset($data->_roomAvailability) || FALSE !== $data->_roomAvailability)) {
				$memcObj->set($memcObjPrefix . 'hotel.result' . $cacheId, array($resultCode, $data), MEMCACHE_COMPRESSED, 60);
			}
		} else {
			// cache
			list ($resultCode, $data) = $cachedResult;
		}
		$urlBack = $this->linkas('#') . '?' . htmlspecialchars(http_build_query($searchArgv + (isset($_GET['page'])
			? array('page' => $_GET['page'])
			: array())));
		
		if (in_array($resultCode, array(
			self::remoteFailedDecode,
			self::remoteOtherError
		))) {
			return $tpl->parse('hotel:results.error', array(
				'url.back' => $urlBack
			));
		}
		
		$summary = $data->HotelSummary;
		$details = $data->HotelDetails;
		$address = array(
			$summary->address1,
			$summary->city
		);
		if (isset($summary->stateProvinceCode)) {
			$address[] = $summary->stateProvinceCode;
		}
		$mainArgv = array(
			'event.self' => $this->my('fullname'),
			'url.back' => $urlBack,
			'name' => $summary->name, // escaped
			'rating' => intval($summary->hotelRating),
			'address' => implode(', ', $address),
			'propertyDescription' => isset($details->propertyDescription)
				? $this->helperTextDecode($details->propertyDescription) : '',
			'propertyInformation' => isset($details->propertyInformation)
				? $this->htmlspecialcharsDecode($details->propertyInformation) : '',
			'guaranteePolicy' => isset($details->guaranteePolicy)
				? $this->htmlspecialcharsDecode($details->guaranteePolicy) : '',
			'depositPolicy' => isset($details->depositPolicy)
				? $this->htmlspecialcharsDecode($details->depositPolicy) : '',
			'checkInInstructions' => isset($details->checkInInstructions)
				? $this->htmlspecialcharsDecode($details->checkInInstructions) : '',
			'areaInformation' => isset($details->areaInformation)
				? $this->htmlspecialcharsDecode($details->areaInformation) : '',
			'list.amenities.1' => '',
			'list.amenities.2' => '',
			'latitude' => $summary->latitude,
			'longitude' => $summary->longitude,
			'availableRooms' => '',
			'list.photos' => '',
			'list.photosThumbs' => '',
		);

		// {{
		$searchArgv_ = $searchArgv;
		$mainArgv['block.rooms'] = $this->partialRenderSearchFormRooms($tpl, $searchArgv);
			
		$optionValues = $tpl->parse_array('list:form.values');
		foreach (array('nights') as $key) {
			$values = explode(',', $optionValues[$key]);
			$mainArgv['list.' . $key] = '';
			foreach ($values as $nr => $value) {
				$mainArgv['list.' . $key] .= $tpl->parse('list:optionShort.item', array(
					'value' => intval($value),
					'name' => htmlspecialchars($value),
					'selected' => $searchArgv[$key] == intval($value)
				));
			}
			unset($searchArgv[$key]);
		}
 		foreach ($searchArgv as $key => $value) {
			$mainArgv['arg.' . $key] = htmlspecialchars($value);
		}
		$mainArgv['arg.hotelId'] = htmlspecialchars($argv['id']);
		$mainArgv['arg.rateKey'] = htmlspecialchars($argv['rateKey']);
		
		$searchArgv = $searchArgv_;
		// }}


		if (isset($data->_roomAvailability[0]) && $data->_roomAvailability[0]->supplierType == 'E' && !empty($details->hotelPolicy)) {
			$mainArgv['hotelPolicy'] = $this->htmlspecialcharsDecode($details->hotelPolicy);
		}

		if ('tournament' == $searchArgv['active_tab'] && !empty($searchArgv['tournament']) && ($tournament = $this->getActiveTournament($searchArgv['tournament'])) != NULL) {
			$tournament['geolocation'] = explode(',', $tournament['geolocation']);
			$tournament['geolocation'][0]  = floatval($tournament['geolocation'][0]);
			$tournament['geolocation'][1]  = floatval($tournament['geolocation'][1]);
			$distance = sqrt(
				($summary->latitude - $tournament['geolocation'][0]) * ($summary->latitude - $tournament['geolocation'][0])
				+ 
				($summary->longitude - $tournament['geolocation'][1]) * ($summary->longitude - $tournament['geolocation'][1]));
			$mainArgv['tournamentDistance'] = sprintf('%.1f', $distance * 65);
		}

		if (isset($data->PropertyAmenities)) {
			$amenitiesCnt = count($data->PropertyAmenities->PropertyAmenity);
			$amenitiesSlice = ceil($amenitiesCnt / 2);
			for ($i = 0; $i < 2; $i++) {
				$amenities = array_slice($data->PropertyAmenities->PropertyAmenity, $i * $amenitiesSlice, $amenitiesSlice);
				foreach ($amenities as $amenity) {
					$mainArgv['list.amenities.' . ($i + 1)] .= $tpl->parse('hotel:amenity.item', array(
						'name' => $amenity->amenity
					));
				}
			}
		}
		
		if (isset($data->HotelImages->HotelImage[0])) {
			$mainArgv['img.head'] = $data->HotelImages->HotelImage[0]->url;
		}
		
		$puzzle = array();
		$puzzleSearch = array(1, 3, 10, 21, 27, 42, 43, 46, 12, 47);
		if (isset($data->HotelImages->HotelImage)) {
		foreach ($data->HotelImages->HotelImage as $image) {
			if (in_array(intval($image->category), $puzzleSearch)) {
				$puzzle[] = $image->url;
				unset($puzzleSearch[array_search($image->category, $puzzleSearch)]);
			}
		}}
		$puzzle = array_slice($puzzle, 0, 5);
		foreach ($puzzle as $k => $image) {
			$mainArgv['img.head' . $k] = $image;
		}
		$mainArgv['showPuzzle'] = count($puzzle) == 5;
		
		if (isset($data->HotelImages->HotelImage)) {
		$hotelGallery = array_slice($data->HotelImages->HotelImage, 0, 25);
		foreach ($hotelGallery as $image) {
			$mainArgv['list.photos'] .= $tpl->parse('hotel:photos.item', array(
				'src' => $image->url,
				'alt' => isset($image->caption)
					? htmlspecialchars($image->caption) : ''
			));
			$mainArgv['list.photosThumbs'] .= $tpl->parse('hotel:photosThumbs.item', array(
				'src' => $image->thumbnailUrl,
				'alt' => isset($image->caption)
					? htmlspecialchars($image->caption) : ''
			));
		}}		
		
		$roomImage = NULL;
		if (isset($data->HotelImages->HotelImage)) {
		foreach ($data->HotelImages->HotelImage as $image) {
			if ($image->category == 3) {
				$roomImage = $image->url;
				break;
			}
		}}
		
		$lowestPriceRoom = array(
			'nr' => NULL,
			'price' => 99999
		);
		if (isset($data->_roomAvailability) && is_array($data->_roomAvailability)) {
		foreach ($data->_roomAvailability as $roomNr => $room) {
			$rateInfo = $room->RateInfos->RateInfo->ChargeableRateInfo;
			$roomArgv = array(
				'typeDescription' => $room->roomTypeDescription,
				'img' => $roomImage,
				'list.valueAdds' => '',
				'list.amenitiesColumns' => '',
				'url.book' => $room->deepLink,
				'price' => $this->helperCurrencyWrite(
					round($rateInfo->{'@averageRate'}),
					$rateInfo->{'@currencyCode'}
				),
				'discount' => isset($room->promoDescription)
					? $room->promoDescription : '',
				'description' => isset($room->details->descriptionLong)
					? $room->details->descriptionLong : '',
				'rateCode' => rawurldecode($room->rateCode),
				'roomTypeCode' => rawurldecode($room->roomTypeCode),
				'supplierType' => rawurldecode($room->supplierType),
			);
			if (floatval($rateInfo->{'@averageRate'}) < $lowestPriceRoom['price']) {
				$lowestPriceRoom['price'] = floatval($rateInfo->{'@averageRate'});
				$lowestPriceRoom['nr'] = $roomNr;
			}
			if (round($rateInfo->{'@averageRate'}) != round($rateInfo->{'@averageBaseRate'})) {
				$roomArgv['basePrice'] = $this->helperCurrencyWrite(
					round($rateInfo->{'@averageBaseRate'}),
					$rateInfo->{'@currencyCode'}
				);
			}
			if (isset($room->ValueAdds->ValueAdd)) {
				foreach ($room->ValueAdds->ValueAdd as $amenity) {
					$roomArgv['list.valueAdds'] .= $tpl->parse('hotel:availableRooms.valueAdds.item', array(
						'name' => $amenity->description
					));
				}
			}
			if (isset($room->details->roomAmenities->RoomAmenity)) {
				$amenitiesCnt = count($room->details->roomAmenities->RoomAmenity);
				$amenitiesSlice = ceil($amenitiesCnt / 3);
				for ($i = 0; $i < 3; $i++) {
					$amenities = array_slice($room->details->roomAmenities->RoomAmenity, $i * $amenitiesSlice, $amenitiesSlice);
					if (0 == count($amenities)) {
						break;
					}
					$amenitiesArgv['list.amenities'] = '';
					foreach ($amenities as $amenity) {
						$amenitiesArgv['list.amenities'] .= $tpl->parse('hotel:availableRooms.amenities.item', array(
							'name' => $amenity->amenity
						));
					}
					$roomArgv['list.amenitiesColumns'] .= $tpl->parse('hotel:availableRooms.amenitiesColumns.item', $amenitiesArgv);
				}
			}
			$mainArgv['availableRooms'] .= $tpl->parse('hotel:availableRooms.item', $roomArgv);
		}}
		if (NULL !== $lowestPriceRoom['nr']) {
			$room = $data->_roomAvailability[$lowestPriceRoom['nr']];
			$rateInfo = $room->RateInfos->RateInfo->ChargeableRateInfo;
			$mainArgv += array(
				'url.lowestPriceBook' => $room->deepLink,
				'lowestPrice' => $this->helperCurrencyWrite(
					round($rateInfo->{'@averageRate'}),
					$rateInfo->{'@currencyCode'}
				)
			);
			if (round($rateInfo->{'@averageRate'}) != round($rateInfo->{'@averageBaseRate'})) {
				$mainArgv['lowestBasePrice'] = $this->helperCurrencyWrite(
					round($rateInfo->{'@averageBaseRate'}),
					$rateInfo->{'@currencyCode'}
				);
			}
		}
		if (isset($data->_roomAvailability) && FALSE === $data->_roomAvailability) {
			$mainArgv['availableRooms'] .= $tpl->parse('hotel:availableRooms.unavailable');
		}

		return $tpl->parse('hotel:main', $mainArgv);
	}
	
	private function renderRoomDetails($hotelId, $rateCode, $roomTypeId, $supplierType)
	{
		$searchArgv = $this->eGetSearchArgv();
		$requestArgv = array(
			'hotelId' => $hotelId
		);
		$this->helperRequestArgvTimed($requestArgv, $searchArgv);
		
		$result = $this->helperCurlGet($this->helperConstructUrl(
			'http://api.ean.com/ean-services/rs/hotel/v3/avail?', 
			'HotelInformationRequest', 
			$requestArgv + array(
				'supplierType' => $supplierType,
				'hotelId' => $hotelId,
				'rateCode' => $rateCode,
				'roomTypeCode' => $roomTypeId
			)
		));
		
		if (isset($result->HotelRoomAvailabilityResponse->HotelRoomResponse->cancellationPolicy)) {
			echo $result->HotelRoomAvailabilityResponse->HotelRoomResponse->cancellationPolicy;
		} else {
			echo $this->load_template()->parse('hotelLavailableRooms.cancellationPolicy.unavailable');
		}
	}

	private function partialRenderSearchResults($tpl, $searchArgv)
	{
		$requestArgv = array();
		if ('tournament' == $searchArgv['active_tab'] && !empty($searchArgv['tournament']) && ($tournament = $this->getActiveTournament($searchArgv['tournament'])) != NULL) {
			$tournament['geolocation'] = explode(',', $tournament['geolocation']);
			$requestArgv['latitude']  = rawurlencode(trim($tournament['geolocation'][0]));
			$requestArgv['longitude'] = rawurlencode(trim($tournament['geolocation'][1]));
		} elseif ('location' == $searchArgv['active_tab'] && !empty($searchArgv['location'])) {
			$requestArgv['destinationString'] = rawurlencode($searchArgv['location']);
		} else {
			return '';
		}
		$this->helperRequestArgvTimed($requestArgv, $searchArgv);

		$cacheId = $requestArgv;
		$cacheId[] = $searchArgv['active_tab'];
		$cacheId[] = moon::user()->id();
		$cacheId = md5(serialize($cacheId));

		$memcObj = moon_memcache::getInstance();
		$memcObjPrefix = moon_memcache::getRecommendedPrefix();
		if (
			//1 ||
			FALSE == ($cachedResult = $memcObj->get($memcObjPrefix . 'hotels.results' . $cacheId)) ||
			!is_array($cachedResult)
		) { 
			// fetch remote data
			list ($resultCode, $data) = $this->getHotelsRemote($requestArgv);
			if (self::remoteOtherError == $resultCode) {
				// retry
				$this->helperSetRemoteSessId('');
				list ($resultCode, $data) = $this->getHotelsRemote($requestArgv);
			}
			if (self::remoteHotelList == $resultCode) {
				$memcObj->set($memcObjPrefix . 'hotels.results' . $cacheId, array($resultCode, $data), MEMCACHE_COMPRESSED, 60);
			}
		} else {
			// cache
			list ($resultCode, $data) = $cachedResult;
		}

		if (in_array($resultCode, array(
			self::remoteFailedDecode,
			self::remoteOtherError
		))) {
			return $tpl->parse('list:results.error');
		}
		
		if (self::remoteHotelList == $resultCode && 0 == count($data)) {
			return $tpl->parse('list:results.empty');
		}

		if (self::remoteAmbiguousAddress == $resultCode) {
			return $tpl->parse('list:results.ambiguousloc');
		}
		
		$mainArgv = array(
			'list.results' => '',
			'list.sorts' => '',
			'paging' => ''
		);

		if (!is_array($data)) {
			$data = array(
				$data
			);
		}
		$data = array_slice($data, 0, 300);

		// sorting
		$this->helperDataSort($data, $searchArgv);
		
		// pagination output, data limit
		$paging = $this->helperPaging(isset($_GET['page']) ? intval($_GET['page']) : 1, count($data), 10, $searchArgv);
		$data_ = array();
		for ($i = max(0, $paging['pnInfo']['from']-1); $i < $paging['pnInfo']['to']; $i++) {
			$data_[] = $data[$i];
		}
		$data = $data_;
		$mainArgv['paging'] = $paging['nav'];
		// sorting output
		$mainArgv['list.sorts'] = $this->partialRenderSorting($tpl, $searchArgv);

		// hotels output
		$urlGet = $searchArgv;
		if ($paging['pnInfo']['page'] > 1) {
			$urlGet['page'] = $paging['pnInfo']['page'];
		}
		foreach ($data as $hotel) {
			$urlGet['hotelId'] = $hotel->hotelId;
			$urlGet['rateKey'] = isset($hotel->RoomRateDetailsList->RoomRateDetails->rateKey)
				? $hotel->RoomRateDetailsList->RoomRateDetails->rateKey
				: '';
			$itemArgv = array(
				'url.details' => $this->linkas('#') . '?' . htmlspecialchars(http_build_query($urlGet)),
				'name' => $hotel->name,
				'address' => $hotel->address1 . ', ' . $hotel->city,
				'hotelRating' => intval($hotel->hotelRating),
				'price' => !empty($hotel->lowRate)
					? $this->helperCurrencyWrite(intval($hotel->lowRate), $hotel->rateCurrencyCode)
					: '',
				'distance' => 'tournament' == $searchArgv['active_tab'] && $hotel->proximityDistance > 0
					? number_format($hotel->proximityDistance, 1) . ' ' . $hotel->proximityUnit
					: '',
			);
			if (isset($hotel->RoomRateDetailsList->RoomRateDetails->RateInfos->RateInfo)) { // searched with availability
				$rateInfo = $hotel->RoomRateDetailsList->RoomRateDetails->RateInfos->RateInfo;
				if (is_array($rateInfo)) {
					$rateInfo = $rateInfo[0];
				}
				if (round($rateInfo->ChargeableRateInfo->{'@averageRate'}) != round($rateInfo->ChargeableRateInfo->{'@averageBaseRate'})) {
					$itemArgv['price'] = $this->helperCurrencyWrite(
						round($rateInfo->ChargeableRateInfo->{'@averageRate'}),
						$rateInfo->ChargeableRateInfo->{'@currencyCode'}
					);
					$itemArgv['basePrice'] = $this->helperCurrencyWrite(
						round($rateInfo->ChargeableRateInfo->{'@averageBaseRate'}),
						$rateInfo->ChargeableRateInfo->{'@currencyCode'}
					);
					if (isset($hotel->RoomRateDetailsList->RoomRateDetails->promoDescription)) {
						$itemArgv['bonusInfo'] = $hotel->RoomRateDetailsList->RoomRateDetails->promoDescription;
					}
				}
			}
			if (isset($hotel->thumbNailUrl)) {
				$itemArgv['img'] = 'http://images.travelnow.com' . $hotel->thumbNailUrl;
			}
			$mainArgv['list.results'] .= $tpl->parse('list:results.item', $itemArgv);
		}
		return $tpl->parse('list:results', $mainArgv);
	}

	private function partialRenderSorting($tpl, $searchArgv)
	{
		$return = '';
		$sortNames = $tpl->parse_array('list:form.sorts');
		$sorts = array(
			'price' => 'asc',
			'proximity' => 'asc',
			'hotelname' => 'asc',
			'bestvalue' => 'desc',
			'stars' => 'desc'
		);
		if ($searchArgv['active_tab'] != 'tournament') {
			unset($sorts['proximity']);
		}
		foreach ($sorts as $key => $direction) {
			$urlDirection = ($searchArgv['sort'] == $key // if active
					? $searchArgv['sort_ord'] == 'desc' // invert direction
					: $direction == 'asc') // else use default
				? 'asc' 
				: 'desc';
			$isDesc = $searchArgv['sort'] == $key 
				? $urlDirection == 'asc' // use direction as if it is right now
				: $urlDirection == 'desc'; // use direction as if it would be when clicked
			
			$return .= $tpl->parse('list:sorts.item', array(
				'name' => $sortNames[$key],
				'url' => $this->linkas('#') . '?' . htmlspecialchars(http_build_query(array_merge($searchArgv, array(
					'sort' => $key,
					'sort_ord' => $urlDirection
				)))),
				'active' => $searchArgv['sort'] == $key,
				'desc' => $isDesc
			));
		}
		
		return $return;
	}
	
	private function partialRenderSearchFormRooms($tpl, &$searchArgv)
	{
		$mainArgv = array();
		$optionValues = $tpl->parse_array('list:form.values');
		foreach (array('rooms') as $key) {
			$values = explode(',', $optionValues[$key]);
			$mainArgv['list.' . $key] = '';
			foreach ($values as $nr => $value) {
				$mainArgv['list.' . $key] .= $tpl->parse('list:optionShort.item', array(
					'value' => intval($value),
					'name' => htmlspecialchars($value),
					'selected' => $searchArgv[$key] == intval($value)
				));
			}
			unset($searchArgv[$key]);
		}
		foreach (array('adults', 'children') as $key) {
			foreach ($searchArgv[$key] as $perRoomNr => $perRoomVal) {
				$values = explode(',', $optionValues[$key . $perRoomNr]);
				$mainArgv['list.' . $key . $perRoomNr] = '';
				foreach ($values as $nr => $value) {
					$mainArgv['list.' . $key . $perRoomNr] .= $tpl->parse('list:optionShort.item', array(
						'value' => intval($value),
						'name' => htmlspecialchars($value),
						'selected' => $searchArgv[$key][$perRoomNr] == intval($value)
					));
				}
			}
			unset($searchArgv[$key]);
		}
		foreach ($searchArgv['children_ages'] as $perRoomNr => $perRoomVal) {
			foreach ($perRoomVal as $key => $value) {
				$mainArgv['list.children_ages' . $perRoomNr . $key] = '';
				for ($i = 1; $i < 19; $i++) {
					$mainArgv['list.children_ages' . $perRoomNr . $key] .= $tpl->parse('list:optionShort.item', array(
						'value' => $i,
						'name' => $i,
						'selected' => $value == $i
					));
				}
			}
		}
		unset($searchArgv['children_ages']);

		return $tpl->parse('list:block.rooms', $mainArgv);
	}
	
	private function helperRequestArgvTimed(&$requestArgv, $searchArgv)
	{
		$dateFrom = trim($searchArgv['check_in']) != ''
			? strtotime($searchArgv['check_in'])
			: 0;
		if ($dateFrom) {
			$requestArgv['arrivalDate']   = strftime('%m/%d/%Y', $dateFrom);
			$requestArgv['departureDate'] = strftime('%m/%d/%Y', $dateFrom + (int)$searchArgv['nights']*24*3600);
			$requestArgv['rooms'] = array();
			for ($i = 0; $i < $searchArgv['rooms']; $i++) {
				$roomRequest = array(
					'numberOfAdults' => $searchArgv['adults'][$i],
				);
				if (!empty($searchArgv['children'][$i])) {
					$roomRequest['numberOfChildren'] = $searchArgv['children'][$i];
					$roomChildrenAges = array();
					foreach($searchArgv['children_ages'][$i] as $age) {
						if (!empty($age)) {
							$roomChildrenAges[] = $age;
						}
					}
					$roomChildrenAges = array_slice($roomChildrenAges, 0, $roomRequest['numberOfChildren']);
					$roomRequest['childrenAges'] = implode(',', $roomChildrenAges);
				}
				$requestArgv['rooms'][] = $roomRequest;
			}
		}
	}
	
	private function helperTextDecode($text)
	{
		$text = $this->htmlspecialcharsDecode($text);
		$text = str_replace('&#x0D;', ' ', $text);
		$text = preg_replace_callback('~<p>(.*?)</p>~', array($this, 'helperTextDecodeReplCall'), $text);
		$text = str_replace(array('<ul>', '</ul>'), array('</p><ul>', '</ul><p>'), $text);
		$text = str_replace('<p></p>', '', $text);
		$text = preg_replace('~</p>\s*(<br />\s*)+~', '</p>', $text);
		$text = preg_replace('~\.\s*</h2>~', '</h2>', $text);
		if (strpos($text, '<') === false) {
			$text = '<h2>Description</h2><p>' . $text . '</p>';
		}
		return $text;
	}

	private function htmlspecialcharsDecode($text)
	{
		$text = htmlspecialchars_decode($text);
		$text = preg_replace('~&(?![a-z]{1,9};)~', '&amp;', $text);
		return $text;
	}
	
	private function helperTextDecodeReplCall($m)
	{
		$line = $m[1];
		if ('' == trim($line)) {
			return;
		}
		if (preg_match('~<(?:strong|b)>(.+?)</(?:strong|b)>~', $line)) {
			return preg_replace('~<(?:strong|b)>(.+?)</(?:strong|b)>(\s{0,10}<br />)?~', '<h2>\1</h2>' . "\n" . '<p>', $line) . '</p>' . "\n";
		} else {
			return '<p>' . $line . '</p>' . "\n";
		}		
	}
	
	private function eGetSearchArgv()
	{
		$defaults = array(
			'active_tab' => 'tournament',
			'tournament' => '',
			'location' => '',
			'check_in' => ' ', // js perk (page load overwrite only " ")
			'nights' => '1',
			'rooms' => '1',
			'adults' => array('2', '0', '0', '0'),
			'children' => array('0', '0', '0', '0'),
			'children_ages' => array(
				array('', '', '', ''),
				array('', '', '', ''),
				array('', '', '', ''),
				array('', '', '', ''),
			),
			'sort' => 'bestvalue',
			'sort_ord' => 'desc'
		);
		$form = $this->form();
		$form->names(array_keys($defaults));
		$form->fill($_GET, FALSE);
		
		$argv = $form->get_values() + $defaults;
		
		foreach(array(
			'sort'       => array('price', 'proximity', 'hotelname', 'bestvalue', 'stars'),
			'sort_ord'   => array('asc', 'desc'),
			'active_tab' => array('tournament', 'location'),
		) as $key => $accepableValues) {
			if (!in_array($argv[$key], $accepableValues)) {
				$argv[$key] = $defaults[$key];
			}
		}
		foreach (array('nights', 'rooms') as $key) {
			$argv[$key] = max(1, intval($argv[$key]));
		}
		foreach (array('tournament') as $key) {
			$argv[$key] = max(0, intval($argv[$key]));
		}

		$argvSub = array();
		for ($i = 0; $i < count($defaults['children']); $i++) {
			$argvSub[$i] = isset($argv['children'][$i])
				? $argv['children'][$i]
				: $defaults['children'][$i];
			$argvSub[$i] = max(0, intval($argvSub[$i]));
			$argvSub[$i] = min(3, $argvSub[$i]);
		}
		$argv['children'] = $argvSub;

		$argvSub = array();
		for ($i = 0; $i < count($defaults['adults']); $i++) {
			$argvSub[$i] = isset($argv['adults'][$i])
				? $argv['adults'][$i]
				: $defaults['adults'][$i];
			$argvSub[$i] = max(0, intval($argvSub[$i]));
			$argvSub[$i] = min(3, $argvSub[$i]);
		}
		$argv['adults'] = $argvSub;
		$argv['adults'][0] = max(1, $argv['adults'][0]);

		$argvSub = array();
		for ($i = 0; $i < count($defaults['children_ages']); $i++) {
			$argvSub[$i] = isset($argv['children_ages'][$i])
				? $argv['children_ages'][$i]
				: $defaults['children_ages'][$i];
			for ($j = 0; $j < count($defaults['children_ages'][$i]); $j++) {
				$argvSub[$i][$j] = isset($argv['children_ages'][$i][$j])
					? $argv['children_ages'][$i][$j]
					: $defaults['children_ages'][$i][$j];
				if ('' == $argvSub[$i][$j]) {
					continue;
				}
				$argvSub[$i][$j] = max(0, intval($argvSub[$i][$j]));
				$argvSub[$i][$j] = min(18, $argvSub[$i][$j]);
			}
		}
		$argv['children_ages'] = $argvSub;

		foreach (array(
			'tournament', 'location', 'check_in'
		) as $key) {
			$argv[$key] = (string)$argv[$key];
		}

		return $argv;
	}
	
	private function getHotelsRemote($requestArgv)
	{
		$result = $this->helperCurlGet($this->helperConstructUrl(
			'http://api.ean.com/ean-services/rs/hotel/v3/list?', 
			'HotelListRequest', 
			$requestArgv + array(
				'options' => 'HOTEL_SUMMARY,ROOM_RATE_DETAILS',
				//'supplierType' => 'E',
				'sort' => 'OVERALL_VALUE'
			)
		));
		
		if (FALSE === $result || !isset($result->HotelListResponse)) {
			return array(self::remoteFailedDecode, NULL);
		}
		
		$result = $result->HotelListResponse;
		if (isset($result->customerSessionId)) {
			$this->helperSetRemoteSessId($result->customerSessionId);
		}
		
		if (isset($result->EanWsError) && isset($result->LocationInfos) && 0 != count($result->LocationInfos->LocationInfo)) {
			return array(self::remoteAmbiguousAddress, $result->LocationInfos->LocationInfo);
		}
		
		if (isset($result->EanWsError) || !isset($result->HotelList->HotelSummary)) {
			return array(self::remoteOtherError, NULL);
		}
		
		return array(self::remoteHotelList, $result->HotelList->HotelSummary);
	}
	
	private function getHotelRemote($requestArgv)
	{
		$result = $this->helperCurlGet($this->helperConstructUrl(
			'http://api.ean.com/ean-services/rs/hotel/v3/info?', 
			'HotelInformationRequest', 
			array(
				//'options' => 'HOTEL_SUMMARY,HOTEL_DETAILS,ROOM_TYPES,PROPERTY_AMENITIES,HOTEL_IMAGES',
				'hotelId' => $requestArgv['hotelId'],
			)
		));
		
		if (FALSE === $result || !isset($result->HotelInformationResponse)) {
			return array(self::remoteFailedDecode, NULL);
		}
		
		$result = $result->HotelInformationResponse;
		if (isset($result->customerSessionId)) {
			$this->helperSetRemoteSessId($result->customerSessionId);
		}
		
		if (isset($result->EanWsError) || !isset($result->HotelSummary)) {
			return array(self::remoteOtherError, NULL);
		}
		
		if (!isset($requestArgv['arrivalDate'])) { // request with no availability
			unset($result->RoomTypes);
			return array(self::remoteHotelInfo, $result);
		}

		if (!is_array($result->HotelImages->HotelImage)) {
			$result->HotelImages->HotelImage = array($result->HotelImages->HotelImage);
		}
		if (!is_array($result->PropertyAmenities->PropertyAmenity)) {
			$result->PropertyAmenities->PropertyAmenity = array($result->PropertyAmenities->PropertyAmenity);
		}
		
		$result->_roomAvailability = FALSE; // default special code -- not found
		//if (is_dev()) { // test account query rate often exceeded
			sleep(1);
		//}
		list ($resultCode, $data) = $this->getHotelRemoteRooms($requestArgv);
		if (self::remoteRoomList == $resultCode) {
			if (!is_array($data)) {
				$data = array($data);
			}
			$result->_roomAvailability = $data;
			
			if (isset($result->RoomTypes->RoomType)) {
				$roomTypes = array();
				if (!is_array($result->RoomTypes->RoomType)) {
					$result->RoomTypes->RoomType = array($result->RoomTypes->RoomType);
				}
				foreach ($result->RoomTypes->RoomType as $roomType) {
					$roomTypes[$roomType->{'@roomCode'}] = $roomType;
				}
				foreach ($result->_roomAvailability as $k => $rAv) {
					if (isset($roomTypes[$rAv->roomTypeCode])) {
						$result->_roomAvailability[$k]->details = $roomTypes[$rAv->roomTypeCode];
					}
				}
			}
			unset($result->RoomTypes);
			
			foreach ($result->_roomAvailability as $k => $room) {
				// $room is somehow only a pointer, but for the sake of consistency, use _rAv[k] to overwrite
				if (isset($room->details->roomAmenities->RoomAmenity) && !is_array($room->details->roomAmenities->RoomAmenity)) {
					$result->_roomAvailability[$k]->details->roomAmenities->RoomAmenity = array($room->details->roomAmenities->RoomAmenity);
				}
				if (isset($room->ValueAdds->ValueAdd) && !is_array($room->ValueAdds->ValueAdd)) {
					$result->_roomAvailability[$k]->ValueAdds->ValueAdd = array($room->ValueAdds->ValueAdd);
				}
			}
		}
		
		return array(self::remoteHotelInfo, $result);
	}
	
	private function getHotelRemoteRooms($requestArgv)
	{
		$result = $this->helperCurlGet($this->helperConstructUrl(
			'http://api.ean.com/ean-services/rs/hotel/v3/avail?', 
			'HotelRoomAvailabilityRequest', 
			$requestArgv
		));
		
		if (FALSE === $result || !isset($result->HotelRoomAvailabilityResponse)) {
			return array(self::remoteFailedDecode, NULL);
		}
		$result = $result->HotelRoomAvailabilityResponse;
		
		if (isset($result->EanWsError) || !isset($result->HotelRoomResponse)) {
			return array(self::remoteOtherError, NULL);
		}
		
		return array(self::remoteRoomList, $result->HotelRoomResponse);
	}
	
	private function helperGetRemoteCommon()
	{
		$requestArgvBase = array(
			'minorRev' => '7',
			'cid' => '336011',
			'apiKey' => 'yan356tcb7g34vv9dkyr7esu',
			'type' => 'json',
			'customerUserAgent' => $_SERVER['HTTP_USER_AGENT'],
			'customerIpAddress' => moon::user()->get_ip(),
		);
		if (is_dev()) {
			$requestArgvBase = array_merge($requestArgvBase, array(
				'cid' => '55505',
				'apiKey' => 'xk9ubjwn8vnykzeq482gz76k',
			));
		}
		if (($sessId = $this->helperGetRemoteSessId()) != '') {
			$requestArgvBase['customerSessionId'] = $sessId;
		}
		
		return $requestArgvBase;
	}
	
	private function helperConstructUrl($requestUrl, $baseElName, $requestArgv)
	{
		$requestArgvBase = $this->helperGetRemoteCommon();
		
		$xml = new SimpleXMLElement('<' . $baseElName . '/>');
		if (isset($requestArgv['rooms'])) {
			$rg = $xml->addChild('RoomGroup');
			foreach ($requestArgv['rooms'] as $room) {
				$r = $rg->addChild('Room');
				$r->addChild('numberOfAdults', $room['numberOfAdults']);
				if (!empty($room['numberOfChildren'])) {
					$r->addChild('numberOfChildren', $room['numberOfChildren']);
					$r->addChild('childAges', $room['childrenAges']);
				}
			}
			unset($requestArgv['rooms']);
		}
		foreach ($requestArgv as $k => $v) {
			$xml->addChild($k, $v);
		}
		$requestArgv = array(
			'xml' => $xml->asXML()
		);

		$requestArgv = array_merge($requestArgvBase, $requestArgv);

		foreach ($requestArgv as $k => $v) {
			$requestArgv[$k] = $k . '=' . rawurlencode($v);
		}
		$requestUrl .= implode('&', $requestArgv);
		
		return $requestUrl;
	}
	
	private function helperCurlGet($requestUrl)
	{
		$ch = curl_init($requestUrl);
		curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
		curl_setopt($ch, CURLOPT_ENCODING, ''); // auto
		curl_setopt($ch, CURLOPT_FAILONERROR, 1);
		curl_setopt($ch, CURLOPT_HTTPHEADER, array(
			'User-Agent: Pokernews CURL',
			'Accept: application/json',
		));
		//curl_setopt($ch, CURLINFO_HEADER_OUT, 1);

		$result = curl_exec($ch);
		if (curl_errno($ch) || ($result = json_decode($result)) == '') {
			curl_close($ch);
			return FALSE;
		}
		curl_close($ch);
		
		return $result;
	}
	
	private function helperSetRemoteSessId($data)
	{
		moon::page()->set_global($this->my('fullaname').'.sessid', rawurlencode($data));
	}
	
	private function helperGetRemoteSessId()
	{
		return moon::page()->get_global($this->my('fullaname').'.sessid');
	}
	
	private function getActiveTournaments()
	{
		return $this->db->array_query_assoc('
			SELECT id, name, from_date, tour FROM ' . $this->table('Tournaments') . '
			WHERE state IN(0,1) AND geolocation IS NOT NULL AND is_live=1
			ORDER BY id DESC
		');
	}
	
	private function getActiveTournament($id)
	{
		$return = $this->db->single_query_assoc('
			SELECT geolocation FROM ' . $this->table('Tournaments') . '
			WHERE id=' . intval($id) . ' AND state IN(0,1) AND geolocation IS NOT NULL
		');
		return empty($return)
			? NULL
			: $return;
	}
	
	private function helperDataSort(&$data, $searchArgv)
	{
		switch ($searchArgv['sort']) {
			case 'price':
				usort($data, array($this, 'sortPrice'));
				break;
			case 'proximity':
				usort($data, array($this, 'sortProximity'));
				break;
			case 'hotelname':
				usort($data, array($this, 'sortHotelname'));
				break;
			case 'stars':
				usort($data, array($this, 'sortStars'));
				break;
		}
		if ($searchArgv['sort_ord'] == 'asc') {
			$data = array_reverse($data);
		}
	}
	
	private function sortStars($a, $b)
	{
		$aVal = (float)$a->hotelRating;
		$bVal = (float)$b->hotelRating;
		if ($aVal == $bVal) {
			return 0;
		}
		return $aVal < $bVal ? 1 : -1;
	}
	
	private function sortHotelname($a, $b)
	{
		$aVal = $a->name;
		$bVal = $b->name;
		if ($aVal == $bVal) {
			return 0;
		}
		return $aVal < $bVal ? 1 : -1;
	}
	
	private function sortProximity($a, $b)
	{
		$aVal = (float)$a->proximityDistance;
		$bVal = (float)$b->proximityDistance;
		if ($aVal == $bVal) {
			return 0;
		}
		return $aVal < $bVal ? 1 : -1;
	}
	
	private function sortPrice($a, $b)
	{
		$aVal = (float)$a->lowRate;
		$bVal = (float)$b->lowRate;
		if ($aVal == $bVal) {
			return 0;
		}
		if ($aVal == 0) {
			return -1;
		}
		if ($bVal == 0) {
			return 1;
		}
		return $aVal < $bVal ? 1 : -1;
	}
	
	private function helperPaging($currPage, $itemsCnt, $listLimit, $getArgv = array())
	{
		$pager = moon::shared('paginate');
		$pager->set_curent_all_limit($currPage, $itemsCnt, $listLimit);
		$pager->set_url(
			$this->linkas('#') . '?' . htmlspecialchars(http_build_query($getArgv + array('page' => '{pg}'))),
			$this->linkas('#') . '?' . htmlspecialchars(http_build_query($getArgv))
		);
		$pnInfo = $pager->get_info();
		return array(
			'pnInfo' => $pager->get_info(),
			'nav' => $pager->show_nav()
		);
	}
	
	private function helperCurrencyWrite($num, $cur)
	{
		$codes=array(
			'USD' => '$',
			'EUR' => '&euro;',
			'GBP' => '&pound;',
			'BRL' => 'R$'
		);
		return isset($codes[$cur]) ? $codes[$cur] . '' . $num : $num . ' ' . $cur ;
	}	
}