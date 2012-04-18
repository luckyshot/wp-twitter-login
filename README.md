Register in Wordpress with Twitter account (Wordpress plugin)
=============================================================

Wordpress Login is a Wordpress plugin that will allow users to register into a Wordpress website with a Twitter account in one single click.

How it works?
-------------------------------------------------------------

See **[Wordpress with Twitter account Full Documentation](http://xaviesteve.com/3128/register-in-wordpress-with-twitter-account-plugin/)** for full documentation

Installation
-------------------------------------------------------------

1. Download the plugin and upload it to your plugins folder as you would do with any other Wordpress plugin

2. Create a new app at Twitter here

3. Copy the Consumer Key and Consumer Secret and paste them in twitterlogin.php

4. Change the $salt variable to something long and complicated

5. Optionally, you can change the username prefix to something else to increase security

To see if a user is logged in through Twitter run `is_user_logged_in()` and then check if the username matches the Twitter prefix (`tw-` by default).

Retrieving user information
-------------------------------------------------------------

To retrieve the current user's data do something like this:

	global $current_user;
	get_currentuserinfo();
	echo 'Hello ' . $current_user->nickname;
	echo 'User token: ' . $current_user->oauth_token;
	echo '<img src="' . $current_user->profile_image_url . '" alt="" />';


To retrieve data from someone else do something like this:

	$user_info = get_userdata($user_id);
	echo 'Username: ' . $user_info->nickname;


More information at See **[Wordpress with Twitter account Full Documentation](http://xaviesteve.com/3128/register-in-wordpress-with-twitter-account-plugin/)**.