<?php
/**
 * A simple PHP library to get Instagram photos per user
 * and to store them on the local server.
 *
 * @author      Hashem Qolami <hashem@qolami.com>
 * @link        https://github.com/HashemQolami/
 * @license     http://opensource.org/licenses/MIT (MIT license)
 * @copyright   Copyright (c) 2015, Hashem Qolami.
 * @version     Version 1.0.0
 */

namespace Hashem\Instaget;

use League\Flysystem\Filesystem;

/**
 * Instaget Class
 */
class Instaget
{
	/**
	 * Name of the file that user's ID will be stored in
	 */
	const ID_FILE = '.id';

	/**
	 * Name of the file that indicates the time of modification
	 */
	const MOD_TIME_FILE = '.modtime';

	/**
	 * The file containing user's feed
	 */
	const FEED_FILE = 'feed.json';

	/**
	 * Application client ID
	 * @var string
	 */
	protected $clientID = '';
	
	/**
	 * Instagram username
	 * @var string
	 */
	protected $username = '';

	/**
	 * Instagram user ID
	 * @var integer
	 */
	protected $userID = 0;

	/**
	 * Feed limit
	 * @var integer
	 */
	protected $count = 6;

	/**
	 * Type of the images to be downloaded
	 * @var string
	 */
	protected $imageType = 'low';

	/**
	 * Expiration time of user's data in seconds
	 * (1 day by default), 0 will prevent caching
	 * 
	 * @var integer
	 */
	protected $expTime = 86400;

	/**
	 * User info
	 * @var array
	 */
	protected $userInfo = [];

	/**
	 * User feed
	 * @var array
	 */
	protected $userFeed = [];

	/**
	 * The root path that images will be saved in
	 * @var string
	 */
	protected $imagePath = 'images/';

	/**
	 * The root path that users' data will be saved in
	 * @var string
	 */
	protected $dataPath = 'data/';

	/**
	 * Current user's data path
	 * @var string
	 */
	protected $userDataPath = '';

	/**
	 * Current user's image path
	 * @var string
	 */
	protected $userImagePath = '';

	/**
	 * File system (an instance of Flysystem)
	 * @var object
	 */
	protected $fs;

	/**
	 * Instaget constructor
	 * 
	 * @param Filesystem $fs   An instance of League\Flysystem\Filesystem class
	 * @param array      $conf user's custom config
	 */
	public function __construct(Filesystem $fs, $conf = [])
	{
		# Inject the Filesystem instance
		$this->fs = $fs;

		# No time limit
		set_time_limit(0);

		# Initialing
		if (isset($conf) && is_array($conf))
			$this->init($conf);
	}

	/**
	 * Initialize the Instaget
	 * 
	 * @param  array $conf user's custom config
	 * @return void
	 */
	public function init($conf =[])
	{
		$props = ['clientID', 'username', 'count', 'imageType', 'expTime', 'imagePath', 'dataPath'];
		$paths = ['imagePath', 'dataPath'];
		
		if (count(array_intersect_key($conf, array_flip($props))))
			foreach ($props as $prop) {
				if (isset($conf[$prop])) {
					
					$value = in_array($prop, $paths) ?
								rtrim($conf[$prop], '/') . '/' :
								trim($conf[$prop]);

					$this->$prop = $value;
				}
			};

		$this->userDataPath = $this->dataPath . $this->username . '/';
		$this->userImagePath = $this->imagePath . $this->username . '/';
	}

	/**
	 * Run the application
	 * 
	 * @return void
	 */
	public function run()
	{
		# Check if the user's feed is available
		if ($this->userExists()) {
			# Check if user's feed is expired
			if ($this->userDataIsExpired()) {
				# Read the user's id from '.id' file
				$this->userID = $this->fs->read($this->userDataPath . self::ID_FILE);

				# Remove the user's image directory
				$this->fs->deleteDir($this->userImagePath);
			}
		} else {
			# Get the user's info from Instagram
			$this->userInfo = $this->getUserInfo();

			# Set the user's ID
			// if (! $this->userID and isset($this->userInfo['id'])) 
			$this->userID = $this->userInfo['id'];

			# Store the user's '.id' file
			$this->fs->write($this->userDataPath . self::ID_FILE, $this->userID);
		}

		if (! $this->userExists() or $this->userDataIsExpired()) {
			# Get the user's feed from Instagram
			# Store the images locally
			$this->userFeed = $this->getUserFeed();

			# Put the user's feed into the feed.json file
			$this->storeUserFeed();

			# Store the modification time in user's '.modtime' file
			$this->fs->put($this->userDataPath . self::MOD_TIME_FILE, time());
		}
		
		# Read the feed.json file
		return $this->readUserFeed();
	}

	/**
	 * userExists
	 *
	 * Checks if user's feed.json file exists
	 * which means that the user's info has been already fetched.
	 * 
	 * @param  string $userDataPath path to user's data directory
	 * @return bool   TRUE          if user's directory exists or FALSE on failure
	 */
	protected function userExists($userDataPath = '')
	{
		$userDataPath = $userDataPath ?: $this->userDataPath;
		return $this->fs->has($userDataPath . self::FEED_FILE);
	}

	/**
	 * userDataIsExpired
	 *
	 * Checks if the user's feed has been expired
	 * 
	 * @param  string $userDataPath
	 * @return bool   TRUE if user's feed is expired, else, FALSE
	 */
	protected function userDataIsExpired($userDataPath = '')
	{
		$userDataPath = $userDataPath ?: $this->userDataPath;
		
		# Read the last modification time from .modtime file
		$modtime = $this->fs->read($userDataPath . self::MOD_TIME_FILE);
		
		// clearstatcache();
		return (time() - $modtime > $this->expTime);
	}

	/**
	 * Get the response of a GET request
	 * 
	 * @param  string $url URL address
	 * @return string      content
	 */
	protected static function get($url = '')
	{
		if (function_exists('curl_init')) {
			$ch = curl_init();
			curl_setopt($ch, CURLOPT_URL, $url);
			curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
			curl_setopt($ch, CURLOPT_HEADER, 0);
			curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, 0); 
			$output = curl_exec($ch);
			curl_close($ch);
		} else {
			$output = file_get_contents($url) or FALSE;
		}
		return $output;
	}

	/**
	 * getImageType
	 * 
	 * @return string type of the images to be downloaded
	 */
	protected function getImageType()
	{
		switch ($this->imageType) {
			case 'thumb':
			case 'thumbnail':
				return 'thumbnail';
				break;
			
			case 'standard':
			case 'large':
				return 'standard_resolution';
				break;
			
			default:
				return 'low_resolution';
				break;
		}
	}

	/**
	 * getUserInfo
	 *
	 * Gets the detail of user's Instagram account
	 * including 'username', 'bio', 'website', 'profile_picture', 'full_name', 'id'
	 *
	 * @param  string $username
	 * @param  string $clientID
	 * @return array
	 */
	protected function getUserInfo($username = '', $clientID = '')
	{
		$username = $username ?: $this->username;
		$clientID = $clientID ?: $this->clientID;

		$json = self::get('https://api.instagram.com/v1/users/search?q='. $username .'&client_id='. $clientID);
		
		if ($json) {
			$user = json_decode($json, true)['data'];
			return isset($user[0]) ? $user[0] : [];
		}

		return [];
	}
	
	/**
	 * getUserFeed
	 *
	 * Gets the latest posts of the user's Instagram account
	 * Downloads the images and stores them locally
	 * 
	 * @param  string  $userID
	 * @param  string  $clientID
	 * @param  integer $count     limit the number of posts
	 * @return array   user feed  empty array on failure
	 */
	protected function getUserFeed($userID = '', $clientID = '', $count = 0)
	{
		$userID   = $userID   ?: $this->userID;
		$clientID = $clientID ?: $this->clientID;
		$count    = $count    ?: $this->count;

		$query = 'https://api.instagram.com/v1/users/'. $userID . '/media/recent/?client_id='. $clientID;

		if ($count)
			$query .=  '&count=' . $count;

		$json = self::get($query) or [];
		
		$feed = json_decode($json, true);

		if (! isset($feed['data']) or ! is_array($feed['data']))
			return [];

		$result = [];
		$i = 0;
		
		foreach ($feed['data'] as $key => $value)
		{
			if (isset($value['link']) and isset($value['images']))
			{
			 	$result[$i]['link'] = $value['link']; 
				
				$image = $this->downloadFile($value['images'][$this->getImageType()]['url']);
				$result[$i]['image'] = $image ?: $value['images'][$this->getImageType()]['url'];
			}

			$i++;
		}

		return $result;
	}

	/**
	 * downloadFile
	 * 
	 * @param  string $url
	 * @param  string $dest
	 * @param  string $filename
	 * @return mixed  file path on success, FALSE on failure
	 */
	protected function downloadFile($url = '', $dest = '', $filename = '')
	{
		$filename = $filename ?: basename($url);
		// $filename = $this->userID . strrchr($url, '.');

		if ($dest !== '') 
			$dest = rtrim($dest, '/'). '/';
		else
			$dest = $this->imagePath . $this->username . '/';

		# Create the directories recursively if they don't exist
		if (! is_dir($dest))
			mkdir($dest, 0755, true);

		$dest .= $filename;

		if (function_exists('curl_init')) {
			$fp = fopen($dest, 'wb');
			
			$ch = curl_init($url);
			curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
			curl_setopt($ch, CURLOPT_HEADER, 0);
			curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, 0);
			curl_setopt($ch, CURLOPT_FILE, $fp);
			$ret = curl_exec($ch);
			curl_close($ch);
			
			fclose($fp);
		} else {
			$ret = file_put_contents($dest, file_get_contents($url));
		}

		return $ret ? $dest : false;
	}

	/**
	 * storeUserFeed
	 *
	 * Stores user's feed in the feed.json file
	 * 
	 * @param  string $userDataPath path to user's data directory
	 * @param  string $feed         user's feed
	 * @return void
	 */
	protected function storeUserFeed($userDataPath = '', $feed = '')
	{
		$userDataPath = $userDataPath ?: $this->userDataPath;
		$feed = $feed ?: $this->userFeed;

		$this->fs->put(
			$this->userDataPath . self::FEED_FILE, json_encode($feed, JSON_UNESCAPED_UNICODE)
		);
	}

	/**
	 * readUserFeed
	 *
	 * Read the user's feed from feed.json file
	 * 
	 * @param  string $userDataPath path to user's data directory
	 * @return array                user's feed
	 */
	protected function readUserFeed($userDataPath = '')
	{
		$userDataPath = $userDataPath ?: $this->userDataPath;
		return json_decode($this->fs->read($userDataPath . self::FEED_FILE), true);
	}
}