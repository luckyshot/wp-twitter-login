# Register in Wordpress with Twitter account (Wordpress plugin)

Wordpress Login is a Wordpress plugin that will allow users to register into a Wordpress website with a Twitter account in one single click.

# Who is this plugin for?

This plugin has been specially coded for intermediate to senior Wordpress developers that want to base their new web application in the Wordpress system. The plugin can be configured easily through the code and it has been coded to be very scalable. Users with no knowledge of PHP may find other solutions more helpful since this plugin doesn't have any GUI. The main purpose is to further develop an app thanks to the high-converting user registration through Twitter one-click sign up process and save time in programming a User Authentication system.

# How it works?

When a user accesses the URL ?tw=login in a Wordpress website he is redirected to Twitter using Matt Harris' OAuth library. Once authenticated, a new Wordpress user is created picking all the information from the Twitter username. The Wordpress username has a prefix (tw- by default) and his Twitter ID (the public username is not used here since it can be changed). The password is his Twitter ID with a custom salt. Extra information is stored in his user profile (nickname, full name, website, bio, etc.) as well as in custom user fields (oauth_token, oauth_token_secret, language, followers and friends count, profile image, etc.).

A caveat in Wordpress is that it requires a valid and unique email address per user. Since Twitter does not provide you with the user's email address, the plugin registers the user with an email address like tw-twitterid@domainname.com.

By default the plugin will not allow users to access WP-Admin which makes this plugin perfect for someone who will provide a service to registered users without ever showing the Wordpress inner pages to them. You can enable access to WP-Admin changing a variable from true to false although you should disallow users from changing their password or they will not be able to log back in.

# Installation

* Download the plugin and upload it to your plugins folder as you would do with any other Wordpress plugin

* Create a new app at Twitter here

* Copy the Consumer Key and Consumer Secret and paste them in twitterlogin.php

* Change the $salt variable to something long and complicated

* Optionally, you can change the username prefix to something else to increase security

To see if a user is logged in through Twitter run is_user_logged_in() and then check if the username matches the Twitter prefix (tw- by default).

To retrieve the current user's data do something like this:

`global $current_user;
get_currentuserinfo();
echo 'Hello ' . $current_user->nickname;
echo 'User token: ' . $current_user->oauth_token;
echo '<img src="' . $current_user->profile_image_url . '" alt="" />';`


To retrieve data from someone else do something like this:

`$user_info = get_userdata($user_id);
echo 'Username: ' . $user_info->nickname;`