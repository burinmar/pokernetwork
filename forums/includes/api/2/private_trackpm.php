<?php
/*======================================================================*\
|| #################################################################### ||
|| # vBulletin 4.2.2 Patch Level 4 - Licence Number VBF4B68250
|| # ---------------------------------------------------------------- # ||
|| # Copyright �2000-2015 vBulletin Solutions Inc. All Rights Reserved. ||
|| # This file may not be redistributed in whole or significant part. # ||
|| # ---------------- VBULLETIN IS NOT FREE SOFTWARE ---------------- # ||
|| # http://www.vbulletin.com | http://www.vbulletin.com/license.html # ||
|| #################################################################### ||
\*======================================================================*/
if (!VB_API) die;

$VB_API_WHITELIST = array(
	'response' => array(
		'HTML' => array(
			'confirmedreceipts' => array(
				'startreceipt',
				'endreceipt',
				'numreceipts',
				'receiptbits' => array(
					'*' => array(
						'receipt' => array(
							'receiptid', 'send_time',
							'read_time', 'title', 'tousername'
						)
					)
				),
				'counter'
			),
			'unconfirmedreceipts' => array(
				'startreceipt',
				'endreceipt',
				'numreceipts',
				'receiptbits' => array(
					'*' => array(
						'receipt' => array(
							'receiptid', 'send_time',
							'read_time', 'title', 'tousername'
						)
					)
				),
				'counter'
			)
		)
	),
	'show' => array(
		'readpm', 'receipts', 'pagenav'
	)
);

function api_result_prerender_2($t, &$r)
{
	switch ($t)
	{
		case 'pm_receiptsbit':
		case 'private_trackpm_receiptbit':
			$r['receipt']['send_time'] = $r['receipt']['sendtime'];
			$r['receipt']['read_time'] = $r['receipt']['readtime'];
			break;
	}
}

vB_APICallback::instance()->add('result_prerender', 'api_result_prerender_2', 2);

/*======================================================================*\
|| ####################################################################
|| # Downloaded: 09:13, Fri Apr 3rd 2015
|| # CVS: $RCSfile$ - $Revision: 35584 $
|| ####################################################################
\*======================================================================*/