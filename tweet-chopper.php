<?php

/*
Plugin Name: Tweet Chopper
Plugin URI: http://www.kevinchoppin.co.uk/tweet-chopper.htm
Description: A very simple and basic widget that shows tweets for multiple authors and trends.
Version: 1.0
Author: Kevin Choppin
Author URI: http://www.kevinchoppin.co.uk
*/

/*  Copyright 2009  Kevin Choppin  (email : tweetchopper@kevinchoppin.co.uk)

    This program is free software; you can redistribute it and/or modify
    it under the terms of the GNU General Public License as published by
    the Free Software Foundation; either version 2 of the License, or
    (at your option) any later version.

    This program is distributed in the hope that it will be useful,
    but WITHOUT ANY WARRANTY; without even the implied warranty of
    MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
    GNU General Public License for more details.

    You should have received a copy of the GNU General Public License
    along with this program; if not, write to the Free Software
    Foundation, Inc., 51 Franklin St, Fifth Floor, Boston, MA  02110-1301  USA
*/

// setup plugin

function tweet_chopper_install(){
   global $wpdb;

   $table_name = $wpdb->prefix . "tweet_chopper";

   if($wpdb->get_var("show tables like '$table_name'") != $table_name) {
      
      $sql = "CREATE TABLE " . $table_name . " (
	  id mediumint(9) NOT NULL AUTO_INCREMENT,
	  user VARCHAR(55) NOT NULL,
	  tweet VARCHAR(255) NOT NULL,
	  userImage VARCHAR(255) NOT NULL,
	  tweetDate datetime NOT NULL,
	  UNIQUE KEY id (id)
	);";

      require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
      dbDelta($sql);
 
   }

      add_option("tweet_chopper_ver", '1.0');
      add_option("tweet_chopper_updated", '');
      add_option("tweet_chopper_tags", '');
      add_option("tweet_chopper_limit", '3');

}

register_activation_hook(__FILE__,'tweet_chopper_install');

// register settings page

add_action('admin_menu', 'tweet_chopper_admin_menu');
function tweet_chopper_admin_menu(){
add_options_page('Tweet Chopper Settings', 'Tweet Chopper', 8, __FILE__, 'tweet_chopper_admin_page');
}

// register css

add_action('wp_head', 'tweet_chopper_css');
function tweet_chopper_css() {
echo '<link type="text/css" rel="stylesheet" href="'.plugins_url('tweet-chopper/tweet-chopper.css').'" />'."\n";
}

// register widget

add_action("widgets_init", "tweet_chopper_load_widget");
function tweet_chopper_load_widget(){
register_sidebar_widget("Tweet Chopper", "tweet_chopper_widget");
}

function tweet_chopper_widget($args = array()) {
global $wpdb;
$table_name = $wpdb->prefix . "tweet_chopper";
require_once(ABSPATH . 'wp-admin/includes/upgrade.php');

$last_update = strtotime(get_option('tweet_chopper_updated'));
$one_minute_ago = mktime(date("H"), date("i"), date("s")-30, date("m"), date("d"),   date("Y"));

if($last_update <= $one_minute_ago){

// get latest tweets

$tags = str_replace("@", "from:", get_option('tweet_chopper_tags'));
$tags = str_replace(" ", "", $tags);
$tags = explode(",", $tags);
$tags = implode(' OR ', $tags);
$qs = urlencode($tags);

        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, "http://search.twitter.com/search.atom?q=".$qs."&lang=en");
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
        $output = curl_exec($ch);
        curl_close($ch);

  $objDOM = new DOMDocument();
  $objDOM->loadXML($output);

  $entryArr = $objDOM->getElementsByTagName("entry");
  $i = 0;

$sqlArr = array();

foreach($entryArr AS $entryVal){

$titleArr = $entryArr->item($i)->getElementsByTagName("title");
$tweetStatus = str_replace("\n", "", $titleArr->item(0)->nodeValue);

$dateArr = $entryArr->item($i)->getElementsByTagName("published");
$published = strtotime($dateArr->item(0)->nodeValue);
$tweetDate = date("Y-m-d H:i:s", $published);

$linksArr = $entryArr->item($i)->getElementsByTagName("link");
$j = 0;

	foreach($linksArr AS $linkVal){

		if($linksArr->item($j)->getAttribute('rel') == 'image'){

		$tweetImage = $linksArr->item($j)->getAttribute('href');

		}

	$j++;

	}

$userArr = $entryArr->item($i)->getElementsByTagName("name");
$tweetUser = explode(" ", $userArr->item(0)->nodeValue);

if($published >= $last_update){

$sqlArr[]= "('".$wpdb->escape($tweetUser[0])."', '".$wpdb->escape(htmlentities($tweetStatus))."', '".$wpdb->escape($tweetImage)."', '".$wpdb->escape($tweetDate)."')";

}

$i++;

}

// insert tweets

if(count($sqlArr) > 0){

      $insert = "INSERT INTO " . $table_name .
            " (user, tweet, userImage, tweetDate) " .
            "VALUES ".implode(",\n",$sqlArr);
      $results = $wpdb->query( $insert );

update_option('tweet_chopper_updated',date("Y-m-d H:i:s"));

}

}

// get latest tweets

$latest_tweets = $wpdb->get_results("SELECT user, tweet, userImage, tweetDate FROM $table_name
	ORDER BY tweetDate DESC
	LIMIT 0,".get_option('tweet_chopper_limit'));

echo $args['before_widget'].'<div id="tweet_chopper"><h2><a href="http://twitter.com" target="_blank">Twitter</a></h2>';

foreach ($latest_tweets as $tweets) {

$tweetStatus = ereg_replace("[[:alpha:]]+://[^<>[:space:]]+[[:alnum:]/]", "<a href=\"\\0\" target=\"_blank\">\\0</a>", $tweets->tweet);
$tweetDateUnix = mktime() - strtotime($tweets->tweetDate);
$tweetDate = ($tweetDateUnix > 60) ? round($tweetDateUnix / 60) : $tweetDateUnix;
$plural = ($tweetDate != '1') ? 's' : '' ;
$tweetDate = ($tweetDateUnix > 60) ? $tweetDate.' minute'.$plural.' ago' : $tweetDate.' second'.$plural.' ago';

echo '<div class="tweet_chopper_tweet">
	<div class="tweet_chopper_left">
	<a href="http://twitter.com/'.$tweets->user.'" target="_blank" title="Follow '.$tweets->user.'" class="tc_avatar"><img src="'.$tweets->userImage.'" alt="'.$tweets->user.'" width="48" height="48" /></a>
	</div>
	<p><a href="http://twitter.com/'.$tweets->user.'" target="_blank" title="Follow '.$tweets->user.'"><strong>'.$tweets->user.':</strong></a> '.$tweetStatus.'</p>
	<div class="tweet_chopper_date"><em>'.$tweetDate.'</em></div>
	</div>';

}

echo '</div>'.$args['after_widget'];

}

function tweet_chopper_admin_page(){ 

if($_POST['action'] == 'update'){

$_POST['tweet_chopper_tags'] = (substr($_POST['tweet_chopper_tags'], -1) == ',') ? substr($_POST['tweet_chopper_tags'], 0, -1) : $_POST['tweet_chopper_tags'] ;

update_option('tweet_chopper_tags',$_POST['tweet_chopper_tags']);
update_option('tweet_chopper_limit',$_POST['tweet_chopper_limit']);

}

$limit = get_option('tweet_chopper_limit');

?>

<div class="wrap">
<h2>Tweet Chopper</h2>

<a href="http://www.kevinchoppin.co.uk/tweetchopper.htm" target="_blank">Tweet Chopper Homepage</a>

<img src="<?php echo plugins_url('tweet-chopper/tweet-chopper.png'); ?>" alt="Tweet Chopper" width="100" style="float: right;" />

<p>Enter your screen names (@screen_name) and hashtags (#trend) seperated with commas (,) below.</p>

<form method="post" action="<?php echo str_replace( '%7E', '~', $_SERVER['REQUEST_URI']); ?>">
<?php wp_nonce_field('update-options'); ?>

<table class="form-table">

<tr valign="top">
<th scope="row"><label for="tweet_chopper_tags">Tags:</label></th>
<td><textarea name="tweet_chopper_tags" id="tweet_chopper_tags" rows="5" cols="35"><?php echo get_option('tweet_chopper_tags'); ?></textarea>
<p>eg; @tweetchopper, #wordpress, #twitter</p>
</td>
</tr>
 
<tr valign="top">
<th scope="row"><label for="tweet_chopper_limit">Tweet limit:</label></th>
<td><select name="tweet_chopper_limit" id="tweet_chopper_limit">
<option value="1"<?php if($limit == '1'){ echo ' selected="selected"'; } ?>>1</option>
<option value="2"<?php if($limit == '2'){ echo ' selected="selected"'; } ?>>2</option>
<option value="3"<?php if($limit == '3'){ echo ' selected="selected"'; } ?>>3</option>
<option value="4"<?php if($limit == '4'){ echo ' selected="selected"'; } ?>>4</option>
<option value="5"<?php if($limit == '5'){ echo ' selected="selected"'; } ?>>5</option>
<option value="6"<?php if($limit == '6'){ echo ' selected="selected"'; } ?>>6</option>
<option value="7"<?php if($limit == '7'){ echo ' selected="selected"'; } ?>>7</option>
<option value="8"<?php if($limit == '8'){ echo ' selected="selected"'; } ?>>8</option>
<option value="9"<?php if($limit == '9'){ echo ' selected="selected"'; } ?>>9</option>
<option value="10"<?php if($limit == '10'){ echo ' selected="selected"'; } ?>>10</option>
</select>
</td>
</tr>

</table>

<input type="hidden" name="action" value="update" />
<input type="hidden" name="page_options" value="tweet_chopper_tags,tweet_chopper_limit" />

<p class="submit">
<input type="submit" class="button-primary" value="<?php _e('Save Changes') ?>" />
</p>

</form>
</div>

<?php } ?>