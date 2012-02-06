<?php
class htmlToCode {
	var $htmlObjects = array();
	var $images = array();
	var $extImages = array();
	var $urls = array();
	var $cards = array();
	var $siteDomain = '';
	var $objId = 0;
	var $hTag = 'h1';
	var $externalImagesAsObj = FALSE;
	
	//tmp
	var $newRootUri = '/novosti/';
	var $forumRootUri = '/poker-forum/';
	
	function parse($s) {
		// replace comments
		$s = preg_replace("/<\!--[^(-->)]+.-->/sm", '', $s);
		$s = str_replace(array('<!--[if gte vml 1]>', '<![endif]-->', '></param>'), array('', '', '/>'), $s);
		$this->urls = $this->cards = $this->htmlObjects = $this->extImages = $this->images = $this->errors = array();
		$this->parseTables($s);
		$s = $this->parseHTML($s);		
		$this->replaceSpecChars($s);
		return $s;
	}
	
	function replaceSpecChars(&$s) {
		$s = str_replace(array('&scaron;', '&Scaron;', '&nbsp;', '&bdquo;', '&ldquo;', '&quot;', '&ndash;', '&rdquo;', '&hellip;', '&lsquo;', '&rsquo;', '&euro;', '&amp;', '&pound;', '&ordm;', '<o:p>', '</o:p>', '&middot;', '&bull;', '&mdash;'), 
		                 array('š',        'Š',        ' ',      '„',       '“',       '"',      '–',       '”',       '…',        '‘',       '’',       '€',      '&',     '£',       '?',      '',      '',       '·',        '•',      '—'), $s);
	}

	function mbChar(&$str, &$index, &$sPos) {
		//if (!isset($str[$index])) return NULL;
		/*$cod = ord($str[$index]);
		if ($cod<192) $st=1;
		elseif ($cod<224) $st=2;
		elseif ($cod<240) $st=3;
		else $st=4;*/
		$char = substr($str, $sPos, 1);
		if ('' === $char || FALSE === $char) return NULL;
		//$index += 1;
		$sPos++;
		return $char;
	}

	function getTags($s, $filter = array()) {
		$tags = array();
		$notClosed = array();
		$tagName = '';
		$tag = '';
		$findTag = FALSE;
		$j = $i = 0;
		$pos = 0;
		$char = '';
		while (NULL !== $char) {
			$char = $this->mbChar($s, $j, $pos);
			// tag
			$closingTag = FALSE;
			if ('<' === $char) {
				$char = $this->mbChar($s, $j, $pos);
				if ('' === $tag) {
					$startPos = $pos;
					if ('/' === $char) $closingTag = TRUE;
				}
				$findTag = TRUE;
				while (NULL !== $char && '>' !== $char && '<' !== $char) {
					$tag .= $char;
					if (TRUE === $findTag) {
						if ("\t" === $char || "\n" === $char || "\r" === $char || ' ' === $char) {
							if ('' !== $tagName) $findTag = FALSE;
							$char = $this->mbChar($s, $j, $pos);
							continue;
						}
						$tagName .= $char;
					}
					$char = $this->mbChar($s, $j, $pos);
				}
				$tagName = rtrim(strtolower($tagName), '/');
				if (in_array($tagName, $filter)) {
					$tag = '<' . $tag . '>';
					if (TRUE === $closingTag) {
						if (0 < count($notClosed)) {
							end($notClosed); $k = key($notClosed); $notClosed[$k][4] = array($startPos - 2);
							$notClosed[$k][4][1] = $pos - $notClosed[$k][4][0];
							$notClosed[$k][4][2] = $tagName;
							$notClosed[$k][4][3] = $tag;
							unset($notClosed[$k]);
						} else {
							$k = $startPos - 2;
							$this->errors[] = array('getTags: tag have not start tag', array($k, $pos -$k, $tagName, $tag));
						}
					} else {
						$i++; $tags[$i] = array($startPos - 2); $notClosed[] = &$tags[$i];
						$tags[$i][1] = $pos - $tags[$i][0];
						$tags[$i][2] = $tagName;
						$tags[$i][3] = $tag;
						$tags[$i][4] = array(NULL, NULL, NULL, NULL);
					}
				}
				$tagName = $tag = '';
			}
		}
		return $tags;
	}
	
	function parseTables(&$s) {
		$tags = $this->getTags($s, array('table', '/table', 'tr', '/tr', 'th', '/th', 'td', '/td'));
		if (0 === count($tags)) return $s;
		$substrReplace = $tables = array();
		$endPos = -1;
		$tableIndex = 0;
		$c = count($tags);
		for ($i = 1; $i < count($tags); $i++) {
			if (NULL === $tags[$i][4][0]) {
				$this->errors[] = array('parseTables: no end tag', $tags[$i]);
				$tags[$i][4] = array(0, 0, '/' . $tags[$i], '</' . $tags[$i] . '>');
			}
			if ($tags[$i][0] > $endPos) {
				$endPos = $tags[$i][4][0];
				$tables[$i] = NULL;
				$tableIndex = $i;
			} elseif ('table' === $tags[$i][2] && !array_key_exists($tableIndex, $substrReplace)) {
				$substrReplace[$tableIndex] = ++$this->objId;
				unset($tables[$tableIndex]);
			} elseif (('td' === $tags[$i][2] || 'th' === $tags[$i][2]) && !array_key_exists($tableIndex, $substrReplace)) {
				if (FALSE !== strpos(strtolower($tags[$i][3]), 'colspan')) {
					$substrReplace[$tableIndex] = ++$this->objId;
					unset($tables[$tableIndex]);
				} else{
					$html = strtolower(substr($s, $tags[$i][0], $tags[$i][4][0] + $tags[$i][4][1] - $tags[$i][0]));
					if (FALSE !== strpos($html, '<br') || FALSE !== strpos($html, '<li')) {
						$substrReplace[$tableIndex] = ++$this->objId;
						unset($tables[$tableIndex]);
					}
				}
			}
		}
		$replace = array();
		if (0 < count($tables)) foreach ($tables as $id => $v) {
			$tblTag = strtolower($tags[$id][3]);
			$width = $this->getAttributeValue($tblTag, 'width');
			if ('' === $width) {
				$style = $this->getStyles($tblTag, array('width'));
				if (1 === count($style)) $width = $style[0];
			}
			if ('' !== $width && FALSE === strpos($width, '%')) $width = '';
			$html = substr($s, $tags[$id][0], $tags[$id][4][0] + $tags[$id][4][1] - $tags[$id][0]);
			$parsed = $this->parseHtml($html);
			$tableTags = $this->getTags($parsed, array('tr', '/tr', 'th', '/th', 'td', '/td'));
			$cellsCount = 0;
			$rows = array();
			$n = $r = $c = $cellsCount = 0;
			foreach ($tableTags as $t) {
				if ('tr' === $t[2]) {
					$r++;
					$n = $r;
					if ($c > $cellsCount) $cellsCount = $c;
					$c = 0; 
					continue;
				}
				$c++;
				if ('th' == $t[2]) $n = -$r;
				$pos = $t[0] + $t[1];
				$rows[$n][$c] = substr($parsed, $pos, $t[4][0] - $pos);
			}
			if ($c > $cellsCount) $cellsCount = $c;
			$code = "\r\n" . '[TABLE' . ('' !== $width ? '="' . $width . '"' : '') . ']' . "\r\n";
			foreach ($rows as $k => $cells) {
				for ($j = 1; $j <= $cellsCount; $j++) {
					$innerText = '';
					if (array_key_exists($j, $cells)) $innerText = $cells[$j];
					if (0 > $k && 1 === $j) $code .= '*';
					if ('' !== $innerText) $innerText = str_replace(array("\r", "\n"), '', trim($innerText));
					$code .= $innerText;
					if ($cellsCount !== $j) $code .= '|';
					elseif (0 > $k) $code .= '*';
				}
				$code .= "\r\n";
			}
			$code .= '[/TABLE]' . "\r\n";
			$replace[0][] = $html;
			$replace[1][] = $code;
		}
		$shift = 0;
		if (!empty($substrReplace)) foreach ($substrReplace as $id => $objId) {
			$v = array('{id:' . $objId . '}', $tags[$id][0] + $shift, $tags[$id][4][0] + $tags[$id][4][1] + $shift);
			$len = $v[2] - $v[1];
			$this->htmlObjects[$objId] = substr($s, $v[1], $len);
			$s = substr($s, 0, $v[1]) . $v[0] . substr($s, $v[2]);
			$shift += mb_strlen($v[0], '8bit') - $len;
		}
		if (!empty($replace)) $s = str_replace($replace[0], $replace[1], $s);
	}

	function parseHTML($s) {
		$strLen = strlen($s);
		$tags = array();
		$notClosed = array();
		$tagName = '';
		$tag = '';
		$findTag = FALSE;
		$j = $i = 0;
		$pos = 0;
		$char = '';
		while (NULL !== $char) {
			$char = $this->mbChar($s, $j, $pos);
			// tag 
			if ('<' === $char) {
				$char = $this->mbChar($s, $j, $pos);
				if ('' === $tag) {
					$closingTag = $noStartTag = FALSE;
					if ('/' === $char) {
						$closingTag = TRUE;
						if (0 < count($notClosed)) {
							end($notClosed); $k = key($notClosed); $notClosed[$k][4] = array($pos - 2); $a = &$notClosed[$k][4]; unset($notClosed[$k]);
						} else {
							$noStartTag = TRUE;
							$a = $tags[$i][4] = array($pos - 2);
							$a = &$tags[$i][4];
						}
					} else {$i++; $tags[$i] = array($pos - 2); $notClosed[] = &$tags[$i]; $a = &$tags[$i]; end($notClosed); $k = key($notClosed);}
				}
				// comment
				if ('!' === $char && ('DOCTYPE' !== substr($s, $j, 7) && '[CDATA'  !== substr($s, $j, 6))) {
					$tag .= $char;
					$tagName = 'comment';
					$char = $this->mbChar($s, $j, $pos);
					while ($char !== NULL) {
						$tag .= $char;
						// end tag
						if ('-' === $char) {
							$commentEnd = '';
							while (' ' === $char || '-' === $char || '>' === $char || "\n" === $char  || "\r" === $char) {
								$char = $this->mbChar($s, $j, $pos);
								$tag .= $char;
								if ('-' === $char || '>' === $char) $commentEnd .= $char;
								if ('>' === $char) break;
							}
							if ('->' === $commentEnd) {
								$tag = '<' . $tag;
								break;
							}
						}
						$char = $this->mbChar($s, $j, $pos);
					}
				} else {
					$findTag = TRUE;
					while (NULL !== $char && '>' !== $char && '<' !== $char) {
						$tag .= $char;
						if (TRUE === $findTag) {
							if ("\t" === $char || "\n" === $char || "\r" === $char || ' ' === $char) {
								if ('' !== $tagName) $findTag = FALSE;
								$char = $this->mbChar($s, $j, $pos);
								continue;
							}
							$tagName .= $char;
						}
						$char = $this->mbChar($s, $j, $pos);
					}
					$tagName = strtolower(rtrim($tagName, '/'));
					if (FALSE === $closingTag) {
						if ('link' === $tagName || 'input' === $tagName || 'meta' === $tagName || 'comment' === $tagName || 'img' === $tagName || 'param' === $tagName || 'br' === $tagName || 'hr' === $tagName) unset($notClosed[$k]);
						else $a[4] = array(NULL, NULL, NULL, NULL);
					}
					$tag = '<' . $tag . '>';
				}
				$a[1] = $pos - $a[0];
				$a[2] = $tagName;
				$a[3] = $tag;
				if (TRUE === $noStartTag) $this->errors[] = array('parseHTML: tag have not start tag', $a);
				$tagName = $tag = '';
			}
		}
		$replace = $replace2 = $replace1 = array(array(), array());
		$substrReplace = $substrReplaceTags = $replacedEndTags = $replacedStartTags = array();
		$objectPos = 0;
		foreach ($tags as $k => $v) {
			for ($n = 0; $n < (array_key_exists(4, $v) ? 2 : 1); $n++) {
				$replaced = FALSE;
				if (1 == $n) $v = $v[4];
				else {
					if (FALSE === array_key_exists(0, $v)) {
						$this->errors[] = array('parseHTML: no data', $v);
						continue;
					}
					if (array_key_exists(4, $v)) {
						if (NULL === $v[4][0]) {
							$this->errors[] = array('parseHTML: no end tag', $v);
							//$missingTag = '</' . $v[2] . '>';
							//$v[4] = array($strLen, strlen($missingTag), '/' . $v[2], $missingTag);
							//$s .= $missingTag;
							//$strLen = strlen($s);
						} elseif (!$v[4][2]) $this->errors[] = array('parseHTML: no end tag name', $v);
						elseif (FALSE === strpos('/' . $v[2], $v[4][2])) $this->errors[] = array('parseHTML: start and end tags not match', $v);
					}
				}
				$tag = $v[2];
				switch ($tag) {
					case 'h2':
					case 'strong':
					case 'b':
						if (in_array($v[3], $replacedStartTags)) break;
						$replacedStartTags[] = $v[3];
						$replace[0][] = $v[3];
						$replace[1][] = '[B]';
						break;
					case '/h2':
					case '/strong':
					case '/b':
						if (in_array($v[3], $replacedEndTags)) break;
						$replacedEndTags[] = $v[3];
						$replace[0][] = $v[3];
						$replace[1][] = '[/B]';
						break;
					case 'i':
					case 'em':
						if (in_array($v[3], $replacedStartTags)) break;
						$replacedStartTags[] = $v[3];
						$replace[0][] = $v[3];
						$replace[1][] = '[I]';
						break;
					case '/i':
					case '/em':
						if (in_array($v[3], $replacedEndTags)) break;
						$replacedEndTags[] = $v[3];
						$replace[0][] = $v[3];
						$replace[1][] = '[/I]';
						break;
					case 'u':
						if (in_array($v[3], $replacedStartTags)) break;
						$replacedStartTags[] = $v[3];
						$replace[0][] = $v[3];
						$replace[1][] = '[U]';
						break;
					case '/u':
						if (in_array($v[3], $replacedEndTags)) break;
						$replacedEndTags[] = $v[3];
						$replace[0][] = $v[3];
						$replace[1][] = '[/U]';
						break;
					case 'q':
						if (in_array($v[3], $replacedStartTags)) break;
						$replacedStartTags[] = $v[3];
						$replace[0][] = $v[3];
						$replace[1][] = '[QUOTE]';
						break;
					case '/q':
						if (in_array($v[3], $replacedEndTags)) break;
						$replacedEndTags[] = $v[3];
						$replace[0][] = $v[3];
						$replace[1][] = '[/QUOTE]';
						break;
					case $this->hTag:
						if (in_array($v[3], $replacedStartTags)) break;
						$replacedStartTags[] = $v[3];
						$replace[0][] = $v[3];
						$replace[1][] = '[H]';
						break;
					case '/' . $this->hTag:
						if (in_array($v[3], $replacedEndTags)) break;
						$replacedEndTags[] = $v[3];
						$replace[0][] = '</' . $this->hTag . '>';
						$replace[1][] = '[/H]';
						break;
					case 'ul':
						if (in_array($v[3], $replacedStartTags)) break;
						$replacedStartTags[] = $v[3];
						$replace[0][] = $v[3];
						$replace[1][] = "\r\n" . '[LIST]';
						break;
					case '/ul':
						if (in_array($v[3], $replacedEndTags)) break;
						$replacedEndTags[] = $v[3];
						$replace[0][] = $v[3];
						$replace[1][] = "\r\n" . '[/LIST]' . "\r\n";
						break;
					case 'ol':
						if (in_array($v[3], $replacedStartTags)) break;
						$replacedStartTags[] = $v[3];
						$type = $this->getAttributeValue($v[3], 'type');
						if ('1' == $type) $replace[1][] = "\r\n" . '[LIST="1"]';
						elseif ('a' == $type) $replace[1][] = "\r\n" . '[LIST="a"]';
						else $replace[1][] = "\r\n" . '[LIST]';
						$replace[0][] = $v[3];
						break;
					case '/ol':
						if (in_array($v[3], $replacedEndTags)) break;
						$replacedEndTags[] = $v[3];
						$replace[0][] = $v[3];
						$replace[1][] = "\r\n" . '[/LIST]' . "\r\n";
						break;
					case 'li':
						if (in_array($v[3], $replacedStartTags)) break;
						$replacedStartTags[] = $v[3];
						$replace[0][] = $v[3];
						$replace[1][] = "\r\n" . '[*]';
						break;
					case '/li':
						if (in_array($v[3], $replacedEndTags)) break;
						$replacedEndTags[] = $v[3];
						$replace[0][] = $v[3];
						$replace[1][] = '';
						break;
					case 'pre':
						if (in_array($v[3], $replacedStartTags)) break;
						$replacedStartTags[] = $v[3];
						$replace[0][] = $v[3];
						$replace[1][] = '[CODE]';
						break;
					case '/pre':
						if (in_array($v[3], $replacedEndTags)) break;
						$replacedEndTags[] = $v[3];
						$replace[0][] = $v[3];
						$replace[1][] = '[/CODE]';
						break;
					case 'sub':
						if (in_array($v[3], $replacedStartTags)) break;
						$replacedStartTags[] = $v[3];
						$replace[0][] = $v[3];
						$replace[1][] = '[SUB]';
						break;
					case '/sub':
						if (in_array($v[3], $replacedEndTags)) break;
						$replacedEndTags[] = $v[3];
						$replace[0][] = $v[3];
						$replace[1][] = '[/SUB]';
						break;
					case 'sup':
						if (in_array($v[3], $replacedStartTags)) break;
						$replacedStartTags[] = $v[3];
						$replace[0][] = $v[3];
						$replace[1][] = '[SUP]';
						break;
					case '/sup':
						if (in_array($v[3], $replacedEndTags)) break;
						$replacedEndTags[] = $v[3];
						$replace[0][] = $v[3];
						$replace[1][] = '[/SUP]';
						break;
					case 'strike':
						if (in_array($v[3], $replacedStartTags)) break;
						$replacedStartTags[] = $v[3];
						$replace[0][] = $v[3];
						$replace[1][] = '[STRIKE]';
						break;
					case '/strike':
						if (in_array($v[3], $replacedEndTags)) break;
						$replacedEndTags[] = $v[3];
						$replace[0][] = $v[3];
						$replace[1][] = '[/STRIKE]';
						break;
					case 'a':
						$href = $this->getAttributeValue($v[3], 'href');
						if ('' === $href) break;
						$replaced = TRUE;
						$newHref = $this->getUrl($href);
						if ('delete' === $newHref && NULL !== $v[4][0]) {
							$k = count($substrReplaceTags);
							$substrReplaceTags[$k] = $v[4][3];
							$k = str_pad('{' . $k . '}', $v[4][1]);
							$substrReplace[] = array($k, $v[4][0], $v[4][1]);
							$replace[0][] = $v[3];
							$replace[1][] = '';
							$replace[0][] = $k;
							$replace[1][] = '';
							break;
						} elseif ('' !== $newHref) $href = $newHref;
						$replace[0][] = $v[3];
						$replace[1][] = '[URL="' . $href . '"' . (FALSE === strpos($v[3], '_blank') ? '' : '+') . ']';
						break;
					case 'iframe':
						$html = substr($s, $v[0], $v[4][0] + $v[4][1] - $v[0]);
						$id = ++$this->objId;
						$replace[0][] = $this->htmlObjects[$id] = $html;
						$replace[1][] = '{id:' . $id . '}';
						break;
					case 'script':
						if (FALSE === strpos($v[3], ' src="')) break;
						$html = substr($s, $v[0], $v[4][0] + $v[4][1] - $v[0]);
						$id = ++$this->objId;
						$replace[0][] = $this->htmlObjects[$id] = $html;
						$replace[1][] = '{id:' . $id . '}';
						break;
					case '/a':
						if (in_array($v[3], $replacedEndTags)) break;
						$replacedEndTags[] = $v[3];
						$replace[0][] = $v[3];
						$replace[1][] = '[/URL]';
						break;
					case 'br':
						if (in_array($v[3], $replacedStartTags)) break;
						$replacedStartTags[] = $v[3];
						$replace[0][] = $v[3];
						$replace[1][] = "\r\n";
						break;
					case 'p':
						if (in_array($v[3], $replacedStartTags)) break;
						$replacedStartTags[] = $v[3];
						$replace[0][] = $v[3];
						$replace[1][] = "\r\n";
						break;
					case '/p':
						if (in_array($v[3], $replacedEndTags)) break;
						$replacedEndTags[] = $v[3];
						$replace[0][] = $v[3];
						$replace[1][] = "\r\n";
						break;
					case 'embed':
						if ($v[0] < $objectPos) break;
					case 'object':
						$objectPos = $v[4][0];
						$html = substr($s, $v[0], $v[4][0] + $v[4][1] - $v[0]);
						$src = $this->getAttributeValue($html, 'src');
						if ('' === $src) $src = $this->getAttributeValue($html, 'data');
						if (FALSE !== strpos($src, 'youtube.com') || FALSE !== strpos($src, 'vimeo.com') || FALSE !== strpos($src, 'pokertube.com') || FALSE !== strpos($src, 'pokertube.streamingbolaget.se') || FALSE !== strpos($src, '24ur.com') || FALSE !== strpos($src, 'brightcove.com') || FALSE !== strpos($src, 'vbox7.com') || FALSE !== strpos($src, 'dailymotion.com') || FALSE !== strpos($src, 'pokerhandreplays.com')) {
							$replace1[0][] = $html;
							$replace1[1][] = '[VIDEO]' . $src . '[/VIDEO]';
						} else {
							$id = ++$this->objId;
							$replace1[0][] = $this->htmlObjects[$id] = $html;
							$replace1[1][] = '{id:' . $id . '}';
						}
						break;
					case 'hr':
						if (in_array($v[3], $replacedStartTags)) break;
						$replacedStartTags[] = $v[3];
						$replace[0][] = $v[3];
						$replace[1][] = '';
						break;
					case 'font':
					case 'span':
					case 'div':
						$tmp = strtolower($v[3]);
						$r = $this->getStyles($tmp, array('text-decoration', 'font-weight', 'font-style'));
						$code = $endCode = '';
						if (!empty($r)) {
							$code = '';
							foreach ($r as $k => $attr) {
								if (0 === $k && 'underline' === $attr) {
									$code .= '[U]';
									$endCode .= '[/U]';
								} elseif (1 === $k && 'bold' === $attr) {
									$code .= '[B]';
									$endCode .= '[/B]';
								} elseif (2 === $k && 'italic' === $attr) {
									$code .= '[I]';
									$endCode .= '[/I]';
								}
							}
							if (NULL !== $v[4][1]) {
								$k = count($substrReplaceTags);
								$substrReplaceTags[$k] = $endCode;
								$k = str_pad('{' . $k . '}', $v[4][1]);
								$substrReplace[] = array($k, $v[4][0], $v[4][1]);
							}
						} else $k = $v[4][3];
						$replace[0][] = $v[3];
						$replace[1][] = $code;
						if (NULL !== $v[4][1]) {
							$replace[0][] = $k;
							$replace[1][] = $endCode;
						}
						break;
					case 'comment':
						$replace[0][] = $v[3];
						$replace[1][] = '';
						break;
					case 'title':
					case '/title':
					case 'link':
					case '/font':
					case '/span':
					case '/div':
						if (in_array($v[3], $replacedEndTags)) break;
						$replacedEndTags[] = $v[3];
						$replace2[0][] = $v[3];
						$replace2[1][] = '';
						break;
					case 'img':
						$src = $this->getAttributeValue($v[3], 'src');
						if ('' === $src) break;
						$replaced = TRUE;
						if (FALSE !== strpos($src, 'upload/Image/cartas') || FALSE !== strpos($src, 'images/smiley/cartas')) {
							$i = mb_strlen($src, '8bit') - 1;
							$card = '';
							$ext = TRUE;
							while ('/' !== $src[$i]) {
								if (TRUE === $ext) {
									if ('.' === $src[$i]) {
										$ext = FALSE;
									}
									--$i;
									continue;
								}
								$card = $src[$i] . $card;
								--$i;
							}
							$card = substr($card, 1);
							$card = '{' . strtolower(substr($card, 1) . $card[0]) . '}';
							$this->cards[$v[3]] = $card;
							$replace1[0][] = $v[3];
							$replace1[1][] = $card;
						} /*elseif (FALSE !== strpos($src, 'upload/Image/karte')) {
							$i = mb_strlen($src, '8bit') - 1;
							$card = '';
							$ext = TRUE;
							while ('/' !== $src[$i]) {
								if (TRUE === $ext) {
									if ('.' === $src[$i]) {
										$ext = FALSE;
									}
									--$i;
									continue;
								}
								$card = $src[$i] . $card;
								--$i;
							}
							$card = strtolower($card);
							$c2 = $card[1];
							if ('k' == $c2) $c2 = 'd';
							elseif ('l' === $c2) $c2 = 's';
							elseif ('m' === $c2) $c2 = 'c';
							elseif ('s' === $c2) $c2 = 'h';
							$c1 = $card[0];
							if ('T' === $c1) $c1 = '10';
							$card = '{' . $c1 . $c2 . '}';
							$replace[0][] = $v[3];
							$replace[1][] = $card;
							$this->cards[$v[3]] = $card;
						}*/ elseif ('' !== $this->siteDomain && FALSE !== strpos($src, 'http://') && FALSE === strpos($src, $this->siteDomain)) {
							if (FALSE === $this->externalImagesAsObj) {
								$replace[0][] = $v[3];
								$replace[1][] = '[IMG]' . $src . '[/IMG]';
								$this->extImages[] = $src;
							} elseif (!($id = array_search($src, $this->images))) {
								$alt = $this->getAttributeValue($v[3], 'alt');
								$id = ++$this->objId;
								$this->images[$this->objId] = array($src, $alt);
								$replace[0][] = $v[3];
								$replace[1][] = '{id:' . $id . '}';
							}
						} else {
							if (!($id = array_search($src, $this->images))) {
								$alt = $this->getAttributeValue($v[3], 'alt');
								$id = ++$this->objId;
								$this->images[$this->objId] = array($src, $alt);
							}
							$replace[0][] = $v[3];
							$replace[1][] = '{id:' . $id . '}';
						}
						break;
				}
				$tag = '';
			}
		}
		if (!empty($substrReplace)) foreach ($substrReplace as $v) $s = substr($s, 0, $v[1])  . $v[0] . substr($s, $v[1] + $v[2]);
		if (!empty($replace1)) $s = str_replace($replace1[0], $replace1[1], $s);
		if (!empty($replace)) $s = str_replace($replace[0], $replace[1], $s);
		if (!empty($replace2)) $s = str_replace($replace2[0], $replace2[1], $s);
		$s = preg_replace('/([\n\s]){3,}/si', "\n\n", trim(str_replace(array("\r"), '', $s))); //pasalinti didelius tarpus
		$s = preg_replace("/(\[([\w]+)\])([\s\n\t]*)?(\[\/\\2\])/sm", '', $s); // replace empty code
		return $s;
	}

	function unreplaced($s) {
		preg_match_all("/<[^>]*>/sm", $s, $matches, PREG_SET_ORDER);
		$unreplaced = array();
		for ($i=0; $i<count($matches); $i++) if (!in_array($matches[$i][0], $unreplaced)) $unreplaced[] = $matches[$i][0];
		return $unreplaced;
	}
	
	function cmp($html, $code, &$s1, &$s2) {
		$s1 = str_replace(array("\t", "\n", "\r"), '',strip_tags($html));
		$this->replaceSpecChars($s1);
		$code = preg_replace("/(\[(VIDEO)\])(.*)?(\[\/\\2\])/sm", '', $code);
		$s2 = preg_replace("/\[((TABLE|\/TABLE|B|\/B|I|\/I|U|\/U|IMG|\/IMG|VIDEO|\/VIDEO|LIST|\/LIST|\*|CODE|\/CODE|SUB|\/SUB|SUP|\/SUP|STRIKE|\/STRIKE|\/URL|URL)((=\"[^\"]*\"){1}\+{0,1}){0,1})\]/sm", '', str_replace(array("\t", "\n", "\r"), '', $code));
		if ($s1 === $s2) return TRUE;
		$i = $j = $i1 = $i2 = 0;
		$notFound = array();
		$c1 = '';
		while (NULL !== $c1) {
			$c1 = $this->mbChar($s1, $i1, $i);
			$c2 = $this->mbChar($s2, $i2, $j);
			//echo '(' . $j . ')' . $c2 . '=' . '(' . $i . ')>' . $c1 . "\n";
			while (NULL !== $c1 && (' ' === $c1 || "\t" === $c1)) {$c1 = $this->mbChar($s1, $i1, $i);}
			while (NULL !== $c2 && (' ' === $c2 || "\t" === $c2)) {$c2 = $this->mbChar($s2, $i2, $j);}
			if ($c2 !== $c1 && NULL !== $c2) {
				$notFound[$i] = '';
				while (NULL !== $c2 && $c2 !== $c1) {
					//echo '(' . $j . ')' . $c2 . '=' . '(' . $i . ')>' . var_dump($c1) . "\n";
					$notFound[$i] .= $c2;
					$c2 = $this->mbChar($s2, $i2, $j);
				}
			}
		}
		return $notFound;
	}

	function getStyles($s, $attr) {
		$r = array();
		$s = strtolower($this->getAttributeValue($s, 'style'));
		$k = 0;
		$attrName = '';
		while (isset($s[$k])) {
			if ("\t" === $s[$k] || "\n" === $s[$k]  || "\r" === $s[$k] || ' ' === $s[$k]) {
				$k++;
				continue;
			}
			if (':' === $s[$k]) {
				$k++;
				$attrName = trim($attrName);
				$i = array_search($attrName, $attr);
				$v = '';
				while (isset($s[$k]) && ';' !== $s[$k] && '"' !== $s[$k] && '>' !== $s[$k] && '\'' !== $s[$k]) $v .= $s[$k++];
				if (FALSE !== $i) $r[$i] = trim($v);
				$attrName = '';
			} else $attrName .= $s[$k];
			$k++;
		}
		return $r;
	}

	function getAttributeValue($s, $attr) {
		$tmp = strtolower($s);
		$v = '';
		if (FALSE !== ($pos = strpos($tmp, $attr))) {
			while (FALSE !== $pos) {
				$s = trim(substr($s, $pos+mb_strlen($attr, '8bit')));
				if ('=' !== $s[0]) {
					$pos = strpos($s, $attr);
					if (FALSE === $pos) return '';
				} else break;
			}
			$qoutType = '';
			$findQuote = FALSE;
			$qout = 0;
			for ($i=0; $i<mb_strlen($s, '8bit'); $i++) {
				if ('' === $v && ("\t" === $s[$i] || "\n" === $s[$i]  || "\r" === $s[$i] || ' ' === $s[$i] || '=' === $s[$i] || '"' === $s[$i] || '\'' === $s[$i])) {
					if (2 === $qout) break;
					if ('=' === $s[$i]) $findQuote = TRUE;
					elseif ($findQuote && '"' === $s[$i]) {$qoutType = '"'; ++$qout;}
					elseif ($findQuote && '\'' === $s[$i]) {$qoutType = '\''; ++$qout;}
					continue;
				} elseif ($qoutType === $s[$i] || '/' === $s[$i] || '>' === $s[$i]) {
					if ('/' === $s[$i]) {if ('>' === $s[$i+1]) break;}
					else break;
				}
				$v .= $s[$i];
			}
		} else return '';
		return $v;
	}

	function getUrl($url) {
		if (array_key_exists($url, $this->urls)) return $this->urls[$url];
		$this->urls[$url] = '';
		if (0 === strpos($url, 'javascript')) $this->urls[$url] = 'delete';
		elseif (FALSE !== strpos($url, 'news.pokernika') && FALSE !== strpos($url, 'all/page-1.html')) $this->urls[$url] = $this->newRootUri;
		elseif (FALSE !== strpos($url, 'strategija_taktika_poker.html')) $this->urls[$url] = '/strategija/';
		elseif (FALSE !== strpos($url, 'vesti/all/page-1.html')) $this->urls[$url] = $this->newRootUri;
		elseif (FALSE !== strpos($url, 'forum/index.php') || FALSE !== strpos($url, 'http://pokernika.com/forum/')) $this->urls[$url] = $this->forumRootUri;
		elseif (FALSE !== strpos($url, 'mailto:')) $this->urls[$url] = 'delete';
		elseif (FALSE !== strpos($url, 'vesti/')) {
			$c = strlen($url) - 6;
			$s = '';
			while (isset($url[$c]) && '/' !== $url[$c]) {
				$s = $url[$c] . $s;
				--$c;
			}
			$id = substr($s, 0, strpos($s, '-'));
			$this->urls[$url] = $this->getNewsUri($id);
		} elseif (0 === strpos($url, '../')) $this->urls[$url] = 'delete';
		elseif ('http://www.pokernika.com' === rtrim($url, '/')) $this->urls[$url] = './';
		elseif ('' !== $this->siteDomain && FALSE !== ($pos = strpos($url, $this->siteDomain))) if ($pos < 20) {
			$this->urls[$url] = 'delete';
			if (FALSE !== strpos($url, 'onlinerooms.') && FALSE !== strpos($url, 'redirect.html')) {
				$t = substr($url, strpos($url, '/', 20));
				$c = 1;
				$s = '';
				while (isset($t[$c]) && '/' !== $t[$c]) {
					$s .= $t[$c];
					++$c;
				}
				$this->urls[$url] = '/' . $s . '/ext/';
			} elseif (FALSE !== strpos($url, 'news.')) {
				$c = strlen($url) - 6;
				$s = '';
				while (isset($url[$c]) && '/' !== $url[$c]) {
					$s = $url[$c] . $s;
					--$c;
				}
				$id = substr($s, 0, strpos($s, '-'));
				$this->urls[$url] = $this->getNewsUri($id);
			}
		}
		return $this->urls[$url];
	}
	
	
	function getNewsUri($id) {
		if (isset($this->newUri[$id])) return $this->newUri[$id];
		$r = mysql_query('SELECT uri, published FROM articles WHERE id = ' . $id);
		if (is_resource($r)) {
			$r = mysql_fetch_array($r);
			$this->newUri[$id] = $this->newRootUri . date('Y', $r[1]) . '/' . date('m', $r[1]) . '/' . $r[0] . '-' . (1000 + $id) . '.htm';
		} else $this->newUri[$id] = '';
		return $this->newUri[$id];
	}

	function getTag($s) {
		$tag = '';
		for ($j=1; $j<mb_strlen($s, '8bit'); $j++) {
			if (' ' === $s[$j] || '>' === $s[$j]) break;
			$tag .= $s[$j];
		}
		return strtolower($tag);
	}
}
