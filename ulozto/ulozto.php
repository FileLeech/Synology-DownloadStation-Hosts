<?php

class SynoFileHostingUlozto {



    private $Url;
    private $Username;
    private $Password;
    private $HostInfo;
    private $Token;
    private $LOGIN_URL = "http://ulozto.cz/login";
    private $LOGIN_URL_SUFFIX = "?do=directDownload";

    public function __construct($Url, $Username, $Password, $HostInfo) {
        $this->Url = $Url;
        $this->Username = $Username;
        $this->Password = $Password;
        $this->HostInfo = $HostInfo;
    }

    //This function returns download url.
    public function GetDownloadInfo() {
        // check user account
        if ($this->Verify() == LOGIN_FAIL) {
            return array(DOWNLOAD_ERROR => LOGIN_FAIL);
        }


            return array(DOWNLOAD_URL => $this->getFileUrl());
 

      }

    public function Verify($ClearCookie = NULL) {
        if ($this->getToken() == true) {
            return USER_IS_PREMIUM;
        } else {
            return LOGIN_FAIL;
        }
    }


    private function getFileUrl() {
	
	sleep(2);
	$PostData = array(
	    'username' => $this->Username,
	    'password' => $this->Password,
	    'remember' => 'false',
	    '_token_'  => $this->Token
	);

    	$PostData = http_build_query($PostData);
	$curl = curl_init();
	curl_setopt($curl, CURLOPT_SSL_VERIFYPEER, FALSE);
	curl_setopt($curl, CURLOPT_POST, TRUE);
	curl_setopt($curl, CURLOPT_POSTFIELDS, $PostData);
	curl_setopt($curl, CURLOPT_USERAGENT, 'Mozilla/5.0 (X11; U; Linux i686; en-US; rv:1.9.0.10) Gecko/2009042523 Ubuntu/9.04 (jaunty) Firefox/3.0.10');
	curl_setopt($curl, CURLOPT_COOKIEJAR, '/tmp/ulozto.coocies.l');
	curl_setopt($curl, CURLOPT_COOKIEFILE, '/tmp/ulozto.coocies.t');
	curl_setopt($curl, CURLOPT_HEADER, TRUE);
	curl_setopt($curl, CURLOPT_FOLLOWLOCATION, TRUE);
	curl_setopt($curl, CURLOPT_RETURNTRANSFER, TRUE);
	curl_setopt($curl, CURLOPT_TIMEOUT, 15);
	curl_setopt($curl, CURLOPT_REFERER, "http://ulozto.cz/login?do=loginForm-submit");
	curl_setopt($curl, CURLOPT_URL, "http://ulozto.cz/login?do=loginForm-submit" );
	curl_exec($curl);
	curl_close($curl);
	
	$curl = curl_init();
	curl_setopt($curl, CURLOPT_SSL_VERIFYPEER, FALSE);
	curl_setopt($curl, CURLOPT_POST, TRUE);
	curl_setopt($curl, CURLOPT_POSTFIELDS, $PostData);
	curl_setopt($curl, CURLOPT_USERAGENT, 'Mozilla/5.0 (X11; U; Linux i686; en-US; rv:1.9.0.10) Gecko/2009042523 Ubuntu/9.04 (jaunty) Firefox/3.0.10');
	curl_setopt($curl, CURLOPT_COOKIEFILE, '/tmp/ulozto.coocies.l');
	curl_setopt($curl, CURLOPT_HEADER, TRUE);
	curl_setopt($curl, CURLOPT_TIMEOUT, 15);
	curl_setopt($curl, CURLOPT_FOLLOWLOCATION, TRUE);
	curl_setopt($curl, CURLOPT_RETURNTRANSFER, TRUE);
	curl_setopt($curl, CURLOPT_REFERER, "http://ulozto.cz/login");
	curl_setopt($curl, CURLOPT_URL, $this->Url . "?do=directDownload" );
	$LoginInfo = curl_exec($curl);
	$info = curl_getinfo($curl);
	$redirect_url = $info['url'];
	curl_close($curl);
	
	return $redirect_url;


    }
    private function getToken() {
        $session = curl_init();
        @curl_setopt($session, CURLOPT_HEADER, false);
        @curl_setopt($session, CURLOPT_USERAGENT, 'Mozilla/5.0 (X11; U; Linux i686; en-US; rv:1.9.0.10) Gecko/2009042523 Ubuntu/9.04 (jaunty) Firefox/3.0.10');
        @curl_setopt($session, CURLOPT_FOLLOWLOCATION, true);
        @curl_setopt($session, CURLOPT_TIMEOUT, 15);
        @curl_setopt($session, CURLOPT_RETURNTRANSFER, true);
        @curl_setopt($session, CURLOPT_SSL_VERIFYPEER, false);
	@curl_setopt($session, CURLOPT_COOKIEJAR, '/tmp/ulozto.coocies.t'); 
	@curl_setopt($session, CURLOPT_URL, 'http://ulozto.cz/login');
        $response = curl_exec($session);
	curl_close($session);
	
	preg_match('/frm-loginForm-_token_" value="(.*?)"/',$response,$tokenValues);

	    if ($response == NULL) {
		return false;
	    }
	    $this->Token = $tokenValues[1];
	    if (strlen($this->Token) != 38) {
		return false;
	    }
	
	return true;

    }
}
