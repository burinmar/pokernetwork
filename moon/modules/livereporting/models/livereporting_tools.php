<?php
/**
 * @package livereporting
 */
/**
 */
require_once 'livereporting_model_pylon.php';
/**
 * Various common helpers, acl, etc.
 * @package livereporting
 * @subpackage models
 */
class livereporting_tools extends livereporting_model_pylon
{
	/**
	 * Simple ACL
	 * @param <string> $action
	 * @param <array> $data
	 * @return <bool> 
	 */
	function isAllowed($action, $data = NULL)
	{
		static $iAdminRepo;
		if (!$iAdminRepo) {
			 $iAdminRepo = moon::user()->i_admin('reporting');
		}
		switch ($action) {
			case 'viewLogHidden':
			case 'writeContent': // do not depend on [A], or check ipn reswitch
				return $iAdminRepo;

			default:
				return FALSE;
		}
	}
	
	/**
	 * Does its best to make an excerpt from a piece plain text / html
	 * 
	 * @param <string> $text Text to process
	 * @param <int> $length Desired length in symbols
	 * @param <int> $length_br Desired length in lines (br ?)
	 * @param <string> $ending Tail to append on truncation
	 * @param <bool> $exact Cut exactly, or shift towards the nearest word end
	 * @param <bool> $considerHtml The input is html
	 * @return <string>
	 */
	function helperHtmlExcerpt($text, $length, $length_br, $ending = '...', $exact = true, $considerHtml = false)
	{
		if ($considerHtml) {
			// if the plain text is shorter than the maximum length, return the whole text
			if (mb_strlen(preg_replace('/<.*?>/', '', $text)) <= $length) {
				return $text;
			}
			//remove html comments
			if (strpos($text, '<!--')) {
				$text=preg_replace("/<!--(.*?)-->/s",'',$text);
			}
			// splits all html-tags to scanable lines
			preg_match_all('/(<.+?>)?([^<>]*)/s', $text, $lines, PREG_SET_ORDER);
			$total_length = mb_strlen($ending);
			$total_br = 0;
			$open_tags = array();
			$truncate = '';
			foreach ($lines as $line_matchings) {
				// if there is any html-tag in this line, handle it and add it (uncounted) to the output
				if (!empty($line_matchings[1])) {
					// if it's an "empty element" with or without xhtml-conform closing slash (f.e. <br/>)
					if (preg_match('/^<(\s*.+?\/\s*|\s*(img|br|input|hr|area|base|basefont|col|frame|isindex|link|meta|param)(\s.+?)?)>$/is', $line_matchings[1])) {
						// do nothing
					// if tag is a closing tag (f.e. </b>)
					} else if (preg_match('/^<\s*\/([^\s]+?)\s*>$/s', $line_matchings[1], $tag_matchings)) {
						// delete tag from $open_tags list
						$pos = array_search($tag_matchings[1], $open_tags);
						if ($pos !== false) {
							unset($open_tags[$pos]);
						}
					// if tag is an opening tag (f.e. <b>)
					} else if (preg_match('/^<\s*([^\s>!]+).*?>$/s', $line_matchings[1], $tag_matchings)) {
						// add tag to the beginning of $open_tags list
						array_unshift($open_tags, strtolower($tag_matchings[1]));
					}
					// add html-tag to $truncate'd text
					$truncate .= $line_matchings[1];
				}
				// calculate the length of the plain text part of the line; handle entities as one character
				$content_length = mb_strlen(preg_replace('/&[0-9a-z]{2,8};|&#[0-9]{1,7};|[0-9a-f]{1,6};/i', ' ', $line_matchings[2]));
				$br_length = substr_count($line_matchings[1], '<br');
				if (($total_length+$content_length> $length) || ($total_br+$br_length > $length_br)) {
					// the number of characters which are left
					$left = $length - $total_length;
					if ($left < min ($length *.05, 100)) {
						$truncate .= $line_matchings[2];
						break;
					}
					$entities_length = 0;
					// search for html entities
					if (preg_match_all('/&[0-9a-z]{2,8};|&#[0-9]{1,7};|[0-9a-f]{1,6};/i', $line_matchings[2], $entities, PREG_OFFSET_CAPTURE)) {
						// calculate the real length of all entities in the legal range
						foreach ($entities[0] as $entity) {
							if ($entity[1]+1-$entities_length <= $left) {
								$left--;
								$entities_length += mb_strlen($entity[0]);
							} else {
								// no more characters left
								break;
							}
						}
					}
					$truncate .= mb_substr($line_matchings[2], 0, $left+$entities_length);
					// maximum lenght is reached, so get off the loop
					break;
				} else {
					$truncate .= $line_matchings[2];
					$total_length += $content_length;
					$total_br += $br_length;
				}
				// if the maximum length is reached, get off the loop
				if($total_length>= $length) {
					break;
				}
			}
		} else {
			if (mb_strlen($text) <= $length) {
				return $text;
			} else {
				$truncate = mb_substr($text, 0, $length - mb_strlen($ending));
			}
		}
		// if the words shouldn't be cut in the middle...
		if (!$exact) {
			if ($total_br == $length_br) {
				// ended with br
			} else {
				$spacepos = mb_strrpos($truncate, ' ');
				$tagopos = mb_strrpos($truncate, '<');
				$tagcpos = mb_strrpos($truncate, '>');
				if (isset($tagcpos) && $spacepos > $tagopos && $spacepos < $tagcpos) {
					$truncate = mb_substr($truncate, 0, $tagcpos + 1);
				} elseif (isset($spacepos) && $spacepos>max((int)$tagopos,(int)$tagcpos)) {
					$truncate = mb_substr($truncate, 0, $spacepos);
				}
			}
		}
		if ($total_br == $length_br) {
			$truncate .= '<br />' . $ending;
		} else {
			$truncate .= $ending;
		}
		if ($considerHtml) {
			// close all unclosed html-tags
			foreach ($open_tags as $tag) {
				$truncate .= '</' . $tag . '>';
			}
		}
		if (2 === $considerHtml) {
			$truncate = preg_replace('~<table.*?</table>~', '', $truncate);
		}
		return $truncate;
	}
	
	/**
	 * Reads data of the common reporting datepicker form
	 * @param <array> $data
	 * @param <string> $prefix
	 * @return <timestamp> 
	 */
	function helperCustomDatetimeRead($data, $prefix = NULL)
	{
		if (NULL !== $prefix) {
			// get rid of prefix
		}
		$keys = explode('_', implode('_', array_keys($data)));
		$values = explode('_', preg_replace('~[^0-9a-z\-+]~i','_',implode('_', $data)));
		$data = array();
		foreach ($keys as $k => $key) {
			$data[$key] = $values[$k];
		}
		$ts = gmmktime($data['hour'], $data['minute'], $data['second'], $data['month'], $data['day'], $data['year']);
		if (!empty($data['timeshift'])) {
			$data['timeshift'] = intval($data['timeshift']);
			$ts -= $data['timeshift'];
		}
		return $ts;
	}

	/**
	 * Writes the common reporting datepicker form
	 * @param <string> $tpl Datepicker format
	 * @param <timestamp> $ts
	 * @param <int> $tz
	 * @param <string> $prefix
	 * @return <string> Html form 
	 */
	function helperCustomDatetimeWrite($tpl, $ts, $tz = NULL, $prefix = NULL)
	{
		$result = '';
		$locale = &moon::locale();
		preg_match_all('~([+\-\~#])([YmdHMSz](?:.[YmdHMSz])*)~', $tpl, $matches);
		list($Y, $m, $d, $H, $M, $S) = explode(',', gmdate('Y,m,d,H,i,s', $ts));
		foreach ($matches[1] as $k => $mFlag) {
			$tTokens = str_split($matches[2][$k]);
			$name = array();
			$size = 0;
			$value = '';
			foreach ($tTokens as $tToken) {
				if ($mFlag == '#') {
					if ($tToken == 'm') {
						$result .= '<select name="' . $prefix . 'month">';
						$months = $locale->months_names();
						foreach ($months as $k2 => $month) {
							$result .= '<option value="' . $k2 . '"' . ($k2 == $m ? ' selected="selected"' : '') . '>' . $month . '</option>';
						}
						$result .= '</select>';
						continue(2);
					} else {
						$mFlag = '+';
					}
				}
				switch ($tToken) {
					case 'Y':
						$name[] = 'year';
						$size += 4;
						$value .= $Y;
						break;
					case 'm':
						$name[] = 'month';
						$size += 2;
						$value .= $m;
						break;
					case 'd':
						$name[] = 'day';
						$size += 2;
						$value .= $d;
						break;
					case 'H':
						$name[] = 'hour';
						$size += 2;
						$value .= $H;
						break;
					case 'M':
						$name[] = 'minute';
						$size += 2;
						$value .= $M;
						break;
					case 'S':
						$name[] = 'second';
						$size += 2;
						$value .= $S;
						break;
					case 'z':
						$name[] = 'timeshift';
						$size += 4;
						$value .= $tz;
						break;
					default:
						$size ++;
						$value .= $tToken;
						break;
				}
			}
			switch ($mFlag) {
				case '-':
					$result .= '<input type="hidden" name="' . $prefix . implode('_', $name) . '" size="' . $size . '" value="' . $value . '" />';
					break;
				case '~':
					$result .= $value . ' <input type="hidden" name="' . $prefix . implode('_', $name) . '" size="' . $size . '" value="' . $value . '" />';
					break;
				default:
					$result .= '<input type="text" name="' . $prefix . implode('_', $name) . '" size="' . $size . '" value="' . $value . '" />';
					break;
			}
		}
		return $result;
	}

	/**
	 * Formats currency
	 * @param <string> $num Amount
	 * @param <string> $cur Currency
	 * @return <string>
	 */
	function helperCurrencyWrite($num, $cur)
	{
		$codes=array(
			'USD' => '$',
			'EUR' => '&euro;',
			'GBP' => '&pound;',
			'BRL' => 'R$'
		);
		return isset($codes[$cur]) ? $codes[$cur] . '' . $num : $num . ' ' . $cur ;
	}
	
	/**
	 * Formats player status
	 * @param <string> $rawStatus
	 * @param <string> $sponsor
	 * @return <string> 
	 */
	function helperPlayerStatus($rawStatus, $sponsor = NULL)
	{
		$status = $rawStatus;
		if (strpos($status, '* ') !== FALSE) {
			$status = str_replace('* ', '', $status);
		} elseif ($sponsor !== NULL) {
			$status = trim($sponsor) . (trim($status) != ''
				? ' ' . trim($status) . ''
				: '');
		}
		if (strpos($status, ':') !== FALSE) {
			$status = explode(':', $status, 2);
			$status = trim($status[0]) . ' (' . trim($status[1]) . ')';
		}
		return trim($status);
	}

	private $cardSuits = array(
		'd' => 'Diamonds',
		'c' => 'Clubs',
		's' => 'Spades',
		'h' => 'Hearts',
		'x' => 'Unknown'
	);
	private $cardColors = array(
		'd' => 'Red',
		'c' => 'Black',
		's' => 'Black',
		'h' => 'Red',
		'x' => 'Black',
	);
	private $cardSuitsHtml = array(
		'd' => '&diams;',
		'c' => '&clubs;',
		's' => '&spades;',
		'h' => '&hearts;',
		'x' => 'x'
	);
	function helperFancyCards($cardsSrc, &$tpl)
	{
		$cards = '';
		$cardsSrc = str_replace(array(' ', ','), '', $cardsSrc);
		$winningCards = array();
		preg_match_all('~[0-9jkqa]+[dcshx]~', $cardsSrc, $winningCards);
		$winningCards = $winningCards[0];
		foreach ($winningCards as $c) {
			$c = array(
				0 => substr($c, 0, strlen($c) - 1),
				1 => $c[strlen($c) - 1]
			);
			$card = array(
				'color' => $this->cardColors[$c[1]],
				'value' => strtoupper($c[0]),
				'suit' => $this->cardSuits[$c[1]],
				'isuit' => strtolower($this->cardSuits[$c[1]]),
				'valueRaw' => $this->cardSuitsHtml[$c[1]]
			);
			$cards .= $tpl->parse('tour:cards.item', $card) ;
		}
		return $cards;
	}
	
	function helperFancyDate($fromDate, $toDate, &$locale)
	{
		$date = '';
		/* @var $locale moon_locale */
		$from = $locale->gmdatef($fromDate, 'liveRepoMonthDay');
		if ($toDate == NULL) {
			return $from . ', ' . gmdate('Y', $fromDate);
		}
		$to = $locale->gmdatef($toDate, 'liveRepoMonthDay');
		if (($yFrom = gmdate('Y', $fromDate)) != ($yTo = gmdate('Y', $toDate))) {
			$date = $from . ', ' . $yFrom . ' - ' . $to . ', ' . $yTo;
		} elseif (($mFrom = gmdate('m', $fromDate)) != ($mTo = gmdate('m', $toDate))) {
			$date = $from . ' - ' . $to . ', ' . gmdate('Y', $fromDate);
		} elseif (($dFrom = gmdate('d', $fromDate)) != ($dTo = gmdate('d', $toDate))) {
			$date = $from . '-' . $dTo . ', ' . gmdate('Y', $fromDate);
		} else {
			$date = $from . ', ' . gmdate('Y', $fromDate);
		}
		return $date;
	}
	
	function helperGetLogosSkins($tournament, $skins, $tours, $argv)
	{
		$ls = array();
		if (!empty($tournament['logo'])) { // if has logo uploaded
			$ls['logo'] = $argv['logoDir'] . $tournament['logo'];
		} elseif ($tournament['skin'] == 5) { // if skin is chosen to be tour-category(tour) dependent
			if (in_array($argv['skin5LogoSuffix'], array('def', 'idx'))) {
				$ls['logo'] = sprintf($skins['img'], $tournament['skin'], $argv['skin5LogoSuffix']);
			} else {
				$key = isset($tours[$tournament['tour']]['key'])
					? $tours[$tournament['tour']]['key']
					: 'default';
				$ls['logo'] = sprintf($skins['tours'], $key . $argv['skin5LogoSuffix']);
			}
		} else { // generic logo without any titles
			$ls['skin_logo'] = sprintf($skins['img'], $tournament['skin'], $argv['skinNLogoSuffix']);
		}
		$ls['skin_color'] = (!empty($tournament['logo_bgcolor']))
			? $tournament['logo_bgcolor']
			: $skins['color'][$tournament['skin']];

		return $ls;
	}
}