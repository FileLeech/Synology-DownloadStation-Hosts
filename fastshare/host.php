<?php
    /**
    Copyright (C) 2015 Michal Feix, www.feix.cz

    This program is free software: you can redistribute it and/or modify
    it under the terms of the GNU General Public License as published by
    the Free Software Foundation, either version 3 of the License, or
    (at your option) any later version.

    This program is distributed in the hope that it will be useful,
    but WITHOUT ANY WARRANTY; without even the implied warranty of
    MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
    GNU General Public License for more details.

    You should have received a copy of the GNU General Public License
    along with this program.  If not, see <http://www.gnu.org/licenses/>.
    
    Built on REPLACEME_DATE
    */

//CUT FROM HERE
	define("LOGIN_FAIL", 1);
	define("USER_IS_PREMIUM", 2);
	define('DOWNLOAD_ERROR', 'error');
	define('DOWNLOAD_URL', 'downloadurl');
	define('DOWNLOAD_COOKIE', 'cookiepath');
	define('ERR_FILE_NO_EXIST', 'noexist');
//CUT UP TO HERE
	
	class SynoFileHostingFastshare {
		private $Url;
		private $Username;
		private $Password;
		private $HostInfo;
		private $COOKIEJAR = "/tmp/fastsharecookie.jar";
	
		public function __construct($Url, $Username, $Password, $HostInfo) {
			$this->Url = $Url;
			$this->Username = $Username;
			$this->Password = $Password;
			$this->HostInfo = $HostInfo;
		}
		
		public function getDownloadInfo() {
			$ret = $this->performLogin();
			if ($ret == FALSE)
				return array(DOWNLOAD_ERROR => LOGIN_FAIL);
			
			$ret = $this->getLink();
			if ($ret == FALSE)
				return array(DOWNLOAD_ERROR => ERR_FILE_NO_EXIST);
			return array(DOWNLOAD_URL => $ret, DOWNLOAD_COOKIE => $this->COOKIEJAR);
		}
		
		public function Verify($ClearCookie) {
			$ret = $this->performLogin();
			if ($ret == FALSE)
				return LOGIN_FAIL;
				
			if ($ClearCookie && file_exists($this->COOKIEJAR))
				unlink($this->COOKIEJAR);
				
			return USER_IS_PREMIUM;
		}
		
		private function performLogin() {
			$postdata = http_build_query(array("login" => $this->Username, "heslo" => $this->Password));
		
			$curlsession = curl_init("http://fastshare.cz/sql.php");
			curl_setopt($curlsession, CURLOPT_HEADER, TRUE);
			curl_setopt($curlsession, CURLOPT_RETURNTRANSFER, TRUE);
			curl_setopt($curlsession, CURLOPT_POST, 1);
			curl_setopt($curlsession, CURLOPT_POSTFIELDS, $postdata);
			curl_setopt($curlsession, CURLOPT_COOKIEJAR, $this->COOKIEJAR);
			curl_setopt($curlsession, CURLOPT_FOLLOWLOCATION, TRUE);
//			curl_setopt($curlsession, CURLOPT_VERBOSE, TRUE);
			$response = curl_exec($curlsession);

			$result = preg_match("/location: \/login\?error/", $response, $matches);
			if ($result == 1)
				return FALSE;
			return TRUE;
		}
		
		private function getLink() {
			$curlsession = curl_init($this->Url);
			curl_setopt($curlsession, CURLOPT_HEADER, FALSE);
			curl_setopt($curlsession, CURLOPT_RETURNTRANSFER, TRUE);
			curl_setopt($curlsession, CURLOPT_COOKIEFILE, $this->COOKIEJAR);
			curl_setopt($curlsession, CURLOPT_COOKIEJAR, $this->COOKIEJAR);
//			curl_setopt($curlsession, CURLOPT_VERBOSE, TRUE);
			$response = curl_exec($curlsession);
			if ($response == FALSE)
				return FALSE;
			
			$result = preg_match("/<a href=\"(.*download.php.*)\">/", $response, $matches);
			if ($result != 1)
				return FALSE;

			return $matches[1];
		}
	}

//CUT FROM HERE
	#$test = new SynoFileHostingFastshare("http://fastshare.cz/testurl", "testuser", "testpassword", "");
	#print_r($test->Verify(FALSE));
	#print_r($test->getDownloadInfo());
//CUT UP TO HERE

?>
