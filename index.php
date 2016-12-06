<?php

/**
 *  Developed with love by Nicolas Devenet <nicolas[at]devenet.info>
 *  Code hosted on https://github.com/Devenet/AdventCalendar
 */

error_reporting(0);

// constants to be used
define('VERSION', '1.3.4');
define('ADVENT_CALENDAR', 'Advent Calendar');
define('URL_DAY', 'day');
define('URL_PHOTO', 'photo');
define('URL_ABOUT', 'about');
define('URL_RSS', 'rss');
define('PRIVATE_FOLDER', './private');
define('USERS_FILE', PRIVATE_FOLDER.'/users.json');
define('ORDER_FILE', PRIVATE_FOLDER.'/order.json');
define('SETTINGS_FILE', PRIVATE_FOLDER.'/settings.json');
define('CALENDAR_FILE', PRIVATE_FOLDER.'/calendar.json');
define('RSS_CACHE_FILE', PRIVATE_FOLDER.'/rss_cache.xml');

define('UNLOCKED', 9);

// load users from file
$users = array();
if (file_exists(USERS_FILE) && $users !== NULL) {
    $users = json_decode(file_get_contents(USERS_FILE), true);
}

// load order for days from file
$order = array();
if (file_exists(ORDER_FILE)) {
    $order = json_decode(file_get_contents(ORDER_FILE), true);
}

// load settings from file
if (file_exists(SETTINGS_FILE)) {
	$settings = json_decode(file_get_contents(SETTINGS_FILE));

	define('TITLE', $settings->title);
	define('YEAR', $settings->year);

	// is it an other month?
	if (isset($settings->month) && !empty($settings->month) && $settings->month > 1 && $settings->month <= 12) { define('MONTH', date('m', mktime(0, 0, 0, $settings->month+0))); }
	else { define('MONTH', 12); }
	// is it an other begin day?
	if (isset($settings->first_day) && !empty($settings->first_day) && $settings->first_day > 0 && $settings->first_day <= 31) { define('FIRST_DAY', date('d', mktime(0, 0, 0, MONTH, $settings->first_day))); }
	else { define('FIRST_DAY', '01'); }
	// is it an other last day?
	if (isset($settings->last_day) && !empty($settings->last_day) && $settings->last_day > FIRST_DAY && $settings->last_day <= 31) { define('LAST_DAY', date('d', mktime(0, 0, 0, MONTH, $settings->last_day))); }
	else { define('LAST_DAY', '24'); }

	// is it a private calendar?
	if (isset($settings->passkey) && !empty($settings->passkey)) { define('PASSKEY', $settings->passkey); }

	// do the user want an other background?
	if (isset($settings->background) && $settings->background == 'alternate') { define('ALTERNATE_BACKGROUND', TRUE); }

	// want to add disqus thread?
	if (isset($settings->disqus_shortname) && !empty($settings->disqus_shortname)) {
		AddOns::Register(AddOns::AddOn('disqus', $settings->disqus_shortname));
		AddOns::JavaScriptRegistred();
	}
	// want to add google analytics?
	if (isset($settings->google_analytics) && !empty($settings->google_analytics) && isset($settings->google_analytics->tracking_id) && isset($settings->google_analytics->domain) ) {
		AddOns::Register(AddOns::AddOn('ga', AddOns::JsonToArray($settings->google_analytics)));
		AddOns::JavaScriptRegistred();
	}
	// want to add piwik?
	if (isset($settings->piwik) && !empty($settings->piwik) && isset($settings->piwik->piwik_url) && isset($settings->piwik->site_id) ) {
		AddOns::Register(AddOns::AddOn('piwik', AddOns::JsonToArray($settings->piwik)));
		AddOns::JavaScriptRegistred();
	}
}
else { die('<!doctype html><html><head><title>'.ADVENT_CALENDAR.'</title><style>body{width:600px;margin:50px auto 20px;}</style></head><body><div style="font-size:30px;"><strong>Oups!</strong> Settings file not found.</div><div><p>Edit <code>private/settings.example.json</code> to personnalize title and year and rename it <code>settings.json</code>.</p><p>If it is not already done, put your photos in the <code>private/</code> folder, and name them with the number of the day you want to illustrate.</p></div></body></html>'); }

// is the directory writable ?
if (!is_writable(realpath(dirname(__FILE__)))) die('<div><strong>Oups!</strong> Application does not have the right to write in its own directory <code>'.realpath(dirname(__FILE__)).'</code>.</div>');
// is the private folder already created? yes, with a .htaccess file
/*if (!is_dir(PRIVATE_FOLDER)) { mkdir(PRIVATE_FOLDER,0705); chmod($_CONFIG['data'],0705); }*/
// are photos deny from web access? [just in case]
if (!is_file(PRIVATE_FOLDER.'/.htaccess')) { file_put_contents(PRIVATE_FOLDER.'/.htaccess', 'Deny from all'); }
if (!is_file(PRIVATE_FOLDER.'/.htaccess')) die('<div><strong>Oups!</strong> Application does not have the right to write in its own directory <code>'.realpath(dirname(__FILE__)).'</code>.</div>');

/*
 *  Core classes
 */
abstract class AddOns {
	const Data = 'data';
	const Name = 'name';

	static private $addons = Array();

	static function Register(Array $addon) {
		if (empty($addon[self::Data])) { $addon[self::Data] = TRUE; }
		self::$addons[$addon['name']] = $addon[self::Data];
	}

	static function AddOn($name, $data = TRUE) {
		return array(self::Name => $name, self::Data => $data);
	}

	static function Found($name) {
		return isset(self::$addons[$name]);
	}

	static function Get($name) {
		if (! self::Found($name)) { return; }
		return self::$addons[$name];
	}

	static function JavaScriptRegistred() {
		self::Register(self::AddOn('js'));
	}

	static function JsonToArray($json) {
		return json_decode(json_encode($json), TRUE);
	}
}

abstract class Image {
	static function get($day) {

		$img = self::getInfo($day);

		if (!empty($img)) {
			header('Content-type: '.$img['type']);
			exit(file_get_contents($img['path']));
		}

		header('Location: ./');
		exit();
	}

	static function getInfo($day) {
		$result = array();
		// check if we can display the request photo
		if (Advent::acceptDay($day) && Advent::isActiveDay($day)) {
			$day = $day;
			$result['url'] = '?'.URL_PHOTO.'='.$day;

			$extension = '.jpg';
			// if .jpg does not exist, load .jpeg photo
			if (! self::exists($day.$extension)) {
				$extension = '.jpeg';
				// in case of .jpg or .jpeg file is not found
				if (! self::exists($day.$extension)) {
					// enhancement #8: use a default image when not found
					$result['type'] = 'image/png';
					$result['path'] = './assets/404.png';
					return $result;
				}
			}
			$result['type'] = 'image/jpeg';
			$result['path'] = PRIVATE_FOLDER.'/'.$day.$extension;

			return $result;
		}
		return NULL;
	}

	static private function exists($file) {
		return file_exists(PRIVATE_FOLDER.'/'.$file);
	}
}

class Day {

	public $day;
	public $active;
	public $url;
	public $title = NULL;
	public $legend = NULL;
	public $text = NULL;

	public function __default($day) {
		$this->day = $day;
		$this->active = Advent::isActiveDay($day);
		$this->url = '?'. URL_DAY .'='. ($this->day);
	}
	public function __construct($day, $title = NULL, $legend = NULL, $text = NULL) {
		$this->__default($day);
		$this->title = $title;
		$this->legend = $legend;
		$this->text = $text;
	}
}

abstract class Advent {

	const BEFORE_ADVENT = -1;
	const CURRENT_ADVENT = 0;
	const AFTER_ADVENT = 1;

	static function state() {
		$now = date('Ymd');

		// if we are before the advent
		if ($now < YEAR.MONTH.FIRST_DAY) { return self::BEFORE_ADVENT; }
		// if we are after
		if ($now > YEAR.MONTH.LAST_DAY) { return self::AFTER_ADVENT; }
		// else we are currently in advent \o/
		return self::CURRENT_ADVENT;
	}

	static function acceptDay($day) {
		return $day >= FIRST_DAY && $day <= LAST_DAY;
	}

	static function isActiveDay($day) {
		$state = self::state();
		return ($state == self::CURRENT_ADVENT && $day <= date('d')) || $state == self::AFTER_ADVENT;
	}

	static private function getDayColorClass($day, $active = FALSE) {
		$result = '';
		// is the day active ?
		if ($active) { $result .= 'active '; }
		// set a color for the background
		$result .= 'day-color';
		return $result;
	}

	static function getDays($order) {
		$result = array();
		for ($i=FIRST_DAY+0; $i<=LAST_DAY; $i++) {
			$result[] = new Day($order[$i]);
		}
		return $result;
	}

	static function getFullDays() {
		$result = array();
		for ($i=FIRST_DAY+0; $i<=LAST_DAY; $i++) {
			$result[] = self::getDay($i);
		}
		return $result;
	}

	static function getDaysHtml($order, $users) {
	    $data = array();
        if(null !== USERNAME){
            if(!file_exists('sett.csv') || !is_readable('sett.csv'))
                echo 'CSV Fail';
            $header = NULL;
            if (($handle = fopen('sett.csv', 'r')) !== FALSE){
                while (($row = fgetcsv($handle, 0, ',')) !== FALSE){
                    if(!$header)
                       $header = $row;
                    else {
                        if(count($header)!=count($row)){ continue; }
                        $data[] = array_combine($header, $row);
                    }
                }
                fclose($handle);
            }
        }
		$result = '<div class="container days">';
		foreach (self::getDays($order) as $d) {
			if ($d->active) { $result .= '<a href="'. $d->url .'" title="Day '. $d->day .'"'; }
			else { $result .= '<div'; }
            if(null !== USERNAME){
                if(!file_exists('sett.csv') || !is_readable('sett.csv'))
                    echo 'CSV Fail';
                $header = NULL;
                $data = array();
                if (($handle = fopen('sett.csv', 'r')) !== FALSE){
                    while (($row = fgetcsv($handle, 0, ',')) !== FALSE){
                        if(!$header)
                           $header = $row;
                        else {
                            if(count($header)!=count($row)){ continue; }
                            $data[] = array_combine($header, $row);
                        }
                    }
                    fclose($handle);
                }
                foreach ($data as $user){
                    if($user['Etternavn'] == $users[USERNAME]){
                        switch ($user[$d->day]) {
                            case 1:
                                $result .= 'class="day-row viewed" style="background-image: url(';
                                $result .= "'assets/dag/".$d->day.".jpg'); ";
                                break;
                            case 0:
                                $result .= ' class="day-row '. self::getDayColorClass($d->day, $d->active);
                                break;
                            default:
                                break;
                        }
                    }
                }
            }
			$result .= '"><span class="active-text">'.$d->day.'</span>';
			if ($d->active) { $result .= '</a>'; }
			else { $result .= '</div>'; }
		}
		return $result.'</div>';
	}

	static function getDay($day) {
		$title = NULL;
		$legend = NULL;
		$text = NULL;
		// check if we have info to display
		if (file_exists(CALENDAR_FILE)) {
			$file = json_decode(file_get_contents(CALENDAR_FILE));
			$day = $day == FIRST_DAY ? ($day+0) : $day;
			if (!empty($file->{$day})) {
				if (!empty($file->{$day}->title)) { $title = htmlspecialchars($file->{$day}->title); }
				if (!empty($file->{$day}->legend)) { $legend = htmlspecialchars($file->{$day}->legend); }
				if (!empty($file->{$day}->text)) { $text = $file->{$day}->text; }
			}
		}
		return new Day($day, $title, $legend, $text);
	}

	static function getDayHtml($day) {
		$result = '<div class="container day">';

		$d = self::getDay($day);
		$title = $d->title;
		$legend = $d->legend;
		$text = $d->text;

		// set the day number block
		$result .= '<a href="./?'. URL_DAY.'='. $day .'" class="day-row '. self::getDayColorClass($day, TRUE) .'"><span>'. $day .'</span></a>';
		// set the title
		$result .= '<h1><span class="day-text">';
		if (!empty($title)) { $result .= $title; }
		else { $result .= 'Day '.$day; }
		$result .= '</span></h1>';
		// clearfix
		$result .= '<div class="clearfix"></div>';

		// display image
		$result .= '<div class="text-center"><img src="./?'.URL_PHOTO.'='. $day .'" class="img-responsive img-thumbnail" alt="Day '. $day .'" />';
		// do we have a legend?
		//if (!empty($legend)) { $result .= '<p class="legend">&mdash; '.$legend.'</p>'; }
		$result .= '</div>';
		// clearfix
		$result .= '<div class="clearfix"></div>';

		// do we have a text?
		if (!empty($text)) { $result .= '<div class="text panel panel-default"><div class="panel-body">'.$text.'</div></div>'; }
		// clearfix
        //$result .= '<div class="clearfix"></div>';
        if (!empty(self::getCode($day))) { $result .= self::getCode($day); }
		/* we do not forget the pagination
		$result .= '<ul class="pager"><li class="previous';
		if (self::isActiveDay($day-1) && ($day-1)>=FIRST_DAY) { $result .= '"><a href="?'. URL_DAY .'='. ($day-1) .'" title="yesterday" class="tip" data-placement="right">'; }
		else { $result .= ' disabled"><a>'; }
		$result .= '<i class="glyphicon glyphicon-hand-left"></i></a></li><li class="next';
		if (self::isActiveDay($day+1) && ($day+1)<=LAST_DAY) { $result .= '"><a href="?'. URL_DAY .'='. ($day+1) .'" title="tomorrow" class="tip" data-placement="left">'; }
		else { $result .= ' disabled"><a>'; }
		$result .= '<i class="glyphicon glyphicon-hand-right"></i></a></li></ul>';*/

		// we add disqus thread if supported
		if (AddOns::Found('disqus')) { $result .= '<div id="disqus_thread"></div>'; }

		return $result.'</div>';
	}
    // Hard coded opt-in for extra non-text content, working on more dynamic feature
	static function getCode($day){
	    $code = '';
	    switch ($day){
	        case '1':
	            $code = '<div class="video-container"><iframe src="https://www.youtube.com/embed/Swos6PO2zNM?autoplay=1" frameborder="0" allowfullscreen></iframe></div><p class="text">Sjekk ut videon <a href="https://www.youtube.com/watch?v=Swos6PO2zNM&feature=youtu.be">her </a>(innlogget med Solidsquare-bruker) hvis den ikke går å spille.</p>';
	            break;
            case '2':
                $code = '<img style="margin-left: auto; margin-right: auto;" src="https://i.kinja-img.com/gawker-media/image/upload/17iqysqjnsthujpg.jpg">';
	            break;
            case '3':
                $code = '<img class="img-responsive" src="https://s-media-cache-ak0.pinimg.com/564x/10/30/8e/10308e90073dfdf669b847924d34408e.jpg">';
                break;
            case '4':
                $code = '<div class="video-container"><iframe src="https://www.youtube.com/embed/GKRI86ldV_w?autoplay=1" frameborder="0" allowfullscreen></iframe></div><p class="text">Sjekk ut videon <a href="https://www.youtube.com/watch?v=GKRI86ldV_w&feature=youtu.be">her </a>(innlogget med Solidsquare-bruker) hvis den ikke går å spille.</p>';
	            break;
            case '5':
		        $code = '<img class="img-responsive" src="https://s-media-cache-ak0.pinimg.com/originals/00/bb/84/00bb84e7400cee35c416737b968d5c64.png">';
                break;
	        default:
	            break;
	    }
	    return $code;
	}

	function bePatient($day) {
		return '<div class="container error"><div class="panel panel-info"><div class="panel-heading"><h3 class="panel-title">Christmas is coming soon!</h3></div><div class="panel-body">But before, <strong>be patient</strong>, day '. $day .' is only in few days. <a href="./" class="illustration text-center tip" title="home"><i class="glyphicon glyphicon-home"></i></a></div></div></div>';
	}

    function soClose($hours, $minutes) {
        $difference = (UNLOCKED*60) - ($hours*60) - $minutes;
        return '<div class="container error"><div class="panel panel-info"><div class="panel-heading"><h3 class="panel-title">Det er riktig dag, men du må pent vente til klokken '.UNLOCKED.'!</h3></div><div class="panel-body">Men det er jo kun <strong>'.$difference.' minutter</strong> igjen da!<a href="./" class="illustration text-center tip" title="home"><i class="glyphicon glyphicon-home"></i></a></div></div></div>';
    }

}

abstract class RSS {

	static protected function escape($string) {
		return '<![CDATA['.$string.']]>';
	}

	static function url() {
		return (empty($_SERVER['REQUEST_SCHEME']) ? 'http' : $_SERVER['REQUEST_SCHEME']).'://'.$_SERVER['HTTP_HOST'].dirname($_SERVER['SCRIPT_NAME']).'/';
	}

	static public function get() {
		header('Content-Type: application/rss+xml; charset=utf-8');

		// can we display the cache?
		if (file_exists(RSS_CACHE_FILE) && date("j", filemtime(RSS_CACHE_FILE)) <= date("j")) {
			exit(file_get_contents(RSS_CACHE_FILE));
		}

		$URL = self::url();
		$xml  = '<?xml version="1.0" encoding="UTF-8"?>'.PHP_EOL;
		$xml .= '<rss version="2.0"  xmlns:atom="http://www.w3.org/2005/Atom">'.PHP_EOL;
		$xml .= '<channel>'.PHP_EOL;
		$xml .= '<atom:link href="'.$URL.'?'.URL_RSS.'" rel="self" type="application/rss+xml" />'.PHP_EOL;
		$xml .= '<title>'.self::escape(TITLE).'</title>'.PHP_EOL;
		$xml .= '<link>'.$URL.'</link>'.PHP_EOL;
		$xml .= '<description>'.self::escape('RSS feed of '.TITLE. ' · Advent Calendar').'</description>'.PHP_EOL;
		$xml .= '<pubDate>'.date("D, d M Y H:i:s O", (file_exists(RSS_CACHE_FILE) ? filemtime(RSS_CACHE_FILE) : time())).'</pubDate>'.PHP_EOL;
		$xml .= '<ttl>1440</ttl>'.PHP_EOL; // 24 hours
		$xml .= '<copyright>'.$URL.'</copyright>'.PHP_EOL;
		$xml .= '<language>en-EN</language>'.PHP_EOL;
		$xml .= '<generator>Advent Calendar</generator>'.PHP_EOL;
		$xml .= '<image>'.PHP_EOL;
		$xml .= '<title>'.self::escape(TITLE).'</title>'.PHP_EOL;
		$xml .= '<url>'.$URL.'assets/favicon.png</url>'.PHP_EOL;
		$xml .= '<link>'.$URL.'</link>'.PHP_EOL;
		$xml .= '<width>48</width>'.PHP_EOL;
		$xml .= '<height>48</height>'.PHP_EOL;
		$xml .= '</image>'.PHP_EOL;
		foreach (Advent::getFullDays() as $day) {
			if ($day->active) {
			$xml .= '<item>'.PHP_EOL;
			$xml .= '<title>'. (empty($day->title) ? 'Day '.$day->day : self::escape($day->title)) .'</title>'.PHP_EOL;
			$xml .= '<link>'.$URL.$day->url.'</link>'.PHP_EOL;
			$xml .= '<description>'.(empty($day->text) ? '' : self::escape($day->text)).'</description>'.PHP_EOL;
			$img = Image::getInfo($day->day);
			$xml .= '<enclosure url="'.$URL.$img['url'].'" length="'.filesize($img['path']).'" type="'.$img['type'].'" />'.PHP_EOL;
			$xml .= '<guid isPermaLink="false">'.$day->day.'</guid>'.PHP_EOL;
			$xml .= '<pubDate>'.date("D, d M Y 00:00:00 O", mktime(0, 0, 0, MONTH, $day->day, YEAR)).'</pubDate>'.PHP_EOL;
			$xml .= '<source url="'.$URL.'?'.URL_RSS.'">'.self::escape(TITLE).'</source>'.PHP_EOL;
			$xml .= '</item>'.PHP_EOL;
			}
		}
		$xml .= '</channel>'.PHP_EOL;
		$xml .= '</rss>'.PHP_EOL;

		file_put_contents(RSS_CACHE_FILE, $xml);
		exit($xml);
	}

	public function getLink() {
		return self::url().'?'.URL_RSS;
	}
}


/*
 * Session management
 */

 session_start();

 define('DS',  TRUE); // used to protect includes
 define('USERNAME', $_SESSION['username']);
 define('SELF',  $_SERVER['PHP_SELF'] );

/*
 * Load template
 */

$template = NULL;

// This version uses the new Array- and CSV-based multi user system instead

/* need to display log form? (this version borrows the design but implements login.php instead)
if (defined('PASSKEY') && isset($loginRequested)) {
	$template = '
	<div class="container text-center">
		<div class="page-header"><h1 class="text-danger">This is a private area!</h1></div>
		<p>Please sign in with your <span class="font-normal">passkey</span> to continue.</p>
		<form method="post" role="form" class="espace-lg form-inline">
			<div class="form-group"><input type="password" name="credential" id="credential" class="form-control input-lg" autofocus required /></div>
			<button type="submit" class="btn btn-default btn-lg tip" data-placement="right" data-title="sign in"><i class="glyphicon glyphicon-user"></i></button>
		</form>
	</div>';
}*/
// want to see a photo ?
if (isset($_GET[URL_PHOTO])) { Image::get($_GET[URL_PHOTO]+0); }
// nothing asked, display homepage
else if (empty($_GET)) {
    if(USERNAME !== null) // Check if user is valid
	    $template = Advent::getDaysHtml($order, $users);
	else // Force login form if not
        include('login.php');
}
// want to display a day
else if (isset($_GET['day'])) {
    if(null !== USERNAME){
        $day = $_GET['day'] + 0;
        if (! Advent::acceptDay($day)) { header('Location: ./'); exit(); }
        if (Advent::isActiveDay($day)) {
            if(date('d') == $day && date('H') < UNLOCKED)
                $template = Advent::soClose(date('H'), date('i'));
            else {
                $template = Advent::getDayHtml($day);
                if(USERNAME !== null) {
                    if(!file_exists('sett.csv') || !is_readable('sett.csv'))
                        echo 'CSV Fail';
                    $header = NULL;
                    $data = array();
                    if (($handle = fopen('sett.csv', 'r')) !== FALSE){
                        while (($row = fgetcsv($handle, 0, ',')) !== FALSE){
                            if(!$header)
                               $header = $row;
                            else {
                                if(count($header)!=count($row)){ continue; }
                                $data[] = array_combine($header, $row);
                            }
                        }
                        fclose($handle);
                    }
                    $input = fopen('sett.csv', 'r');  //open for reading
                    $output = fopen('sett_update.csv', 'w'); //open for writing
                    while($data = fgetcsv($input)){  //read each line as an array
                       //modify data here
                       if ($data[0] == $users[USERNAME]) {

                          //Replace line here fix
                          $data[$day] = 1;
                       }
                       //write modified data to new file
                       fputcsv( $output, $data);
                    }
                    //close both files
                    fclose( $input );
                    fclose( $output );
                    //clean up
                    unlink('sett.csv');// Delete obsolete BD
                    rename('sett_update.csv', 'sett.csv'); //Rename temporary to new
                }
            }
        } else { $template = Advent::bePatient($day); }
    }
    else {
        include('login.php');
    }
}

/* rss feed is requested (only supported for no procted Advent Calendar) (not used here)
if (isset($_GET[URL_RSS])) {
	if (!defined('PASSKEY')) { RSS::get(); }
	else {
		header('HTTP/1.1 501 Not Implemented', true, 501);
		$template = '
		<div class="container text-center">
			<div class="page-header"><h1 class="text-warning">Functionality not supported</h1></div>
			<div class="espace-lg">
				<p>The RSS feed is not available for a protected '.ADVENT_CALENDAR.'.</p>
				<p><a href="./" class="text-center tip" title="home" data-placement="bottom"><i class="glyphicon glyphicon-home"></i></a></p>
			</div>
		</div>';
	}
}*/

/* want to display about page [no need to be logged in to access] (not used here)
if (isset($_GET[URL_ABOUT])) {
	// if ugly URL
	if (!empty($_GET[URL_ABOUT])) { header('Location: ./?'.URL_ABOUT); exit(); }
	$template = file_get_contents('./assets/about.html');
}*/

// default template is 404
if (empty($template)) {
	$template = '<div class="container error"><div class="panel panel-danger"><div class="panel-heading"><h3 class="panel-title">404 Not Found</h3></div><div class="panel-body">The requested URL was not found on this server. <a href="./" class="illustration illustration-danger text-center tip" title="home"><i class="glyphicon glyphicon-home"></i></a></div></div></div>';
	header('HTTP/1.1 404 Not Found', true, 404);
}

// helper (not used here)
//$authentificated = defined('PASSKEY') && isset($_SESSION['welcome']);

?><!doctype html>
<html lang="no">
	<head>
        <script>
            window.onpageshow = function(event) {
                if (event.persisted) {
                    window.location.reload()
                }
            };
        </script>
		<meta charset="UTF-8" />
		<title><?php echo TITLE, ' &middot; ', ADVENT_CALENDAR; ?></title>

		<!-- Parce qu’il y a toujours un peu d’humain derrière un site... -->
		<meta name="author" content="Nicolas Devenet" />

		<meta name="viewport" content="width=device-width, initial-scale=1.0" />
		<link rel="shortcut icon" type="image/x-icon" href="assets/favicon.ico" />
		<link rel="icon" type="image/png" href="assets/favicon.png" />

		<link href="assets/bootstrap.min.css" rel="stylesheet">
		<link href="assets/adventcalendar.css" rel="stylesheet">
		<link href="//fonts.googleapis.com/css?family=Lato:300,400,700" rel="stylesheet" type="text/css">

		<?php if (!defined('PASSKEY')): ?><link rel="alternate" type="application/rss+xml" href="<?php echo RSS::getLink(); ?>" title="<?php echo TITLE; ?>" /><?php endif; ?>
	</head>

	<body>
		<nav class="navbar navbar-default navbar-static-top" role="navigation">
		<div class="container">
        <audio id="musikk" controls>
            <source src="assets/Best - Christmas Songs for 2016.mp3" type="audio/mpeg">
            Your browser does not support the audio element.
        </audio>

		<?php
		if(null !== USERNAME)
            echo '<span class="pull-right user">Du er logget inn som '.USERNAME.'. <a href="logout.php">Logg ut</a></span>';
        ?>
		</div>
		</nav>

        <script>
            var myAudio = document.getElementById("musikk");
            var pause = sessionStorage.getItem("pause");
            if(pause !== "true"){
                document.getElementById("musikk").play();
            }
        </script>
		<div id="snowflakeContainer">

		<div class="background<?php if(defined('ALTERNATE_BACKGROUND')) { echo ' alternate-background'; } ?>">
		<?php
			echo $template . '<p class="snowflake">*</p>';
		?>
		</div>

		<footer>
		<div class="container">
			<!--<p class="pull-right"><a href="#" id="goHomeYouAreDrunk" class="tip" data-placement="left" title="upstairs"><i class="glyphicon glyphicon-tree-conifer"></i></a></p>-->
			<div class="notice">
				<a href="https://github.com/Devenet/AdventCalendar" rel="external"><?php echo ADVENT_CALENDAR; ?></a> &middot; Version <?php echo VERSION; ?>
				<br />Developed with love by <a href="http://nicolas.devenet.info" rel="external">Nicolas Devenet</a>.
				<br />Forked and perfected by <a href="http://solidsquare.no/karl-olofsson/" rel="external">Karl Olofsson</a>.
                <br />Designed with a smile by <a href="http://solidsquare.no/marthe-sofie-eide/" rel="external">Marthe Sofie Eide</a>.
			</div>
		</div>
		</footer>

		<script src="assets/jquery.min.js"></script>
		<script src="assets/bootstrap.min.js"></script>
		<script src="assets/adventcalendar.js"></script>
        <script src="assets/fallingsnow_v6.js"></script>
		<!--<?php if (AddOns::Found('js')): ?>
		<script>
			<?php if (AddOns::Found('disqus')): ?>
			var disqus_shortname = '<?php echo AddOns::Get("disqus"); ?>';
			(function() {
				var dsq = document.createElement('script'); dsq.type = 'text/javascript'; dsq.async = true;
				dsq.src = '//' + disqus_shortname + '.disqus.com/embed.js';
				(document.getElementsByTagName('head')[0] || document.getElementsByTagName('body')[0]).appendChild(dsq);
			})();
			<?php endif; ?>
			<?php if (AddOns::Found('ga')): $ga = AddOns::Get('ga'); ?>
			(function(i,s,o,g,r,a,m){i['GoogleAnalyticsObject']=r;i[r]=i[r]||function(){ (i[r].q=i[r].q||[]).push(arguments)},i[r].l=1*new Date();a=s.createElement(o), m=s.getElementsByTagName(o)[0];a.async=1;a.src=g;m.parentNode.insertBefore(a,m) })(window,document,'script','//www.google-analytics.com/analytics.js','ga');
				ga('create', '<?php echo $ga["tracking_id"]; ?>', '<?php echo $ga["domain"]; ?>');
				ga('send', 'pageview');
			<?php endif; ?>
			<?php if (AddOns::Found('piwik')): $piwik = AddOns::Get('piwik'); ?>
			var _paq = _paq || [];
			(function(){ var u='//<?php echo $piwik["piwik_url"]; ?>/'; _paq.push(['setSiteId', '<?php echo $piwik["site_id"]; ?>']); _paq.push(['setTrackerUrl', u+'piwik.php']); _paq.push(['trackPageView']); _paq.push(['enableLinkTracking']); var d=document, g=d.createElement('script'), s=d.getElementsByTagName('script')[0]; g.type='text/javascript'; g.defer=true; g.async=true; g.src=u+'piwik.js'; s.parentNode.insertBefore(g,s); })();
			<?php endif; ?>
		</script>
		<?php endif; ?>-->
        <script>
            var myAudio = document.getElementById("musikk");
            myAudio.onpause = function() {
                sessionStorage.setItem("pause", "true");
            };
        </script>
	</body>
</html>
