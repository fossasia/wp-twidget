<?php
	/* 
		Plugin Name: Twitget
		Plugin URI: http://bostjan-cigan.com/plugins
		Description: A simple widget that shows your recent tweets with fully customizable HTML output, hashtag support and more.
		Version: 3.3.8
		Author: Bostjan Cigan
		Author URI: http://bostjan-cigan.com
		License: GPL v2
	*/ 
	
	// Wordpress formalities here ...
	
	// Lets register things
	if(!class_exists('Loklak')) {
		require 'lib/loklak_php_api/loklak.php';
	}
	if(!class_exists('tmhOAuth')) {
		require 'lib/tmhOAuth.php';
	}
	if(!class_exists('tmhUtilities')) {
		require 'lib/tmhUtilities.php';
	}
	if(!class_exists('TwitterAPIExchange')) {
		require 'lib/TwitterAPIExchange.php';
	}
	
	register_activation_hook(__FILE__, 'twitget_install');
	register_deactivation_hook(__FILE__, 'twitget_uninstall');
	add_action('admin_menu', 'twitget_admin_menu_create');
	add_action('widgets_init', create_function('', 'return register_widget("simple_tweet_widget");')); // Register the widget
	add_shortcode('twitget', 'twitget_shortcode_handler');
	add_action('wp_head', 'twitget_js_include');
	add_action('admin_enqueue_scripts', 'twidget_auth_disable_js_include' );
	add_action('init', 'twitget_jquery_include');
	
	global $twitget_plugin_install_options;
	$twitget_plugin_install_options = array(
		'twitter_username' => '',
		'use_https_url' => false,
		'tweet_data' => NULL,
		'last_access' => time(),
		'time_limit' => 5,
		'number_of_tweets' => 5,
		'show_avatar' => true,
		'time_format' => 'D jS M y H:i',
		'show_powered_by' => false,
		'language' => 'en',
		'version' => '3.38',
		'loklak_api' => false,
		'consumer_key' => '',
		'consumer_secret' => '',
		'user_token' => '',
		'user_secret' => '',
		'twitter_api' => 0, // 0 is Matt Harris's lib, 1 is James Mallisons lib
		'links_new_window' => false,
		'show_retweets' => false,
		'exclude_replies' => false,
		'show_relative_time' => false,
		'truncate_tweet' => false,
		'show_full_url' => false,
		'truncate_tweet_size' => 100,
		'custom_string' => '<img class="alignleft" src="{$profile_image}">
<a href="https://www.twitter.com/{$user_twitter_name}">@{$user_twitter_name}</a>
<br />
{$user_description}
<ul class="pages">
{$tweets_start}
	<li>{$tweet_text}<br />{$tweet_time}</li>
{$tweets_end}
</ul>'

	);
	
	global $twitget_language_array;
	$twitget_language_array = array(
			"Arabic" => "ar",
			"Arabic (Moroccan)" => "ar-ma",
			"Bahasa (Indonesia)" => "id",
			"Bahasa (Malaysia)" => "ms-my",
			"Basque" => "eu",
			"Bulgarian" => "bg",
			"Catalan" => "ca",
			"Chuvash" => "cv",
			"Chinese" => "zh-cn",
			"Chinese (Traditional)" => "zh-tw",
			"Czech" => "cs",
			"Danish" => "da",
			"Dutch" => "nl",
			"German" => "de",
			"English" => "en",
			"English (Canadian)" => "en-ca",
			"English (UK)" => "en-gb",
			"Esperanto" => "eo",
			"Spanish" => "es",
			"Finnish" => "fi",
			"French" => "fr",
			"French (Canadian)" => "fr-ca",
			"Galician" => "gl",
			"Hebrew" => "he",
			"Hungarian" => "hu",
			"Icelandic" => "is",
			"Italian" => "it",
			"Japanese" => "ja",
			"Korean" => "ko",
			"Latvian" => "lv",
			"Nepalese" => "ar",
			"Norwegian (nynorsk)" => "nn",
			"Norwegian (bokmål)" => "nb",
			"Polish" => "pl",
			"Portugese" => "pt",
			"Portugese (Brazil)" => "pt-br",
			"Russian" => "ru",
			"Slovenian" => "sl",
			"Swedish" => "sv",
			"Thai" => "th",
			"Turkish" => "tr",
			"Tamaziyt" => "tzm-la",
			"Ukranian" => "uk"
	);
	
	// The array for converting PHP formatted date to moment.js date
	global $twitget_time_array;
	$twitget_time_array = array(
		"d" => "DD", // 01 - 31 day of the month
		"D" => "ddd", // Mon through Sun
		"j" => "D", // 1 to 31 day of the month without zeroes
		"l" => "dddd", // Sunday through Saturday
		"N" => "D", // 1 - 7 (day)
		"S" => "Do", // st, nd, rd, th - Because PHP supports suffix for only day of month (j)
		"w" => "d", // 0 - 6 day of week
		"z" => "DDD", // 0 - 365 day of year
		"W" => "wo", // 42nd week of year
		"F" => "MMMM", // January through December
		"m" => "MM", // 01 - 12
		"M" => "MMM", // Jan through Dec
		"n" => "M", // 1 - 12
		"t" => "", // 28 - 31 (number of days in month)
		"L" => "", // 1 if leap year, 0 otherwise
		"o" => "YYYY", // 1999 or 2003
		"Y" => "YYYY", // 1999 or 2003
		"y" => "YY", // 99 or 03
		"a" => "a", // am or pm
		"A" => "A", // AM or PM
		"B" => "", // 000 to 999 swatch
		"g" => "h", // 1 through 12
		"G" => "H", // 0 through 23
		"h" => "hh", // 01 to 12
		"H" => "HH", // 00 to 23
		"i" => "mm", // 00 to 59 minutes
		"s" => "ss", // 00 to 59 seconds
		"u" => ""  // microseconds
	);
	
	// Get current options
	$plugin_options_settings = get_option('twitget_settings');

	// Check if version is smaller and update
	if(is_array($plugin_options_settings) && isset($plugin_options_settings['version'])) { 
		if(((float) ($plugin_options_settings['version'])) < ((float) ($twitget_plugin_install_options['version']))) {
			twitget_update();
		}
	}
	
	function twitget_install() {
		global $twitget_plugin_install_options;
		add_option('twitget_settings', $twitget_plugin_install_options);
	}
	
	function twitget_jquery_include() {
		if(!wp_script_is('jquery')) {
			wp_register_script('jquery', 'http://ajax.googleapis.com/ajax/libs/jquery/2.0.0/jquery.min.js');
			wp_enqueue_script('jquery');
		}	
	}

	function twitget_js_include() {
		$moment_js = plugin_dir_url(__FILE__).'js/moment.js';
		$lang_js = plugin_dir_url(__FILE__).'js/langs.min.js';
		wp_register_script('moment.js', $moment_js);
		wp_enqueue_script('moment.js');
		wp_register_script('langs.js', $lang_js);
		wp_enqueue_script('langs.js');
	}

	function twidget_auth_disable_js_include() {
		$twitter_auth_disable_js = plugin_dir_url(__FILE__).'js/twitter_auth_disable.js';
		wp_register_script('twitter_auth_disable.js', $twitter_auth_disable_js);
		wp_enqueue_script('twitter_auth_disable.js');	
	}

	
	function twitget_update() {
		
		global $twitget_plugin_install_options;
		$plugin_options_settings = get_option('twitget_settings');
		
		// Legacy purposes only, before 3.0, delete these variables from options
		$html_output = array(
			'after_image_html',
			'before_tweets_html',
			'tweet_start_html',
			'tweet_middle_html',
			'tweet_end_html',
			'after_tweets_html',
			'mode',
			'use_custom',
			'use_cookie',
			'cookie_expiration',
			'show_browser_time',
			'show_local_time'
		);

		if((float) $plugin_options_settings['version'] < (float) $twitget_plugin_install_options['version']) {
			foreach($twitget_plugin_install_options as $key => $value) {
				$plugin_options_settings[$key] = (isset($plugin_options_settings[$key]) && strcmp($key, "version") != 0) ? $plugin_options_settings[$key] : $value;
			}
			foreach($html_output as $key) { // Legacy purposes only, before 2.0, delete variables from options
				unset($plugin_options_settings[$key]);
			}
			$plugin_options_settings['version'] = $twitget_plugin_install_options['version'];
			update_option('twitget_settings', $plugin_options_settings);
		}
		
	}
	
	function twitget_uninstall() {
		delete_option('twitget_settings');
	}

	function twitget_admin_menu_create() {
		add_options_page('Twitget Settings', 'Twitget', 'administrator', __FILE__, 'twitget_settings');	
	}

	// Shortcode function
	function twitget_shortcode_handler($attributes, $content = null) {
		ob_start();
		show_recent_tweets();
		return ob_get_clean();
	}

	function twitter_status() {
	
		$options = get_option('twitget_settings');

		if($options['loklak_api']) {
			$loklak = new Loklak();
			$response = $loklak->search('', null, null, $options['twitter_username']);
			$user = $loklak->user($options['twitter_username']);
			$user = json_decode($user, true);
			$user = json_decode($user['body'], true);
			$response = json_decode($response, true);
            $response = json_decode($response['body'], true);
            $response = $response['statuses'];
            for ($i=0; $i < sizeof($response); $i++) { 
            	$response[$i] = array_merge($response[$i], $user);
            }
            	
            
            $options['tweet_data'] = $response;
		}

		else if($options['twitter_api'] == 0) {
			
			$tmhOAuth = new tmhOAuth(
										array(
											'consumer_key' => $options['consumer_key'],
											'consumer_secret' => $options['consumer_secret'],
											'user_token' => $options['user_token'],
											'user_secret' => $options['user_secret'],
											'curl_ssl_verifypeer' => false
										)
									);
	 
			$request_array = array();
			$request_array['screen_name'] = $options['twitter_username'];
			$request_array['include_rts'] = $options['show_retweets'];
			$request_array['exclude_replies'] = $options['exclude_replies'];
	 
			$code = $tmhOAuth->request('GET', $tmhOAuth->url('1.1/statuses/user_timeline'), $request_array);
	 
			$response = $tmhOAuth->response['response'];

			$options['tweet_data'] = $response;
			
		}
		else {
		
			$twitter = new TwitterAPIExchange(
										array(
											'oauth_access_token' => $options['user_token'],
											'oauth_access_token_secret' => $options['user_secret'],
											'consumer_key' => $options['consumer_key'],
											'consumer_secret' => $options['consumer_secret']
										)
									);
									
			$url = 'https://api.twitter.com/1.1/statuses/user_timeline.json';
			$getfield = '?screen_name='.$options['twitter_username'];
			
			if($options['show_retweets']) {
				$getfield .= '&include_rts=true';
			}
			if(!$options['exclude_replies']) {
				$getfield .= '&exclude_replies=true';
			}
			
			$requestMethod = 'GET';
			$response = $twitter->setGetfield($getfield)->buildOauth($url, $requestMethod)->performRequest();

			$options['tweet_data'] = $response;
			
		}

		update_option('twitget_settings', $options);
	
	}
	
	function process_links($text, $new, $full, $tweet) {

		if(isset($tweet["entities"]["urls"])) {
			foreach($tweet["entities"]["urls"] as $key => $data) {
				
				if($full) {
					if($new) {
						$text = str_replace($data["url"], '<a href="'.$data["expanded_url"].'" target="_blank">'.$data["display_url"].'</a>', $text);
					}
					else {
						$text = str_replace($data["url"], '<a href="'.$data["expanded_url"].'">'.$data["display_url"].'</a>', $text);
					}
				}
				else {
					if($new) {
						$text = str_replace($data["url"], '<a href="'.$data["url"].'" target="_blank">'.$data["url"].'</a>', $text);
					}
					else {
						$text = str_replace($data["url"], '<a href="'.$data["url"].'">'.$data["url"].'</a>', $text);
					}				
				}
			}			
		}

		if(isset($tweet["entities"]["media"])) {
			foreach($tweet["entities"]["media"] as $key => $data) {
				
				if($full) {
					if($new) {
						$text = str_replace($data["url"], '<a href="'.$data["expanded_url"].'" target="_blank">'.$data["display_url"].'</a>', $text);
					}
					else {
						$text = str_replace($data["url"], '<a href="'.$data["expanded_url"].'">'.$data["display_url"].'</a>', $text);
					}
				}
				else {
					if($new) {
						$text = str_replace($data["url"], '<a href="'.$data["url"].'" target="_blank">'.$data["url"].'</a>', $text);
					}
					else {
						$text = str_replace($data["url"], '<a href="'.$data["url"].'">'.$data["url"].'</a>', $text);
					}				
				}
			}			
		}

		if($new) {
			$text = preg_replace('/@(\w+)/', '<a href="http://twitter.com/$1" target="_blank">@$1</a>', $text);
			$text = preg_replace('/\s#(\w+)/', ' <a href="http://twitter.com/search?q=%23$1" target="_blank">#$1</a>', $text);
		}
		else {
			$text = preg_replace('/@(\w+)/', '<a href="http://twitter.com/$1">@$1</a>', $text);
			$text = preg_replace('/\s#(\w+)/', ' <a href="http://twitter.com/search?q=%23$1">#$1</a>', $text);		
		}

		return $text;

	}

	function show_recent_tweets() {

		$options = get_option('twitget_settings');
		$get_data = false;
		
		if(!isset($options['tweet_data'])) {
			$get_data = true;
		}
		
		if(time() - $options['last_access'] > $options['time_limit'] * 60) {
			$get_data = true;
			$options['last_access'] = time();
			update_option('twitget_settings', $options);
		}
		
		unset($options);
		$options = get_option('twitget_settings');

		if($get_data) {
			twitter_status();
		}
		
		unset($options);
		$options = get_option('twitget_settings');
		
		if(!is_array($options["tweet_data"])) {
			$tweets = json_decode($options['tweet_data'], true);
		}
		else {
			$tweets = $options['tweet_data'];	
		}
		
		if(is_array($tweets) && isset($tweets) && isset($tweets[0]['user'])) {

			$limit = $options['number_of_tweets'];

			$image_url = ($options['use_https_url']) ? $tweets[0]['user']['profile_image_url_https'] : $tweets[0]['user']['profile_image_url']; // {$profile_image}
			$twitter_username = $tweets[0]['user']['screen_name']; // {$user_twitter_name}
			$twitter_username_real = $tweets[0]['user']['name']; // {$user_real_name}
			$twitter_user_url = $tweets[0]['user']['url']; // {$url}
			$twitter_user_description = $tweets[0]['user']['description']; // {$user_description}
			$twitter_follower_count = $tweets[0]['user']['followers_count']; // {$follower_count}
			$twitter_friends_count = $tweets[0]['user']['friends_count']; // {$friends_count}
			$twitter_user_location = $tweets[0]['user']['location']; // {$user_location}

			$result = "";

			$custom_string = $options['custom_string'];
			$feed_string = twitget_get_substring($custom_string, "{\$tweets_start}", "{\$tweets_end}");
			
			$feed_whole_string = "";

			$i = 0;
			$tweet_date_array = array();
			foreach($tweets as $tweet) {
				$tweet_text = $tweet['text'];
				$tweet_location = $options['loklak_api'] ? $tweet['place_name'] : $tweet['place']['full_name'];
				$link_processed = "";
				if(isset($tweet['retweeted_status'])) {
					$first = current(explode(":", $tweet_text));
					$whole_tweet = $first.": ";
					$whole_tweet .= $tweet['retweeted_status']['text'];
					$link_processed = process_links($whole_tweet, $options['links_new_window'], $options['show_full_url'], $tweet);
				}
				else {
					$link_processed = process_links($tweet['text'], $options['links_new_window'], $options['show_full_url'], $tweet);
				}

				$tweet_date_array[$i] = strtotime($tweet['created_at']);
				
				$tweet_id = $tweet['id_str'];
				
				if($options["truncate_tweet"]) {
					$link_processed = twitget_truncate_tweet($link_processed, $options["truncate_tweet_size"], "...", true, true);
				}
				
				$feed_string_tmp = str_replace("{\$tweet_text}", $link_processed, $feed_string);
				$feed_string_tmp = str_replace("{\$tweet_time}", '<span class="'.$i.'_tweet_date"></span>', $feed_string_tmp);				
				$feed_string_tmp = str_replace("{\$tweet_location}", $tweet_location, $feed_string_tmp);
				$feed_string_tmp = str_replace("{\$retweet}", '<a href="http://twitter.com/intent/retweet?tweet_id='.$tweet_id.'" target="_blank">Retweet</a>', $feed_string_tmp);
				$feed_string_tmp = str_replace("{\$reply}", '<a href="http://twitter.com/intent/tweet?in_reply_to='.$tweet_id.'" target="_blank">Reply</a>', $feed_string_tmp);
				$feed_string_tmp = str_replace("{\$favorite}", '<a href="http://twitter.com/intent/favorite?tweet_id='.$tweet_id.'" target="_blank">Favorite</a>', $feed_string_tmp);
				$feed_string_tmp = str_replace("{\$retweet_link}", "http://twitter.com/intent/retweet?tweet_id=".$tweet_id, $feed_string_tmp);
				$feed_string_tmp = str_replace("{\$reply_link}", "http://twitter.com/intent/tweet?in_reply_to=".$tweet_id, $feed_string_tmp);
				$feed_string_tmp = str_replace("{\$favorite_link}", "http://twitter.com/intent/favorite?tweet_id=".$tweet_id, $feed_string_tmp);
				$feed_string_tmp = str_replace("{\$tweet_link}", "http://twitter.com/".$options['twitter_username']."/statuses/".$tweet_id, $feed_string_tmp);	

				if(isset($tweet['retweeted_status'])) {
					$profile_url = $tweet["retweeted_status"]["user"]["profile_image_url"]; // 48x48 px
					$profile_73_url = str_replace("_normal", "_bigger", $profile_url); // 73x73 px
					$profile_24_url = str_replace("_normal", "_mini", $profile_url); // 24x24 px
					$profile_original_url = str_replace("_normal", "", $profile_url); // Original size
					$feed_string_tmp = str_replace("{\$profile_image_normal_url}", $profile_url, $feed_string_tmp);
					$feed_string_tmp = str_replace("{\$profile_image_bigger_url}", $profile_73_url, $feed_string_tmp);
					$feed_string_tmp = str_replace("{\$profile_image_mini_url}", $profile_24_url, $feed_string_tmp);
					$feed_string_tmp = str_replace("{\$profile_image_original_url}", $profile_original_url, $feed_string_tmp);
				}
				else {
					$profile_url = $tweet['user']['profile_image_url']; // 48x48 px
					$profile_73_url = str_replace("_normal", "_bigger", $profile_url); // 73x73 px
					$profile_24_url = str_replace("_normal", "_mini", $profile_url); // 24x24 px
					$profile_original_url = str_replace("_normal", "", $profile_url); // Original size
					$feed_string_tmp = str_replace("{\$profile_image_normal_url}", $profile_url, $feed_string_tmp);
					$feed_string_tmp = str_replace("{\$profile_image_bigger_url}", $profile_73_url, $feed_string_tmp);
					$feed_string_tmp = str_replace("{\$profile_image_mini_url}", $profile_24_url, $feed_string_tmp);
					$feed_string_tmp = str_replace("{\$profile_image_original_url}", $profile_original_url, $feed_string_tmp);
				}

				$feed_whole_string .= $feed_string_tmp;
				if($i == $limit - 1) {
					break;
				}
				$i = $i + 1;
			}
				
			$feed_start = "{\$tweets_start}";
			$feed_end = "{\$tweets_end}";
			$start_pos = strrpos($custom_string, $feed_start);
			$end_pos = strrpos($custom_string, $feed_end) + strlen($feed_end);
			$tag_length = $end_pos - $start_pos + 1;

			$feed_string = substr_replace($custom_string, $feed_whole_string, $start_pos, $tag_length);
			$feed_string = str_replace("{\$profile_image}", $image_url, $feed_string);
			$feed_string = str_replace("{\$user_twitter_name}", $twitter_username, $feed_string);
			$feed_string = str_replace("{\$user_real_name}", $twitter_username_real, $feed_string);
			$feed_string = str_replace("{\$url}", $twitter_user_url, $feed_string);
			$feed_string = str_replace("{\$user_description}", $twitter_user_description, $feed_string);
			$feed_string = str_replace("{\$follower_count}", $twitter_follower_count, $feed_string);
			$feed_string = str_replace("{\$friends_count}", $twitter_friends_count, $feed_string);
			$feed_string = str_replace("{\$user_location}", $twitter_user_location, $feed_string);
			$feed_string = str_replace("{\$tweet_location}", $tweet_location, $feed_string);

			$profile_url = $image_url;
			$profile_73_url = str_replace("_normal", "_bigger", $profile_url); // 73x73 px
			$profile_24_url = str_replace("_normal", "_mini", $profile_url); // 24x24 px
			$profile_original_url = str_replace("_normal", "", $profile_url); // Original size
			$feed_string = str_replace("{\$profile_image_normal_url}", $profile_url, $feed_string);
			$feed_string = str_replace("{\$profile_image_bigger_url}", $profile_73_url, $feed_string);
			$feed_string = str_replace("{\$profile_image_mini_url}", $profile_24_url, $feed_string);
			$feed_string = str_replace("{\$profile_image_original_url}", $profile_original_url, $feed_string);

			$result = $feed_string;

			if(isset($tweets['errors'][0]['code'])) {
				$result = $options['before_tweets_html'].'<p>The Twitter feed is currently unavailable or the username does not exist.</p>';
			}
			
			if($options['show_powered_by']) {
				$result = $result.'<p>Powered by <a href="http://wpplugz.is-leet.com">wpPlugz</a></p>';
			}
		
		}
		else {
			if($options['loklak_api']) 
				$result = "Can't reach Loklak API";
			else {
				$result = "Twitter outputted an error: <br />";
				$result .= $tweets['errors'][0]['message'].".";
			}
		}

		echo $result;
		
		$time_format = $options['time_format'];
		$moment_js_time = twitget_convert_from_php_to_momentjs($time_format);
		
		
?>

		<script type="text/javascript">
			jQuery(document).ready(function() { 
				<?php if(strlen($options['language']) > 0) {
				?>
					moment.lang('<?php echo $options['language']; ?>');
				<?php
					}
					else {
				?>
					moment.lang('en');
				<?php
					}
				?>
				<?php if(!empty($tweet_date_array)) { foreach($tweet_date_array as $c => $val) { ?>
				var date_val_<?php echo $c; ?> = <?php echo $val; ?>;
				<?php if($options["show_relative_time"]) { ?>
				var date_<?php echo $c; ?> = moment.unix(date_val_<?php echo $c; ?>).fromNow();
				<?php } else { ?>
				var date_<?php echo $c; ?> = moment.unix(date_val_<?php echo $c; ?>).format("<?php echo $moment_js_time; ?>");
				<?php } ?>
				jQuery(".<?php echo $c; ?>_tweet_date").html(date_<?php echo $c; ?>);
				<?php } } ?>
			});
		</script>

<?php

	}

	/**
	 *
	 * Converts PHP time format to moment.js time format
	 * @parameters String PHP date format
	 *
	*/
	function twitget_convert_from_php_to_momentjs($date_format) {
	
		global $twitget_time_array;
		$time_exploded = explode(" ", $date_format);
		$time_string = "";
		foreach($time_exploded as $time_token) {
			if(strlen($time_token) == 1) {
				if(array_key_exists($time_token, $twitget_time_array)) {
					$time_string .= $twitget_time_array[$time_token];
				}
				else {
					$time_string .= $time_token;
				}
			}
			else {
				if($time_token == "jS") {
					$time_string .= $twitget_time_array["S"]." ";
				}
				else {
					for($i=0; $i<strlen($time_token); $i++) {
						if(array_key_exists($time_token[$i], $twitget_time_array)) {
							$time_string .= $twitget_time_array[$time_token[$i]];
						}
						else {
							$time_string .= $time_token[$i];
						}
					}
				}
			}
			$time_string .= " ";
		}
		
		return $time_string;
	
	}
	
	// Get substring between two strings
	function twitget_get_substring($string, $start, $end) {

		$pos = stripos($string, $start);
		$str = substr($string, $pos);
		$str_two = substr($str, strlen($start));
		$second_pos = stripos($str_two, $end);
		$str_three = substr($str_two, 0, $second_pos);
		$unit = trim($str_three);
		
		return $unit;
	}

	/**
	 * Truncates text.
	 *
	 * Cuts a string to the length of $length and replaces the last characters
	 * with the ending if the text is longer than length.
	 *
	 * @param string  $text String to truncate.
	 * @param integer $length Length of returned string, including ellipsis.
	 * @param string  $ending Ending to be appended to the trimmed string.
	 * @param boolean $exact If false, $text will not be cut mid-word
	 * @param boolean $considerHtml If true, HTML tags would be handled correctly
	 * @return string Trimmed string.
	 */
	function twitget_truncate_tweet($text, $length, $ending, $exact, $considerHtml) {
		if ($considerHtml) {
			// if the plain text is shorter than the maximum length, return the whole text
			if (strlen(preg_replace('/<.*?>/', '', $text)) <= $length) {
				return $text;
			}
			// splits all html-tags to scanable lines
			preg_match_all('/(<.+?>)?([^<>]*)/s', $text, $lines, PREG_SET_ORDER);
			$total_length = strlen($ending);
			$open_tags = array();
			$truncate = '';
			foreach ($lines as $line_matchings) {
				// if there is any html-tag in this line, handle it and add it (uncounted) to the output
				if (!empty($line_matchings[1])) {
					// if it's an "empty element" with or without xhtml-conform closing slash (f.e. <br/>)
					if (preg_match('/^<(\s*.+?\/\s*|\s*(img|br|input|hr|area|base|basefont|col|frame|isindex|link|meta|param)(\s.+?)?)>$/is', $line_matchings[1])) {
						// do nothing
					// if tag is a closing tag (f.e. </b>)
					} else if (preg_match('/^<\s*\/([^\s]+?)\s*>$/s', $line_matchings[1], $tag_matchings)) {
						// delete tag from $open_tags list
						$pos = array_search($tag_matchings[1], $open_tags);
						if ($pos !== false) {
							unset($open_tags[$pos]);
						}
					// if tag is an opening tag (f.e. <b>)
					} else if (preg_match('/^<\s*([^\s>!]+).*?>$/s', $line_matchings[1], $tag_matchings)) {
						// add tag to the beginning of $open_tags list
						array_unshift($open_tags, strtolower($tag_matchings[1]));
					}
					// add html-tag to $truncate'd text
					$truncate .= $line_matchings[1];
				}
				// calculate the length of the plain text part of the line; handle entities as one character
				$content_length = strlen(preg_replace('/&[0-9a-z]{2,8};|&#[0-9]{1,7};|[0-9a-f]{1,6};/i', ' ', $line_matchings[2]));
				if ($total_length+$content_length> $length) {
					// the number of characters which are left
					$left = $length - $total_length;
					$entities_length = 0;
					// search for html entities
					if (preg_match_all('/&[0-9a-z]{2,8};|&#[0-9]{1,7};|[0-9a-f]{1,6};/i', $line_matchings[2], $entities, PREG_OFFSET_CAPTURE)) {
						// calculate the real length of all entities in the legal range
						foreach ($entities[0] as $entity) {
							if ($entity[1]+1-$entities_length <= $left) {
								$left--;
								$entities_length += strlen($entity[0]);
							} else {
								// no more characters left
								break;
							}
						}
					}
					$truncate .= substr($line_matchings[2], 0, $left+$entities_length);
					// maximum lenght is reached, so get off the loop
					break;
				} else {
					$truncate .= $line_matchings[2];
					$total_length += $content_length;
				}
				// if the maximum length is reached, get off the loop
				if($total_length>= $length) {
					break;
				}
			}
		} else {
			if (strlen($text) <= $length) {
				return $text;
			} else {
				$truncate = substr($text, 0, $length - strlen($ending));
			}
		}
		// if the words shouldn't be cut in the middle...
		if (!$exact) {
			// ...search the last occurance of a space...
			$spacepos = strrpos($truncate, ' ');
			if (isset($spacepos)) {
				// ...and cut the text in this position
				$truncate = substr($truncate, 0, $spacepos);
			}
		}
		// add the defined ending to the text
		$truncate .= $ending;
		if($considerHtml) {
			// close all unclosed html-tags
			foreach ($open_tags as $tag) {
				$truncate .= '</' . $tag . '>';
			}
		}
		return $truncate;
	}
	
	function twitget_settings() {
	
		$twitget_settings = get_option('twitget_settings');
		$message = '';
		
		if(isset($_POST['twitget_username']) && is_array($twitget_settings) === true && isset($twitget_settings) && $twitget_settings != null) {
		
			if(wp_verify_nonce($_POST['twitget_nonce'], 'twitget_nonce')) {

				$show_powered = isset($_POST['twitget_show_powered']) ? $_POST['twitget_show_powered'] : null;
				$show_retweets = isset($_POST['twitget_retweets']) ? $_POST['twitget_retweets'] : null;
				$twitget_exclude = isset($_POST['twitget_exclude_replies']) ? $_POST['twitget_exclude_replies'] : null;
				$twitget_relative = isset($_POST['twitget_relative_time']) ? $_POST['twitget_relative_time'] : null;
				$new_link = isset($_POST['twitget_links_new_window']) ? $_POST['twitget_links_new_window'] : null;
				$full_url = isset($_POST['twitget_links_full']) ? $_POST['twitget_links_full'] : null;
				$https_url = isset($_POST['twitget_links_https']) ? $_POST['twitget_links_https'] : null;

				$twitget_settings['twitter_username'] = stripslashes($_POST['twitget_username']);
				$twitget_settings['time_limit'] = (int) $_POST['twitget_refresh'];
				$twitget_settings['number_of_tweets'] = (int) $_POST['twitget_number'];
				$twitget_settings['time_format'] = stripslashes($_POST['twitget_time']);
				$twitget_settings['show_powered_by'] = (isset($show_powered)) ? true : false;
				$twidget_settings['loklak_api'] = (int) ($POST['loklak_api']);
				$twitget_settings['consumer_key'] = stripslashes($_POST['twitget_consumer_key']);
				$twitget_settings['consumer_secret'] = stripslashes($_POST['twitget_consumer_secret']);
				$twitget_settings['user_token'] = stripslashes($_POST['twitget_user_token']);
				$twitget_settings['user_secret'] = stripslashes($_POST['twitget_user_secret']);
				$twitget_settings['twitter_api'] = (int) ($_POST['twitget_api']);
				$twitget_settings['show_retweets'] = (isset($show_retweets)) ? true : false;
				$twitget_settings['exclude_replies'] = (isset($twitget_exclude)) ? true : false;
				$twitget_settings['show_relative_time'] = (isset($twitget_relative)) ? true : false;
				$twitget_settings['custom_string'] = stripslashes(html_entity_decode($_POST['twitget_custom_output']));
				$twitget_settings['links_new_window'] = (isset($new_link)) ? true : false;
				$twitget_settings['language'] = $_POST['twitget_time_language'];
				$twitget_settings['truncate_tweet'] = (isset($_POST['truncate_tweet'])) ? true : false;
				$twitget_settings['truncate_tweet_size'] = intval($_POST['truncate_tweet_size']);
				$twitget_settings['show_full_url'] = (isset($full_url)) ? true : false;
				$twitget_settings['use_https_url'] = (isset($https_url)) ? true : false;
				$message = "Settings updated.";

				update_option('twitget_settings', $twitget_settings);
				
				unset($twitget_settings);
			
			}
			else {
				$message = "Security check failed. You do not have permission to do this.";
			}
			
		}

		$twitget_options = get_option('twitget_settings');

		$twitget_options["time_format"];
		
?>

		<div id="icon-options-general" class="icon32"></div><h2>Twitget Settings</h2>
<?php

		if(strlen($message) > 0) {
		
?>

			<div id="message" class="updated">
				<p><strong><?php echo $message; ?></strong></p>
			</div>

<?php
			
		}

?>
        
                <form method="post" action="">
				<table class="form-table">
					<tr>
						<th scope="row"><img src="<?php echo plugin_dir_url(__FILE__).'twitter.png'; ?>" height="96px" width="96px" /></th>
						<td>
							<p>Thank you for using this plugin. If you like the plugin, you can <a href="http://gum.co/twitget" target="_blank">buy me a cup of coffee</a> :)</p> 
							<p>Visit the official website @ <a href="http://wpplugz.is-leet.com">wpPlugz</a>.</p>
							<p>This plugin uses the <a href="https://github.com/themattharris/tmhOAuth">tmhOAuth</a> library by Matt Harris, <a href="http://momentjs.com/">moment.js</a> by Tim Wood and <a href="https://github.com/J7mbo/twitter-api-php">TwitterAPIExchange</a> by James Mallison.</p>
                        </td>
					</tr>		
					<tr>
						<th scope="row"><label for="twitget_username">Twitter username</label></th>
						<td>
							<input type="text" name="twitget_username" id="twitget_username" value="<?php echo esc_attr($twitget_options['twitter_username']); ?>" />
							<br />
            				<span class="description">Your Twitter username.</span>
						</td>
					</tr>
					<tr>
						<th scope="row"><label for="loklak_api">Loklak Library</label></th>
						<td>
							<input type="checkbox" name="loklak_api" id="loklak_api" value="true" <?php if($twitget_options['loklak_api'] == true) { ?>checked="checked"<?php } ?> />
							<br />
            				<span class="description">Use anonymous API of <a href="http://loklak.org/">loklak.org</a> and get plugin data through loklak (no registration and authentication required).<a href="http://loklak.org/">Find out more</a></span>
						</td>
					</tr>
					<tr>
						<th scope="row"><label for="twitget_api">Twitter Library</label></th>
						<td>
							<select name="twitget_api" id="twitget_api">
								<option value="0" <?php if($twitget_options['twitter_api'] == 0) { ?> selected="selected" <?php } ?>>tmhOAuth</option>
								<option value="1" <?php if($twitget_options['twitter_api'] == 1) { ?> selected="selected" <?php } ?>>Twitter-API-PHP</option>
							</select>
							<br />
            				<span class="description">Set the library you will be using. tmhOAuth is the default library.</span>
						</td>
					</tr>
					<tr>
						<th scope="row"><label for="twitget_data">Twitter API data</label></th>
						<td>
							<table class="form-table">
							<tr>
								<th scope="row"><label for="twitget_consumer_key">Consumer key</label></th>
								<td>
									<input type="text" name="twitget_consumer_key" id="twitget_consumer_key" size="70" value="<?php echo esc_attr($twitget_options['consumer_key']); ?>" /><br />
									<span class="description">Enter your consumer key here.</span>
								</td>
							</tr>
							<tr>
								<th scope="row"><label for="twitget_consumer_secret">Consumer secret</label></th>
								<td>
									<input type="text" name="twitget_consumer_secret" id="twitget_consumer_secret" size="70" value="<?php echo esc_attr($twitget_options['consumer_secret']); ?>" /><br />
									<span class="description">Enter your consumer secret key here.</span>
								</td>
							</tr>
							<tr>
								<th scope="row"><label for="twitget_user_token">Access token</label></th>
								<td>
									<input type="text" name="twitget_user_token" id="twitget_user_token" size="70" value="<?php echo esc_attr($twitget_options['user_token']); ?>" /><br />
									<span class="description">Enter your access token key here.</span>
								</td>
							</tr>
							<tr>
								<th scope="row"><label for="twitget_user_secret">Access token secret</label></th>
								<td>
									<input type="text" name="twitget_user_secret" id="twitget_user_secret" size="70" value="<?php echo esc_attr($twitget_options['user_secret']); ?>" /><br />
									<span class="description">Enter your access token secret key here.</span>
								</td>
							</tr>							
							</table>
							<span class="description">Enter your API keys here. If you don't know how do to that, follow this <a href="http://www.youtube.com/watch?v=noB3P-K-wb4" target="_blank">video tutorial</a>.</span>							
						</td>
					</tr>
					<tr>
						<th scope="row"><label for="twitget_refresh">Twitter feed refresh (in minutes)</label></th>
						<td>
							<input type="text" name="twitget_refresh" id="twitget_refresh" value="<?php echo esc_attr($twitget_options['time_limit']); ?>" />
							<br />
            				<span class="description">In how many minutes does the Twitter feed refresh.</span>
						</td>
					</tr>
					<tr>
						<th scope="row"><label for="twitget_number">Number of tweets</label></th>
						<td>
							<input type="text" name="twitget_number" id="twitget_number" value="<?php echo esc_attr($twitget_options['number_of_tweets']); ?>" />
							<br />
            				<span class="description">How many tweets are shown.</span>
						</td>
					</tr>
					<tr>
						<th scope="row"><label for="twitget_time">Time format</label></th>
						<td>
							<input type="text" name="twitget_time" id="twitget_time" value="<?php echo esc_html($twitget_options['time_format']); ?>" />
							<br />
            				<span class="description">The time format.</span>
						</td>
					</tr>
					<tr>
						<th scope="row"><label for="twitget_time_language">Time format language</label></th>
						<td>
							<select name="twitget_time_language" id="twitget_time_language">
								<option value="-1">--- Select time language ---</option>
								<?php
									global $twitget_language_array;
									foreach($twitget_language_array as $lang => $short) {
									
								?>
										<option value="<?php echo $short; ?>" <?php if($short == $twitget_options['language']) { ?> selected="selected" <?php } ?>><?php echo $lang; ?></option>
								
								<?php
								
									}
								
								?>
							</select>
							<br />
            				<span class="description">The language of the time format.</span>
						</td>
					</tr>
					<tr>
						<th scope="row"><label for="twitget_retweets">Show retweets</label></th>
						<td>
		    	            <input type="checkbox" name="twitget_retweets" id="twitget_retweets" value="true" <?php if($twitget_options['show_retweets'] == true) { ?>checked="checked"<?php } ?> />
							<br />
            				<span class="description">Check this if you want to include retweets in your feed.</span>
						</td>
					</tr>	
					<tr>
						<th scope="row"><label for="twitget_links_full">Show full URLs</label></th>
						<td>
		    	            <input type="checkbox" name="twitget_links_full" id="twitget_links_full" value="true" <?php if($twitget_options['show_full_url'] == true) { ?>checked="checked"<?php } ?> />
							<br />
            				<span class="description">Check this if you want to show full URLs (not t.co).</span>
						</td>
					</tr>
					<tr>
						<th scope="row"><label for="twitget_links_new_window">Open Twitter feed links in new window</label></th>
						<td>
		    	            <input type="checkbox" name="twitget_links_new_window" id="twitget_links_new_window" value="true" <?php if($twitget_options['links_new_window'] == true) { ?>checked="checked"<?php } ?> />
							<br />
            				<span class="description">Check this if you want URLs in Twitter feed to open in a new window.</span>
						</td>
					</tr>
					<tr>
						<th scope="row"><label for="twitget_links_new_window">Use HTTPS whenever possible</label></th>
						<td>
		    	            <input type="checkbox" name="twitget_links_https" id="twitget_links_https" value="true" <?php if($twitget_options['use_https_url'] == true) { ?>checked="checked"<?php } ?> />
							<br />
            				<span class="description">Check this if you want your URLs (images) to be using HTTPS.</span>
						</td>
					</tr>
					<tr>
						<th scope="row"><label for="twitget_exclude_replies">Exclude replies</label></th>
						<td>
		    	            <input type="checkbox" name="twitget_exclude_replies" id="twitget_exclude_replies" value="true" <?php if($twitget_options['exclude_replies'] == true) { ?>checked="checked"<?php } ?> />
							<br />
            				<span class="description">Check this if you want to exclude replies in your feed.</span>
						</td>
					</tr>
					<tr>
						<th scope="row"><label for="truncate_tweet">Truncate Tweets</label></th>
						<td>
		    	            <input type="checkbox" name="truncate_tweet" id="truncate_tweet" value="true" <?php if($twitget_options['truncate_tweet'] == true) { ?>checked="checked"<?php } ?> />
							<br />
            				<span class="description">Check this if you want to truncate the size of tweets (set number of characters below).</span>
						</td>
					</tr>		
					<tr>
						<th scope="row"><label for="truncate_tweet_size">Truncate Tweet size</label></th>
						<td>
							<input type="text" name="truncate_tweet_size" id="truncate_tweet_size" value="<?php echo esc_html($twitget_options['truncate_tweet_size']); ?>" />
							<br />
            				<span class="description">Limit the number of outputted characters of a tweet.</span>
						</td>
					</tr>
					<tr>
						<th scope="row"><label for="twitget_relative_time">Show relative time</label></th>
						<td>
		    	            <input type="checkbox" name="twitget_relative_time" id="twitget_relative_time" value="true" <?php if($twitget_options['show_relative_time'] == true) { ?>checked="checked"<?php } ?> />
							<br />
            				<span class="description">Show relative time of tweets (for instance 5 minutes ago).</span>
						</td>
					</tr>		
					<tr>
						<th scope="row"><label for="twitget_show_powered">Show powered by message</label></th>
						<td>
		    	            <input type="checkbox" name="twitget_show_powered" id="twitget_show_powered" value="true" <?php if($twitget_options['show_powered_by'] == true) { ?>checked="checked"<?php } ?> />
							<br />
            				<span class="description">Show powered by message, if you decide not to show it, please consider a <a href="https://www.paypal.com/cgi-bin/webscr?cmd=_s-xclick&hosted_button_id=SKMW3BAC8KE52" target="_blank">donation</a>.</span>
						</td>
					</tr>		
				</table>

				<h3>Advanced options</h3>
				<table class="form-table">
					<tr>
						<th scope="row"><label for="twitget_custom_output">Use custom output</label></th>
						<td>
							<textarea rows="10" cols="100" name="twitget_custom_output" id="twitget_custom_output" /><?php echo htmlentities($twitget_options['custom_string']); ?></textarea><br />
							<span class="description">
							<p>You can enter custom HTML in the box bellow and achieve the output you want.</p>
							<p>When marking the output of your twitter feed you must include {$tweets_start} at the start of your twitter feed and {$tweets_end} in the end.</p> 

							<strong><p>AVAILABLE VARIABLES</p></strong>
							<strong><p>Used inside of loop</p></strong>
							{$tweet_text} - the text of the tweet<br />
							{$tweet_time} - the time of the tweet<br />
							{$tweet_location} - the location of the tweet (example: Budapest)<br />
							{$retweet} - outputs a ready retweet link with the text Retweet, opens in new tab<br />
							{$reply} - outputs a ready reply link with the text Reply, opens in new tab<br />
							{$favorite} - outputs a favorite link with the text Favorite, opens in new tab<br />
							{$retweet_link} - returns URL of retweet link<br />
							{$reply_link} - returns URL of reply link<br />
							{$favorite_link} - returns URL of favorite link<br />
							{$tweet_link} - returns URL of tweet<br />
							<strong><p>Used outside or inside of loop</p></strong>
							{$profile_image} - the url to the profile image of the user<br />
							{$user_real_name} - the real name of the user<br />
							{$user_twitter_name} - username of the twitter user<br />
							{$url} - website url of the user<br />
							{$user_description} - description of the user<br />
							{$user_location} - user location<br />
							{$follower_count} - number of followers<br />
							{$friends_count} - number of friends<br />
							{$profile_image_normal_url} - return URL of tweet profile image - 48x48 px size (if retweet in loop, returns original tweet profile image)<br />
							{$profile_image_bigger_url} - return URL of tweet profile image - 73x73 px size (if retweet in loop, returns original tweet profile image)<br />
							{$profile_image_mini_url} - return URL of tweet profile image - 24x24px size (if retweet in loop, returns original tweet profile image)<br />
							{$profile_image_original_url} - return URL of tweet profile image - original size (if retweet in loop, returns original tweet profile image)<br /><br />
							</span>
						</td>
					</tr>		
				</table>
				<?php $nonce = wp_create_nonce("twitget_nonce"); ?>
				<input type="hidden" name="twitget_nonce" id="twitget_nonce" value="<?php echo $nonce; ?>">
				<p><input type="submit" name="Submit" class="button-primary" value="<?php esc_attr_e('Update options') ?>" /></p>
				</form>


<?php

	}
		
	// Here, the widget code begins
	class simple_tweet_widget extends WP_Widget {
		
		function __construct() {
			$widget_ops = array('classname' => 'simple_tweet_widget', 'description' => 'Display your recent tweets.' );			
			parent::__construct('simple_tweet_widget', 'Twitget', $widget_ops);
		}
		
		function widget($args, $instance) {
			
			extract($args);
			$title = apply_filters('widget_title', $instance['title']);
			
			echo $before_widget;

			if($title) {
				echo $before_title . $title . $after_title;
			}
			
			// The widget code and the widgeet output
			
			show_recent_tweets();
			
			// End of widget output
			
			echo $after_widget;
			
		}
		
	    function update($new_instance, $old_instance) {		
			$instance = $old_instance;
			$instance['title'] = strip_tags($new_instance['title']);
	        return $instance;
    	}
		
		function form($instance) {	

        	$title = esc_attr($instance['title']);
		
?>

			<p>
				<label for="<?php echo $this->get_field_id('title'); ?>">
					<?php _e('Title: '); ?>
	            </label> 
				<input class="widefat" id="<?php echo $this->get_field_id('title'); ?>" name="<?php echo $this->get_field_name('title'); ?>" type="text" value="<?php echo $title; ?>" />
			</p>

<?php 

		}

	}
	
?>
