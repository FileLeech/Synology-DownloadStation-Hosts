<?php
/* 
* @author: @__marmar__
* credits to mcampbell (matthewcambpell508@gmail.com)
*/

/*
error_reporting(E_ERROR | E_WARNING | E_PARSE);
define('LOGIN_FAIL', 4);
define('USER_IS_FREE', 5);
define('USER_IS_PREMIUM', 6);
define('ERR_FILE_NO_EXIST', 114);
define('ERR_REQUIRED_PREMIUM', 115);
define('ERR_NOT_SUPPORT_TYPE', 116);
define('DOWNLOAD_STATION_USER_AGENT', "Mozilla/4.0 (compatible; MSIE 6.1; Windows XP)");
*/

class HostingUlozto {
	private $Url;
	private $Username;
	private $Password;
	private $HostInfo;
	private $Token;
	private $UT_COOKIE_JAR = '/tmp/ut.cookie';
	private $LOGIN_URL = "http://uloz.to/login";
	private $LOGIN_URL_SUFFIX = "?do=downloadDialog-loginForm-submit";
		
	public function __construct($Url, $Username, $Password, $HostInfo) {
		$this->Url = $Url;
		$this->Username = $Username;
		$this->Password = $Password;
		$this->HostInfo = $HostInfo;
	}
	
	public function Verify() {
		return $this->performLogin(true);
	}
	
	public function GetDownloadInfo($ClearCookie) {
		$login = $this->performLogin();

		if ($login == LOGIN_FAIL) {
			$DownloadInfo = array();
			$DownloadInfo[DOWNLOAD_ERROR] = ERR_REQUIRED_PREMIUM;
		} else {
			$DownloadInfo = array();
			$DownloadInfo[DOWNLOAD_URL] = $login;
		}

		return $DownloadInfo;
	}
	
	private function getElementById($id) {
		$xpath = new DOMXPath($this->domDocument);
		return $xpath->query("//*[@id='$id']")->item(0);
	}

	private function performLogin( $verifyOnly = false ) {
		$ret = LOGIN_FAIL;
		
		// We need to get token from input inside the page 
		$curl = curl_init();
		curl_setopt($curl, CURLOPT_SSL_VERIFYPEER, FALSE);
		curl_setopt($curl, CURLOPT_USERAGENT, DOWNLOAD_STATION_USER_AGENT);
		curl_setopt($curl, CURLOPT_COOKIEJAR, $this->UT_COOKIE_JAR);
		curl_setopt($curl, CURLOPT_HEADER, TRUE);
		curl_setopt($curl, CURLOPT_RETURNTRANSFER, TRUE);
		curl_setopt($curl, CURLOPT_URL, $this->Url );
		$firstPage = curl_exec($curl);
		curl_close($curl);

		if ($firstPage !== false) {
			// get input
			$dom = new DOMDocument();
			libxml_use_internal_errors(true);
			$dom->validateOnParse = true;
			$dom->loadHTML($firstPage);
			libxml_clear_errors();

			$tokenInput = $dom->getElementById("frmloginForm-_token_");
			if ($tokenInput == NULL) {
				return LOGIN_FAIL;
			}
			$this->Token = $tokenInput->getAttribute('value');

			if (strlen($this->Token) != 10) {
				return LOGIN_FAIL;
			}
		}
		sleep(2);

		$PostData = array(
			'username' => $this->Username,
			'password' => $this->Password,
			'remember' => 'false',
			'_token_'  => $this->Token,
			'login'    => 'Přihlásit'
		);

		$PostData = http_build_query($PostData);

		$curl = curl_init();
		curl_setopt($curl, CURLOPT_SSL_VERIFYPEER, FALSE);
		curl_setopt($curl, CURLOPT_POST, TRUE);
		curl_setopt($curl, CURLOPT_POSTFIELDS, $PostData);
		curl_setopt($curl, CURLOPT_USERAGENT, DOWNLOAD_STATION_USER_AGENT);
		//curl_setopt($curl, CURLOPT_COOKIEJAR, $this->UT_COOKIE_JAR);
		curl_setopt($curl, CURLOPT_COOKIEFILE, $this->UT_COOKIE_JAR);
		curl_setopt($curl, CURLOPT_HEADER, TRUE);
		curl_setopt($curl, CURLOPT_RETURNTRANSFER, TRUE);
		curl_setopt($curl, CURLOPT_REFERER, $this->Url);
		curl_setopt($curl, CURLOPT_URL, $this->Url . $this->LOGIN_URL_SUFFIX );
		$LoginInfo = curl_exec($curl);

		$info = curl_getinfo($curl);
		$error_code = $info['http_code'];
		$redirect_url = $info['redirect_url'];

		curl_close($curl);

		//auth is filefactory logged in cookie value
		if ($LoginInfo != false) {			
			if ($error_code == 301 || $error_code == 302 || $error_code == 303) {
				if ( stristr($redirect_url, 'dla') ) { //dla.uloz.to
					if ($verifyOnly == true) {
						// when user clicks on Verify button
						return USER_IS_PREMIUM;
					} else {
						return $redirect_url;
					}
				} else {
					return LOGIN_FAIL;
				}
			} else {
				return LOGIN_FAIL;
			}
		}

		return LOGIN_FAIL;
	}
	
}


?>
