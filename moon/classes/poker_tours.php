<?php

function poker_tours_backend()
{
	$iDir = '/img/live_poker/tour_logos/';
	return array(
		1 => array(
			'uri'	=> 'wsop',
			'title'	=> 'World Series of Poker',
			'title_pre' => 'WSOP',
			'img1'	=> $iDir . 'wsop.png',
			'img2'	=> $iDir . 'wsop-big.png',
			'key' => 'wsop',
			'playlist_id' => 81509938001
		),
		9 => array(
			'uri'	=> 'wsope',
			'title'	=> 'World Series of Poker Europe',
			'title_pre' => 'WSOPE',
			'img1'	=> $iDir . 'wsop-europe.png',
			'img2'	=> $iDir . 'wsop-europe-big.png',
			'key' => 'wsop-europe',
			'playlist_id' => 10005954001 // default index
		),
		11 => array(
			'uri'	=> 'wsop-circuit',
			'title'	=> 'World Series of Poker Circuit',
			'title_pre' => 'WSOPC',
			'img1'	=> $iDir . 'wsop-circuit.png',
			'img2'	=> $iDir . 'wsop-circuit-big.png',
			'key' => 'wsop-circuit',
			'playlist_id' => 81234241001
		),
		2 => array(
			'uri'	=> 'wpt',
			'title'	=> 'World Poker Tour',
			'title_pre' => 'WPT',
			'img1'	=> $iDir . 'wpt.png',
			'img2'	=> $iDir . 'wpt-big.png',
			'key' => 'wpt',
			'playlist_id' => 83061431001
		),
		6 => array(
			'uri'	=> 'ept',
			'title'	=> 'European Poker Tour (EPT)',
			'title_pre' => 'EPT',
			'img1'	=> $iDir . 'ept.png',
			'img2'	=> $iDir . 'ept-big.png',
			'key' => 'ept',
			'playlist_id' => 57826444001
		),
		12 => array(
			'uri'	=> 'napt',
			'title'	=> 'North American Poker Tour',
			'title_pre' => 'NAPT',
			'img1'	=> $iDir . 'napt.png',
			'img2'	=> $iDir . 'napt-big.png',
			'key' => 'napt',
			'playlist_id' => 10005954001 // default index
		),
		5 => array(
			'uri'	=> 'lapt',
			'title'	=> 'Latin American Poker Tour',
			'title_pre' => 'LAPT',
			'img1'	=> $iDir . 'lapt.png',
			'img2'	=> $iDir . 'lapt-big.png',
			'key' => 'lapt',
			'playlist_id' => 67394885001
		),
		7 => array(
			'uri'	=> 'appt',
			'title'	=> 'Asia Pacific Poker Tour',
			'title_pre' => 'APPT',
			'img1'	=> $iDir . 'appt.png',
			'img2'	=> $iDir . 'appt-big.png',
			'key' => 'appt',
			'playlist_id' => 67393172001
		),
		8 => array(
			'uri'	=> 'rps',
			'title'	=> 'Russian Poker Series',
			'title_pre' => 'RPS',
			'img1'	=> $iDir . 'rps.png',
			'img2'	=> $iDir . 'rps-big.png',
			'key' => 'rps',
			'playlist_id' => 10005954001 // default index
		),
		10 => array(
			'uri'	=> 'apt',
			'title'	=> 'Asian Poker Tour',
			'title_pre' => 'APT',
			'img1'	=> $iDir . 'apt.png',
			'img2'	=> $iDir . 'apt-big.png',
			'key' => 'apt',
			'playlist_id' => 9142273001
		),
		3 => array(
			'uri'	=> 'aussie-millions',
			'title'	=> 'Aussie Millions',
			'title_pre' => 'Aussie Millions',
			'img1'	=> $iDir . 'aussie-millions.png',
			'img2'	=> $iDir . 'aussie-millions-big.png',
			'key' => 'aussie-millions',
			'playlist_id' => 57849371001
		),
		4 => array(
			'uri'	=> 'pokernews-cup',
			'title'	=> 'PokerNews Cup',
			'title_pre' => 'PokerNews Cup',
			'img1'	=> $iDir . 'pokernews-cup.png',
			'img2'	=> $iDir . 'pokernews-cup-big.png',
			'key' => 'pn-cup',
			'playlist_id' => 72326519001
		),
		13 => array(
			'uri'	=> 'unibet-open',
			'title'	=> 'Unibet Open',
			'title_pre'	=> 'Unibet Open',
			'img1'	=> $iDir . 'unibet-open.png',
			'img2'	=> $iDir . 'unibet-open-big.png',
			'key' => 'unibet-open',
			'playlist_id' => 72326519001
		),
		14 => array(
			'uri'	=> 'challenge',
			'title'	=> 'PokerNews Challenge',
			'title_pre'	=> 'PokerNews Challenge',
			'img1'	=> $iDir . 'challenge.png',
			'img2'	=> $iDir . 'challenge-big.png',
			'key' => 'challenge',
			'playlist_id' => 72326519001
		),
		15 => array(
			'uri'	=> 'lspf',
			'title'	=> 'Lietuvos Sportinio Pokerio Federacija',
			'title_pre'	=> 'LSPF',
			'img1'	=> $iDir . 'lspf.png',
			'img2'	=> $iDir . 'lspf-big.png',
			'key' => 'LSPF',
			'playlist_id' => 72326519001
		),
		16 => array(
			'uri'	=> 'bpt',
			'title'	=> 'Balkan Poker Tour',
			'title_pre'	=> 'BPT',
			'img1'	=> $iDir . 'bpt.png',
			'img2'	=> $iDir . 'bpt-big.png',
			'key' => 'bpt',
			'playlist_id' => 72326519001
		),
		16 => array(
			'uri'	=> 'master-classics-of-poker',
			'title'	=> 'Master Classics of Poker',
			'title_pre'	=> 'Master Classics of Poker',
			'img1'	=> $iDir . 'master-classics.png',
			'img2'	=> $iDir . 'master-classics-big.png',
			'key' => 'master-classics-of-poker',
			'playlist_id' => 72326519001
		),
		17 => array(
			'uri'	=> 'rpt',
			'title'	=> 'Russian Poker Tour',
			'title_pre'	=> 'RPT',
			'img1'	=> $iDir . 'rpt.png',
			'img2'	=> $iDir . 'rpt-big.png',
			'key' => 'rpt',
			'playlist_id' => 72326519001
		),
		18 => array(
			'uri'	=> 'asia-poker-king',
			'title'	=> 'Asia Poker King',
			'title_pre'	=> 'Asia Poker King',
			'img1'	=> $iDir . 'pokerking.png',
			'img2'	=> $iDir . 'pokerking-big.jpg',
			'key' => 'asia-poker-king',
			'playlist_id' => 72326519001
		),
		19 => array(
			'uri'	=> 'macau-poker-cup',
			'title'	=> 'Macau Poker Cup',
			'title_pre'	=> 'Macau Poker Cup',
			'img1'	=> $iDir . 'macau-pokercup.png',
			'img2'	=> $iDir . 'macau-pokercup-big.jpg',
			'key' => 'macau-poker-cup',
			'playlist_id' => 72326519001
		),
		20 => array(
			'uri'	=> 'nbc-heads-up-poker-championship',
			'title'	=> 'NBC Heads-Up Poker Championship',
			'title_pre'	=> 'NBC Heads-Up',
			'img1'	=> $iDir . 'nbc.png',
			'img2'	=> $iDir . 'nbc-big.jpg',
			'key' => 'nbc-heads-up-poker-championship',
			'playlist_id' => 72326519001
		),
		21 => array(
			'uri'	=> 'latvian-open',
			'title'	=> 'Latvian Open',
			'title_pre'	=> 'Latvian Open',
			'img1'	=> $iDir . 'lo.png',
			'img2'	=> $iDir . 'lo-big.png',
			'key' => 'latvian-open',
			'playlist_id' => 72326519001
		),
		22 => array(
			'uri'	=> 'olympic-online-poker-series',
			'title'	=> 'Olympic Online Poker Series',
			'title_pre'	=> 'Olympic Online Poker Series',
			'img1'	=> $iDir . 'olympic.png',
			'img2'	=> $iDir . 'olympic-big.png',
			'key' => 'olympic-online-poker-series',
			'playlist_id' => 72326519001
		),
		23 => array(
			'uri'	=> 'triobet-live',
			'title'	=> 'Triobet Live',
			'title_pre'	=> 'Triobet Live',
			'img1'	=> $iDir . 'triobet-live.png',
			'img2'	=> $iDir . 'triobet-live-big.png',
			'key' => 'triobet-live',
			'playlist_id' => 72326519001
		),
		24 => array(
			'uri'	=> 'eesti-naiste-pokkeriliiga',
			'title'	=> 'Eesti Naiste Pokkeriliiga',
			'title_pre'	=> 'Eesti Naiste Pokkeriliiga',
			'img1'	=> $iDir . 'eesti-naiste.png',
			'img2'	=> $iDir . 'eesti-naiste-big.png',
			'key' => 'eesti-naiste-pokkeriliiga',
			'playlist_id' => 72326519001
		),
		25 => array(
			'uri'	=> 'eesti-meistrivõistlused-pokkeris',
			'title'	=> 'Eesti Meistrivõistlused Pokkeris',
			'title_pre'	=> 'Eesti Meistrivõistlused Pokkeris',
			'img1'	=> $iDir . 'eesti-meistri.png',
			'img2'	=> $iDir . 'eesti-meistri-big.png',
			'key' => 'eesti-meistrivõistlused-pokkeris',
			'playlist_id' => 72326519001
		),
		26 => array(
			'uri'	=> 'epic-poker-league',
			'title'	=> 'Epic Poker League',
			'title_pre'	=> 'Epic Poker League',
			'img1'	=> $iDir . 'epic-poker.png',
			'img2'	=> $iDir . 'epic-poker-big.png',
			'key' => 'epic-poker-league',
			'playlist_id' => 72326519001
		),
		27 => array(
			'uri'	=> 'croatian-poker-series',
			'title'	=> 'Croatian Poker Series',
			'title_pre'	=> 'Croatian Poker Series',
			'img1'	=> $iDir . 'cps.png',
			'img2'	=> $iDir . 'cps-big.png',
			'key' => 'croatian-poker-series',
			'playlist_id' => 72326519001
		),
		28 => array(
			'uri'	=> 'eureka-poker-tour',
			'title'	=> 'Eureka Poker Tour',
			'title_pre'	=> 'Eureka Poker Tour',
			'img1'	=> $iDir . 'eureka.png',
			'img2'	=> $iDir . 'eureka-big.jpg',
			'key' => 'eureka-poker-tour',
			'playlist_id' => 72326519001
		),
		29 => array(
			'uri'	=> 'danube-poker-masters',
			'title'	=> 'Danube Poker Masters',
			'title_pre'	=> 'Danube Poker Masters',
			'img1'	=> $iDir . 'danube.png',
			'img2'	=> $iDir . 'danube-big.png',
			'key' => 'danube-poker-masters',
			'playlist_id' => 72326519001
		),
		30 => array(
			'uri'	=> 'balkan-live-turniri',
			'title'	=> 'Balkan Live Turniri',
			'title_pre'	=> 'Balkan Live Turniri',
			'img1'	=> $iDir . 'balkan.png',
			'img2'	=> $iDir . 'balkan-big.png',
			'key' => 'balkan-live-turniri',
			'playlist_id' => 72326519001
		),
		30 => array(
			'uri'	=> 'belgian-poker-series',
			'title'	=> 'Belgian Poker Series',
			'title_pre'	=> 'Belgian Poker Series',
			'img1'	=> $iDir . 'bps.png',
			'img2'	=> $iDir . 'bps-big.png',
			'key' => 'belgian-poker-series',
			'playlist_id' => 72326519001
		),
		31 => array(
			'uri'	=> 'croatian-poker-tour',
			'title'	=> 'Croatian Poker Tour',
			'title_pre'	=> 'Croatian Poker Tour',
			'img1'	=> $iDir . 'cpt.png',
			'img2'	=> $iDir . 'cpt-big.png',
			'key' => 'croatian-poker-tour',
			'playlist_id' => 72326519001
		),
		32 => array(
			'uri'	=> 'continental-poker-series',
			'title'	=> 'Continental Poker Series',
			'title_pre'	=> 'Continental Poker Series',
			'img1'	=> $iDir . 'continental.png',
			'img2'	=> $iDir . 'continental-big.png',
			'key' => 'continental-poker-series',
			'playlist_id' => 72326519001
		),
		33 => array(
			'uri'	=> 'italian-poker-tour',
			'title'	=> 'Italian Poker Tour',
			'title_pre'	=> 'Italian Poker Tour',
			'img1'	=> $iDir . 'ipt.png',
			'img2'	=> $iDir . 'ipt-big.png',
			'key' => 'italian-poker-tour',
			'playlist_id' => 72326519001
		),
		34 => array(
			'uri'	=> 'deepstacks-poker-tour',
			'title'	=> 'DeepStacks Poker Tour',
			'title_pre'	=> 'DeepStacks Poker Tour',
			'img1'	=> $iDir . 'deepstacks.png',
			'img2'	=> $iDir . 'deepstacks-big.png',
			'key' => 'deepstacks-poker-tour',
			'playlist_id' => 72326519001
		),
		35 => array(
			'uri'	=> 'paf-live',
			'title'	=> 'Paf Live',
			'title_pre'	=> 'Paf Live',
			'img1'	=> $iDir . 'paf.png',
			'img2'	=> $iDir . 'paf-big.png',
			'key' => 'paf-live',
			'playlist_id' => 72326519001
		),
		36 => array(
			'uri'	=> 'mega-poker-series',
			'title'	=> 'Mega Poker Series',
			'title_pre'	=> 'Mega Poker Series',
			'img1'	=> $iDir . 'megapokerseries.png',
			'img2'	=> $iDir . 'megapokerseries-big.png',
			'key' => 'mega-poker-series',
			'playlist_id' => 72326519001
		),
		37 => array(
			'uri'	=> 'cercle-cadet-paris',
			'title'	=> 'Cercle Cadet Paris',
			'title_pre'	=> 'Cercle Cadet Paris',
			'img1'	=> $iDir . 'cercle-cadet-paris.png',
			'img2'	=> $iDir . 'cercle-cadet-paris-big.png',
			'key' => 'cercle-cadet-paris',
			'playlist_id' => 72326519001
		),
		38 => array(
			'uri'	=> 'anzpt',
			'title'	=> 'Australia New Zealand Poker Tour',
			'title_pre'	=> 'Australia New Zealand Poker Tour',
			'img1'	=> $iDir . 'anzpt.png',
			'img2'	=> $iDir . 'anzpt-big.png',
			'key' => 'anzpt',
			'playlist_id' => 72326519001
		),
	);
}