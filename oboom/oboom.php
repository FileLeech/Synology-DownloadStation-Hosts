<?php
/* 
* @author: Machinimo
* roccabyte@gmail.com
* created 03/01/15
*/
class SynoFileHostingOboom {
	private $Url;
	private $Username;
	private $Password;
	private $HostInfo;
	private $SessionToken;
	private $OBOOM_USERMANAGEMENT_URL = 'http://www.oboom.com/1';
	private $OBOOM_DOWNLOAD_URL = 'http://api.oboom.com/1';
	private $OBOOM = 'oboom';
	
	public function __construct($Url, $Username, $Password, $HostInfo) {
		$this->Url = $Url;
		$this->Username = $Username;
		$this->Password = $Password;
		$this->HostInfo = $HostInfo;
	}
	// This function returns download url.
	public function GetDownloadInfo() {
		$ret = FALSE;
		$VerifyRet = $this->Verify (FALSE);
		if (LOGIN_FAIL == $VerifyRet) {
			//$ret = $this->DownloadWaiting ( FALSE );
		} else if (USER_IS_FREE == $VerifyRet) {
			//$ret = $this->DownloadWaiting ( TRUE );
		} else {
			$ret = $this->GetPremiumDownloadLink ( $this->CookieValue );
		}
		return $ret;
	}
	// This function verifies and returns account type.
	public function Verify($ClearCookie) {
		$ret = LOGIN_FAIL;
		
		if (! empty ( $this->Username ) && ! empty ( $this->Password )) {
			$response = $this->PerformLogin ( $this->Username, $this->Password );
			
			if($response == FALSE){
				goto End;
			}
			
			$this->SessionToken = $response[1]['session'];
			$isPremium = $response[1]['user']['premium'];
			
			if($isPremium == 'null') {
				$ret = USER_IS_FREE;
			} else {
				$ret = USER_IS_PREMIUM;
			}
		} else {
			$this->SessionToken = $this->GetGuestSession();
			$ret = USER_IS_FREE;
		}
		
		End:
		return $ret;
	}
	
	private function GetPremiumDownloadLink($CookieValue){
		$curl = curl_init();
		
		preg_match('@https?://(www.)?oboom\.com/([\w]{8})@i', $this->Url, $id);
				
		$PostData = http_build_query(array(
				'token' => $this->SessionToken,
				'item' => $id[2]
		));
		
		$queryUrl = $this->OBOOM_DOWNLOAD_URL . "/dl";
				
		curl_setopt_array($curl, array(
			CURLOPT_SSL_VERIFYPEER => FALSE,
			CURLOPT_USERAGENT => DOWNLOAD_STATION_USER_AGENT,
			CURLOPT_POST => TRUE,
			CURLOPT_POSTFIELDS => $PostData,
			CURLOPT_RETURNTRANSFER => TRUE,
			CURLOPT_URL => $queryUrl
		));
		
		$downloadTicketPage = curl_exec($curl);
		$parsedJsonDownloadTicket = @json_decode($downloadTicketPage, true);
				
		if($parsedJsonDownloadTicket[0] == 200){
			$downloadUrl = sprintf('http://%s/1/dlh?ticket=%s', $parsedJsonDownloadTicket[1], $parsedJsonDownloadTicket[2]);
			$header = get_headers($downloadUrl, 1);			
			
			preg_match_all("/[^\'\']+$/", $header['Content-Disposition'], $filename);
			
			$filename = urldecode($filename[0][0]);
			
			$DownloadInfo = array();
			$DownloadInfo[DOWNLOAD_URL] = $downloadUrl;
			$DownloadInfo[DOWNLOAD_FILENAME] = $filename;
			return $DownloadInfo;
		} else {
			$DownloadInfo = array();
			$DownloadInfo[DOWNLOAD_ERROR] = ERR_FILE_NO_EXIST;
			return $DownloadInfo;
		}
		
		return $DownloadInfo;
	}
	
	private function GetGuestSession(){
		$ret = FALSE;
		
		$PostData = array (
				'source' => '/#app'
		);
		
		$queryUrl = $this->OBOOM_USERMANAGEMENT_URL . "/guestsession";
		$PostData = http_build_query ( $PostData );
		$curl = curl_init ();
		curl_setopt_array($curl, array(
			CURLOPT_SSL_VERIFYPEER => FALSE,
			CURLOPT_USERAGENT => DOWNLOAD_STATION_USER_AGENT,
			CURLOPT_POST => TRUE,
			CURLOPT_POSTFIELDS => $PostData,
			CURLOPT_RETURNTRANSFER => TRUE,
			CURLOPT_URL => $queryUrl
		));
		$LoginInfo = curl_exec ( $curl );
		curl_close ( $curl );
		
		if (FALSE != $LoginInfo) {
			$json = @json_decode($page, true);
			if ($json[0] == 200) {
				$ret = $json[1];
			} else {
				$ret = FALSE;
			}
		}
		return $ret;
	}
	
	// This function performs login action and returns the session token.
	private function PerformLogin($Username, $Password) {
		$ret = FALSE;
		
		require('PasswordHash.php');
		
		$mysalt = strrev($this->Password);
		$hash = pbkdf2('sha1', $this->Password, $mysalt, 1000, 16);
		
		$PostData = array (
				'auth' => $this->Username,
				'pass' => $hash,
				'source' => '/#app'
		);
		$queryUrl = $this->OBOOM_USERMANAGEMENT_URL . "/login";
		$PostData = http_build_query ( $PostData );
		$curl = curl_init ();
		curl_setopt_array($curl, array(
			CURLOPT_SSL_VERIFYPEER => FALSE,
			CURLOPT_USERAGENT => DOWNLOAD_STATION_USER_AGENT,
			CURLOPT_POST => TRUE,
			CURLOPT_POSTFIELDS => $PostData,
			CURLOPT_RETURNTRANSFER => TRUE,
			CURLOPT_URL => $queryUrl
		));
		$page = curl_exec ( $curl );
		curl_close ( $curl );
		if (FALSE != $page) {			
			$json = @json_decode($page, true);
			if ($json[0] == 200) {
				$ret = $json;
			} else {
				$ret = FALSE;
			}
		}
		return $ret;
	}
}
?>
