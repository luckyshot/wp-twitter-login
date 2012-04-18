<?php
/*
Plugin Name: TwitterLogin
Plugin URI: 
Description: Enables users to register in Wordpress through a Twitter account in a simple click using OAuth. The plugin can also block the Admin Panel for Twitter users (recommended).
Author: Xavi Esteve
Version: 0.1
Author URI: http://xaviesteve.com/
Credits: Matt Harris, Callum Macdonald
*/

class TwitterLogin {
	
	// Plugin options
	public $disableadminpanel = true;
	private $salt = 'BasB2987n8-asdohnn62tnhwgdjhg2yubTEB@72bnkdgksna'; // Change this to something long and complicated. Keep it very secret 
	private $twitter_user_prefix = 'twitter_'; // Prefix every Twitter username will have
	
	private $twitter_consumer_key = ''; // Create and see here: https://dev.twitter.com/apps/
	private $twitter_consumer_secret = '';
	
	private $wpadmin_required_capability = 'edit_others_posts';
	private $domainname;
	
	
	public function __construct() {
		// Gets domain name so we can register with fake unique email addresses
		$nowww = ereg_replace('www\.','',get_bloginfo('url'));
		$domain = parse_url($nowww);
		if(!empty($domain["host"])) {
			$this->domainname = $domain["host"];
		}else{
			$this->domainname = $domain["path"];
		}
		
	}
	
	
	
	
	
	/**
	 * Login/Logout links
	 */	
	public function view_login_link() {
		return ''.get_bloginfo('url').'/?tw=login';
	}
	
	public function view_logout_link() {
		return ''.get_bloginfo('url').'/?tw=logout';
	}
	
	
	
	
	
	/**
	 * Load
	 * The controller, checks for any actions
	 */
	public function load() {	
		// User is logging in with Twitter
		if (isset($_GET['tw']) && $_GET['tw']=='login') {
			$this->login();
			
		// User is logging out of Wordpress
		}else if (isset($_GET['tw']) && $_GET['tw']=='logout') {
			wp_logout();
			
		// Twitter callback
		}else if(isset($_REQUEST['oauth_verifier'])) {
			$this->callback();
		}
	}
	
	
	
	
	
	
	
	/**
	 * Twitter init
	 * Initialize Twitter API using tmhOAuth library
	 */
	public function twitter_init() {
		global $tmhOAuth;
		session_start();
		
		require_once('tmhOAuth/tmhOAuth.php');
		require_once('tmhOAuth/tmhUtilities.php');
		$tmhOAuth = new tmhOAuth(array(
			'consumer_key'    => $this->twitter_consumer_key,
			'consumer_secret' => $this->twitter_consumer_secret,
		));
		$this->here = tmhUtilities::php_self();
		return true;
	}
	
	
	
	
	
	
	
	/**
	 * Login
	 * Either logs into Wordpress (if user had already authed) or starts dance with Twitter
	 */
	public function login() {
		global $tmhOAuth;
		$this->twitter_init();
			
		// User already logged in with Twitter
		if ( isset($_SESSION['access_token']) ) {
			$tmhOAuth->config['user_token']  = $_SESSION['access_token']['oauth_token'];
			$tmhOAuth->config['user_secret'] = $_SESSION['access_token']['oauth_token_secret'];
			
			$code = $tmhOAuth->request('GET', $tmhOAuth->url('1/account/verify_credentials'));
			if ($code == 200) {
				$resp = json_decode($tmhOAuth->response['response']);
				$this->wplogin($this->twitter_user_prefix.$resp->id);
			} else {
				$this->outputError($tmhOAuth, 'login session');
			}
			
		}else{

			$callback = isset($_REQUEST['oob']) ? 'oob' : $this->here;
		
			$params = array(
				'oauth_callback'  => $callback,
			);
		
			if (isset($_REQUEST['force_write'])) :
				$params['x_auth_access_type'] = 'write';
			elseif (isset($_REQUEST['force_read'])) :
				$params['x_auth_access_type'] = 'read';
			endif;
		
			$code = $tmhOAuth->request('POST', $tmhOAuth->url('oauth/request_token', ''), $params);
		
			if ($code == 200) {
				$_SESSION['oauth'] = $tmhOAuth->extract_params($tmhOAuth->response['response']);
				$method = isset($_REQUEST['authenticate']) ? 'authenticate' : 'authorize';
				$force  = isset($_REQUEST['force']) ? '&force_login=1' : '';
				$authurl = $tmhOAuth->url("oauth/{$method}", '') .  "?oauth_token={$_SESSION['oauth']['oauth_token']}{$force}";
				header("Location: ".$authurl);
			} else {
				$this->outputError($tmhOAuth, 'login first');
			}
		}
		
	}
	
	
	
	
	/**
	 * Callback
	 * Gets the reply from Twitter
	 */
	public function callback() {
		global $tmhOAuth;
		$this->twitter_init();

		$tmhOAuth->config['user_token']  = $_SESSION['oauth']['oauth_token'];
		$tmhOAuth->config['user_secret'] = $_SESSION['oauth']['oauth_token_secret'];
	
		$code = $tmhOAuth->request('POST', $tmhOAuth->url('oauth/access_token', ''), array(
			'oauth_verifier' => $_REQUEST['oauth_verifier']
		));
	
		if ($code == 200) {
			// Get access token
			$_SESSION['access_token'] = $tmhOAuth->extract_params($tmhOAuth->response['response']);
			// Fill in tokens
			$tmhOAuth->config['user_token']  = $_SESSION['access_token']['oauth_token'];
  		$tmhOAuth->config['user_secret'] = $_SESSION['access_token']['oauth_token_secret'];
			// Request all user's data
			$code = $tmhOAuth->request('GET', $tmhOAuth->url('1/account/verify_credentials'));
			if ($code == 200) {
				$resp = json_decode($tmhOAuth->response['response']);
				// Create user
				$wpuserarray = array(
					"user_login" => $this->twitter_user_prefix.$resp->id,
					"user_pass" => $this->salt.$resp->id,
					"user_email" => $this->twitter_user_prefix.$resp->screen_name."@".$this->domainname,
					"first_name" => $resp->name,
					"description" => $resp->description,
					"user_url" => $resp->url,
					"nickname" => $resp->screen_name,
				);
				$extraarray = array(
					"lang" => $resp->en,
					"profile_image_url" => $resp->profile_image_url,
					"followers_count" => $resp->followers_count,
					"friends_count" => $resp->friends_count,
					"oauth_token" => $_SESSION['oauth']['oauth_token'],
					"oauth_token_secret" => $_SESSION['oauth']['oauth_token_secret'],
				);
				// Create the user if necessary
				$userid = $this->create_user($wpuserarray, $extraarray);
				
				unset($_SESSION['oauth']);
				
				// Login the user
				if ($userid) { 
					$this->wplogin($wpuserarray['user_login']);
					header("Location: ".$this->here);
				}else{
					header("Location: ".$this->here."?tw=error");
				}
			}else{
				$this->outputError($tmhOAuth, 'callback 2');
			}
		} else {
			$this->outputError($tmhOAuth, 'callback 1');
		}		
	}
	





	/**
	 * WP Login
	 * Login user to Wordpress (note that no password is needed)
	 * and no auth check is done here so double-check when you use this method
	 */
	private function wplogin($username) {
		$user = get_userdatabylogin($username);
		$userid = $user->ID;
		wp_set_current_user($userid, $username);
		wp_set_auth_cookie($userid);
		do_action('wp_login', $username);
	}



	
	/**
	 * Output error
	 * Displays error to admins when debug enabled
	 */
	private function outputError($tmhOAuth, $position = '') {
		global $tmhOAuth;
		//@@@if (WP_DEBUG && current_user_can($this->wpadmin_required_capability)) {
			echo 'Error '.$position.': ' . $tmhOAuth->response['response'] . PHP_EOL;
			tmhUtilities::pr($tmhOAuth);
		//@@@
	}
	
	
	
	
	
	
	/**
	 * Create user
	 * Creates user if it doesn't exist and fills in all extra fields
	 */
	private function create_user($array, $extraarray) {		
		// Check if username exists
		require_once(ABSPATH . WPINC . '/registration.php');
		if (!username_exists($array['user_login'])) {
			// Create user
			$userid = wp_insert_user($array );
			if (is_numeric($userid) && $userid > 0) {
				foreach ($extraarray as $key => $value) {
					update_usermeta( $userid, $key, $value );
				}
				return $userid;
			}else{
				return $userid;
			}
		}else{
			return 'usernameexists';
		}
	}
	

	
	
	
	
	
	/**
	 * Disable Admin
	 * Disable Admin menu for everyone except required capability
	 */
	public function disableadmin() {
		// Is this the admin interface?
		if (
			// Look for the presence of /wp-admin/ in the url
			stripos($_SERVER['REQUEST_URI'],'/wp-admin/') !== false
			&&
			// Allow calls to async-upload.php
			stripos($_SERVER['REQUEST_URI'],'async-upload.php') == false
			&&
			// Allow calls to admin-ajax.php
			stripos($_SERVER['REQUEST_URI'],'admin-ajax.php') == false
		) {
			if (!current_user_can($this->wpadmin_required_capability)) {
				wp_redirect(get_bloginfo('url'),302);
			}
		}
	}
	
}


$tl = new TwitterLogin;



// Disable admin panel hook
if ($tl->disableadminpanel == true) {
	add_action('init', array($tl, 'disableadmin'), 0);
}



// Hide password and email fields in profile
function tl_admin_css() {
   echo '<style type="text/css">
					// Login
					#login #nav,
					label[for=email],
					// Profile page
					#your-profile #email,
					#your-profile #password {display:none;}
					</style>';
}
add_action('admin_head', 'tl_admin_css');
add_action('login_head', 'tl_admin_css');



// Disable password change
if (isset($_REQUEST['action']) && $_REQUEST['action']=='lostpassword') {header('Location: '.get_bloginfo('url').'/wp-login.php');}
if (stripos($_SERVER['REQUEST_URI'], 'user-edit.php') !== false) {
	if (isset($_REQUEST['pass1'])) {$_REQUEST['pass1']=null;}
	if (isset($_REQUEST['pass2'])) {$_REQUEST['pass2']=null;}
	if (isset($_REQUEST['email'])) {$_REQUEST['pass1']=null;}
}
// Disable password reset
function disable_password_reset() { return false; }
add_filter ( 'allow_password_reset', 'disable_password_reset' );


// See if there is anything to do
add_action('init', array($tl, 'load'), 1);


/*
FYI - The response from Twitter

    [default_profile_image] => 
    [id] => 12345678
    [screen_name] => xaviesteve
    [time_zone] => London
    [profile_background_tile] => 1
    [favourites_count] => 7
    [contributors_enabled] => 
    [profile_sidebar_fill_color] => d4eaf1
    [geo_enabled] => 
    [profile_image_url] => http://a0.twimg.com/profile_images/1208768656/xavi-bridge-photo_normal.jpg
    [utc_offset] => 0
    [followers_count] => 249
    [name] => Xavi Esteve
    [show_all_inline_media] => 
    [profile_image_url_https] => https://si0.twimg.com/profile_images/1208768656/xavi-bridge-photo_normal.jpg
    [profile_background_color] => 03476c
    [follow_request_sent] => 
    [verified] => 
    [protected] => 
    [default_profile] => 
    [lang] => en
    [profile_background_image_url] => http://a0.twimg.com/profile_background_images/60693189/twitter.jpg
    [url] => http://xaviesteve.com/
    [profile_link_color] => 00aaff
    [description] => Front-end Web Developer and Graphic Designer from Barcelona based in London. I am passionate in Wordpress, jQuery, CSS, UX, UI, JavaScript, PHP and SEO.
    [created_at] => Tue Mar 24 14:37:38 +0000 2009
    [listed_count] => 15
    [profile_use_background_image] => 1
    [id_str] => 26248137
    [profile_background_image_url_https] => https://si0.twimg.com/profile_background_images/60693189/twitter.jpg
    [friends_count] => 113
    [profile_text_color] => 64686b
    [following] => 
    [is_translator] => 
    [location] => London, United Kingdom
    [notifications] => 
    [statuses_count] => 657
    [status] => stdClass Object
        (
            [in_reply_to_screen_name] => 
            [in_reply_to_status_id] => 
            [favorited] => 
            [created_at] => Tue Mar 27 13:58:02 +0000 2012
            [truncated] => 
            [place] => 
            [coordinates] => 
            [possibly_sensitive_editable] => 1
            [possibly_sensitive] => 
            [in_reply_to_user_id] => 
            [in_reply_to_status_id_str] => 
            [id_str] => 12345678901234567890
            [retweeted] => 
            [retweet_count] => 0
            [in_reply_to_user_id_str] => 
            [source] => <a href="http://twitterfeed.com" rel="nofollow">twitterfeed</a>
            [id] => 12345678901234567890
            [contributors] => 
            [geo] => 
            [text] => Compare files with Dreamweaver in Mac OSX â€" Step by step guide: It is very helpful to be able to compare differe... http://t.co/e6VmIsF8
        )

    [profile_sidebar_border_color] => ffffff
*/
