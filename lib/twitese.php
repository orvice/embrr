<?php
	set_time_limit(15);
	if(!isset($_SESSION)){
		session_start();
	}
	include_once('mobile_device_detect.php');
	mobile_device_detect(true,true,true,true,true,true,'https://t.orzdream.com/',false);
	include_once('config.php');
	include_once('utility.php');
	include_once('twitteroauth.php');
	include_once('oauth_lib.php');

	function refreshProfile(){
		$t = getTwitter();
		$user = $t->veverify();
		$time = $_SERVER['REQUEST_TIME']+3600*24*365;
		setcookie('friends_count', $user->friends_count, $time, '/');
		setcookie('statuses_count', $user->statuses_count, $time, '/');
		setcookie('followers_count', $user->followers_count, $time, '/');
		setcookie('imgurl', getAvatar($user->profile_image_url), $time, '/');
		setcookie('name', $user->screen_name, $time, '/');
		setcookie('listed_count', $user->listed_count, $time, '/');
	}

	function getDefCookie($name, $default="") {
		if (getCookie($name)) return getCookie($name);
		else return $default;
	}
	function format_time($time){
		date_default_timezone_set('UTC');
		return strtotime($time);
	}
	function formatText($text) {
		//如果开启了魔术引号\" \' 转回来
		if (get_magic_quotes_gpc()) {
			$text = stripslashes($text);
		}

		//添加url链接
		$urlReg = '/(((http|https|ftp):\/\/){1}([[:alnum:]\-\.])+(\.)(([[:alnum:]]){2,4})?([[:alnum:]\/+=%#&@\:\;_\.~\?\!\-\,]*))/i';
		$text = preg_replace($urlReg, '<a href="\1" target="_blank" rel="noreferrer">\1</a>', $text);

		//添加@链接
		$atReg = '/\B@{1}(([a-zA-Z0-9\_\.\-])+)/i';
		$text = preg_replace($atReg,	'<a href="user.php?id=\1" target="_blank">\0</a>', $text);

		//添加 list 链接
		$listReg = '/(\<a[\w+=\:\%\#\&\.~\?\"\'\/\- ]+\>@{1}([a-zA-Z0-9_\.\-]+)<\/a\>([\/a-zA-Z0-9_\.\-]+))/i';
		$text = preg_replace($listReg,	'<a href="list.php?id=\2\3" target="_blank">@\2\3</a>', $text);

		//添加标签链接
		$tagReg = "/\B(\#{1}([\w]*[\pN\pC\pL]+[\w]*))([\s]*)/u";
		$text = preg_replace_callback($tagReg, function($matches) {
			return '<a target="_blank" href="search.php?q=%23'.rawurlencode($matches[2]).'">#<span class="hashtag">'.$matches[2].'</span></a>'.$matches[3];
		}, $text);

		$text = formatTweetID($text);

		return $text;
	}

	function formatEntities($entities,$extended_entities,$html){
		$user_mentions = $entities->user_mentions;
		$hashtags = $entities->hashtags;
		$urls = $entities->urls;
		if(count($user_mentions) > 0) {
			foreach($user_mentions as $user_mention) {
				$name = $user_mention->screen_name;
				$html = str_replace("@$name","<a href=\"user.php?id=$name\" target=\"_blank\">@$name</a>",$html);
			}
		}
		if(count($hashtags) > 0) {
			foreach($hashtags as $hashtag) {
				$text = $hashtag->text;
				$html = str_replace("#$text","<a target=\"_blank\" href=\"search.php?q=%23".rawurlencode($text)."\">#<span class=\"hashtag\">$text</span></a>",$html);
			}	
		}
		if(count($urls) > 0) {
			$scheme = (!isset($_SERVER['HTTPS']) || $_SERVER['HTTPS'] != "on") ? 'http' : 'https';
			foreach($urls as $url) {
				$exp = is_null($url->expanded_url) ? $url->url : $url->expanded_url;
				if(substr($url->url,0,4) != 'http') $url->url = 'http://'.$url->url;
				if(isset($url->display_url)) {
					$dis = $url->display_url;
				} else {
					$tmp = explode('://', $url->url);
					$dis = $tmp[1];
				}
				$html = str_replace($url->url,"<a href=\"$exp\" target=\"_blank\" rel=\"noreferrer\" class=\"tweet_url\">$dis</a>",$html);
			}	
		}
		if(isset($entities->media)) {
			$medias = $entities->media;
			foreach($medias as $media) {
				$url = $media->media_url_https;
				if (getcookie('p_avatar') == 'true') {
						$url = 'img.php?imgurl='.$url;
				}
				$html = str_replace($media->url,"<a href=\"$url\" target=\"_blank\" rel=\"noreferrer\">$media->display_url</a>",$html);
			}
		}
		$ret = array('text' => $html);

		$extended_str = '';
		if($extended_entities != null) {
			if (isset($extended_entities->media)) {
				$medias = $extended_entities->media;
				foreach($medias as $media) {
					$url = $media->media_url_https;
					if (getcookie('p_avatar') == 'true') {
							$url = 'img.php?imgurl='.$url;
					}
					$extended_str .= "<a href=\"$url\" target=\"_blank\" rel=\"noreferrer\">$media->display_url</a>";
				}
			}
		}
		$ret['extended'] = $extended_str;

		return $ret;
	}

	function formatTweetID($text){
		$reg = '/(\<a[\w+=@\:\%\#\&\.~\?\"\'\/\-\! ]+\>[\S]+<\/a\>)/i';
		preg_match_all($reg, $text, $tmpMatches);
		if(count($tmpMatches) > 0){
			$text = preg_replace($reg, '$_holder_$', $text);
		}
		preg_match_all('/([\d]{10,})/', $text, $matches);
		if(count($matches) > 0){
			$matches = array_unique($matches[0]);
			foreach($matches as $match){
				$text = str_replace($match, '<a title="We think it\'s a tweet ID, isn\'t it?" href="status.php?id='.$match.'" target="_blank">'.$match.'</a>', $text);
			}
			$tmpReg = '/\$_holder_\$/i';
			foreach($tmpMatches[0] as $match){
				$text = preg_replace($tmpReg, $match, $text, 1);
			}
		}
		return $text;
	}

	function processCurl($url,$postdata=false,$header=false)
	{
		$ch = curl_init($url);

		curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
		curl_setopt($ch, CURLOPT_VERBOSE, 1);
		curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, 0);
		curl_setopt($ch, CURLOPT_TIMEOUT,120);
		
		if($postdata !== false) {
			curl_setopt ($ch, CURLOPT_POST, true);
			curl_setopt ($ch, CURLOPT_POSTFIELDS, $postdata);
		}
		
		if($header !== false) {
			curl_setopt($ch, CURLOPT_HTTPHEADER, $header);
		}
		
		$response = curl_exec($ch);
		$responseInfo=curl_getinfo($ch);
		curl_close($ch);
		if( intval( $responseInfo['http_code'] ) == 200 )
			return $response;		
		else
			return false;
	}

	function objectifyXml( $data ){

		if( function_exists('simplexml_load_string') ) {
			$obj = simplexml_load_string( $data );
		}
		if (isset($obj->error) || !$obj) return false;
		else return $obj;

		return false;
	}

	function objectifyJson($data){
		if(function_exists("json_decode")){
			$obj = json_decode($data);
		}
		if(!isset($obj->error) || $obj){
			return $obj;
		}
		return false;
	}
	
	function getTwitter() {
		if(loginStatus()){
			$access_token = $_SESSION['access_token'] ? $_SESSION['access_token'] : null;
			$oauth_token = $access_token ? $access_token['oauth_token'] : $_COOKIE['oauth_token'];
			$oauth_token_secret = $access_token ? $access_token['oauth_token_secret'] : $_COOKIE['oauth_token_secret'];
			$oauth = new TwitterOAuth(CONSUMER_KEY, CONSUMER_SECRET, $access_token['oauth_token'], $access_token['oauth_token_secret']);
			return $oauth;
		}
		return null;
	}

   function loginStatus() {
	   if(isset($_SESSION['login_status'])){
	      return $_SESSION['login_status'] == 'verified' ? true : false;
	   }elseif(getEncryptCookie("oauth_token") != "" && getEncryptCookie("oauth_token_secret") != "" && getCookie("user_id") != "" && getCookie("name") != ""){
	      $access_token = array("oauth_token" => getEncryptCookie("oauth_token"), "oauth_token_secret" => getEncryptCookie("oauth_token_secret"), "user_id" => getCookie("user_id"), "screen_name" => getCookie("name"));
	      $_SESSION['access_token'] = $access_token;
	      $_SESSION['login_status'] = 'verified';
	      refreshProfile();
	      return true;
	   }
	   return false;
   }

	function getAvatar($profileImg){
		if (getcookie('p_avatar') == 'true') {
				return 'img.php?imgurl='.$profileImg;
		}
		return preg_replace('/https?:\/\/\w+([0-9])\.twimg\.com/i','https://s3.amazonaws.com/twitter_production',$profileImg);
	}

	// $target: can't be current user
	// $source: use the current user as the source user implicitly if not specified
	// 9 => no relationship
	// 1 => fo each other
	// 2 => $source fo $target
	// 3 => $target fo $source
	// 4 => $source blocking $target
	function getRelationship($target, $source = false){
		$relationship = getTwitter()->relationship($target, $source)->relationship;
		$target = $relationship->target;
		$source = $relationship->source;
		$result = 0;
		if($source->muting == true){
			$result |= 8;
		}
		if($source->blocking == true){
			$result |= 4;
		}
		if($target->following == true){
			$result |= 2;
		}
		if($source->following == true){
			$result |= 1;
		}
		return $result;
	}
	
	function urlshorten($url, $type='goo.gl'){
		switch($type){
			case 'goo.gl':
			$data = json_encode(array('longUrl' => $url));
			$api = 'https://www.googleapis.com/urlshortener/v1/url?key=AIzaSyDsX2BAo9Jc2yG3Pq1VbLQALqdrtDFvXkg';
			$header = array('Content-type: application/json');
			$result = objectifyJson(processCurl($api,$data,$header))->id;
			break;
			case 'zi.mu':
			$api = 'http://zi.mu/api.php?format=simple&action=shorturl&url=';
			$result = objectifyJson(processCurl($api.rawurlencode($url)));
			break;
			default:
			break;
		}
		return $result;
	}

	function expandRedirect($shorturl, &$hops) {
		if (count($hops) >= 10) {
			return false;
		}
		$head = array_change_key_case(get_headers($shorturl, TRUE), CASE_LOWER);
		if (!isset($head['location']) || empty($head['location'])) {
			return $shorturl;
		}
		$prevhop = $shorturl;
		foreach((array)$head['location'] as $redir) {
			if (substr($redir, 0, 1)=='/' || preg_match('/[\.\/]'.preg_quote(parse_url($prevhop, PHP_URL_HOST)).'$/', parse_url($redir, PHP_URL_HOST))) {
				return $prevhop;
			}
			$hops[] = $prevhop;
			$prevhop = $redir;
		}
		return expandRedirect($redir, $hops);
	}
?>
