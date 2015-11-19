<?php
/* 
Please, do not rate my part of code. It's my first (and one of the last) PHP code.
I started it without any knowledge of PHP

https://github.com/kradam/synology-turbobit
*/

// Verify results
define('LOGIN_FAIL', 4); 
define('USER_IS_FREE', 5); 
define('USER_IS_PREMIUM', 6); 
// GetDownloadInfo result array indexes
define('DOWNLOAD_ERROR', 'error');
define('DOWNLOAD_URL', 'downloadurl');
define('ERR_REQUIRED_PREMIUM', 115);  
define('ERR_FILE_NO_EXIST', 114);

// curl parameters
define('DOWNLOAD_STATION_USER_AGENT', "Mozilla/4.0 (compatible; MSIE 6.1; Windows XP)");
define('HTTPHEADER', "Accept-Language: en-us,en;");  // default lang is russian
// URLs and file paths
define('TURBOBIT_COOKIE_FILE', '/tmp/turbobit.cookie');
define('TURBOBIT_URL', 'http://turbobit.net'); // main and account info URL
define('TURBOBIT_LOGIN_URL', 'http://turbobit.net/user/login');

define("DOWNLOAD_TEST_FILE1", "http://turbobit.net/8pzb64nxyv4z.html");
define("DOWNLOAD_TEST_FILE2", "http://turbobit.net/ok0fzrw3ryip/The.Originals.S02E13.PLSUBBED.HDTV.XviD-Rafal77.avi.html");
define("DOWNLOAD_TEST_FILE_UNEXISTING", "http://turbobit.net/081lzb1hb25r.html"); 

define("FILE_CONTAINING_EMAIL", 'email.txt');  

/*
$DebugAllowed = ( ); // source in "dev" dir => debugging allowed
echo "DA $DebugAllowed \n";
$Debug = $DebugAllowed && 1; // debbuging 1/0
echo "Debug $Debug \n";
$testing = $DebugAllowed && 1; // testing
*/

// test if code is in dirname containing 'dev'
if (strstr(__DIR__, "dev") != FALSE) {
	$email = file_get_contents(__DIR__ . '/email.txt');
	// echo $email;
	unlink(TURBOBIT_COOKIE_FILE);
	$c = new SynoFileHostingTurbobit(DOWNLOAD_TEST_FILE1, $email, "Lewandowski", "Turbobit");
	//$c = new SynoFileHostingTurbobit(DOWNLOAD_TEST_FILE_UNEXISTING, 'kradam@gmail.com', "", "Turbobit");
	print("\nGetDownloadInfo1:\n");
	print_r($c->GetDownloadInfo());

 	sleep(10);
	$d = new SynoFileHostingTurbobit(DOWNLOAD_TEST_FILE2, $email, "Lewandowski", "Turbobit");
 	print("\nGetDownloadInfo2:\n");
	print_r($d->GetDownloadInfo());
 }


class SynoFileHostingTurbobit {	
	private $Url;
	private $Username;
	private $Password;
	private $HostInfo;
	private $Log;
				
	public function __construct($Url, $Username, $Password, $HostInfo) {
		$this->Url = $Url;	
		$this->Username = $Username;
		$this->Password = $Password;
		$this->HostInfo = $HostInfo; // not used
		
		// log to console if this code is in directory name containing 'dev'
		// else log to file
		if (strstr(__DIR__, "dev") != FALSE) {  // existing and != FALSE
			$this->Log = new Logger(Logger::LogMediaWebPage);
		} else {
			$this->Log = new Logger(Logger::LogMediaFile, "/tmp/turbobit.log");	
		}
		//var_dump($this->Log);			
	}

	//This function returns download url.
	public function GetDownloadInfo() {
		$DownloadInfo = array(); // result
		$VerifyRet = $this->Verify(FALSE);
		if (USER_IS_PREMIUM == $VerifyRet) {			
			$curl = curl_init();
			curl_setopt($curl, CURLOPT_USERAGENT,DOWNLOAD_STATION_USER_AGENT);
			curl_setopt($curl, CURLOPT_COOKIEJAR, TURBOBIT_COOKIE_FILE);
			curl_setopt($curl, CURLOPT_COOKIEFILE, TURBOBIT_COOKIE_FILE); 		
			curl_setopt($curl, CURLOPT_URL, $this->Url);
			curl_setopt($curl, CURLOPT_HTTPHEADER, array(HTTPHEADER));
			curl_setopt($curl, CURLOPT_HEADER, FALSE);
			curl_setopt($curl, CURLOPT_RETURNTRANSFER, TRUE);			
			$Page = curl_exec($curl);						
			curl_close($curl);			
		} else {  // no premium
			$DownloadInfo[DOWNLOAD_ERROR] = ERR_REQUIRED_PREMIUM;
			return $DownloadInfo;
		}
		
		if (FALSE == $Page) {
			$this->Log->LogWrite("Error during fetching download URL: ". $this->Url);
			$DownloadInfo[DOWNLOAD_ERROR] = ERR_FILE_NO_EXIST;
			return $DownloadInfo;								
		}

		// 'http://turbobit.net//download/redirect/6FFCE863E7FF709E7F6CB5E4AB06F240p/hik5y5p56iqh'
		preg_match("/'http:\/\/turbobit.net\/\/download\/redirect\/.*?'/", $Page, $match);
		if (isset($match[0])) {
			$DownloadInfo[DOWNLOAD_URL] = substr($match[0], 1, -1); // trim apostrophes			 
			$this->Log->LogWrite("(OK) Fetched URL: ", $DownloadInfo[DOWNLOAD_URL]);
		} else {
			$DownloadInfo[DOWNLOAD_ERROR] = ERR_FILE_NO_EXIST;
			$this->Log->LogAddFile("Downloading URL '$this->Url' doesnt exists or error parsing saved file: ",$Page, "turbobit_download_page", ".html");						
		} 				
		return $DownloadInfo;
	}
		
	/* 
	 This function verifies and returns account type.
	 It returns USER_IS_PREMIUM if it is true or LOGIN_FAIL if login failed or user is free.
	 Free accounts got a captcha and thus are not supported.	 
	*/    	
	public function Verify($ClearCookie)
	{		
		$ret = LOGIN_FAIL;
		
		// required (?) by Synology
		if ($ClearCookie && file_exists(TURBOBIT_COOKIE_FILE)) {			
			unlink(TURBOBIT_COOKIE_FILE);
			$this->Log->LogWrite("(OK) Cookie file cleared");
		}
		
		if (file_exists(TURBOBIT_COOKIE_FILE))  // try to use existing cookie before login
			if ($this->IsPremiumAccount())
				return USER_IS_PREMIUM; 
		
		if (!empty($this->Username) && !empty($this->Password)) {
			$ret = $this->TurbobitLogin($this->Username, $this->Password);
		}
		return $ret;	
	}
	
	//This function performs login action and checks kind of accout (free ore premium)
	//called from Verify. It returns values like in Verify description. 
	private function TurbobitLogin($Username, $Password) 
	{		
		// if (TB_SKIP_AUTH) 
		//	return USER_IS_PREMIUM;
		$ret = FALSE;
		/*
		file:///user/login?
		user[login]=kradam@gmail.com
		user[pass]=uphaknr1
		user[captcha_type]= 
		user[captcha_subtype]=
		user[submit]=Sign in
		user[memory]=on
		*/
		$PostData = array(
				'user[login]'=>$this->Username,
				'user[captcha_type]'=>'',
				'user[captcha_subtype]'=>'',
				'user[pass]'=>$this->Password,		
				'user[memory]'=>"on", // user memory means: remember me
				'user[submit]'=>"Sign in"	);  
		$PostData = http_build_query($PostData);
								
		$curl = curl_init();		
		curl_setopt($curl, CURLOPT_USERAGENT, DOWNLOAD_STATION_USER_AGENT);
		curl_setopt($curl, CURLOPT_POST, TRUE);		
		curl_setopt($curl, CURLOPT_POSTFIELDS, $PostData);
		curl_setopt($curl, CURLOPT_COOKIEJAR, TURBOBIT_COOKIE_FILE);
		curl_setopt($curl, CURLOPT_HEADER, FALSE); // set TRUE displays Header for debug purposes 
		curl_setopt($curl, CURLOPT_HTTPHEADER, array(HTTPHEADER));  
		curl_setopt($curl, CURLOPT_RETURNTRANSFER, FALSE);  	
		curl_setopt($curl, CURLOPT_URL, TURBOBIT_LOGIN_URL);		
		$LoginInfo = curl_exec($curl);		
		curl_close($curl);	
		
		// if HTTP POST succeeded
		if (FALSE != $LoginInfo && file_exists(TURBOBIT_COOKIE_FILE)) {
			$CookieFile = file_get_contents(TURBOBIT_COOKIE_FILE);			
			// cookieof logged user: user_isloggedin 1\n   
			preg_match('/user_isloggedin\s(.*)\n/', $CookieFile, $ret);			
			if (isset($ret[0])) 
				if (strstr($ret[0],"1")) {
					if (FALSE != $this->IsPremiumAccount()) {										
						$ret = USER_IS_PREMIUM;
					}
					else 
						$ret = USER_IS_FREE;				
				}
				else 
					$ret = LOGIN_FAIL;
		} else {
			$this->Log->LogWrite("Error during fetching ". TURBOBIT_LOGIN_URL);
			return FALSE;			
		}
									
		if ($ret != USER_IS_PREMIUM) {
			$this->Log->LogAddFile("Invalid user '$this->Username', free account (5) or login failed (4): $ret, cookie file saved: ", $CookieFile, "turbobit_cookie_file", ".txt");
		} else 
			$this->Log->LogWrite("(OK) User '$this->Username' logged as premium");
		
		return $ret;		
	}
	
	private function IsPremiumAccount()
	{
		$ret = FALSE;	
		$curl = curl_init();	
		curl_setopt($curl, CURLOPT_SSL_VERIFYPEER, FALSE);
		curl_setopt($curl, CURLOPT_USERAGENT, DOWNLOAD_STATION_USER_AGENT);
		curl_setopt($curl, CURLOPT_COOKIEFILE, TURBOBIT_COOKIE_FILE);
		curl_setopt($curl, CURLOPT_COOKIEJAR, TURBOBIT_COOKIE_FILE);
		curl_setopt($curl, CURLOPT_RETURNTRANSFER, TRUE);  // return page content	
		curl_setopt($curl, CURLOPT_URL, TURBOBIT_URL);
		curl_setopt($curl, CURLOPT_HTTPHEADER, array(HTTPHEADER));
		curl_setopt($curl, CURLOPT_HEADER, FALSE); // set TRUE displays Header for debug purposes	
		$AccountRet = curl_exec($curl);		
		curl_close($curl);
		if (FALSE == $AccountRet) {
			$this->Log->LogWrite("Error during fetching account URL: " . TURBOBIT_URL);		
			return FALSE;
		}				
		//$this->Log->LogWrite('$AccountRet', $AccountRet);
		if (strstr($AccountRet, "Turbo access till"))  {
			$this->Log->LogWrite("(OK) Premium user verified");
			$ret = TRUE;
		} else
			$this->Log->LogAddFile("Error, not a premium user, account page saved: ", $AccountRet, "turbobit_account_page", ".html");		
		return $ret;
	}
				
}

class Logger {
	const LogMediaWebPage = 1;
	const LogMediaFile = 2;
	
	private $LogFileName = null;	
	private $LogMedia= LogMediaWebPage;	

	public function __construct($LogMedia = Logger::LogMediaWebPage, $LogFileName = "log") {
		
		/* If (($LogMedia !=  Logger::LogMediaWebPage) && ($LogMedia != Logger::LogMediaFile)) 
			trigger_error("Bad value of Logmedia parameter in Logger class constructor.", E_USER_ERROR); */
		$this->LogMedia = $LogMedia;		
		$this->LogFileName = $LogFileName;	
		// echo("lm $this->LogMedia");
	}

	function __destruct() {	}

	//Open Logfile
	private function logOpen(){
		//$today = date('Y-m-d'); //Current Date
		//$this->handle = fopen($this->lName . '_' . $today, 'a') or exit("Can't open " . $this->lName . "_" . $today); //Open log file for writing, if it does not exist, create it.
	}

	//Write Message to Logfile
	public function LogWrite($Header = "", $Var = null) {	
		$msg = $Header . ($Var == null? "" : print_r($Var, TRUE));		
		if ($this->LogMedia == Logger::LogMediaWebPage) {
			echo $msg . "\n";
		}
		else {	
			$msg = "[" . date("m-d H:i:s") . "] " . $msg . PHP_EOL;
			file_put_contents($this->LogFileName, $msg, FILE_APPEND) or die("Failed");							
		}		
	}
	
	public function LogAddFile($Header, $FileContent, $FileNamePrefix = "", $FileExt = "") {
		if ($this->LogMedia == Logger::LogMediaWebPage) {
			echo $Header . "<BR/>\n" . $FileContent;		
		} else {						
			$FileName = dirname($this->LogFileName) . "/" . $FileNamePrefix . "_" . date("m-d_H-i-s") . $FileExt;
			$this->LogWrite($Header . $FileName);		
			file_put_contents($FileName, $FileContent, FILE_TEXT);
		}		
	} 
	
	public function LogClear(){	}
}

?>
