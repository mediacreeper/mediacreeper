<?php
/**
 * Mediacreeper API implementation
 *
 * May use the cURL extension in PHP
 * - Ubuntu: sudo apt-get install php5-curl
 * - CentOS: yum install php-common php-curl
 *
 * Written during 24 HBC
 * (c) 2011-2012 Martin Alice
 *
 */
class MediacreeperAPI {
	const CLIENT_VERSION	= 'MediaCreeper_for_WP/mediacreeper-v1.1.zip';
	const API_ENDPOINT	= 'http://api.mediacreeper.com/';
	const API_TIMEOUT	= 2;

	private $curl;

	public function __construct() {
		$this->curl = NULL;
		if(function_exists('curl_init')) {
			$this->curl = curl_init();
			curl_setopt($this->curl, CURLOPT_USERAGENT, self::CLIENT_VERSION);
			curl_setopt($this->curl, CURLOPT_TIMEOUT, self::API_TIMEOUT);
			curl_setopt($this->curl, CURLOPT_RETURNTRANSFER, 1);
			curl_setopt($this->curl, CURLOPT_FOLLOWLOCATION, 1);
			curl_setopt($this->curl, CURLOPT_ENCODING, 'gzip');
		}
	}

	/**
	 * Fetch data from Mediacreeper API
	 *
	 * @param $path Path to API function (i.e. 'site/search/example.com')
	 *
	 * @return Decoded JSON on success, NULL on failure
	 */
	public function call($path) {
		$url = self::API_ENDPOINT . $path;

		if($this->curl !== NULL) {
			curl_setopt($this->curl, CURLOPT_URL, $url);
			if(($data = @curl_exec($this->curl)) === FALSE) {
				$this->error = 'Mediacreeper: cURL failed to fetch "'. $url .'": '. curl_error($this->curl);
				return NULL;
			}
			else if(($status = curl_getinfo($this->curl, CURLINFO_HTTP_CODE)) !== 200) {
				$this->error = 'Mediacreeper: cURL got HTTP status '. $status .', expected 200. URL: '. $url;
				return NULL;
			}
		}
		else {
			$parts = parse_url($url);
			$host = $parts['host'];
			if($parts['scheme'] === 'https')
				$host = 'ssl://'. $host;

			$data = NULL;
			if(empty($parts['port'])) $parts['port'] = 80;
			if(($fd = fsockopen($host, $parts['port'], $errcode, $errstr, self::API_TIMEOUT)) !== FALSE) {
				$req = sprintf("GET %s HTTP/1.1\r\nHost: %s\r\nConnection: close\r\n\r\n",
						$parts['path'], $parts['host']);
				fwrite($fd, $req);
				$resp = '';
				while(!feof($fd))
					$resp .= fread($fd, 4096);
				fclose($fd);

				list($headers, $data) = explode("\r\n\r\n", $resp, 2);
				$headers = explode("\r\n", $headers);
				$statusline = explode(' ', $headers[0]);
				if(count($statusline) < 2 || $statusline[1] !== '200') {
					$this->error = 'Mediacereper: Unexpected server response "'. $headers[0] .'" for URL: '. $url;
					$data = NULL;
				}
			}
			else {
				$this->error = 'Mediacreeper: Failed to retrieve URL "'. $url .'": '. $errstr;
			}
		}

		return @json_decode($data);
	}

	/**
	 * IP returns clients IP, as it's received at api.mediacreeper.com, either as IPv4 or IPv6
	 *
	 * @return Object (ip, version, type)
	 */
	public function ip() {
		return $this->call('echo');
	}

	/**
	 * Time returns the servers local time (which should be GMT+0000) in two formats, wallclock and timestamp.
	 *
	 * @return Object (wallclock, timestamp)
	 */
	public function time() {
		return $this->call('time');
	}

	/**
	 * Online returns the days the service has been online.
	 *
	 * @return Object (days, exact)
	 */
	public function online() {
		return $this->call('online');
	}

	/**
	 * Latest returns a list of the latest 500 hits, just like the site.
	 *
	 * @return Array
	 */
	public function latest() {
		return $this->call('latest');
	}

	/**
	 * Latest/Since returns a list of hits since (timestamp)
	 *
	 * @param $timestamp UNIX timestamp
	 *
	 * @return Array
	 */
	public function latestSince($timestamp) {
		return $this->call('latest/since/'. urlencode($timestamp));
	}

	/**
	 * Site/List returns a list of sites with their corresponding IDs
	 *
	 * @return Array
	 */
	public function siteList() {
		return $this->call('site/list');
	}

	/**
	 * Site/ID returns a list of hits for site with ID, limited to 500
	 *
	 * @param $id Site ID
	 *
	 * @return Array
	 */
	public function siteID($id) {
		return $this->call('site/id/'. urlencode($id));
	}

	/**
	 * Site/Search returns a list of hits for site with searched tag, limited to 500
	 *
	 * @param $domain Domain, i.e 'example.com'
	 *
	 * @return Array
	 */
	public function siteSearch($domain) {
		return $this->call('site/search/'. urlencode($domain));
	}

	/**
	 * Name/List returns a list of names with their corresponding IDs
	 *
	 * @return Array
	 */
	public function nameList() {
		return $this->call('name/list');
	}

	/**
	 * Name/ID returns a list of hits for name with ID, limited to 100
	 *
	 * @param $id Name ID
	 *
	 * @return Array
	 */
	public function nameId($id) {
		return $this->call('name/id/'. urlencode($id));
	}

	/**
	 * Name/Search returns a list of hits for name with searched, limited to 100
	 *
	 * @param $tag Name tag to search for
	 *
	 * @return Array
	 */
	public function nameSearch($tag) {
		return $this->call('name/search/'. urlencode($tag));
	}

	/**
	 * Toplist/Site returns a toplist based on hits during the last 7 days
	 *
	 * @return Array
	 */
	public function toplistSite() {
		return $this->call('toplist/site');
	}

	/**
	 * Toplist/Name returns a toplist based on hits during the last 7 days
	 *
	 * @return Array
	 */
	public function toplistName() {
		return $this->call('toplist/name');
	}

	/**
	 * Toplist/Hidden returns a toplist based on hits during the last 7 days
	 *
	 * @return Array
	 */
	public function toplistHidden() {
		return $this->call('toplist/hidden');
	}

	/**
	 * Hits/Today returns a list of accumulated positive and total hits for todays date
	 *
	 * @return Array
	 */
	public function hitsToday() {
		return $this->call('hits/today');
	}

	/**
	 * Hits/LastWeek returns a list of accumulated positive and total hits per day for last week
	 *
	 * @return Array
	 */
	public function hitsLastWeek() {
		return $this->call('hits/lastweek');
	}

	/**
	 * Hits/LastMonth returns a list of accumulated positive and total hits per day for last month
	 *
	 * @return Array
	 */
	public function hitsLastMonth() {
		return $this->call('hits/lastmonth');
	}

	/**
	 * Hits/LastYear returns a list of accumulated positive and total hits per day for last year
	 *
	 * @return Array
	 */
	public function hitsLastYear() {
		return $this->call('hits/lastyear');
	}

	/**
	 * Hits/All returns a list of accumulated positive and total hits per day for all time
	 *
	 * @return Array
	 */
	public function hitsAll() {
		return $this->call('hits/all');
	}

	/**
	 * Attempt to find the site ID for site $site
	 *
	 * $domain Domain (i.e. "example.com")
	 *
	 * @return Site ID or 0 if not found
	 */
	public function getMediaCreeperSiteId($domain) {
		$domain = preg_replace('/^www\./', '', $domain);
		$arr = $this->siteSearch($domain);
		foreach($arr as $entry) {
			if(!strcasecmp($entry->site, $domain))
				return intval($entry->site_id);
		}

		return 0;
	}
}
