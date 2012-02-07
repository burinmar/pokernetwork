<?php
class promotions extends moon_com {

	function onload() {
		$this->myTable = $this->table('PromoList');
	}

	function events($event, $par) {
		switch ($event) {
			case 'sync-activate':
				//aktyvuojam spec. freeroll nusiurbima is com
				cronTask('reviews.promotions#sync-do');
				$page = & moon :: page();
				$page->set_local('transporter', 'ok');
				break;

			case 'sync-export':
				//eksportuojam special promotions (praso transporteris)
				$promoList = array();
				if(isset($par['timestamp'])){
					$timestamp = intval($par['timestamp']);
					$promoList = $this->db->array_query_assoc("
						SELECT *  FROM ".$this->myTable."
						WHERE `updated` > ".$timestamp." AND hide=0"
						);
				}
				$page = & moon :: page();
				$page->set_local('transporter', $promoList);
				break;

			}
	}

}
?>