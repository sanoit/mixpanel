<?php
/*
Plugin Name: Mixpanel Plugin
Plugin URI: http://sanoagency.com/mixpanel-for-wordpress/
Description: Provides Mixpanel PHP integration for Wordpress.
Version: 1.3.1
Author: Sano Agency
Author URI: http://sanoagency.com/
License: GPL2
*/

if(!class_exists('MetricsTracker'))
{
	class MetricsTracker {
	    public $token;
	    public $host = 'http://api.mixpanel.com/';
	    
	    public function __construct($token_string) {
	        $this->token = $token_string;
	    }
	    
	    function track($event, $properties=array()) {
					global $current_user; 
					global $mix_panel_options;
					
	        $params = array(
	            'event' => $event,
	            'properties' => $properties
	            );
	    		if($mix_panel_options['chk_track_name'])
	    		{
		        if(is_user_logged_in()) 
		        {
							get_currentuserinfo(); 												
							if(is_object($current_user) && isset($current_user->user_login))
							{
			            $params['properties']['name_tag'] = $current_user->user_login;						
							}
		        }
		        else
		        {
			            $params['properties']['name_tag'] = 'guest';						
		        }
	    		}
	    			
	        if (!isset($params['properties']['token'])){
	            $params['properties']['token'] = $this->token;
	        }
	        
	        if (!isset($params['properties']['ip'])){
	            $params['properties']['ip'] = $_SERVER['REMOTE_ADDR'];
	        }
	        
	        if (!isset($params['properties']['session'])){
	            $params['properties']['session'] = session_id();
	        }

	        if (!isset($params['properties']['date'])){
	            $params['properties']['Date'] = get_the_date('l', '','',false);
	        }
	        
	        
	        $url = $this->host . 'track/?data=' . base64_encode(json_encode($params));
	        
	        //you still need to run as a background process
	        exec("curl '" . $url . "' >/dev/null 2>&1 &"); 
	    }
	}
}

global $metrics;
global $mix_panel_options;
$mix_panel_options = get_option('mixpanel_options');
if(empty($mix_panel_options)) $mix_panel_options['MIXPANEL_TOKEN'] = 'TMP';

$metrics = new MetricsTracker($mix_panel_options['MIXPANEL_TOKEN']);

function mixpanel_head()
{
	global $wp_query;
	global $metrics;
	global $current_user; 
	global $mix_panel_options;
	
	$page_object = $wp_query->get_queried_object();
	$post_id     = $wp_query->get_queried_object_id();

	if(!$mix_panel_options['chk_active']) return;

	if($mix_panel_options['chk_track_pages'] && (is_home() || is_front_page()))
	{	
		// track home page hit
		$metrics->track('Viewed Homepage', 
		                    array());
	}
	else if($mix_panel_options['chk_track_categories'] && (is_category() || is_archive()))
	{
		$metrics->track('Viewed Category',
			array('Post Category'=>wp_title('',false,false), 'url'=>$_SERVER['REQUEST_URI'], 'Post ID'=>$post_id));		
	}
	else if($mix_panel_options['chk_track_pages'] && is_page())
	{
		$author = get_userdata($page_object->post_author);
		// track page hit
		$metrics->track('Viewed Page',
			array('Title'=>wp_title('',false,false), 'Author'=>$author->user_login, 'url'=>$_SERVER['REQUEST_URI'], 'Page ID'=>$post_id, 'Published Date'=>$page_object->post_date));
	}
	else if($mix_panel_options['chk_track_posts'] && is_single())
	{
		// track post hit
		$category = get_the_category($post_id);
		$tags = get_the_tags($post_id);
		$tag_array = array();
		foreach($tags as $tag)
		{
			$tag_array[]= $tag->name;
		}
		
		$category_array = array();
		foreach($category as $cat)
		{
			$category_array[] = $cat->cat_name;
		}
				
		$author = get_userdata($page_object->post_author);
		
		$metrics->track('Viewed Post',
			array('Post Title'=>wp_title('',false,false), 'Post Category(s)'=>(count($category) ? $category_array : (is_object($page_object->labels) ? $page_object->labels->singular_name : 'Unknown')), 'Post Author'=>$author->user_login, 'Post Tag(s)'=>$tag_array, 'url'=>$_SERVER['REQUEST_URI'], 'post_id'=>$post_id, 'Published Date'=>$page_object->post_date, 'Comment Count'=>$page_object->comment_count));
	}
	else if($mix_panel_options['chk_track_pages'])
	{
		$metrics->track('Viewed Other',
			array('Title'=>wp_title('',false,false), 'Type'=>(is_object($page_object->labels) ? $page_object->labels->singular_name : 'Unknown'), 'url'=>$_SERVER['REQUEST_URI'], 'post_id'=>$post_id));
	}
	echo "<!-- Mixpanel -->
	<script type=\"text/javascript\" src=\"".get_bloginfo('wpurl')."/wp-content/plugins/mixpanel/waypoints.min.js\"></script>
<script type=\"text/javascript\">
(function(d,c)
{var a,b,g,e;a=d.createElement(\"script\");a.type=\"text/javascript\";
a.async=!0;a.src=(\"https:\"===d.location.protocol?\"https:\":\"http:\")
+'//api.mixpanel.com/site_media/js/api/mixpanel.2.js';
b=d.getElementsByTagName(\"script\")[0];b.parentNode.insertBefore(a,b);c._i=[];c.init=function(a,d,f){
var b=c;\"undefined\"!==typeof f?b=c[f]=[]:f=\"mixpanel\";
g=\"disable track track_pageview track_links track_forms register register_once unregister identify name_tag set_config\".split(\" \");
for(e=0;e<g.length;e++)
(function(a){b[a]=function(){b.push([a].concat(Array.prototype.slice.call(arguments,0)))}})(g[e]);c._i.push([a,d,f])};window.mixpanel=c})(document,[]);
mixpanel.init(\"".$metrics->token."\");
";
				
if($mix_panel_options['chk_track_name'] && is_object($current_user)) echo 'mixpanel.name_tag("'.$current_user->user_login.'");';
if($mix_panel_options['chk_track_paragraphs'])
{
echo "
	jQuery(document).ready(function() {
		
	jQuery(\".entry p\").each(function (i, el) {
		jQuery(el).waypoint(function (event, direction) {
			if (direction === \"down\") {
				mixpanel.track(\"View Paragraph\", {url: window.location.href, Title: \"".trim(wp_title('',false,false))."\", index: i});
			}
		}, {offset: 'bottom-in-view'});
	});
	});";
}
if($mix_panel_options['chk_track_scroll'])
{
echo "
	jQuery(document).ready(function() {
		opts = {
				offset: '75%',
				scroll: '25%'
			};
		jQuery(\"#content\").waypoint(function(event, direction) {
			jQuery(\"#content\").waypoint('remove');
      // 25% the way down the page
			mixpanel.track(\"Scroll Page\", {url: window.location.href, Title: \"".trim(wp_title('',false,false))."\", percent: opts['scroll']});
			if(opts['offset'] == '75%')
			{
				opts = { offset: '50%', scroll: '50%' };
				jQuery(\"#content\").waypoint(opts);
			}
			else if(opts['offset'] == '50%')
			{
				opts = { offset: '25%', scroll: '75%' };
				jQuery(\"#content\").waypoint(opts);
			}
			else if(opts['offset'] == '25%')
			{
				opts = { offset: '0%', scroll: '100%' };
				jQuery(\"#content\").waypoint(opts);
			}
			else
			{
				jQuery(\"#content\").waypoint('remove');
			}
		}, opts );
	});";
}
echo "
</script>
<!-- /Mixpanel -->";
}

add_action('wp_head', 'mixpanel_head');

function mixpanel_comment($comment_ID, $approved)
{
		// record approved comments count
		if((is_bool($approved) ? $approved : $approved == 'approve') && $comment = get_comment($comment_ID))
		{						
			$metrics->track('comment_count',
				array('Comment Count'=>count(get_approved_comments($comment->comment_post_ID)), 'Author'=>$comment->comment_author_email, 'post_id'=>$comment->comment_post_ID, 'Title'=>get_the_title($comment->comment_post_ID), 'url'=>get_permalink($comment->comment_post_ID)));
		}
}

// call mixpanel comment when a comment is either posted (if pre-approved author), or when it is approved by an admin
if($mix_panel_options['chk_track_comments'])
{
	add_action('comment_post', 'mixpanel_comment');
	add_action('wp_set_comment_status', 'mixpanel_comment');
}



// ------------------------------------------------------------------------
// REQUIRE MINIMUM VERSION OF WORDPRESS:                                               
// ------------------------------------------------------------------------
// THIS IS USEFUL IF YOU REQUIRE A MINIMUM VERSION OF WORDPRESS TO RUN YOUR
// PLUGIN. IN THIS PLUGIN THE WP_EDITOR() FUNCTION REQUIRES WORDPRESS 3.2 
// OR ABOVE. ANYTHING LESS SHOWS A WARNING AND THE PLUGIN IS DEACTIVATED.                    
// ------------------------------------------------------------------------

function requires_wordpress_version() {
	global $wp_version;
	$plugin = plugin_basename( __FILE__ );
	$plugin_data = get_plugin_data( __FILE__, false );

	if ( version_compare($wp_version, "3.2", "<" ) ) {
		if( is_plugin_active($plugin) ) {
			deactivate_plugins( $plugin );
			wp_die( "'".$plugin_data['Name']."' requires WordPress 3.3 or higher, and has been deactivated! Please upgrade WordPress and try again.<br /><br />Back to <a href='".admin_url()."'>WordPress admin</a>." );
		}
	}
}
add_action( 'admin_init', 'requires_wordpress_version' );

// Set-up Action and Filter Hooks
register_activation_hook(__FILE__, 'mixpanel_add_defaults');
register_uninstall_hook(__FILE__, 'mixpanel_delete_plugin_options');
add_action('admin_init', 'mixpanel_init' );
add_action('admin_menu', 'mixpanel_add_options_page');
add_filter( 'plugin_action_links', 'mixpanel_plugin_action_links', 10, 2 );

// Delete options table entries ONLY when plugin deactivated AND deleted
function mixpanel_delete_plugin_options() {
	delete_option('mixpanel_options');
}

// Define default option settings
function mixpanel_add_defaults() {
	$tmp = get_option('mixpanel_options');
    if(($tmp['chk_default_options_db']=='1')||(!is_array($tmp))) {
		delete_option('mixpanel_options'); 
		$arr = array(	"MIXPANEL_TOKEN" => "Your mixpanel.com API Token", "chk_track_name" => 0, "chk_track_posts" => 1, "chk_track_comments" => 1, "chk_track_pages" => 1, "chk_track_categories" => 0, "chk_track_fb" => 0, "chk_track_scroll" => 0, 'chk_track_paragraphs' => 0, 'chk_active' => 1);
		update_option('mixpanel_options', $arr);
	}
}

// ------------------------------------------------------------------------------
// CALLBACK FUNCTION FOR: add_action('admin_init', 'mixpanel_init' )
// ------------------------------------------------------------------------------
// THIS FUNCTION RUNS WHEN THE 'admin_init' HOOK FIRES, AND REGISTERS YOUR PLUGIN
// SETTING WITH THE WORDPRESS SETTINGS API. YOU WON'T BE ABLE TO USE THE SETTINGS
// API UNTIL YOU DO.
// ------------------------------------------------------------------------------

// Init plugin options to white list our options
function mixpanel_init(){
	register_setting( 'mixpanel_plugin_options', 'mixpanel_options', 'mixpanel_validate_options' );
}

// ------------------------------------------------------------------------------
// CALLBACK FUNCTION FOR: add_action('admin_menu', 'mixpanel_add_options_page');
// ------------------------------------------------------------------------------
// THIS FUNCTION RUNS WHEN THE 'admin_menu' HOOK FIRES, AND ADDS A NEW OPTIONS
// PAGE FOR YOUR PLUGIN TO THE SETTINGS MENU.
// ------------------------------------------------------------------------------

// Add menu page
function mixpanel_add_options_page() {
	add_options_page('Mixpanel Options Page', 'Mixpanel by Sano', 'manage_options', __FILE__, 'mixpanel_render_form');
}

// ------------------------------------------------------------------------------
// CALLBACK FUNCTION SPECIFIED IN: add_options_page()
// ------------------------------------------------------------------------------
// THIS FUNCTION IS SPECIFIED IN add_options_page() AS THE CALLBACK FUNCTION THAT
// ACTUALLY RENDER THE PLUGIN OPTIONS FORM AS A SUB-MENU UNDER THE EXISTING
// SETTINGS ADMIN MENU.
// ------------------------------------------------------------------------------

// Render the Plugin options form
function mixpanel_render_form() {
	?>
	<div class="wrap">
		
		<!-- Display Plugin Icon, Header, and Description -->
		<div class="icon32" id="icon-options-general"><br></div>
		<h2>Mixpanel Options</h2>
		<p>Please configure <a href="https://mixpanel.com/" target="_blank">Mixpanel</a> for use on this site.</p>

		<!-- Beginning of the Plugin Options Form -->
		<form method="post" action="options.php">
			<?php settings_fields('mixpanel_plugin_options'); ?>
			<?php $options = get_option('mixpanel_options'); ?>

			<!-- Table Structure Containing Form Controls -->
			<!-- Each Plugin Option Defined on a New Table Row -->
			<table class="form-table">
				<!-- Textbox Control -->
				<tr valign="top" style="border-top:#dddddd 1px solid;">
					<th scope="row">Activate Tracking</th>
					<td>
						<label><input name="mixpanel_options[chk_active]" type="checkbox" value="1" <?php if (isset($options['chk_active'])) { checked('1', $options['chk_active']); } ?> /> enable Mixpanel tracking on site.</label>
					</td>
				</tr>
				<tr>
					<th scope="row">Mixpanel Token</th>
					<td>
						<input type="text" size="57" name="mixpanel_options[MIXPANEL_TOKEN]" value="<?php echo $options['MIXPANEL_TOKEN']; ?>" />
					</td>
				</tr>
				<tr valign="top" style="border-top:#dddddd 1px solid;">
					<th scope="row">Report Name</th>
					<td>
						<label><input type="checkbox" name="mixpanel_options[chk_track_name]" value="1"  <?php if (isset($options['chk_track_name'])) { checked('1', $options['chk_track_name']); } ?> /> include Wordpress user name</label>
					</td>
				</tr>
				<tr valign="top" style="border-top:#dddddd 1px solid;">
					<th scope="row">Track Page Views</th>
					<td>
						<label><input name="mixpanel_options[chk_track_pages]" type="checkbox" value="1" <?php if (isset($options['chk_track_pages'])) { checked('1', $options['chk_track_pages']); } ?> /> track page views.</label>
					</td>
				</tr>
				<tr valign="top">
					<th scope="row">Track Category Views</th>
					<td>
						<label><input name="mixpanel_options[chk_track_categories]" type="checkbox" value="1" <?php if (isset($options['chk_track_categories'])) { checked('1', $options['chk_track_categories']); } ?> /> track category/archive views.</label>
					</td>
				</tr>
				<tr valign="top">
					<th scope="row">Track Post Views</th>
					<td>
						<label><input name="mixpanel_options[chk_track_posts]" type="checkbox" value="1" <?php if (isset($options['chk_track_posts'])) { checked('1', $options['chk_track_posts']); } ?> /> track post views.</label>
					</td>
				</tr>
				<tr valign="top">
					<th scope="row">Track Comments</th>
					<td>
						<label><input name="mixpanel_options[chk_track_comments]" type="checkbox" value="1" <?php if (isset($options['chk_track_comments'])) { checked('1', $options['chk_track_comments']); } ?> /> track number of comments.</label>
					</td>
				</tr>
				<tr valign="top" style="border-top:#dddddd 1px solid;">
					<th scope="row">Track Paragraph Views</th>
					<td>
						<label><input name="mixpanel_options[chk_track_paragraphs]" type="checkbox" value="1" <?php if (isset($options['chk_track_paragraphs'])) { checked('1', $options['chk_track_paragraphs']); } ?> /> track paragraphs as users view them.</label>
					</td>
				</tr>
				<tr valign="top">
					<th scope="row">Track Content Scrolling</th>
					<td>
						<label><input name="mixpanel_options[chk_track_scroll]" type="checkbox" value="1" <?php if (isset($options['chk_track_scroll'])) { checked('1', $options['chk_track_scroll']); } ?> /> track page scroll percentage.</label>
					</td>
				</tr>
				<tr><td colspan="2"><div style="margin-top:10px;"></div></td></tr>
				<tr valign="top" style="border-top:#dddddd 1px solid;">
					<th scope="row">Database Options</th>
					<td>
						<label><input name="mixpanel_options[chk_default_options_db]" type="checkbox" value="1" <?php if (isset($options['chk_default_options_db'])) { checked('1', $options['chk_default_options_db']); } ?> /> Restore defaults upon plugin deactivation/reactivation</label>
						<br /><span style="color:#666666;margin-left:2px;">Only check this if you want to reset plugin settings upon Plugin reactivation</span>
					</td>
				</tr>
			</table>
			<p class="submit">
			<input type="submit" class="button-primary" value="<?php _e('Save Changes') ?>" />
			</p>
		</form>
		<p>Brought to you by <a href="http://www.sanoagency.com/" target="_blank">Sano Agency</a>. Options based on <a href="http://wordpress.org/extend/plugins/plugin-options-starter-kit/"  target="_blank">Plugin Options Starter Kit</a>.</p>
		</div>
	<?php	
}

// Sanitize and validate input. Accepts an array, return a sanitized array.
function mixpanel_validate_options($input) {
	 // strip html from textboxes
	$input['MIXPANEL_TOKEN'] =  wp_filter_nohtml_kses($input['MIXPANEL_TOKEN']); // Sanitize textbox input (strip html tags, and escape characters)
	return $input;
}

// Display a Settings link on the main Plugins page
function mixpanel_plugin_action_links( $links, $file ) {

	if ( $file == plugin_basename( __FILE__ ) ) {
		$mixpanel_links = '<a href="'.get_admin_url().'options-general.php?page=mixpanel/mixpanel.php">'.__('Mixpanel Settings').'</a>';
		// make the 'Settings' link appear first
		array_unshift( $links, $mixpanel_links );
	}

	return $links;
}

?>