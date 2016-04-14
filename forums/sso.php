<?
$url = "http://".$_SERVER['HTTP_HOST']."/xmlrpc.php";
//$url = "http://125.214.71.130/xmlrpc.php";
//$url = "http://".$_SERVER['SERVER_NAME']."/xmlrpc.php";
function _vb_xml_login( $username, $password )
  {
  	global $url;  	
  	//$domain = str_replace("www","",$_SERVER['HTTP_HOST']);
  	$domain = $_SERVER['HTTP_HOST'];
	  $drupal_sesname = 'SESS'. md5(ltrim($domain, '.'));
	  $drupal_sesname_id = $_COOKIE[$drupal_sesname];
  	
    $xml_post = "<?xml version=\"1.0\"?>";
    $xml_post .= "<methodCall><methodName>vb.login</methodName>";
    $xml_post .= "<params><param><value><string>$username</string></value></param>";
    $xml_post .= "<param><value><string>$password</string></value></param>";
    $xml_post .= "<param><value><string>$drupal_sesname_id</string></value></param>";
	  $xml_post .= "</params>";
    $xml_post .= "</methodCall>";

    $params = array(
      'http' => array(
                  'method' => 'POST',
                  'content' => $xml_post                 
                  )
    );

	  $ctx = stream_context_create($params);	  
    $fp = fopen($url, 'rb', false, $ctx);
    $response = @stream_get_contents($fp);        
    if (!$response)
	  {
			$response = preg_replace('/<[a-zA-Z0-9\/"]+>/',"",$response);
			$response = preg_replace('/.*?>/',"",$response);
			$response = preg_replace('/\n/',"",$response);
			$response = preg_replace('/ +/',"",$response);
	  }
  }


function _vb_xml_logout( $uname )
  {
  	global $url;
    $xml_post = "<?xml version=\"1.0\"?>";
    $xml_post .= "<methodCall><methodName>vb.logout</methodName>";
    $xml_post .= "<params><param><value><string>$uname</string></value></param>";
	  $xml_post .= "</params>";
    $xml_post .= "</methodCall>";

    $params = array(
      'http' => array(
                  'method' => 'POST',
                  'content' => $xml_post
                  )
    );
    
	  $ctx = stream_context_create($params);
    $fp = @fopen($url, 'rb', false, $ctx);
    $response = @stream_get_contents($fp);
    if (!$response)
	  {
			$response = preg_replace('/<[a-zA-Z0-9\/"]+>/',"",$response);
			$response = preg_replace('/.*?>/',"",$response);
			$response = preg_replace('/\n/',"",$response);
			$response = preg_replace('/ +/',"",$response);
	  }
  }
  ?>