<?php
class TorrentSearchBTDigg {
	private $qurl = 'http://api.btdigg.org/api/private-ea6b127fc1020b5a/s02?q=%s&order=0';
	public function __construct() {
	}
	
	public function prepare($curl, $query) {
		$url = sprintf($this->qurl,urlencode($query));
		curl_setopt($curl, CURLOPT_URL, $url);
		curl_setopt($curl, CURLOPT_FAILONERROR, 1);
		curl_setopt($curl, CURLOPT_REFERER, $url);
		curl_setopt($curl, CURLOPT_FOLLOWLOCATION, 1);
		curl_setopt($curl, CURLOPT_RETURNTRANSFER,1);
		curl_setopt($curl, CURLOPT_TIMEOUT, 20);
		curl_setopt($curl, CURLOPT_USERAGENT, 'Mozilla/5.0 (Windows; U; Windows NT 5.1; en; rv:1.9.0.4) Gecko/2008102920 AdCentriaIM/1.7 Firefox/3.0.4');
		curl_setopt($curl, CURLOPT_ENCODING, 'deflate');
	}
	
	public function parse($plugin, $response) {
		$btlist = json_decode( $response );
		$count=0;
		
		foreach($btlist as $bt) {
			
			#$plugin->addResult($title, $download, $size, $datetime, $page, $hash, $seeds, $leechs, $category);
			$plugin->addResult($bt->name, $bt->magnet, $bt->size, date("Y/m/d",$bt->added), $bt->info, $bt->info_hash, 0, $bt->reqs, "BTDigg");
			$count++;
		}
		return $count;
	}
}
?>
