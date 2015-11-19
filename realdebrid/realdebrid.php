<?php

define('LOGIN_FAIL', 4);
define('USER_IS_FREE', 5);
define('USER_IS_PREMIUM', 6);
define('ERR_FILE_NO_EXIST', 114);
define('ERR_REQUIRED_PREMIUM', 115);
define('ERR_NOT_SUPPORT_TYPE', 116);
define('DOWNLOAD_STATION_USER_AGENT', "Mozilla/4.0 (compatible; MSIE 6.1; Windows XP)");
define('DOWNLOAD_URL', 'downloadurl'); // Real download url
define('DOWNLOAD_FILENAME', 'filename'); // Saved file name define('DOWNLOAD_COUNT', 'count'); // Number of seconds to wait
define('DOWNLOAD_ISQUERYAGAIN', 'isqueryagain'); // 1: Use the original url query from the user again. 2: Use php output url query again.
define('DOWNLOAD_ISPARALLELDOWNLOAD', 'isparalleldownload');//Task can download parallel flag.
define('DOWNLOAD_COOKIE', 'cookiepath');

class RealDebridFileHost {
	private $url, $user, $pass, $hostInfo, $cookie = "/tmp/realdebrid.cookie";
	
	public function __construct($url, $user, $pass, $hostInfo) {
		$this->url = $url;
		$this->user = $user;
		$this->pass = $pass;
		$this->hostInfo = $hostInfo;
	}
	
	public function GetDownloadInfo() {
		if(!file_exists($this->cookie))
			$this->Verify(false);
		
		$res = $this->Get("https://real-debrid.com/ajax/unrestrict.php?link=".$this->url);
		
		return array(
			// INFO_NAME => $this->hostInfo[INFO_NAME],
		    DOWNLOAD_URL => $res->generated_links[0][2],
		    // DOWNLOAD_ISPARALLELDOWNLOAD => true,
		    // DOWNLOAD_FILENAME => $res->generated_links[0][0],
		);
	}
	
	public function Verify($ClearCookie) {
		$res = $this->Get("https://real-debrid.com/ajax/login.php?user=".urlencode($this->user)."&pass=".md5($this->pass),true);
				
		if($ClearCookie && file_exists($this->cookie))
			unlink($this->cookie);
				
		return !$res || $res->error ? LOGIN_FAIL : USER_IS_PREMIUM;
	}
	
	private function Get($url,$getCookie=false) {
		$curl = curl_init();
		
		curl_setopt($curl, CURLOPT_SSL_VERIFYPEER, false);
		curl_setopt($curl, CURLOPT_USERAGENT, DOWNLOAD_STATION_USER_AGENT);
		curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);
		curl_setopt($curl, CURLOPT_URL, $url);
		
		if($getCookie)
			curl_setopt($curl, CURLOPT_COOKIEJAR, $this->cookie);
		else
			curl_setopt($curl, CURLOPT_COOKIEFILE, $this->cookie);
		
		$res = curl_exec($curl);
		
		curl_close($curl);
		
		return json_decode($res);
	}
}

/*
// Test
$realDebrid = new RealDebridFileHost("url","username","password",array());
echo $realDebrid->Verify(true);
var_dump($realDebrid->GetDownloadInfo());
*/

?>
