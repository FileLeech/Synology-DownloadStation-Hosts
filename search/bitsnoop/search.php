<?php
class TorrentSearchBitSnoop {
	private $qurl = 'http://bitsnoop.com/search/all/%s/c/d/1/?fmt=rss';
	public function __construct() {
	}
	
	public function prepare($curl, $query) {
		$url = sprintf($this->qurl,urlencode(str_replace('-',' ',$query)));
		curl_setopt($curl, CURLOPT_URL, $url);

		curl_setopt($curl, CURLOPT_FAILONERROR, 1);
		curl_setopt($curl, CURLOPT_REFERER, $qurl);
		curl_setopt($curl, CURLOPT_FOLLOWLOCATION, 1);
		curl_setopt($curl, CURLOPT_RETURNTRANSFER,1);
		curl_setopt($curl, CURLOPT_TIMEOUT, 20);
		curl_setopt($curl, CURLOPT_USERAGENT, 'Mozilla/5.0 (Windows; U; Windows NT 5.1; en; rv:1.9.0.4) Gecko/2008102920 AdCentriaIM/1.7 Firefox/3.0.4');
		curl_setopt($curl, CURLOPT_ENCODING, 'deflate');
	}
	
	public function parse($plugin, $response) {
		$regexp2 = 		"<item>.*".
					"<title>(.*)</title>.*".//title
					"<category>(.*)</category>.*".//category
					"<link>(.*)</link>.*".//page
					"<numSeeders>(.*)</numSeeders>.*".//seeds
					"<numLeechers>(.*)</numLeechers>.*".//leechs
					"<numFiles>(.*)</numFiles>.*".//files
					"<size>(.*)</size>.*".//size
					"<enclosure url=\"(.*)\".*".//download
					"<infoHash>(.*)</infoHash>.*".//hash
					"<magnetURI><!\[CDATA\[(.*)\]\].*".//magnet
					"</item>";

		$count=0;
		if(preg_match_all("|$regexp2|siU", $response, $matches2, PREG_SET_ORDER)) {
			foreach($matches2 as $match2) {
				$i = 1;
				$title=$match2[$i++];
				$category=$match2[$i++];
				$page=$match2[$i++];
				$seeds=$match2[$i++];
				$leechs=$match2[$i++];
				$datetime="files:".$match2[$i++]; //use to show files number, date value in not available in RSS.
				$size=$match2[$i++];
				$download=$match2[$i++];
				$hash=$match2[$i++];
				$magnet=$match2[$i++];
				$plugin->addResult($title, $magnet, $size, $datetime, $page, $hash, $seeds, $leechs, $category);
				$count++;
			}
		}
		return $count;
	}
}
?>
