<?php

function parseCookieFile($file) { 
    $aCookies = array(); 
    $aLines = file($file); 
    foreach($aLines as $line){ 
      if('#'==$line{0}) 
        continue; 
      $arr = explode("\t", $line); 
      if(isset($arr[5]) && isset($arr[6])) 
        $aCookies[$arr[5]] = $arr[6]; 
    }         
    return $aCookies; 
}  

class FileHostingRyuShare {
	private $Url;
	private $Username;
	private $Password;
	private $HostInfo;
	private $RYUSHARE_COOKIE = '/tmp/ryushare.cookie';
	private $RYUSHARE_LOGIN_URL = 'http://ryushare.com/login.python';
	private $RYUSHARE_ACCOUNT_URL = 'http://ryushare.com/my-account.python';
	private $RYUSHARE = 'ryushare';
	private $DOWNLOAD_PASSWORD = '';
	private $CookieValue;
	private $BROWSER_AGENT_ID = 'Mozilla/5.0 (iPad; CPU OS 6_0 like Mac OS X) AppleWebKit/536.26 (KHTML, like Gecko) Version/6.0 Mobile/10A5355d Safari/8536.25';

	public function __construct($Url, $Username, $Password, $HostInfo) {
		$this->Url = $Url;
		$this->Username = $Username;
		$this->Password = $Password;
		$this->HostInfo = $HostInfo; 
	}

	//This function returns download url.
	public function GetDownloadInfo() {
		// print("Logging in...\n");
		$ret = FALSE;
		$VerifyRet = $this->Verify(FALSE);
		if (LOGIN_FAIL == $VerifyRet) {
			$DownloadInfo = array();
			$DownloadInfo[DOWNLOAD_ERROR] = ERR_REQUIRED_PREMIUM;
			$ret = $DownloadInfo;
		} else if (USER_IS_FREE == $VerifyRet) {
			$DownloadInfo = array();
			$DownloadInfo[DOWNLOAD_ERROR] = ERR_REQUIRED_PREMIUM;
		 	$ret = $DownloadInfo;
		} else {
			$ret = $this->DownloadPremium();
		}
		return $ret;
	}

	//This function verifies and returns account type.
	public function Verify($ClearCookie) {
		$ret = LOGIN_FAIL;
		$this->CookieValue = FALSE;
		if (!empty($this->Username) && !empty($this->Password)) {
			$this->CookieValue = $this->RyushareLogin($this->Username, $this->Password);
		}
		if (FALSE == $this->CookieValue) { 
			goto End;
		}
		if ($this->IsFreeAccount()) {
			$ret = USER_IS_FREE;
		} else {
			$ret = USER_IS_PREMIUM; 
		}

		End:
		return $ret; 
	}

	//This function performs login action. 
	private function RyushareLogin($Username, $Password) {
		$ret = FALSE;
		//Save cookie file
		$PostData = array('op'=>'login', 'redirect'=>'http://ryushare.com/',
						  'login'=>$this->Username, 'password'=>$this->Password,
						  'loginFormSubmit'=>'Login');
		$queryUrl = $this->RYUSHARE_LOGIN_URL;
		$PostData = http_build_query($PostData);
		$curl = curl_init();
		curl_setopt($curl, CURLOPT_SSL_VERIFYPEER, FALSE);
		curl_setopt($curl, CURLOPT_USERAGENT, $BROWSER_AGENT_ID);
		curl_setopt($curl, CURLOPT_POST, TRUE);
		curl_setopt($curl, CURLOPT_POSTFIELDS, $PostData);
		curl_setopt($curl, CURLOPT_COOKIEJAR, $this->RYUSHARE_COOKIE);
		curl_setopt($curl, CURLOPT_HEADER, TRUE);
		curl_setopt($curl, CURLOPT_RETURNTRANSFER, TRUE);
		curl_setopt($curl, CURLOPT_URL, $queryUrl);
		$LoginInfo = curl_exec($curl);
		curl_close($curl);
		if (FALSE != $LoginInfo && file_exists($this->RYUSHARE_COOKIE)) {
			$ret = parseCookieFile($this->RYUSHARE_COOKIE); 
			if (!empty($ret['login'])) {
				$ret = $ret['login'];
			} else {
				$ret = FALSE;
			}
		}
		return $ret; 
	}

	private function DownloadParsePage() {
		$Option = array();
		$curl = curl_init();
		curl_setopt($curl, CURLOPT_SSL_VERIFYPEER, FALSE);
		curl_setopt($curl, CURLOPT_USERAGENT, DOWNLOAD_STATION_USER_AGENT);
		curl_setopt($curl, CURLOPT_COOKIEFILE, $this->RYUSHARE_COOKIE);
		curl_setopt($curl, CURLOPT_COOKIEJAR, $this->RYUSHARE_COOKIE);
		curl_setopt($curl, CURLOPT_RETURNTRANSFER, TRUE);
		curl_setopt($curl, CURLOPT_URL, $this->Url);
		$ret = curl_exec($curl);
		curl_close($curl);
		return $ret; 
	}

	// Edited by phas
	private function IsFreeAccount() {
		$curl = curl_init();
		curl_setopt($curl, CURLOPT_SSL_VERIFYPEER, FALSE);
		curl_setopt($curl, CURLOPT_USERAGENT, $BROWSER_AGENT_ID);
		curl_setopt($curl, CURLOPT_COOKIEFILE, $this->RYUSHARE_COOKIE); 
		curl_setopt($curl, CURLOPT_RETURNTRANSFER, TRUE); 
		curl_setopt($curl, CURLOPT_URL, $this->RYUSHARE_ACCOUNT_URL); 
		$AccountRet = curl_exec($curl);
		curl_close($curl);
		preg_match('/Premium account expire/', $AccountRet, $match); 
		if (empty($match[0])) {
			return TRUE;
		}
		return FALSE;
	}

	//This function get premium download url. 
	private function DownloadPremium() {
		for ($i = 1; $i <= 20; $i++) {
			usleep(1000);
			// print("Try ");print($i);print("\n");
			$page = $this->DownloadParsePage();
			if (FALSE != $page) {
				preg_match('/id="btn_download"/', $page, $btn_download);
				if (empty($btn_download[0])) {
					$DownloadInfo = array();
					$DownloadInfo[DOWNLOAD_ERROR] = ERR_FILE_NO_EXIST;
					return $DownloadInfo;
				}
				preg_match('/name="id" value="([a-zA-Z0-9]+)"/', $page, $in_id);
				preg_match('/name="rand" value="([a-zA-Z0-9]+)"/', $page, $in_rand);

				if (empty($in_id[1])) {
					$DownloadInfo = array();
					$DownloadInfo[DOWNLOAD_ERROR] = ERR_NOT_SUPPORT_TYPE;
					return $DownloadInfo;
				}
				if (empty($in_rand[1])) {
					$DownloadInfo = array();
					$DownloadInfo[DOWNLOAD_ERROR] = ERR_NOT_SUPPORT_TYPE;
					return $DownloadInfo;
				}

				$downloadUrl = $this->GetDownloadUrl($in_id[1], $in_rand[1], $this->DOWNLOAD_PASSWORD);
				if (FALSE != $downloadUrl) {
					$DownloadInfo = array();
					$DownloadInfo[DOWNLOAD_URL] = $downloadUrl;
					$DownloadInfo[DOWNLOAD_COOKIE] = $this->RYUSHARE_COOKIE;
					$DownloadInfo[DOWNLOAD_STATION_USER_AGENT] = $this->BROWSER_AGENT_ID;
					$DownloadInfo[DOWNLOAD_ISPARALLELDOWNLOAD] = FALSE;

					return $DownloadInfo;
				}
				usleep(1000);
			}
		}
	
		$DownloadInfo = array();
		$DownloadInfo[DOWNLOAD_ERROR] = ERR_FILE_NO_EXIST;
		return $DownloadInfo;
	}

	private function GetDownloadUrl($id, $rand, $password) {
		$PostData = array('op'=>'download2', 'id'=>$id, 'rand'=>$rand,
						  'method_premium'=>'1', 'password'=>$password, 'capcode'=>'false',
						  'down_direct'=>'1', 'btn_download'=>'Create Download Link');

		$queryUrl = $this->Url;
		$PostData = http_build_query($PostData);
		$curl = curl_init();
		curl_setopt($curl, CURLOPT_SSL_VERIFYPEER, FALSE);
		curl_setopt($curl, CURLOPT_USERAGENT, $BROWSER_AGENT_ID);
		curl_setopt($curl, CURLOPT_POST, TRUE);
		curl_setopt($curl, CURLOPT_POSTFIELDS, $PostData);
		curl_setopt($curl, CURLOPT_COOKIEFILE, $this->RYUSHARE_COOKIE);
		curl_setopt($curl, CURLOPT_COOKIEJAR, $this->RYUSHARE_COOKIE);
		curl_setopt($curl, CURLOPT_HEADER, TRUE);
		curl_setopt($curl, CURLOPT_RETURNTRANSFER, TRUE);
		curl_setopt($curl, CURLOPT_URL, $queryUrl);
		$page = curl_exec($curl);
		curl_close($curl);

		$replace = array("\r\n", "\n", "\r");
		$page = str_replace($replace, '', $page);
		if (FALSE == $page) {
			return FALSE;
		}

		preg_match('/class="err">([^<]*)</', $page, $err);
		if (!empty($err[1])) {
			return FALSE;
		}
		preg_match('/id="btn_download"/', $page, $btn_download);
		if (!empty($btn_download[0])) {
			return FALSE;		
		}

		preg_match('/href="([^"]+)">Click here/', $page, $downloadUrl);
		if (!empty($downloadUrl[1]))
			return $downloadUrl[1];
		
		return FALSE;
	}
}

?>
