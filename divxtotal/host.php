<?php

class SynoFileHostingDivxTotal
{

    private $Url;
    private $Username;
    private $Password;
    private $HostInfo;

    public function __construct($Url, $Username, $Password, $HostInfo)
    {
        $this->Url = $Url;
        $this->Username = $Username;
        $this->Password = $Password;
        $this->HostInfo = $HostInfo;
    }

    public function GetDownloadInfo()
    {
        $curl = curl_init();
        curl_setopt($curl, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($curl, CURLOPT_FOLLOWLOCATION, 1);
        curl_setopt($curl, CURLOPT_USERAGENT, DOWNLOAD_STATION_USER_AGENT);
        curl_setopt($curl, CURLOPT_POST, true);
        curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($curl, CURLOPT_URL, $this->Url);

        $page = curl_exec($curl);
        preg_match("/download\.php\?id\=(.*?)\"/", $page, $match);
        $DownloadInfo[DOWNLOAD_URL] = "http://www.divxtotal.com/download.php?id=" . $match[1];

        return $DownloadInfo;
    }

    public function Verify($ClearCookie)
    {
        return USER_IS_FREE;
    }
}
?>
