<?/*************************************************
modified: 2010-11-30 09:19
version: 2.9.1
project: Moon
author: Audrius Naslenas, audrius@vpu.lt
*************************************************/
class moon_mail {
var $eol = "\r\n";
var $idPrefix;
var $headers;
var $subject;
var $charset;
var $to, $smtpTo;
var $images, $attachments;
var $msgText, $msgHtml;
var $imagesID;
var $contentTypes;
var $smtp;
function moon_mail() {
$this->idPrefix = 'Moon' . time();
$this->imagesID = $this->images = $this->attachments = array();
$this->headers = array();
$h = array('From', 'To', 'Date', 'Return-Path', 'Priority', 'Importance', 'Reply-To', 'MIME-Version', 'X-Mailer', 'X-Priority', 'X-Sender', 'Cc', 'Bcc');
foreach ($h as $name) {
$this->headers[$name] = '';
}$this->charset = 'UTF-8';
$this->to = $this->subject = '';
$this->headers['X-Mailer'] = 'Moon (www)';
}function charset($charset) {
$this->charset = trim($charset);
}function from($email, $name = '') {
if ($name != '') {
$email = $name . '<'.$email.'>';
}list($emails, $full) = $this->_address_list($email, 'From:');
if ($emails !== '') {
$this->headers['From'] = $full;
$this->headers['Return-Path'] = $emails;
$this->headers['X-Sender'] = $emails;
}}function to($email, $name = '') {
if ($name != '') {
$email = $name . '<'.$email.'>';
}list($emails, $full) = $this->_address_list($email, 'To:');
if ($emails !== '') {
$this->to = $emails;
$this->smtpTo = $full;
return TRUE;
}else {
$this->to = '';
return FALSE;
}}function reply_to($email, $name = '') {
if ($name != '') {
$email = $name . '<'.$email.'>';
}list($emails, $full) = $this->_address_list($email, 'Reply-To:');
$this->headers['Reply-To'] = $full;
}function cc($cc) {
list($emails, $full) = $this->_address_list($cc, 'Cc:');
$this->headers['Cc'] = $full;
}function bcc($bcc) {
list($emails) = $this->_address_list($bcc, 'Bcc:');
$this->headers['Bcc'] = str_replace($this->eol . "\t", '', $emails);
}function urgent($isUrgent = true) {
if ($isUrgent) {
$this->headers['Priority'] = 'urgent';
$this->headers['X-Priority'] = '1 (Highest)';
$this->headers['Importance'] = 'High';
}else {
$this->headers['Priority'] = 'normal';
$this->headers['X-Priority'] = '3 (Normal)';
$this->headers['Importance'] = '';
}}function subject($subject) {
$this->subject = $this->_head_encode(trim($subject));
}function body($txt, $htm = '') {
$this->msgText = $this->msgHtml = '';
$eol = $this->eol;
if ($txt !== '') {
$h = array();
$h[] = 'Content-Type: text/plain; charset="' . $this->charset . '"';
$h[] = 'Content-Transfer-Encoding: quoted-printable';
$this->msgText = implode($eol, $h) . $eol . $eol . $this->quoted_printable_encode($txt) . $eol;
}if ($htm !== '') {
foreach ($this->imagesID as $name => $cid) {
$htm = str_replace('"' . $name . '"', '"cid:' . $cid . '"', $htm);
}$h = array();
$h[] = 'Content-Type: text/html; charset="' . $this->charset . '"';
$h[] = 'Content-Transfer-Encoding: quoted-printable';
$this->msgHtml = implode($eol, $h) . $eol . $eol . $this->quoted_printable_encode($htm) . $eol;
}}function image($filename, $name = '', $binaryData = NULL) {
if (!empty ($filename)) {
$binaryData = $this->_get_file($filename);
if ($binaryData === FALSE) {
return;
}if ($name == '') {
$name = $filename;
}}if (!is_null($binaryData)) {
$this->imagesID[$name] = $cid = $this->_id();
$iname = basename($name);
$eol = $this->eol;
$h = array();
$h[] = 'Content-Type: ' . $this->content_type($iname) . ';' . $eol . "\tname=\"$iname\"";
$h[] = 'Content-Transfer-Encoding: base64';
$h[] = 'Content-Disposition: inline;' . $eol . "\t" . 'filename="' . $iname . '"';
$h[] = 'Content-ID: <' . $cid . '>';
$this->images[$name] = implode($eol, $h) . $eol . $eol . chunk_split(base64_encode($binaryData)) . $eol;
}elseif (isset ($this->imagesID[$name])) {
unset ($this->images[$name]);
unset ($this->imagesID[$name]);
}}function attachment($filename, $name = '', $description = '', $encoding = '', $binaryData = NULL) {
if (!empty ($filename)) {
$binaryData = $this->_get_file($filename);
if ($binaryData === FALSE) {
return;
}if ($name == '') {
$name = $filename;
}}if (!is_null($binaryData)) {
$iname = basename($name);
$eol = $this->eol;
$type = $this->content_type($iname);
if ($encoding == '') {
$encoding = $type == 'text/plain' || $type == 'text/html' ? 'quoted-printable' : 'base64';
}switch ($encoding) {
case '7bit' :
$binaryData = chunk_split($binaryData);
break;
case 'quoted-printable' :
$binaryData = $this->quoted_printable_encode($binaryData);
break;
default :
$binaryData = chunk_split(base64_encode($binaryData));
$encoding = 'base64';
}$h = array();
$h[] = 'Content-Type: ' . $type . ';' . $eol . "\tname=\"$iname\"";
$h[] = 'Content-Transfer-Encoding: ' . $encoding;
$h[] = 'Content-Disposition: attachment;' . $eol . "\t" . 'filename="' . $iname . '"';
if ($description) {
$h[] = 'Content-Description: ' . $this->_head_encode($description);
}$this->attachments[$name] = implode($eol, $h) . $eol . $eol . $binaryData . $eol;
}elseif (isset ($this->attachments[$name])) {
unset ($this->attachments[$name]);
}}function content_type($fext, $newContentType = '') {
$ext = strrchr($fext, '.');
$ext = $ext === FALSE ? basename($fext) : strtolower(substr($ext, 1));
if (!empty ($newContentType)) {
$this->contentTypes[$ext] = $newContentType;
}if (isset ($this->contentTypes[$ext])) {
return $this->contentTypes[$ext];
}$r = array('dot' => 'doc', 'xla' => 'xls', 'midi' => 'mid', 'jpeg' => 'jpg', 'tiff' => 'tif', 'html' => 'htm', 'mpeg' => 'mpg', 'qt' => 'mov', 'pps' => 'ppt', 'eps' => 'ps');
if (isset ($r[$ext])) {
$ext = $r[$ext];
}$c = array('doc' => 'application/msword', 'ppt' => 'application/vnd.ms-powerpoint', 'xls' => 'application/vnd.ms-excel', 'odt' => 'application/vnd.oasis.opendocument.text', 'odp' => 'application/vnd.oasis.opendocument.presentation', 'ods' => 'application/vnd.oasis.opendocument.spreadsheet', 'odg' => 'application/vnd.oasis.opendocument.graphics', 'docx' => 'application/vnd.openxmlformats-officedocument.wordprocessingml.document', 'pptx' => 'application/vnd.openxmlformats-officedocument.presentationml.presentation', 'xlsx' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet', 'pdf' => 'application/pdf', 'ps' => 'application/postscript', 'zip' => 'application/zip', 'csv' => 'text/x-csv', 'gif' => 'image/gif', 'jpg' => 'image/jpeg', 'tif' => 'image/tiff', 'png' => 'image/png', 'htm' => 'text/html', 'xml' => 'text/xml', 'js' => 'text/javascript', 'txt' => 'text/plain', 'mpg' => 'video/mpeg', 'mov' => 'video/quicktime', 'avi' => 'video/x-msvideo', 'mid' => 'audio/midi', 'rtf' => 'application/rtf', 'tar' => 'application/x-tar', 'gz' => 'application/x-gzip', 'swf' => 'application/x-shockwave-flash');
return (isset ($c[$ext]) ? $c[$ext] : 'application/octet-stream');
}function send() {
if (!$this->to) {
$this->_error('noto', 'W');
return FALSE;
}list($headers, $msg) = explode($this->eol . $this->eol, $this->construct_mail(), 2);
$ok = @ mail($this->to, $this->subject, $msg, trim($headers));
if (!$ok) {
$this->_error('mail_send', 'F', array('email' => $this->to));
return FALSE;
}return $ok;
}function add_header($name, $value) {
$this->headers[$name] = $value;
}function is_email($email, $checkDomain = FALSE) {
$ok = (preg_match("/^[a-z0-9!#$%&'*+\/=?\_`{|}~-]+([\\.-][a-z0-9!#$%&'*+\/=?^_`{|}~-]+)*" .
"@" . "([a-z0-9]+([\.-][a-z0-9]+)*)+" .
"\\.[a-z]{2,}$" .
"$/i", $email));
if ($checkDomain && $ok && function_exists('getmxrr')) {
$domain = substr(strstr($email, '@'), 1);
if (getmxrr($domain, $temp)) {
$ok = TRUE;
}elseif (checkdnsrr($domain, 'ANY')) {
$ok = TRUE;
}else {
$ok = FALSE;
}}return $ok;
}function quoted_printable_encode($input, $line_max = 76) {
$lines = preg_split("/(?:\r\n|\r|\n)/", $input);
$eol = $this->eol;
$escape = '=';
$output = '';
foreach ($lines as $line) {
$linlen = $this->_strlen($line);
$newline = '';
for ($i = 0; $i < $linlen; $i++) {
$char = $line[$i];
$dec = ord($char);
if (($dec == 32) AND ($i == ($linlen - 1))) {
$char = '=20';
}elseif ($dec == 9) {
;
}elseif (($dec == 61) OR ($dec < 32) OR ($dec > 126)) {
$char = $escape . strtoupper(sprintf('%02s', dechex($dec)));
}if ((strlen($newline) + strlen($char)) >= $line_max) {
$output .= $newline . $escape . $eol;
$newline = '';
}$newline .= $char;
}$output .= $newline . $eol;
}return $output;
}function construct_mail() {
$eol = $this->eol;
$msg = '';
if ($this->msgHtml) {
if (count($this->images)) {
$bid = '--=_r.' . $this->_id();
$msg = 'Content-Type: multipart/related;' . $eol . chr(9) . 'boundary="' . $bid . '"' . $eol . $eol;
$msg .= 'This is a MIME encoded message' . $eol . $eol;
$msg .= '--' . $bid . $eol . $this->msgHtml;
foreach ($this->images as $v) {
$msg .= '--' . $bid . $eol . $v;
}$msg .= '--' . $bid . '--' . $eol;
}else {
$msg = $this->msgHtml;
}}if ($this->msgHtml !== '' && $this->msgText !== '') {
$this->headers['MIME-Version'] = '1.0';
$bid = '--=_a.' . $this->_id();
$msg = 'Content-Type: multipart/alternative;' . $eol . chr(9) . 'boundary="' . $bid . '"' . $eol . $eol . 'This is a MIME encoded message' . $eol . $eol . '--' . $bid . $eol . $this->msgText . '--' . $bid . $eol . $msg . '--' . $bid . '--' . $eol;
}elseif ($this->msgText !== '') {
$msg = $this->msgText;
}if (count($this->attachments)) {
$this->headers['MIME-Version'] = '1.0';
$bid = '--=_m.' . $this->_id();
$msg = 'Content-Type: multipart/mixed;' . $eol . chr(9) . 'boundary="' . $bid . '"' . $eol . $eol . 'This is a MIME encoded message' . $eol . $eol . '--' . $bid . $eol . $msg;
foreach ($this->attachments as $v) {
$msg .= '--' . $bid . $eol . $v;
}$msg .= '--' . $bid . '--' . $eol;
}$h = '';
$this->headers['Date'] = date('r');
foreach ($this->headers as $k => $v) {
if ($v !== '' && $k !== 'Bcc') {
$h .= $k . ': ' . $v . $eol;
}}$msg = $h . $msg;
if ($this->headers['Bcc'] !== '') {
list($headers, $msg) = explode($eol . $eol, $msg, 2);
$msg = $headers . $eol . 'Bcc: ' . $this->headers['Bcc'] . $eol . $eol . $msg;
}return $msg;
}function _strlen($s) {
return function_exists('mb_strlen') ? mb_strlen($s, '8bit') : strlen($s);
}function _head_encode($txt) {
if ($count = preg_match_all('/[\\x7f-\\xff]/',$txt, $m)) {
$ilg = $this->_strlen($txt);
$eB = 100 * $count / $ilg > 30 ? TRUE : FALSE;
$beg = '=?' . $this->charset . '?' . ($eB ? 'B' : 'Q') . '?';
$maxilg = 73 - strlen($beg);
$m = array('');
$row = 0;
for ($i = 0; $i < $ilg; $i++) {
$char = '';
$cod = ord($txt[$i]);
if ($cod < 192) {
$st = 1;
}elseif ($cod < 224) {
$st = 2;
}elseif ($cod < 240) {
$st = 3;
}else {
$st = 4;
}for ($j = 0; $j < $st; $j++) {
$char .= $txt[$i + $j];
}$i += $j - 1;
$encode = $eB ? base64_encode($m[$row] . $char) : $this->_q_encode($m[$row] . $char);
if (strlen($encode) > $maxilg) {
$row++;
}if (!isset ($m[$row])) {
$m[$row] = $char;
}else {
$m[$row] .= $char;
}}foreach ($m as $k => $v) {
$m[$k] = $eB ? base64_encode($v) : $this->_q_encode($v);
}$txt = $beg . implode('?=' . $this->eol . "\t" . $beg, $m) . '?=';
}return $txt;
}function _q_encode($line) {
$len = $this->_strlen($line);
$newline = '';
for ($i = 0; $i < $len; $i++) {
$char = $line[$i];
$dec = ord($char);
if (($dec == 61) OR ($dec < 32) OR ($dec > 126)) {
$char = '=' . strtoupper(sprintf('%02s', dechex($dec)));
}$newline .= $char;
}$newline = str_replace(array('_', '?', ' '), array('=5F', '=3F', '_'), $newline);
return $newline;
}function _address_list($elist, $where='') {
$emails = array();
$name = FALSE;
$i = 0;
$a = explode(',', $elist);
foreach ($a as $s) {
$i = strrpos($s, '<');
if ($i !== FALSE && ($j = strpos($s, '@', $i)) && ($k = strpos($s, '>', $j))) {
$n = substr($s, 0, $i);
if ($name !== FALSE) {
$n = $name . ',' . $n;
}$e = substr($s,$i+1,$k-$i-1);
$emails[] = array($e, $n);
$name = FALSE;
}elseif ($i === FALSE && strpos($s, '@', $i)) {
$n = $name === FALSE ? '' : ',';
$emails[] = array(trim($s), $n);
$name = FALSE;
} else {
$name = $name === FALSE ? $s : ',' . $s;
}}$notatom = '/[\\x00-\\x20\\x22\\x28\\x29\\x2c\\x2e\\x3a-\\x3c'.
'\\x3e\\x40\\x5b-\\x5d\\x7f-\\xff]+/';
$bad = array();
$a1 = $a2 = array();
foreach ($emails as $k=>$v) {
if (!$this->is_email($v[0])) {
$bad[] = $v[1] . '<' . $v[0] . '>';
continue;
}$n = trim($v[1]);
while (isset($n[1]) && ($n[0] === '"' || $n[0] === "'") && isset($n[1]) && $n[0] === substr($n,-1) ) {
$n = substr($n,1,-1);
}$n = preg_replace('/["\\\\]/','\\\\${0}', trim($n));
if ($n !=='' && preg_match($notatom, $n)) {
if (preg_match('/[\\x7f-\\xff]/',$n)) {
$n = $this->_head_encode($n);
}else {
$n = '"' . $n . '"';
}};
$emails[$k][1] = $n;
$a1[] = $v[0];
$a2[] =  ($n == '' ? $v[0] : $n . ' <' . $v[0] . '>');
}if (count($bad)) {
$this->_error('bad_email', 'W', array('email' => implode('|', $bad), 'where' => $where));
}return array(implode(',' . $this->eol . "\t", $a1), implode(',' . $this->eol . "\t", $a2),$a1);
}function _get_file($filename) {
$s = file_exists($filename) ? file_get_contents($filename) : FALSE;
if ($s === FALSE) {
$this->_error('nofile', 'W', array('file' => $filename));
}return $s;
}function _id() {
return md5(uniqid($this->idPrefix));
}function _error($code, $tipas='W', $m = '') {
if (class_exists('moon')) {
$m['where'] = $this->_whereError();
moon :: error(array("@mail.$code", $m), $tipas);
}else {
echo 'mail[' . $code . '] ' . serialize($m);
}}function _whereError()
{$a = debug_backtrace();
$failas = $line = '';
foreach ($a as $v) {
$c = empty($v['class']) ? '' : $v['class'];
if ($c == __CLASS__ || $c == get_class($this) ) {
$failas = $v['file'];
$line = $v['line'];
continue;
}break;
}return $failas . ':' .  $line;
}function smtp_connect($args = FALSE) {
$default = array();
$default['host'] = 'localhost';
$default['port'] = '25';
$default['secure'] = '';
$default['username'] = '';
$default['password'] = '';
$default['hostname'] = 'localhost';
$default['debug'] = '0';
$default['timeout'] = '10';
$args = is_array($args) ? array_merge($default, $args) : $default;
if (is_null($this->smtp)) {
$this->smtp = new SMTP();
}$this->smtp->do_debug = $args['debug'];
$connected = $this->smtp->Connected();
if (!$connected) {
$hostinfo = array();
if (preg_match('/^(.+):([0-9]+)$/', trim($args['host']), $hostinfo)) {
$host = $hostinfo[1];
$port = (int)$hostinfo[2];
}else {
$host = trim($args['host']);
$port = (int)$args['port'];
}$tls = ($args['secure'] == 'tls');
$ssl = ($args['secure'] == 'ssl');
if ($host && $this->smtp->Connect(($ssl ? 'ssl:/'.'/' : '') . $host, $port, $args['timeout'])) {
$fail = FALSE;
if (!empty ($args['hostname'])) {
$hello = $args['hostname'];
}else {
$hello = isset ($_SERVER['SERVER_NAME']) ? $_SERVER['SERVER_NAME'] : 'localhost.localdomain';
}if ($this->smtp->Hello($hello)) {
if ($tls) {
if ($this->smtp->StartTLS()) {
$fail = !$this->smtp->Hello($hello);
}else {
$fail = TRUE;
}if ($fail) {
$this->_error('smtp.tls');
}}}else {
$this->_error('smtp.helo');
$fail = TRUE;
}$connected = true;
if (!$fail && $args['username']) {
if (!$this->smtp->Authenticate($args['username'], $args['password'])) {
$this->_error('smtp.authenticate');
$fail = TRUE;
}}if ($fail) {
$this->smtp->Reset();
$this->smtp->Quit();
$connected = FALSE;
}}if (!$connected) {
$this->_error('smtp.connect_host');
}}return $connected;
}function smtp_close() {
$this->smtpReset = FALSE;
if (!is_null($this->smtp)) {
if ($this->smtp->Connected()) {
$this->smtp->Quit();
$this->smtp->Close();
}}}var $smtpReset = FALSE;
function smtp_send() {
if (!$this->to) {
$this->_error('noto', 'W');
return FALSE;
}if (!$this->smtp->Connected()) {
return FALSE;
}$this->headers['To'] = $this->smtpTo;
$this->headers['Subject'] = $this->subject;
$bcc = $this->headers['Bcc'];
$this->headers['Bcc'] = '';
list($headers, $msg) = explode($this->eol . $this->eol, $this->construct_mail(), 2);
$bad_rcpt = array();
if ($this->smtpReset) {
$this->smtp->Reset();
}list($smtp_from) = explode(',', $this->headers['Return-Path'], 2);
if (!$this->smtp->Mail($smtp_from)) {
$this->_error('smtp.from_failed', 'W', array('email'=>$smtp_from));
return false;
}list(,,$emails) = $this->_address_list($this->to);
list(,,$cc) = $this->_address_list($this->headers['Cc']);
list(,,$bcc) = $this->_address_list($this->headers['Bcc']);
$emails = array_unique(array_merge($emails, $cc, $bcc));
foreach ($emails as $to) {
if (!$this->smtp->Recipient($to)) {
$bad_rcpt[] = $to;
}}if (count($bad_rcpt) > 0) {
$badaddresses = implode(', ', $bad_rcpt);
$this->_error('smtp.recipients_failed' , 'N', array('emails'=>$badaddresses));
}if (!$this->smtp->Data($headers.$this->eol . $this->eol.$msg)) {
$this->_error('smtp.data_not_accepted');
return FALSE;
}$this->smtpReset = TRUE;
return true;
}}?>