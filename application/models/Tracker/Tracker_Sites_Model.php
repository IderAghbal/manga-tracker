<?php declare(strict_types=1); defined('BASEPATH') OR exit('No direct script access allowed');

/**
 * Class Tracker_Sites_Model
 */
class Tracker_Sites_Model extends CI_Model {
	public function __construct() {
		parent::__construct();
	}

	public function __get($name) {
		//TODO: Is this a good idea? There wasn't a good consensus on if this is good practice or not..
		//      It's probably a minor speed reduction, but that isn't much of an issue.
		//      An alternate solution would simply have a function which generates a PHP file with code to load each model. Similar to: https://github.com/shish/shimmie2/blob/834bc740a4eeef751f546979e6400fd089db64f8/core/util.inc.php#L1422
		$validClasses = [
			'Base_Site_Model',
			'Base_FoolSlide_Site_Model',
			'Base_myMangaReaderCMS_Site_Model',
			'Base_GlossyBright_Site_Model',
			'Base_Roku_Site_Model',
			'Base_WP_Manga_Site_Model'
		];
		if(!class_exists($name) || !(in_array(get_parent_class($name), $validClasses))) {
			return get_instance()->{$name};
		} else {
			$this->loadSite($name);
			return $this->{$name};
		}
	}

	private function loadSite(string $siteName) : void {
		$this->{$siteName} = new $siteName();
	}
}

abstract class Base_Site_Model extends CI_Model {
	public $site          = '';
	public $titleFormat   = '//';
	public $chapterFormat = '//';
	public $pageSeparator = ''; //NOTE: Each site must set this manually.
	public $hasCloudFlare = FALSE;
	public $userAgent     = 'Mozilla/5.0 (Windows NT 6.1; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/65.0.3325.181 Safari/537.36';

	public $baseURL = '';

	/**
	 * 0: No custom updater.
	 * 1: Uses following page.
	 * 2: Uses latest releases page.
	 */
	public $customType = 0;

	public $canHaveNoChapters = FALSE;

	public $siteRateLimit = 600;

	public function __construct() {
		parent::__construct();

		$this->load->database();

		$this->site = get_class($this);
	}

	/**
	 * Generates URL to the title page of the requested series.
	 *
	 * NOTE: In some cases, we are required to store more data in the title_string than is needed to generate the URL. (Namely as the title_string is our unique identifier for that series)
	 *       When storing additional data, we use ':--:' as a delimiter to separate the data. Make sure to handle this as needed.
	 *
	 * Example:
	 *    return "http://mangafox.me/manga/{$title_url}/";
	 *
	 * Example (with extra data):
	 *    $title_parts = explode(':--:', title_url);
	 *    return "https://bato.to/comic/_/comics/-r".$title_parts[0];
	 *
	 * @param string $title_url
	 * @return string
	 */
	abstract public function getFullTitleURL(string $title_url) : string;

	/**
	 * Generates chapter data from given $title_url and $chapter.
	 *
	 * Chapter must be in a (v[0-9]+/)?c[0-9]+(\..+)? format.
	 *
	 * NOTE: In some cases, we are required to store the chapter number, and the segment required to generate the chapter URL separately.
	 *       Much like when generating the title URL, we use ':--:' as a delimiter to separate the data. Make sure to handle this as needed.
	 *
	 * Example:
	 *     return [
	 *        'url'    => $this->getFullTitleURL($title_url).'/'.$chapter,
	 *        'number' => "c{$chapter}"
	 *    ];
	 *
	 * @param string $title_url
	 * @param string $chapter
	 * @return array [url, number]
	 */
	abstract public function getChapterData(string $title_url, string $chapter) : array;

	/**
	 * Generates chapter page URL from given chapterData.
	 *
	 * Will return NULL if pageSeparator is not set.
	 *
	 * @param array $chapterData
	 * @param int   $page
	 *
	 * @return null|string
	 */
	final public function getChapterPageURL(array $chapterData, int $page = 1) : ?string {
		$pageURL = NULL;
		if($this->pageSeparator !== '') {
			$pageSeparator = $this->pageSeparator;
			if(substr($chapterData['url'], -1) === $pageSeparator) {
				//We don't want double trailing slashes, so fix this when possible.
				$pageSeparator = '';
			}
			$pageURL = $chapterData['url'] . $pageSeparator . $page;
		}
		return $pageURL;
	}

	/**
	 * Used to get the latest chapter of given $title_url.
	 *
	 * This <should> utilize both get_content and parseTitleDataDOM functions when possible, as these can both reduce a lot of the code required to set this up.
	 *
	 * $titleData params must be set accordingly:
	 * * `title` should always be used with html_entity_decode.
	 * * `latest_chapter` must match $this->chapterFormat.
	 * * `last_updated` should always be in date("Y-m-d H:i:s") format.
	 * * `followed` should never be set within via getTitleData, with the exception of via a array_merge with doCustomFollow.
	 *
	 * $firstGet is set to true when the series is first added to the DB, and is used to follow the series on given site (if possible).
	 *
	 * @param string $title_url
	 * @param bool   $firstGet
	 * @return array|null [title,latest_chapter,last_updated,followed?]
	 */
	abstract public function getTitleData(string $title_url, bool $firstGet = FALSE) : ?array;

	public function handleBatchUpdate(string $title_url) : array {
		$return = [
			'limited'   => FALSE,
			'titleData' => NULL
		];
		if(($rateLimit = $this->_getSiteRateLimit()) <= $this->siteRateLimit) {
			$this->_setSiteRateLimit($rateLimit);

			$return['titleData'] = $this->getTitleData($title_url);
		} else {
			$return['limited'] = TRUE;
		}
		return $return;
	}

	/**
	 * Validates given $title_url against titleFormat.
	 *
	 * Failure to match against titleFormat will stop the series from being added to the DB.
	 *
	 * @param string $title_url
	 * @return bool
	 */
	final public function isValidTitleURL(string $title_url) : bool {
		$success = (bool) preg_match($this->titleFormat, $title_url);
		if(!$success) log_message('error', "Invalid Title URL ({$this->site}): {$title_url}");
		return $success;
	}

	/**
	 * Validates given $chapter against chapterFormat.
	 *
	 * Failure to match against chapterFormat will stop the chapter being updated.
	 *
	 * @param string $chapter
	 * @return bool
	 */
	final public function isValidChapter(string $chapter) : bool {
		$success = (bool) preg_match($this->chapterFormat, $chapter);
		if(!$success) log_message('error', "Invalid Chapter ({$this->site}): {$chapter}");
		return $success;
	}



	public function stripChapter(string $chapter) : string {
		return $chapter;
	}

	/**
	 * Used by getTitleData (& similar functions) to get the requested page data.
	 *
	 * @param string $url
	 * @param string $cookie_string
	 * @param string $cookiejar_path
	 * @param bool   $follow_redirect
	 * @param bool   $isPost
	 * @param array  $postFields
	 *
	 * @return array|bool
	 */
	final protected function get_content(string $url, string $cookie_string = "", string $cookiejar_path = "", bool $follow_redirect = FALSE, bool $isPost = FALSE, array $postFields = []) {
		$refresh = TRUE; //For sites that have CloudFlare, we want to loop get_content again.
		$loops   = 0;
		while($refresh && $loops < 2) {
			$refresh = FALSE;
			$loops++;

			$ch = curl_init();
			curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
			curl_setopt($ch, CURLOPT_ENCODING , "gzip");
			//curl_setopt($ch, CURLOPT_VERBOSE, 1);
			curl_setopt($ch, CURLOPT_HEADER, 1);

			if($follow_redirect)        curl_setopt($ch, CURLOPT_FOLLOWLOCATION, TRUE);

			if($cookies = $this->cache->get("cloudflare_{$this->site}")) {
				$cookie_string .= "; {$cookies}";
			}

			if(!empty($cookie_string))  curl_setopt($ch, CURLOPT_COOKIE, $cookie_string);
			if(!empty($cookiejar_path)) curl_setopt($ch, CURLOPT_COOKIEFILE, $cookiejar_path);

			//Some sites check the useragent for stuff, use a pre-defined user-agent to avoid stuff.
			curl_setopt($ch, CURLOPT_USERAGENT, $this->userAgent);

			//NOTE: This is required for SSL URLs for now. Without it we tend to get error code 60.
			curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, TRUE);

			curl_setopt($ch, CURLOPT_URL, $url);

			if($isPost) {
				curl_setopt($ch,CURLOPT_POST, count($postFields));
				curl_setopt($ch,CURLOPT_POSTFIELDS, http_build_query($postFields));
			}

			$response = curl_exec($ch);

			$this->Tracker->admin->incrementRequests();

			if($response === FALSE) {
				log_message('error', "curl failed with error: ".curl_errno($ch)." | ".curl_error($ch));
				//FIXME: We don't always account for FALSE return
				return FALSE;
			}

			$status_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
			$header_size = curl_getinfo($ch, CURLINFO_HEADER_SIZE);
			$headers     = http_parse_headers(substr($response, 0, $header_size));
			$body        = substr($response, $header_size);
			curl_close($ch);

			if($status_code === 503) $refresh = $this->handleCloudFlare($url, $body);
		}

		return [
			'headers'     => $headers,
			'status_code' => $status_code,
			'body'        => $body
		];
	}

	final private function handleCloudFlare(string $url, string $body) : bool {
		$refresh = FALSE;

		if((strpos($body, 'DDoS protection by Cloudflare') !== FALSE) || (strpos($body, '<input type="hidden" id="jschl-answer" name="jschl_answer"/>') !== FALSE)) {
			//print "Cloudflare detected? Grabbing Cookies.\n";
			if(!$this->hasCloudFlare) {
				//TODO: Site appears to have enabled CloudFlare, disable it and contact admin.
				//      We'll continue to bypass CloudFlare as this may occur in a loop.
			}

			$urlData = [
				'url'        => $url,
				'user_agent' => $this->userAgent
			];
			//TODO: shell_exec seems bad since the URLs "could" be user inputted? Better way of doing this?
			$result = shell_exec('python '.APPPATH.'../_scripts/get_cloudflare_cookie.py '.escapeshellarg(json_encode($urlData)));
			$cookieData = json_decode($result, TRUE);

			$this->cache->save("cloudflare_{$this->site}", $cookieData['cookies'],  31536000 /* 1 year, or until we renew it */);
			log_message('debug', "Saving CloudFlare Cookies for {$this->site}");

			$refresh = TRUE;
		} else {
			//Either site doesn't have CloudFlare or we have bypassed it. Either is good!
		}
		return $refresh;
	}

	/**
	 * Used by getTitleData to get the title, latest_chapter & last_updated data from the data returned by get_content.
	 *
	 * parseTitleDataDOM checks if the data returned by get_content is valid via a few simple checks.
	 * * If the request was actually successful, had a valid status code & data wasn't empty. We also do an additional check on an optional $failure_string param, which will throw a failure if it's matched.
	 *
	 * Data is cleaned by cleanTitleDataDOM prior to being passed to DOMDocument.
	 *
	 * All $node_* params must be XPath to the requested node, and must only return 1 result. Anything else will throw a failure.
	 *
	 * @param array        $content
	 * @param string       $title_url
	 * @param string       $node_title_string
	 * @param string       $node_row_string
	 * @param string       $node_latest_string
	 * @param string       $node_chapter_string
	 * @param closure|null $failureCall
	 * @param closure|null $noChaptersCall
	 * @param closure|null $extraCall
	 * @param closure|null $statusCall //FIXME: This is really ugly.
	 *
	 * @return DOMElement[]|false [nodes_title,nodes_chapter,nodes_latest]
	 */
	final protected function parseTitleDataDOM(
		$content, string $title_url,
		string $node_title_string, string $node_row_string,
		string $node_latest_string, string $node_chapter_string,
		closure $failureCall = NULL, closure $noChaptersCall = NULL, closure $extraCall = NULL, closure $statusCall = NULL) {

		if(!is_array($content)) {
			log_message('error', "{$this->site} : {$title_url} | Failed to grab URL (See above curl error)");
		} else {
			list('headers' => $headers, 'status_code' => $status_code, 'body' => $data) = $content;

			if(!($status_code >= 200 && $status_code < 300)) {
				if($status_code === 502) {
					// Site is overloaded, no need to log this.
				} else if(!is_null($statusCall) && is_callable($statusCall) && $statusReturn = $statusCall($status_code, $data)) {
					if(!array_key_exists('ignore', $statusReturn)) {
						log_message('error', "{$this->site} : {$title_url} | Failure status call matched");
					}
				} else {
					log_message('error', "{$this->site} : {$title_url} | Bad Status Code ({$status_code})");
				}
			} else if(empty($data)) {
				log_message('error', "{$this->site} : {$title_url} | Data is empty? (Status code: {$status_code})");
			} else if(!is_null($failureCall) && is_callable($failureCall) && $failureCall($data)) {
				log_message('error', "{$this->site} : {$title_url} | Failure call matched");
			} else {
				$data = $this->cleanTitleDataDOM($data); //This allows us to clean the DOM prior to parsing. It's faster to grab the only part we need THEN parse it.

				$dom = new DOMDocument();
				libxml_use_internal_errors(TRUE);
				$dom->loadHTML('<?xml encoding="utf-8" ?>' . $data);
				libxml_use_internal_errors(FALSE);

				$xpath = new DOMXPath($dom);
				$nodes_title = $xpath->query($node_title_string);
				$nodes_row   = $xpath->query($node_row_string);
				if($nodes_title->length === 1) {
					if($nodes_row->length === 1) {
						$firstRow      = $nodes_row->item(0);
						$nodes_latest  = $xpath->query($node_latest_string,  $firstRow);

						if($node_chapter_string !== '') {
							$nodes_chapter = $xpath->query($node_chapter_string, $firstRow);
						} else {
							$nodes_chapter = $nodes_row;
						}

						if($nodes_latest->length === 1 && $nodes_chapter->length === 1) {
							$returnData = [
								'nodes_title'   => $nodes_title->item(0),
								'nodes_latest'  => $nodes_latest->item(0),
								'nodes_chapter' => $nodes_chapter->item(0)
							];

							if(is_callable($extraCall)) $extraCall($xpath, $returnData);

							return $returnData;
						} else {
							log_message('error', "{$this->site} : {$title_url} | Invalid amount of nodes (LATEST: {$nodes_latest->length} | CHAPTER: {$nodes_chapter->length})");
						}
					} elseif($this->canHaveNoChapters && !is_null($noChaptersCall) && is_callable($noChaptersCall)) {
						$returnData = [
							'nodes_title'   => $nodes_title->item(0)
						];

						$noChaptersCall($data, $xpath, $returnData);

						if(is_array($returnData)) {
							if(is_callable($extraCall) && is_array($returnData)) $extraCall($xpath, $returnData);
						} else {
							log_message('error', "{$this->site} : {$title_url} | canHaveNoChapters set, but doesn't match possible checks! XPath is probably broken.");
						}

						return $returnData;
					} else {
						log_message('error', "{$this->site} : {$title_url} | Invalid amount of nodes (ROW: {$nodes_row->length})");
					}
				} else {
					log_message('error', "{$this->site} : {$title_url} | Invalid amount of nodes (TITLE: {$nodes_title->length})");
				}
			}
		}

		return FALSE;
	}

	/**
	 * Used by parseTitleDataDOM to clean the data prior to passing it to DOMDocument & DOMXPath.
	 * This is mostly done as an (assumed) speed improvement due to the reduced amount of DOM to parse, or simply just making it easier to parse with XPath.
	 *
	 * @param string $data
	 * @return string
	 */
	public function cleanTitleDataDOM(string $data) : string {
		return $data;
	}

	/**
	 * Used to follow a series on given site if supported.
	 *
	 * This is called by getTitleData if $firstGet is true (which occurs when the series is first being added to the DB).
	 *
	 * Most of the actual following is done by handleCustomFollow.
	 *
	 * @param string $data
	 * @param array  $extra
	 * @return array
	 */
	final public function doCustomFollow(string $data = "", array $extra = []) : array {
		$titleData = [];
		$this->handleCustomFollow(function($content, $id, closure $successCallback = NULL) use(&$titleData) {
			if(is_array($content)) {
				if(array_key_exists('status_code', $content)) {
					$statusCode = $content['status_code'];
					if($statusCode === 200) {
						$isCallable = is_callable($successCallback);
						if(($isCallable && $successCallback($content['body'])) || !$isCallable) {
							$titleData['followed'] = 'Y';

							log_message('info', "doCustomFollow succeeded for {$id}");
						} else {
							log_message('error', "doCustomFollow failed (Invalid response?) for {$id}");
						}
					} else {
						log_message('error', "doCustomFollow failed (Invalid status code ({$statusCode})) for {$id}");
					}
				} else {
					log_message('error', "doCustomFollow failed (Missing status code?) for {$id}");
				}
			} else {
				log_message('error', "doCustomFollow failed (Failed request) for {$id}");
			}
		}, $data, $extra);
		return $titleData;
	}

	/**
	 * Used by doCustomFollow to handle following series on sites.
	 *
	 * Uses get_content to get data.
	 *
	 * $callback must return ($content, $id, closure $successCallback = NULL).
	 * * $content is simply just the get_content data.
	 * * $id is the dbID. This should be passed by the $extra arr.
	 * * $successCallback is an optional success check to make sure the series was properly followed.
	 *
	 * @param callable $callback
	 * @param string   $data
	 * @param array    $extra
	 */
	public function handleCustomFollow(callable $callback, string $data = "", array $extra = []) {
		if($this->customType === 2) {
			$content = ['status_code' => 200];
			$callback($content, $extra['id']);
		}
	}

	/**
	 * Used to check the sites following page for new updates (if supported).
	 * This should work much like getTitleData, but instead checks the following page.
	 *
	 * This must return an array containing arrays of each of the chapters data.
	 */
	public function doCustomUpdate() {}

	/**
	 * Used by the custom updater to check if a chapter looks newer than the current one.
	 *
	 * This calls doCustomCheckCompare which handles the majority of the checking.
	 * NOTE: Depending on the site, you may need to call getChapterData to get the chapter number to be used with this.
	 *
	 * @param string $oldChapterString
	 * @param string $newChapterString
	 * @return bool
	 */
	public function doCustomCheck(?string $oldChapterString, string $newChapterString) : bool {
		if(!is_null($oldChapterString)) {
			$oldChapterSegments = explode('/', $this->getChapterData('', $oldChapterString)['number']);
			$newChapterSegments = explode('/', $this->getChapterData('', $newChapterString)['number']);

			$status = $this->doCustomCheckCompare($oldChapterSegments, $newChapterSegments);
		} else {
			$status = TRUE;
		}

		return $status;
	}

	/**
	 * Used by doCustomCheck to check if a chapter looks newer than the current one.
	 * Chapter must be in a (v[0-9]+/)?c[0-9]+(\..+)? format.
	 *
	 * To avoid issues with the occasional off case, this will only ever return true if we are 100% sure that the new chapter is newer than the old one.
	 *
	 * @param array $oldChapterSegments
	 * @param array $newChapterSegments
	 * @return bool
	 */
	final public function doCustomCheckCompare(array $oldChapterSegments, array $newChapterSegments) : bool {
		//NOTE: We only need to check against the new chapter here, as that is what is used for confirming update.
		$status = FALSE;

		//Make sure we have a volume element
		if(count($oldChapterSegments) === 1) array_unshift($oldChapterSegments, 'v0');
		if(count($newChapterSegments) === 1) array_unshift($newChapterSegments, 'v0');

		$oldCount = count($oldChapterSegments);
		$newCount = count($newChapterSegments);
		if($newCount === $oldCount) {
			//Make sure chapter format looks correct.
			//NOTE: We only need to check newCount as we know oldCount is the same count.
			if($newCount === 2) {
				//FIXME: Can we loop this?
				$oldVolume = substr(array_shift($oldChapterSegments), 1);
				$newVolume = substr(array_shift($newChapterSegments), 1);

				//Forcing volume to 0 as TBD might not be the latest (although it can be, but that is covered by other checks)
				if(in_array($oldVolume, ['TBD', 'TBA', 'NA', 'LMT'])) $oldVolume = 0;
				if(in_array($newVolume, ['TBD', 'TBA', 'NA', 'LMT'])) $newVolume = 0;

				$oldVolume = floatval($oldVolume);
				$newVolume = floatval($newVolume);
			} else {
				$oldVolume = 0;
				$newVolume = 0;
			}
			$oldChapter = floatval(substr(array_shift($oldChapterSegments), 1));
			$newChapter = floatval(substr(array_shift($newChapterSegments), 1));

			if($newChapter > $oldChapter && ($oldChapter >= 10 && $newChapter >= 10)) {
				//$newChapter is higher than $oldChapter AND $oldChapter and $newChapter are both more than 10
				//This is intended to cover the /majority/ of valid updates, as we technically shouldn't have to check volumes.

				$status = TRUE;
			} elseif($newVolume > $oldVolume && ($oldChapter < 10 && $newChapter < 10)) {
				//This is pretty much just to match a one-off case where the site doesn't properly increment chapter numbers across volumes, and instead does something like: v1/c1..v1/c5, v2/c1..v1/c5 (and so on).
				$status = TRUE;
			} elseif($newVolume > $oldVolume && $newChapter >= $oldChapter) {
				//$newVolume is higher, and chapter is higher so no need to check chapter.
				$status = TRUE;
			} elseif($newChapter > $oldChapter) {
				//$newVolume isn't higher, but chapter is.
				$status = TRUE;
			}
		}

		return $status;
	}

	final private function _getSiteRateLimit() : int {
		return (int) ($this->cache->get("{$this->site}_ratelimit") ?: 0);
	}
	final private function _setSiteRateLimit(?int $rateLimit = NULL) : bool {
		//We would just use increment(), but we can't set ttl with it...
		$currentRateLimit = $rateLimit ?: $this->_getSiteRateLimit();
		return $this->cache->save("{$this->site}_ratelimit", $currentRateLimit + 1,3600);
	}
}

abstract class Base_FoolSlide_Site_Model extends Base_Site_Model {
	public $titleFormat   = '/^[a-z0-9_-]+$/';
	public $chapterFormat = '/^(?:en(?:-us)?|pt|es)\/[0-9]+(?:\/[0-9]+(?:\/[0-9]+(?:\/[0-9]+)?)?)?$/';
	public $pageSeparator = 'page/';
	public $customType    = 2;

	public function getFullTitleURL(string $title_url) : string {
		return "{$this->baseURL}/series/{$title_url}";
	}

	public function getChapterData(string $title_url, string $chapter) : array {
		$chapter_parts = explode('/', $chapter); //returns #LANG#/#VOLUME#/#CHAPTER#/#CHAPTER_EXTRA#(/#PAGE#/)
		return [
			'url'    => $this->getChapterURL($title_url, $chapter),
			'number' => ($chapter_parts[1] !== '0' ? "v{$chapter_parts[1]}/" : '') . "c{$chapter_parts[2]}" . (isset($chapter_parts[3]) ? ".{$chapter_parts[3]}" : '')/*)*/
		];
	}
	public function getChapterURL(string $title_url, string $chapter) : string {
		return "{$this->baseURL}/read/{$title_url}/{$chapter}/";
	}

	public function getTitleData(string $title_url, bool $firstGet = FALSE) : ?array {
		$titleData = [];

		$jsonURL = $this->getJSONTitleURL($title_url);
		if($content = $this->get_content($jsonURL)) {
			$json = json_decode($content['body'], TRUE);
			if($json && isset($json['chapters']) && count($json['chapters']) > 0) {
				$titleData['title'] = trim($json['comic']['name']);

				//FoolSlide title API doesn't appear to let you sort (yet every other API method which has chapters does, so we need to sort ourselves..
				usort($json['chapters'], function($a, $b) {
					return floatval("{$b['chapter']['chapter']}.{$b['chapter']['subchapter']}") <=> floatval("{$a['chapter']['chapter']}.{$a['chapter']['subchapter']}");
				});
				$latestChapter = reset($json['chapters'])['chapter'];

				$latestChapterString = "{$latestChapter['language']}/{$latestChapter['volume']}/{$latestChapter['chapter']}";
				if($latestChapter['subchapter'] !== '0') {
					$latestChapterString .= "/{$latestChapter['subchapter']}";
				}
				$titleData['latest_chapter'] = $latestChapterString;

				//No need to use date() here since this is already formatted as such.
				$titleData['last_updated'] = ($latestChapter['updated'] !== '0000-00-00 00:00:00' ? $latestChapter['updated'] : $latestChapter['created']);
			}
		}

		return (!empty($titleData) ? $titleData : NULL);
	}

	public function doCustomUpdate() {
		$titleDataList = [];

		$jsonURL = $this->getJSONUpdateURL();
		if(($content = $this->get_content($jsonURL)) && $content['status_code'] == 200) {
			if(($json = json_decode($content['body'], TRUE)) && isset($json['chapters'])) {
				//This should fix edge cases where chapters are uploaded in bulk in the wrong order (HelveticaScans does this with Mousou Telepathy).
				usort($json['chapters'], function($a, $b) {
					$a_date = new DateTime($a['chapter']['updated'] !== '0000-00-00 00:00:00' ? $a['chapter']['updated'] : $a['chapter']['created']);
					$b_date = new DateTime($b['chapter']['updated'] !== '0000-00-00 00:00:00' ? $b['chapter']['updated'] : $b['chapter']['created']);
					return $b_date <=> $a_date;
				});

				$parsedTitles = [];
				foreach($json['chapters'] as $chapterData) {
					if(!in_array($chapterData['comic']['stub'], $parsedTitles)) {
						$parsedTitles[] = $chapterData['comic']['stub'];

						$titleData = [];
						$titleData['title'] = trim($chapterData['comic']['name']);

						$latestChapter = $chapterData['chapter'];

						$latestChapterString = "en/{$latestChapter['volume']}/{$latestChapter['chapter']}";
						if($latestChapter['subchapter'] !== '0') {
							$latestChapterString .= "/{$latestChapter['subchapter']}";
						}
						$titleData['latest_chapter'] = $latestChapterString;

						//No need to use date() here since this is already formatted as such.
						$titleData['last_updated'] = ($latestChapter['updated'] !== '0000-00-00 00:00:00' ? $latestChapter['updated'] : $latestChapter['created']);

						$titleDataList[$chapterData['comic']['stub']] = $titleData;
					} else {
						//We already have title data for this title.
						continue;
					}
				}
			} else {
				log_message('error', "{$this->site} - Custom updating failed (no chapters arg?) for {$this->baseURL}.");
			}
		} else {
			log_message('error', "{$this->site} - Custom updating failed for {$this->baseURL}.");
		}

		return $titleDataList;
	}

	public function getJSONTitleURL(string $title_url) : string {
		return "{$this->baseURL}/api/reader/comic/stub/{$title_url}/format/json";
	}
	public function getJSONUpdateURL() : string {
		return "{$this->baseURL}/api/reader/chapters/orderby/desc_created/format/json";
	}
}

abstract class Base_myMangaReaderCMS_Site_Model extends Base_Site_Model {
	public $titleFormat   = '/^[a-zA-Z0-9_-]+$/';
	public $chapterFormat = '/^(?:oneshot|(?:chapter-)?[a-zA-Z0-9\._-]+)$/';
	public $pageSeparator = '/';
	public $customType    = 2;

	public function getFullTitleURL(string $title_url) : string {
		return "{$this->baseURL}/manga/{$title_url}";
	}

	public function getChapterData(string $title_url, string $chapter) : array {
		$chapterN = (ctype_digit($chapter) ? "c${chapter}" : $chapter);
		return [
			'url'    => $this->getChapterURL($title_url, $chapter),
			'number' => $chapterN
		];
	}
	public function getChapterURL(string $title_url, string $chapter) : string {
		return $this->getFullTitleURL($title_url).'/'.$chapter;
	}

	public function getTitleData(string $title_url, bool $firstGet = FALSE) : ?array {
		$titleData = [];

		$fullURL = $this->getFullTitleURL($title_url);

		$content = $this->get_content($fullURL);

		$data = $this->parseTitleDataDOM(
			$content,
			$title_url,
			"(//h2[@class='widget-title'])[1]",
			"//ul[contains(@class, 'chapters')]/li[not(contains(@class, 'btn'))][1]",
			"div[contains(@class, 'action')]/div[@class='date-chapter-title-rtl']",
			'h5/a[1] | h3/a[1]',
			NULL,
			NULL,
			NULL,
			function(int $status_code, $data) {
				// We want to silently fail here.
				$success = ($status_code === 500 && strpos($data, 'Whoops, looks like something went wrong.') !== FALSE);
				return ['success' => $success, 'ignore' => TRUE];
			}
		);
		if($data) {
			$titleData['title'] = trim($data['nodes_title']->textContent);

			$segments = explode('/', (string) $data['nodes_chapter']->getAttribute('href'));
			$needle = array_search('manga', array_reverse($segments, TRUE), TRUE) + 2;
			$titleData['latest_chapter'] = $segments[$needle];

			$dateString = $data['nodes_latest']->nodeValue;
			$titleData['last_updated'] = date("Y-m-d H:i:s", strtotime(preg_replace('/ (-|\[A\]).*$/', '', $dateString)));
		}

		return (!empty($titleData) ? $titleData : NULL);
	}

	public function doCustomUpdate() {
		$titleDataList = [];

		$updateURL = "{$this->baseURL}/latest-release";
		if(($content = $this->get_content($updateURL)) && $content['status_code'] === 200) {
			$data = $content['body'];

			$data = preg_replace('/^[\s\S]+<dl>/', '<dl>', $data);
			$data = preg_replace('/<\/dl>[\s\S]+$/', '</dl>', $data);

			$dom = new DOMDocument();
			libxml_use_internal_errors(TRUE);
			$dom->loadHTML($data);
			libxml_use_internal_errors(FALSE);

			$xpath      = new DOMXPath($dom);
			$nodes_rows = $xpath->query("//dl/dd | //div[@class='mangalist']/div[@class='manga-item']");
			if($nodes_rows->length > 0) {
				foreach($nodes_rows as $row) {
					$titleData = [];

					$nodes_title   = $xpath->query("div[@class='events ']/div[@class='events-body']/h3[@class='events-heading']/a | h3/a", $row);
					$nodes_chapter = $xpath->query("(div[@class='events '][1]/div[@class='events-body'][1] | div[@class='manga-chapter'][1])/h6[@class='events-subtitle'][1]/a[1]", $row);
					$nodes_latest  = $xpath->query("div[@class='time'] | small", $row);

					if($nodes_title->length === 1 && $nodes_chapter->length === 1 && $nodes_latest->length === 1) {
						$title = $nodes_title->item(0);

						preg_match('/(?<url>[^\/]+(?=\/$|$))/', $title->getAttribute('href'), $title_url_arr);
						$title_url = $title_url_arr['url'];

						if(!array_key_exists($title_url, $titleDataList)) {
							$titleData['title'] = trim($title->textContent);

							$chapter = $nodes_chapter->item(0);
							preg_match('/(?<chapter>[^\/]+(?=\/$|$))/', $chapter->getAttribute('href'), $chapter_arr);
							$titleData['latest_chapter'] = $chapter_arr['chapter'];

							$dateString = str_replace('/', '-', trim($nodes_latest->item(0)->nodeValue)); //NOTE: We replace slashes here as it stops strtotime interpreting the date as US date format.
							if($dateString == 'T') {
								$dateString = date("Y-m-d",now());
							}
							$titleData['last_updated'] = date("Y-m-d H:i:s", strtotime($dateString . ' 00:00'));

							$titleDataList[$title_url] = $titleData;
						}
					} else {
						log_message('error', "{$this->site}/Custom | Invalid amount of nodes (TITLE: {$nodes_title->length} | CHAPTER: {$nodes_chapter->length}) | LATEST: {$nodes_latest->length})");
					}
				}
			} else {
				log_message('error', "{$this->site} | Following list is empty?");
			}
		} else {
			log_message('error', "{$this->site} - Custom updating failed for {$this->baseURL}.");
		}

		return $titleDataList;
	}
}

abstract class Base_GlossyBright_Site_Model extends Base_Site_Model {
	public $titleFormat   = '/^[a-zA-Z0-9_-]+$/';
	public $chapterFormat = '/^[0-9\.]+$/';
	public $pageSeparator = '/';

	public $customType    = 2;

	public function getFullTitleURL(string $title_url) : string {
		return "{$this->baseURL}/{$title_url}";
	}

	public function getChapterData(string $title_url, string $chapter) : array {
		return [
			'url'    => $this->getFullTitleURL($title_url).'/'.$chapter.'/',
			'number' => "c{$chapter}"
		];
	}

	public function getTitleData(string $title_url, bool $firstGet = FALSE) : ?array {
		$titleData = [];

		$fullURL = "{$this->baseURL}/manga-rss/{$title_url}";
		$content = $this->get_content($fullURL);
		$data    = $this->parseTitleDataDOM(
			$content,
			$title_url,
			'//rss/channel/image/title',
			'//rss/channel/item[1]',
			'pubdate',
			'title',
			function($data) {
				return strpos($data, '<image>') === FALSE;
			}
		);
		if($data) {
			$titleData['title'] = preg_replace('/^Recent chapters of (.*?) manga$/', '$1', trim($data['nodes_title']->textContent));

			//For whatever reason, DOMDocument breaks the <link> element we need to grab the chapter, so we have to grab it elsewhere.
			$titleData['latest_chapter'] = preg_replace('/^.*? - ([0-9\.]+) - .*?$/', '$1', trim($data['nodes_chapter']->textContent));

			$titleData['last_updated'] = date('Y-m-d H:i:s', strtotime((string) $data['nodes_latest']->textContent));
		}

		return (!empty($titleData) ? $titleData : NULL);
	}

	public function doCustomUpdate() {
		$titleDataList = [];

		$baseURLRegex = str_replace('.', '\\.', parse_url($this->baseURL, PHP_URL_HOST));
		if(($content = $this->get_content($this->baseURL)) && $content['status_code'] == 200) {
			$data = $content['body'];

			$dom = new DOMDocument();
			libxml_use_internal_errors(TRUE);
			$dom->loadHTML($data);
			libxml_use_internal_errors(FALSE);

			$xpath      = new DOMXPath($dom);
			$nodes_rows = $xpath->query("//div[@id='wpm_mng_lst']/div | //*[@id='wpm_mng_lst']/li/div");
			if($nodes_rows->length > 0) {
				foreach($nodes_rows as $row) {
					$titleData = [];

					$nodes_title   = $xpath->query("a[2]", $row);
					$nodes_chapter = $xpath->query("a[2]", $row);
					$nodes_latest  = $xpath->query("b", $row);

					if($nodes_latest->length === 0) {
						$nodes_latest = $xpath->query('text()[last()]', $row);
					}

					if($nodes_title->length === 1 && $nodes_chapter->length === 1 && $nodes_latest->length === 1) {
						$title   = $nodes_title->item(0);
						$chapter = $nodes_chapter->item(0);

						preg_match('/'.$baseURLRegex.'\/(?<url>.*?)\//', $title->getAttribute('href'), $title_url_arr);
						$title_url = $title_url_arr['url'];

						if(!array_key_exists($title_url, $titleDataList)) {
							$titleData['title'] = trim($title->getAttribute('title'));

							preg_match('/(?<chapter>[^\/]+(?=\/$|$))/', $chapter->getAttribute('href'), $chapter_arr);
							$titleData['latest_chapter'] = $chapter_arr['chapter'];

							$dateString = trim($nodes_latest->item(0)->textContent);
							switch($dateString) {
								case 'Today':
									$dateString = date("Y-m-d", now());
									break;

								case 'Yesterday':
									$dateString = date("Y-m-d", strtotime("-1 days"));
									break;

								default:
									//Do nothing
									break;
							}
							$titleData['last_updated'] = date("Y-m-d H:i:s", strtotime($dateString));

							$titleDataList[$title_url] = $titleData;
						}
					} else {
						log_message('error', "{$this->site}/Custom | Invalid amount of nodes (TITLE: {$nodes_title->length} | CHAPTER: {$nodes_chapter->length}) | LATEST: {$nodes_latest->length})");
					}
				}
			} else {
				log_message('error', "{$this->site} | Following list is empty?");
			}
		} else {
			log_message('error', "{$this->site} - Custom updating failed.");
		}

		return $titleDataList;
	}
}

abstract class Base_Roku_Site_Model extends Base_Site_Model {
	public $titleFormat   = '/^[a-zA-Z0-9-]+$/';
	public $chapterFormat = '/^[0-9\.]+$/';

	public $customType    = 2;

	public function getFullTitleURL(string $title_url) : string {
		return "{$this->baseURL}/series/{$title_url}";
	}
	public function getChapterData(string $title_url, string $chapter) : array {
		return [
			'url'    => "{$this->baseURL}/read/{$title_url}/{$chapter}",
			'number' => "c{$chapter}"
		];
	}
	public function getTitleData(string $title_url, bool $firstGet = FALSE) : ?array {
		$titleData = [];
		$fullURL = $this->getFullTitleURL($title_url);
		$content = $this->get_content($fullURL);
		$data = $this->parseTitleDataDOM(
			$content,
			$title_url,
			"//div[@id='activity']/descendant::div[@class='media'][1]/descendant::div[@class='media-body']/h2/text()",
			"//ul[contains(@class, 'media-list')]/li[@class='media'][1]/a",
			"div[@class='media-body']/span[@class='text-muted']",
			""
		);
		if($data) {
			$titleData['title'] = trim(preg_replace('/ Added on .*$/','', $data['nodes_title']->textContent));
			$titleData['latest_chapter'] = preg_replace('/^.*\/([0-9\.]+)$/', '$1', (string) $data['nodes_chapter']->getAttribute('href'));

			$dateString = preg_replace('/^Added (?:on )?/', '',$data['nodes_latest']->textContent);
			$titleData['last_updated'] =  date("Y-m-d H:i:s", strtotime($dateString));
		}
		return (!empty($titleData) ? $titleData : NULL);
	}


	public function doCustomUpdate() {
		$titleDataList = [];

		$updateURL = "{$this->baseURL}/latest";
		if(($content = $this->get_content($updateURL)) && $content['status_code'] == 200) {
			$data = $content['body'];

			$dom = new DOMDocument();
			libxml_use_internal_errors(TRUE);
			$dom->loadHTML($data);
			libxml_use_internal_errors(FALSE);

			$xpath      = new DOMXPath($dom);
			$nodes_rows = $xpath->query("//div[@class='content-wrapper']/div[@class='row']/div/div");
			if($nodes_rows->length > 0) {
				foreach($nodes_rows as $row) {
					$titleData = [];

					$nodes_title   = $xpath->query("div[@class='caption']/h6/a", $row);
					$nodes_chapter = $xpath->query("div[@class='panel-footer no-padding']/a", $row);
					$nodes_latest  = $xpath->query("div[@class='caption']/text()", $row);

					if($nodes_title->length === 1 && $nodes_chapter->length === 1 && $nodes_latest->length === 1) {
						$title = $nodes_title->item(0);

						preg_match('/(?<url>[^\/]+(?=\/$|$))/', $title->getAttribute('href'), $title_url_arr);
						$title_url = $title_url_arr['url'];

						if(!array_key_exists($title_url, $titleDataList)) {
							$titleData['title'] = trim($title->textContent);

							$chapter = $nodes_chapter->item(0);
							preg_match('/(?<chapter>[^\/]+(?=\/$|$))/', $chapter->getAttribute('href'), $chapter_arr);
							$titleData['latest_chapter'] = $chapter_arr['chapter'];

							$dateString = trim(str_replace('Added ', '', $nodes_latest->item(0)->textContent));
							$titleData['last_updated'] = date("Y-m-d H:i:s", strtotime($dateString));

							$titleDataList[$title_url] = $titleData;
						}
					} else {
						log_message('error', "{$this->site}/Custom | Invalid amount of nodes (TITLE: {$nodes_title->length} | CHAPTER: {$nodes_chapter->length}) | LATEST: {$nodes_latest->length})");
					}
				}
			} else {
				log_message('error', "{$this->site} | Following list is empty?");
			}
		} else {
			log_message('error', "{$this->site} - Custom updating failed.");
		}

		return $titleDataList;
	}
}

//CHECK: RSS might be better to use here?
abstract class Base_WP_Manga_Site_Model extends Base_Site_Model {
	public $titleFormat   = '/^[a-zA-Z0-9_-]+$/';
	public $chapterFormat = '/^(?:[0-9]+-[0-9]+\/)?(?:oneshot|(?:chapter-)?[0-9a-zA-Z\.\-_]+)$/';
	//TODO: Get PageSeperator

	public $customType    = 2;

	public $titleStub = 'manga';

	public function getFullTitleURL(string $title_url) : string {
		return "{$this->baseURL}/{$this->titleStub}/{$title_url}/";
	}

	public function getChapterData(string $title_url, string $chapter) : array {
		if(strpos($chapter, '/')) {
			$chapterArr = explode('/', $chapter);
			$chapterN   = "v{$chapterArr[0]}/c".str_replace('chapter_','',$chapterArr[1]);
		} else if (ctype_digit($chapter)) {
			$chapterN = 'c'.str_replace('chapter_','', $chapter);
		}
		return [
			'url'    => $this->getChapterURL($title_url, $chapter),
			'number' => $chapterN ?? $chapter
		];
	}

	public function getChapterURL(string $title_url, string $chapter) : string {
		return $this->getFullTitleURL($title_url).$chapter.'/';
	}

	public function getTitleData(string $title_url, bool $firstGet = FALSE) : ?array {
		$titleData = [];

		$fullURL = $this->getFullTitleURL($title_url);
		$content = $this->get_content($fullURL);

		$data = $this->parseTitleDataDOM(
			$content,
			$title_url,
			"(//div[@class='post-title'])/h3[1]",
			"(//ul[contains(@class, 'list-chap') or contains(@class, 'version-chap')][1]/li[@class='wp-manga-chapter'])[1]",
			"span[@class='chapter-release-date']/i[1]",
			'a[1]',
			function($data) {
				return strpos($data, 'Whoops, looks like something went wrong.') !== FALSE;
			}
		);
		if($data) {
			$titleData['title'] = trim($data['nodes_title']->textContent);

			$segments = explode('/', (string) $data['nodes_chapter']->getAttribute('href'));
			$needle = array_search($this->titleStub, array_reverse($segments, TRUE), TRUE) + 2;
			$titleData['latest_chapter'] = implode('/', array_slice($segments, $needle));

			$dateString = $data['nodes_latest']->nodeValue;
			$titleData['last_updated'] = date('Y-m-d H:i:s', strtotime(preg_replace('/ (-|\[A\]).*$/', '', $dateString)));
		}

		return (!empty($titleData) ? $titleData : NULL);
	}

	public function doCustomUpdate() {
		$titleDataList = [];

		$updateURL = "{$this->baseURL}/page/1/?s&post_type=wp-manga";
		if(($content = $this->get_content($updateURL)) && $content['status_code'] === 200) {
			$data = $content['body'];

			$data = preg_replace('/^[\s\S]+<!-- container & no-sidebar-->/', '', $data);
			$data = preg_replace('/<div class="ad c-ads custom-code body-bottom-ads">[\s\S]+$/', '', $data);

			$dom = new DOMDocument();
			libxml_use_internal_errors(TRUE);
			$dom->loadHTML($data);
			libxml_use_internal_errors(FALSE);

			$xpath      = new DOMXPath($dom);
			$nodes_rows = $xpath->query("//div[@class='tab-content-wrap']/div/div[@class='row']/div[@class='c-tabs-item__content']/div[@class='col-sm-10 col-md-10']");
			if($nodes_rows->length > 0) {
				foreach($nodes_rows as $row) {
					$titleData = [];

					$nodes_title   = $xpath->query("div[@class='tab-summary']/div[@class='post-title']/h4/a", $row);
					$nodes_chapter = $xpath->query("div[@class='tab-meta']/div[@class='meta-item latest-chap']/span[@class='font-meta chapter']/a", $row);
					$nodes_latest  = $xpath->query("div[@class='tab-meta']/div[@class='meta-item post-on']/span[@class='font-meta']", $row);

					if($nodes_title->length === 1 && $nodes_chapter->length === 1 && $nodes_latest->length === 1) {
						$title = $nodes_title->item(0);

						preg_match('/(?<url>[^\/]+(?=\/$|$))/', $title->getAttribute('href'), $title_url_arr);
						$title_url = $title_url_arr['url'];

						if(!array_key_exists($title_url, $titleDataList)) {
							$titleData['title'] = trim($title->textContent);

							$chapter = $nodes_chapter->item(0);

							$segments = explode('/', (string) $chapter->getAttribute('href'));
							$needle = array_search($this->titleStub, array_reverse($segments, TRUE), TRUE) + 2;
							$titleData['latest_chapter'] = implode('/', array_slice($segments, $needle));

							$titleData['last_updated'] = date('Y-m-d H:i:s', strtotime($nodes_latest->item(0)->nodeValue));

							$titleDataList[$title_url] = $titleData;
						}
					} else {
						log_message('error', "{$this->site}/Custom | Invalid amount of nodes (TITLE: {$nodes_title->length} | CHAPTER: {$nodes_chapter->length}) | LATEST: {$nodes_latest->length})");
					}
				}
			} else {
				log_message('error', "{$this->site} | Following list is empty?");
			}
		} else {
			log_message('error', "{$this->site} - Custom updating failed for {$this->baseURL}.");
		}

		return $titleDataList;
	}
}
