<?php

define('HELLSPY_LOGIN_URL', 'http://www.hellspy.com/dclient/auth/login');
define('HELLSPY_LOGOUT_URL', 'http://www.hellspy.com/dclient/auth/logout');
define('HELLSPY_PING_URL', 'http://www.hellspy.com/dclient/auth/ping');
define('HELLSPY_DETAIL_URL', 'http://www.hellspy.com/dclient/info/getFileDetail');

class HellSpy
{
    protected $userAgent = 'HellSpy.cz - client v1.0.2';
    protected $cookieJar;

    public $logged = false;
    public $userId = null;
    public $userName = null;

    public function __construct($cookieJar = 'hellspy.cookie')
    {
        $this->cookieJar = $cookieJar;
    }

    public function post($url, $data = false, $parseJson = true)
    {
        if ($data === false) $data = array();
        $data = http_build_query($data);
        $curl = curl_init();
        curl_setopt($curl, CURLOPT_SSL_VERIFYPEER, FALSE);
        curl_setopt($curl, CURLOPT_USERAGENT, $this->userAgent);
        curl_setopt($curl, CURLOPT_POST, TRUE);
        curl_setopt($curl, CURLOPT_POSTFIELDS, $data);
        curl_setopt($curl, CURLOPT_COOKIEFILE, $this->cookieJar);
        curl_setopt($curl, CURLOPT_COOKIEJAR, $this->cookieJar);
        curl_setopt($curl, CURLOPT_HEADER, FALSE);
        curl_setopt($curl, CURLOPT_RETURNTRANSFER, TRUE);
        curl_setopt($curl, CURLOPT_URL, $url);
        $response = curl_exec($curl);
        curl_close($curl);

        if ($parseJson && $response) {
            return json_decode($response, true);
        }

        return $response;
    }

    public function download($url, $dest)
    {
        $fp = fopen($dest, 'w');
        $curl = curl_init();
        curl_setopt($curl, CURLOPT_SSL_VERIFYPEER, FALSE);
        curl_setopt($curl, CURLOPT_USERAGENT, $this->userAgent);
        curl_setopt($curl, CURLOPT_COOKIEFILE, $this->cookieJar);
        curl_setopt($curl, CURLOPT_COOKIEJAR, $this->cookieJar);
        curl_setopt($curl, CURLOPT_HEADER, FALSE);
        curl_setopt($curl, CURLOPT_RETURNTRANSFER, TRUE);
        curl_setopt($curl, CURLOPT_URL, $url);
        curl_setopt($curl, CURLOPT_FILE, $fp);
        curl_setopt($curl, CURLOPT_FOLLOWLOCATION, true);
        curl_exec($curl);
        curl_close($curl);
        fclose($fp);

        return true;
    }

    public function login($username, $password)
    {
        $this->logged = false;
        $this->userId = null;
        $this->userName = null;

        $res = $this->post(HELLSPY_LOGIN_URL, array('passwd' => $password, 'login' => $username));
        if (!$res || 
            !is_array($res) ||
            !isset($res['message']) ||
            $res['message'] != 'OK') {
            return false;
        }
        
        $res = $this->post(HELLSPY_PING_URL);
        if (!$res || 
            !is_array($res) ||
            !isset($res['message']) ||
            !isset($res['data']) ||
            $res['message'] != 'OK') {
            return false;
        }

        if (isset($res['data']['identity']) &&
            isset($res['data']['identity']['user_id']) &&
            isset($res['data']['identity']['Login'])) {
            $this->logged = true;
            $this->userName = $res['data']['identity']['Login'];
            $this->userId = $res['data']['identity']['user_id'];
            return true;
        }

        return false;
    }

    public function logout()
    {
        $this->logged = false;
        $this->userId = null;
        $this->userName = null;

        $this->post(HELLSPY_LOGOUT_URL);
    }

    public function parseUrl($url)
    {
        $url = explode('/', $url);
        if (count($url) < 5) return false;
        if ($url[2] != 'www.hellspy.cz') return false;
        
        $fileId = intval($url['4']);
        $fileUri = $url['3'];

        if ($fileId <= 0) return false;
        if (empty($fileUri)) return false;

        return array(
            'fileId' => $fileId,
            'fileUri' => $fileUri,
        );
    }

    public function getFileDetail($fileId, $fileUri)
    {
        $res = $this->post(HELLSPY_DETAIL_URL, array(
            'file_id' => $fileId,
            'file_uri' => $fileUri,
        ));
        if (!$res || 
            !is_array($res) ||
            !isset($res['message']) ||
            !isset($res['data']) ||
            $res['message'] != 'OK') {
            return false;
        }
        return $res['data'];
    }

    public function getFileDetailByUrl($url)
    {
        if (!$url = $this->parseUrl($url)) return false;

        return $this->getFileDetail($url['fileId'], $url['fileUri']);
    }

    public function downloadFile($fileId, $fileUri, $destDir = './')
    {
        $data = $this->getFileDetail($fileId, $fileUri);
        if (!isset($data['download_url'])) return false;
        if (!isset($data['filename'])) return false;
        $url = $data['download_url'];
        $filename = $data['filename'];

        $res = $this->download($url, $destDir . $filename);

        return $res;
    }

    public function downloadFileByUrl($url, $destDir = './')
    {
        if (!$url = $this->parseUrl($url)) return false;
        return $this->downloadFile($url['fileId'], $url['fileUri'], $destDir);
    }
}

class SynoFileHostingHellSpy
{
    protected $url, $username, $password, $hostInfo;
    protected $hellspy;

    public function __construct($url, $username, $password, $hostInfo)
    {
        $this->url = $url;
        $this->username = $username;
        $this->password = $password;
        $this->hostInfo = $hostInfo;

        $this->hellspy = new HellSpy('/tmp/hellspy.cookie');
    }

    public function GetDownloadInfo()
    {
        $ver = $this->Verify(false);

        if ($ver == LOGIN_FAIL) {
            return array(
                DOWNLOAD_ERROR => LOGIN_FAIL,
            );
        }

        if (!$file = $this->hellspy->getFileDetailByUrl($this->url)) {
            return array(
                DOWNLOAD_ERROR => ERR_FILE_NO_EXIST,
            );
        }

        return array(
            DOWNLOAD_COOKIE => '/tmp/hellspy.cookie',
            DOWNLOAD_ISPARALLELDOWNLOAD => true,
            DOWNLOAD_FILENAME => $file['filename'],
            DOWNLOAD_URL => $file['download_url'],
        );
    }

    public function Verify($clearCookie = false)
    {
        if (!$this->hellspy->login($this->username, $this->password)) {
            return LOGIN_FAIL;
        }

        if ($clearCookie) {
            $this->hellspy->logout();
        }

        return USER_IS_PREMIUM;
    }
}
