<?php
/**
 * MediaCreeper plugin for WordPress
 *
 * This file contains the main code for the entire plugin
 * and is called by the plugin file mediacreeper.php
 *
 * (c) 2012 Martin Alice
 */

require_once('MediacreeperAPI.class.php');
class Mediacreeper {
	/* For recurring tasks - see mediacreeper.php */
	const CRON_ACTION_NAME = 'mediacreeper_cron_action';
	const CRON_ACTION_FUNC = 'mediacreeper_cron_wrapper';

	/* Private data */
	private $env;

	/* For storing settings in Wordpress options table */
	public static $optionGroupName = 'MediaCreeper-group';
	public static $optionName = 'MediaCreeper-settings';

	/* Public data */
	public $error;

	/**
	 * Constructor
	 */
	function __construct() {
		/* Initial configuration - updated in ::setupEnvironment() */
		$this->env = array(
			'log_func'	=> array($this, 'logToFile'),
			'log_filename'	=> WP_DEBUG? '/tmp/mediacreeper.debug': NULL
		);

		global $wpdb;
		if(isset($wpdb)) {
			$wpdb->mediacreeper = $wpdb->prefix .'mediacreeper';
			$wpdb->mediacreeper_names = $wpdb->prefix .'mediacreeper_names';
		}

		$this->setError(NULL);
		$this->setupEnvironment();

		/* Initialize plugin when we're certain necessary resources have been loaded */
		if(function_exists('add_action')) {
			add_action('admin_init', array(&$this, 'initPlugin'));
			add_action('admin_menu', array(&$this, 'addOptionsPage'));
			add_action('wp_dashboard_setup', array(&$this, 'addDashboardWidget'));
		}

		$this->log(__FUNCTION__ .': Done in constructor');
	}

	/**
	 * Set public error message
	 *
	 * @param $message Error message (use NULL to clear)
	 * @param $source (optional) Source of error
	 *
	 * @return Nothing
	 */
	private function setError($message, $source = NULL) {
		$this->error = $message;
		if(!empty($source))
			$message = $source .': '. $message;
		if(!empty($message))
			$this->log($message);
	}

	/**
	 * Check MediaCreeper dependencies
	 *
	 * @return TRUE if dependencies are met, FALSE otherwise
	 */
	private function checkDependencies() {
		$this->log(__FUNCTION__ .': Checking deps');
		if(!function_exists('curl_init')) {
			/**
			 * Too intrusive?!
			 *
			$this->setError('ERROR: The cURL extension is missing in PHP. '.
					'Under Ubuntu, install with: sudo apt-get install php5-curl. '.
					'Under CentOS, install with: sudo yum install php-common.',
					__FUNCTION__);
			return FALSE;
			*/
		}

		return TRUE;
	}

	/**
	 * MediaCreeper plugin initialization
	 *
	 * @return Nothing
	 */
	public function initPlugin() {
		$this->log(__FUNCTION__ .': Initializing plugin');
		if($this->checkDependencies()) {

			/* Register our setting - requires Wordpress >= 2.7 */
			register_setting(self::$optionGroupName, self::$optionName);

			/* Flot graph script */
			wp_register_script('mediacreeper-flot',
				plugins_url('flot/jquery.flot.js', __FILE__));
			wp_enqueue_script('mediacreeper-flot');
			wp_register_script('mediacreeper-flot-pie',
				plugins_url('flot/jquery.flot.pie.js', __FILE__));
			wp_enqueue_script('mediacreeper-flot-pie');
			/**
			 * For jQuery Flot > 0.7
			 *
			wp_register_script('mediacreeper-flot-time',
				plugins_url('flot/jquery.flot.time.js', __FILE__));
			wp_enqueue_script('mediacreeper-flot-time');
			*/

			/* Display metabox for existing articles */
			if(function_exists('add_meta_box') && isset($_REQUEST['post'])) {
				add_meta_box('mediacreeper-metabox',
					'Recent media company visitors - MediaCreeper',
					array($this, 'renderMetabox'), 'post');
			}
		}

		$this->log(__FUNCTION__ .': Done');
	}

	/**
	 * Initialize MediaCreeper environment
	 *
	 * @return Nothing
	 */
	private function setupEnvironment() {
		/* Support running under both Apache and Nginx */
		if(!function_exists('apache_request_headers')) $arr = $_SERVER;
		else $arr = apache_request_headers();
		foreach($arr as $key => $value) {
			if(substr($key, 0, 5) === 'HTTP_') $key = substr($key, 5);
			$key = strtoupper($key);
			$this->env[$key] = $value;
		}

		/* Save potential error from form/update request to display it later */
		if(isset($_REQUEST['mcerror'])) $this->setError($_REQUEST['mcerror']);

		$this->log(__FUNCTION__ .': Done setting up environment');
	}

	/**
	 * Dashboard widget for the plugin
	 * @return Nothing
	 */
	public function addDashboardWidget() {
		$this->log(__FUNCTION__ .': Adding dashboard widget');
		if(function_exists('wp_add_dashboard_widget') /* new in 2.7 */) {
			wp_add_dashboard_widget('mediacreeper-widget',
					'Recent media company visits - MediaCreeper',
					array($this, 'renderDashboardWidget'));
		}
		$this->log(__FUNCTION__ .': Done');
	}

	/**
	 * Render dashboard widget
	 * @return Nothing
	 */
	public function renderDashboardWidget() {
		require_once(dirname(__FILE__) .'/widget.php');
	}

	/**
	 * Options page for the plugin
	 * This menu entry will show up inside the Settings menu
	 * in the administration area of Wordpress.
	 *
	 */
	public function addOptionsPage() {
		$this->log(__FUNCTION__ .': Adding options page to menu');
		$ret = add_options_page('MediaCreeper settings', 'MediaCreeper', 'manage_options',
				'mediacreeper-settings', array(&$this, 'renderOptionsPage'));
		$this->log(__FUNCTION__ .': Done: "'. $ret .'"');
	}

	/**
	 * Render MediaCreeper options page
	 */
	public function renderOptionsPage() {
		$this->log(__FUNCTION__ .': Rendering options page');
		require_once(dirname(__FILE__) .'/options.php');
		$this->log(__FUNCTION__ .': Done rendering');
	}

	/**
	 * Render metabox on post
	 */
	public function renderMetabox() {
		global $post, $wpdb;

		/* New post? */
		if($post->post_status !== 'publish') {
			echo '<p>No data available yet</p>';
			return;
		}

		$q = $wpdb->prepare("SELECT ts, ref, value AS name
				FROM $wpdb->mediacreeper m
				LEFT JOIN $wpdb->mediacreeper_names n
				ON m.name_id=n.id AND n.kind='name'
				WHERE post_id=%d
				ORDER BY ts DESC
				LIMIT 50", $post->ID);
		$hits = $wpdb->get_results($q);

		/* Render metabox contents */
		require(dirname(__FILE__) .'/metabox.php');
	}

	/**
	 * Activation hook
	 * Runs only when the plugin is activated
	 *
	 * @return Nothing
	 */
	public function activate() {
		$this->log(__FUNCTION__ .': Activation hook called');
		$this->initDatabaseTables();
		$this->initOptions();

		/* Schedule recurring data download from MediaCreeper */
		wp_schedule_event(time(), 'hourly', Mediacreeper::CRON_ACTION_NAME);
		$this->log(__FUNCTION__ .': Scheduled "'. Mediacreeper::CRON_ACTION_NAME .'" to run hourly');
		$this->log(__FUNCTION__ .': Done');
	}

	/**
	 * Deactivation hook
	 * Runs only when the plugin is deactivated
	 *
	 * @return Nothing
	 */
	public function deactivate() {
		$this->log(__FUNCTION__ .': Deactivation hook called');
		wp_clear_scheduled_hook(Mediacreeper::CRON_ACTION_NAME);
		$this->log(__FUNCTION__ .': De-scheduled "'. Mediacreeper::CRON_ACTION_NAME .'"');
		$this->log(__FUNCTION__ .': Done deactivating');
	}

	/**
	 * Fetch recent visits to site from MediaCreeper API
	 * Run by externally defined mediacreeper_run_cronjob() function
	 *
	 * @return Number of hits fetched from the API
	 */
	public function cronjob() {
		global $wpdb;

		$this->log(__FUNCTION__ .': Starting cronjbo');

		$api = new MediacreeperAPI();

		$options = get_option(self::$optionName);
		if(!$options) {
			$this->log(__FUNCTION__ .': Config error, aborting cron');
			return -1;
		}

		if(!isset($options['site_id']) || $options['site_id'] <= 0) {
			$domain = $options['site'];
			$options['site_id'] = $api->getMediaCreeperSiteId($domain);
			if(!$options['site_id']) {
				$this->log(__FUNCTION__ .': Failed to retrieve site ID for domain: '. $domain);
				return -1;
			}

			$this->log(__FUNCTION__ .': Site ID for domain "'. $domain .'" is: '. $options['site_id']);
			update_option(self::$optionName, $options);
			$this->log(__FUNCTION__ .': Done updating site ID');
		}


		$sites_seen = array();
		$names_seen = array();
		$refs_seen = array();

		$api = new MediacreeperAPI();
		$hits = $api->siteId($options['site_id']);

		if(is_array($hits)) foreach($hits as $hit) {
			if(!in_array($hit->referrer, $refs_seen)) {
				$post_id = url_to_postid($hit->referrer);
				$refs_seen[$hits->referrer] = $post_id;
			}
			else {
				$post_id = $refs_seen[$hits->referrer];
			}

			$q = $wpdb->prepare("INSERT INTO $wpdb->mediacreeper
				SET ts=%d, post_id=%d, site_id=%d, name_id=%s, ref=%s
				ON DUPLICATE KEY UPDATE ts=%d, post_id=%d, site_id=%d, name_id=%s, ref=%s",
				$hit->timestamp, $post_id, $hit->site_id, $hit->name_id, $hit->referrer,
				$hit->timestamp, $post_id, $hit->site_id, $hit->name_id, $hit->referrer);
			$wpdb->query($q);

			if(!in_array($hit->name_id, $names_seen)) {
				$q = $wpdb->prepare("INSERT INTO $wpdb->mediacreeper_names
					SET id=%d, kind=%s, value=%s
					ON DUPLICATE KEY UPDATE id=%d, kind=%s, value=%s",
					$hit->name_id, 'name', $hit->name,
					$hit->name_id, 'name', $hit->name);

				$wpdb->query($q);
				$names_seen[] = $hit->name_id;
			}

			if(!in_array($hit->site_id, $sites_seen)) {
				$q = $wpdb->prepare("INSERT INTO $wpdb->mediacreeper_names
					SET id=%d, kind=%s, value=%s
					ON DUPLICATE KEY UPDATE id=%d, kind=%s, value=%s",
					$hit->site_id, 'site', $hit->site,
					$hit->site_id, 'site', $hit->site);

				$wpdb->query($q);
				$sites_seen[] = $hit->site_id;
			}
		}

		$wpdb->query("DELETE FROM $wpdb->mediacreeper
			WHERE ts < UNIX_TIMESTAMP(DATE_SUB(NOW(), INTERVAL 1 YEAR))");

		$this->log(__FUNCTION__ .': Done with cron');

		return count($hits);
	}

	/**
	 * Create necessary database tables
	 * Runs only when the plugin is activated
	 */
	private function initDatabaseTables() {
		global $wpdb;

		$this->log(__FUNCTION__ .': Creating tables in MySQL if necessary');


		require_once(ABSPATH . 'wp-admin/includes/upgrade.php');

		$sql = "CREATE TABLE IF NOT EXISTS $wpdb->mediacreeper (
			ts int(10) unsigned NOT NULL,
			post_id int(11) NOT NULL DEFAULT '0',
			site_id int(11) NOT NULL,
			name_id int(11) NOT NULL,
			ref varchar(255) NOT NULL DEFAULT '',
			PRIMARY KEY  (ts)
			) ENGINE=MyISAM DEFAULT CHARSET=utf8";

		dbDelta($sql);

		$sql = "CREATE TABLE $wpdb->mediacreeper_names (
		  id int(11) unsigned NOT NULL,
		  kind enum('name','site') NOT NULL DEFAULT 'name',
		  value varchar(255) NOT NULL DEFAULT '',
		  PRIMARY KEY  (id, kind)
		) ENGINE=MyISAM DEFAULT CHARSET=utf8";
		dbDelta($sql);

		$opts = get_option(self::$optionName);
		if($opts) {
			$opts['db_version'] = 1;
			update_option(self::$optionName, $opts);
		}

		$this->log(__FUNCTION__ .': Done');
	}

	/**
	 * Initialize option group
	 * Runs only when the plugin is activated
	 */
	private function initOptions() {
		$defaults = array(
			'site'		=> $this->getServerName(),
			'site_id'	=> 0,
			'log_func'	=> NULL, /* no logging by default */
			'log_filename'	=> WP_DEBUG? '/tmp/mediacreeper.debug': NULL,
			'show_metabox'	=> 1,
			'show_widget'	=> 1,
			'db_version'	=> 1,
			'tracker_tag'	=> 1,
		);

		$opts = get_option(self::$optionName);
		if($opts === FALSE) {
			$opts = $defaults;
			add_option(self::$optionName, $opts, '', 'no' /* don't autoload */);
			$this->applyOptions($opts);
			$this->log(__FUNCTION__ .': Default options installed: '. json_encode($opts));
		}
		else {
			$num_opts_updated = 0;
			foreach($defaults as $key => $value) {
				if(isset($opts[$key]))
					continue;

				$opts[$key] = $value;
				$num_opts_updated++;
			}

			if($num_opts_updated) {
				update_option(self::$optionName, $opts);
				$this->log(__FUNCTION__ .': '. $num_opts_updated .' options updated');
			}
		}
	}

	/**
	 * Apply modified options
	 */
	private function applyOptions($opts) {
		if(!is_array($opts))
			return;

		switch($opts['log_func']) {
		case 'file':
			$this->env['log_func'] = array($this, 'logToFile');
			break;
		case 'php':
			$this->env['log_func'] = array($this, 'logToPhp');
			break;
		case NULL:
		case '':
		default:
			/* Disable internal logging */
			unset($this->env['log_func']);
			break;
		}
	}

	/**
	 * Variable arguments log handler
	 * @param (mixed) Variable number of arguments
	 * @return Nothing
	 */
	private function log() {
		$args = func_get_args();
		$logMessage = implode(' ', $args);
		foreach($this->env as $key => $value) if(!strcasecmp($key, 'log_func'))
			call_user_func($this->env[$key], $logMessage);
	}

	/**
	 * Log timestamped messages to file
	 */
	public function logToFile($str) {
		if(($fd = @fopen($this->env['log_filename'], 'a')) !== FALSE) {
			@fwrite($fd, strftime('[%F %T]'). " $str\n");
			fclose($fd);
		}
	}

	/**
	 * Log to PHP internal log (if configured)
	 */
	public function logToPhp($str) {
		error_log($str);
	}

	/**
	 * Get server hostname
	 * @return Server hostname
	 */
	public function getServerName() {
		if(isset($this->env['HOST']))
			return $this->env['HOST'];

		return $this->env['SERVER_NAME'];
	}

	/**
	 * Get real IP
	 * @return Real IP
	 */
	public function getIP() {
		if(!isset($this->env['X_FORWARDED_FOR']))
			return $this->env['REMOTE_ADDR'];

		$forwardedList = explode(',', $this->env['X_FORWARDED_FOR']);
		$forwardedList = array_reverse($forwardedList);
		$ip = trim(array_shift($forwardedList));

		return $ip;
	}
}
