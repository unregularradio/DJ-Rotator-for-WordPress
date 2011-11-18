<?php
/*
Plugin Name: DJ Rotator for WordPress
Plugin URI: http://gregrickaby.com/2011/11/dj-rotator-for-wordpress
Description: Easily create a Jock Rotator to display which DJ is currently on-air. You can upload/delete jocks via the options panel. <strong>Display the Jock Rotator by using either the <code>jrwp();</code> template tag or a <code>[jrwp]</code> shortcode in your theme. </strong>
Author: Greg Rickaby
Version: 0.0.1
Author URI: http://gregrickaby.com
Big thanks to Nathan Rice and his WP-Cycle Plugin which got me started in the right direction. I love the GPL.
*/


/**
 * Defines the default variables that will be used throughout the plugin
 * @since 0.0.1
 */
$jrwp_defaults = apply_filters( 'jrwp_defaults', array(
	'header_text' => 'On-Air Now',
	'img_width' => 250,
	'img_height' => 125,
	'div' => 'jock-rotator',
	'header_class' => 'jock-header',
	'image_class' => 'jock-image',
	'desc_class' => 'jock-desc',
	'time_zone' => 'America/Chicago'
));


/**
 * Pull the settings from the DB
 * @since 0.0.1
 */
$jrwp_settings = get_option( 'jrwp_settings' );
$jrwp_images = get_option( 'jrwp_images' );
$jrwp_settings = wp_parse_args($jrwp_settings, $jrwp_defaults);


/**
 * The following section registers settings, adds a link to the options page, and a link
 * on the plugin page to "settings"
 * @since 0.0.1
 */
add_action( 'admin_init', 'jrwp_register_settings' );
function jrwp_register_settings() {
	register_setting( 'jrwp_images', 'jrwp_images', 'jrwp_images_validate' );
	register_setting( 'jrwp_settings', 'jrwp_settings', 'jrwp_settings_validate' );
}

add_action( 'admin_menu', 'add_jrwp_menu' );
function add_jrwp_menu() {
		add_submenu_page( 'options-general.php', 'Jock Roator', 'Jock Rotator', 8, 'jock-rotator', 'jrwp_admin_page' );
}

add_filter( 'plugin_action_links_' . plugin_basename(__FILE__) , 'jrwp_plugin_action_links' );
function jrwp_plugin_action_links($links) {
	$jrwp_settings_link = sprintf( '<a href="%s">%s</a>', admin_url( 'options-general.php?page=jock-rotator' ), __( 'Settings' ) );
	array_unshift($links, $jrwp_settings_link);
	return $links;
}


/**
 * This function is the code that gets loaded when the settings page gets loaded by the browser.
 * It calls functions that handle image uploads and image settings changes, as well as producing the visible page output.
 * @since 0.0.1
 */
function jrwp_admin_page() {
	echo '<div class="wrap">';
	
		// handle image upload, if necessary
		if($_REQUEST['action'] == 'wp_handle_upload')
			jrwp_handle_upload();
		
		// delete an image, if necessary
		if(isset($_REQUEST['delete']))
			jrwp_delete_upload($_REQUEST['delete']);
		
		// the image management form
		jrwp_images_admin();
		
		// the settings management form
		jrwp_settings_admin();

	echo '</div>';
}


/**
 * this section handles uploading images, addingthe image data to the database, deleting images,
 * and deleting image data from the database.
 * @since 0.0.1
 */
function jrwp_handle_upload() {
	global $jrwp_settings, $jrwp_images;
	
	// upload the image
	$upload = wp_handle_upload($_FILES['jrwp'], 0);
	
	// extract the $upload array
	extract($upload);
	
	// the URL of the directory the file was loaded in
	$upload_dir_url = str_replace(basename($file), '', $url);
	
	// get the image dimensions
	list($width, $height) = getimagesize($file);
	
	// if the uploaded file is NOT an image
	if(strpos($type, 'image') === FALSE) {
		unlink($file); // delete the file
		echo '<div class="error" id="message"><p>Sorry, but the file you uploaded does not seem to be a valid image. Please try again.</p></div>';
		return;
	}
	
	// if the image doesn't meet the minimum width/height requirements ...
	if($width < $jrwp_settings['img_width'] || $height < $jrwp_settings['img_height']) {
		unlink($file); // delete the image
		echo '<div class="error" id="message"><p>Sorry, but this image does not meet the minimum height/width requirements. Please upload another image</p></div>';
		return;
	}
	
	// if the image is larger than the width/height requirements, then scale it down.
	if($width > $jrwp_settings['img_width'] || $height > $jrwp_settings['img_height']) {
		//	resize the image
		$resized = image_resize($file, $jrwp_settings['img_width'], $jrwp_settings['img_height'], true, 'resized');
		$resized_url = $upload_dir_url . basename($resized);
		//	delete the original
		unlink($file);
		$file = $resized;
		$url = $resized_url;
	}
	
	// make the thumbnail
	$thumb_height = round((100 * $jrwp_settings['img_height']) / $jrwp_settings['img_width']);
	if(isset($upload['file'])) {
		$thumbnail = image_resize($file, 100, $thumb_height, true, 'thumb');
		$thumbnail_url = $upload_dir_url . basename($thumbnail);
	}
	
	// use the timestamp as the array key and id
	$time = date('YmdHis');
	
	// add the image data to the array
	$jrwp_images[$time] = array(
		'id' => $time,
		'file' => $file,
		'file_url' => $url,
		'thumbnail' => $thumbnail,
		'thumbnail_url' => $thumbnail_url,
		'image_links_to' => ''
	);
	
	// add the image information to the database
	$jrwp_images['update'] = 'Added';
	update_option('jrwp_images', $jrwp_images);
}

// delete the image, and removes the image data from the db
function jrwp_delete_upload($id) {
	global $jrwp_images;
	
	// if the ID passed to this function is invalid,
	// halt the process, and don't try to delete.
	if(!isset($jrwp_images[$id])) return;
	
	// delete the image and thumbnail
	unlink($jrwp_images[$id]['file']);
	unlink($jrwp_images[$id]['thumbnail']);
	
	// indicate that the image was deleted
	$jrwp_images['update'] = 'Deleted';
	
	// remove the image data from the db
	unset($jrwp_images[$id]);
	update_option('jrwp_images', $jrwp_images);
}


/**
 * These two functions check to see if an update to the data just occurred. if it did, then they
 * will display a notice, and reset the update option.
 * @since 0.0.1
 */
 
function jrwp_images_update_check() {
	global $jrwp_images;
	if($jrwp_images['update'] == 'Added' || $jrwp_images['update'] == 'Deleted' || $jrwp_images['update'] == 'Updated') {
		echo '<div class="updated fade" id="message"><p>Jock(s) '.$jrwp_images['update'].' Successfully</p></div>';
		unset($jrwp_images['update']);
		update_option('jrwp_images', $jrwp_images);
	}
}


function jrwp_settings_update_check() {
	global $jrwp_settings;
	if(isset($jrwp_settings['update'])) {
		echo '<div class="updated fade" id="message"><p>Jock Roator Settings <strong>'.$jrwp_settings['update'].'</strong></p></div>';
		unset($jrwp_settings['update']);
		update_option('jrwp_settings', $jrwp_settings);
	}
}


/**
 * these two functions display the front-end code on the admin page. it's mostly form markup.
 * @since 0.0.1
 */

// display the images administration code
function jrwp_images_admin() { ?>
	<?php global $jrwp_images; ?>
	<h2><?php _e( 'Jock Photos', 'jrwp' ); ?></h2>
	<table class="form-table">
		<tr valign="top"><th scope="row">Upload a photo</th>
			<td>
			<form enctype="multipart/form-data" method="post" action="?page=jock-rotator">
				<input type="hidden" name="post_id" id="post_id" value="0" />
				<input type="hidden" name="action" id="action" value="wp_handle_upload" />
				
				<label for="jrwp">Select a File: </label>
				<input type="file" name="jrwp" id="jrwp" />
				<input type="submit" class="button-primary" name="html-upload" value="Upload" />
			</form>
			</td>
		</tr>
	</table><br />
	
    <h2><?php _e( 'Jock Information', 'jrwp' ); ?></h2>
    <?php jrwp_images_update_check(); ?>
	<?php if(!empty($jrwp_images)) : ?>
	<table class="widefat fixed" cellspacing="0">
		<thead>
			<tr>
				<th scope="col">Jock Photo</th>
                <th scope="col">Description</th>
				<th scope="col">Photo Links To</th>
                <th scope="col">Days On-Air</th>
                <th scope="col">Start Time</th>
                <th scope="col">End Time</th>
				<th scope="col">Actions</th>
			</tr>
		</thead>
		
		<tfoot>
			<tr>
				<th scope="col">Jock Picture</th>
                <th scope="col">Description</th>
				<th scope="col">Photo Links To</th>
                <th scope="col">Days On-Air</th>
                <th scope="col">Start Time</th>
                <th scope="col">End Time</th>
				<th scope="col">Actions</th>
			</tr>
		</tfoot>
		
		<tbody>
		
		<form method="post" action="options.php">
		<?php settings_fields( 'jrwp_images' ); ?>
		<?php foreach((array)$jrwp_images as $image => $data) : ?>
			<tr>
            	<input type="hidden" name="jrwp_images[update]" value="Updated" />
				<input type="hidden" name="jrwp_images[<?php echo $image; ?>][id]" value="<?php echo $data['id']; ?>" />
				<input type="hidden" name="jrwp_images[<?php echo $image; ?>][file]" value="<?php echo $data['file']; ?>" />
				<input type="hidden" name="jrwp_images[<?php echo $image; ?>][file_url]" value="<?php echo $data['file_url']; ?>" />
				<input type="hidden" name="jrwp_images[<?php echo $image; ?>][thumbnail]" value="<?php echo $data['thumbnail']; ?>" />
				<input type="hidden" name="jrwp_images[<?php echo $image; ?>][thumbnail_url]" value="<?php echo $data['thumbnail_url']; ?>" />
                <input type="hidden" name="jrwp_images[<?php echo $image; ?>][desc]" value="<?php echo $data['desc']; ?>" />
                <input type="hidden" name="jrwp_images[<?php echo $image; ?>][days]" value="<?php echo $data['days']; ?>" />
                <input type="hidden" name="jrwp_images[<?php echo $image; ?>][start_time]" value="<?php echo $data['start_time']; ?>" />
                <input type="hidden" name="jrwp_images[<?php echo $image; ?>][end_time]" value="<?php echo $data['end_time']; ?>" />
				<th scope="row" class="column-slug"><img src="<?php echo $data['thumbnail_url']; ?>" /></th>
                <td><textarea name="jrwp_images[<?php echo $image; ?>][desc]" cols="20" rows="3" /><?php echo $data['desc']; ?></textarea></td>
				<td><input type="text" name="jrwp_images[<?php echo $image; ?>][image_links_to]" value="<?php echo $data['image_links_to']; ?>" size="25" /></td>
                <td>
                	<select style="height:65px;" name="jrwp_images[<?php echo $image; ?>][days][]" multiple="multiple" size="5">
						<option value="0" <?php selected('0', $data['days']); ?>>Sunday</option>
						<option value="1" <?php selected('1', $data['days']); ?>>Monday</option>
						<option value="2" <?php selected('2', $data['days']); ?>>Tuesday</option>
						<option value="3" <?php selected('3', $data['days']); ?>>Wednesday</option>
						<option value="4" <?php selected('4', $data['days']); ?>>Thursday</option>
						<option value="5" <?php selected('5', $data['days']); ?>>Friday</option>
						<option value="6" <?php selected('6', $data['days']); ?>>Saturday</option>
					</select>
            	</td>
                <td><input type="text" name="jrwp_images[<?php echo $image; ?>][start_time]" value="<?php echo $data['start_time']; ?>" size="5" /></td>
                <td><input type="text" name="jrwp_images[<?php echo $image; ?>][end_time]" value="<?php echo $data['end_time']; ?>" size="5" /></td>
				<td class="column-slug"><input type="submit" class="button-primary" value="Update" /> <a href="?page=jock-rotator&amp;delete=<?php echo $image; ?>" class="button">Delete</a></td>
			</tr>
		<?php endforeach; ?>
		</form>
		
		</tbody>
	</table>
	<?php endif; ?>

<?php
}

// display the settings administration code
function jrwp_settings_admin() { ?>
	<h2><?php _e( 'Jock Rotator Settings', 'jock-rotator' ); ?></h2>
    <?php jrwp_settings_update_check(); ?>
	<form method="post" action="options.php">
	<?php settings_fields( 'jrwp_settings' ); ?>
	<?php global $jrwp_settings; $options = $jrwp_settings; ?>
	
    <h3>Name</h3>
    <table class="form-table"> 
    	<tbody>         
        	<tr valign="top">
            	<th scope="row"><?php _e( 'Module Name', 'jrwp' ); ?></th>
				<td>                
                <input type="text" name="jrwp_settings[header_text]" value="<?php echo $options['header_text'] ?>" />
                <p><span class="description"><?php _e( 'Give the module a name. Default: <code>On-Air Now</code>', 'jrwp' ); ?></span></p>
                </td>
        </tr>		
		<input type="hidden" name="jrwp_settings[update]" value="UPDATED" />	
	</table>
    
    <h3>Photo Dimensions</h3>
    <table class="form-table">	
    	<tbody>
			<tr valign="top">
            	<th scope="row"><?php _e( 'The minimum photo dimensions that will be allowed to be uploaded', 'jrwp' ); ?></th>
				<td>
                <label for="jrwp_settings[img_width]">Width </label><input type="text" name="jrwp_settings[img_width]" value="<?php echo $options['img_width'] ?>" class="small-text" />
				<label for="jrwp_settings[img_height]">Height </label><input type="text" name="jrwp_settings[img_height]" value="<?php echo $options['img_height'] ?>" class="small-text" />
                <p><span class="description"><?php _e( 'Large photos will be scaled both automatically and proportionally. Default: <code>250x125</code>', 'jrwp' ); ?></span></p>
				</td>
        	</tr>
       </tbody>
	</table>
    
    <h3>CSS Options</h3>
    <table class="form-table">	
    	<tbody>
			<tr valign="top">
            	<th scope="row"><?php _e( 'Jock Rotator DIV ID', 'jrwp' ); ?></th>
				<td>            		
            		<input type="text" name="jrwp_settings[div]" value="<?php echo $options['div'] ?>" />
                    <p><span class="description"><?php _e( 'Set the CSS <code>ID</code> of the module. Default: <code>jock-rotator</code>', 'jrwp' ); ?></span></p>
                </td>
        	</tr>
            <tr valign="top">
            	<th scope="row"><?php _e( 'Jock Rotator Header Class', 'jrwp' ); ?></th>
				<td>            		
            		<input type="text" name="jrwp_settings[header_class]" value="<?php echo $options['header_class'] ?>" />
                    <p><span class="description"><?php _e( 'Set the CSS <code>class</code> of the <code>&lt;h3&gt;</code>. Default: <code>jock-header</code>', 'jrwp' ); ?></span></p>
                </td>
        	</tr>
			<tr valign="top">
            	<th scope="row"><?php _e( 'Jock Rotator Image Class', 'jrwp' ); ?></th>
				<td>            		
            		<input type="text" name="jrwp_settings[image_class]" value="<?php echo $options['image_class'] ?>" />
                    <p><span class="description"><?php _e( 'Set the CSS <code>class</code> of the photo. Default: <code>jock-image</code>', 'jrwp' ); ?></span></p>
                </td>
        	</tr>
            <tr valign="top">
            	<th scope="row"><?php _e( 'Jock Rotator Description Class', 'jrwp' ); ?></th>
				<td>            		
            		<input type="text" name="jrwp_settings[desc_class]" value="<?php echo $options['desc_class'] ?>" />
                    <p><span class="description"><?php _e( 'Set the CSS <code>class</code> of the description. Default: <code>jock-desc</code>', 'jrwp' ); ?></span></p>
                </td>
        	</tr>
        </tbody>
    </table>
    <h3>Timezone</h3>
    <table class="form-table"> 
    	<tbody>         
        	<tr valign="top">
            	<th scope="row">Select your timezone</th>
				<td>    
                <select name="jrwp_settings[time_zone]">
					<option value="Kwajalein" <?php selected('Kwajalein', $options['time_zone']); ?>>(GMT -12:00) Eniwetok, Kwajalein</option>
					<option value="Pacific/Midway" <?php selected('Pacific/Midway', $options['time_zone']); ?>>(GMT -11:00) Midway Island, Samoa</option>
					<option value="Pacific/Honolulu" <?php selected('Pacific/Honolulu', $options['time_zone']); ?>>(GMT -10:00) Hawaii</option>
					<option value="America/Anchorage" <?php selected('America/Anchorage', $options['time_zone']); ?>>(GMT -9:00) Alaska</option>
					<option value="America/Los_Angeles" <?php selected('America/Los_Angeles', $options['time_zone']); ?>>(GMT -8:00) Pacific Time (US &amp; Canada)</option>
					<option value="America/Denver" <?php selected('America/Denver', $options['time_zone']); ?>>(GMT -7:00) Mountain Time (US &amp; Canada)</option>
					<option value="America/Chicago" <?php selected('America/Chicago', $options['time_zone']); ?>>(GMT -6:00) Central Time (US &amp; Canada), Mexico City</option>
					<option value="America/New_York" <?php selected('America/New_York', $options['time_zone']); ?>>(GMT -5:00) Eastern Time (US &amp; Canada), Bogota, Lima</option>
                    <option value="America/Caracas" <?php selected('America/Caracas', $options['time_zone']); ?>>(GMT -4:30) Caracas</option>
					<option value="America/Halifax" <?php selected('America/Halifax', $options['time_zone']); ?>>(GMT -4:00) Atlantic Time (Canada), Caracas, La Paz</option>
					<option value="America/St_Johns" <?php selected('America/St_Johns', $options['time_zone']); ?>>(GMT -3:30) Newfoundland, St. Johns</option>
					<option value="America/Argentina/Buenos_Aires" <?php selected('America/Argentina/Buenos_Aires', $options['time_zone']); ?>>(GMT -3:00) Brazil, Buenos Aires, Georgetown</option>
					<option value="Atlantic/South_Georgia" <?php selected('Atlantic/South_Georgia', $options['time_zone']); ?>>(GMT -2:00) Mid-Atlantic</option>
					<option value="Atlantic/Azores" <?php selected('Atlantic/Azores', $options['time_zone']); ?>>(GMT -1:00) Azores, Cape Verde Islands</option>
					<option value="Europe/Dublin" <?php selected('Europe/Dublin', $options['time_zone']); ?>>(GMT) Western Europe Time, London, Lisbon, Casablanca</option>
					<option value="Europe/Belgrade" <?php selected('Europe/Belgrade', $options['time_zone']); ?>>(GMT +1:00) Brussels, Copenhagen, Madrid, Paris</option>
					<option value="Europe/Minsk" <?php selected('Europe/Minsk', $options['time_zone']); ?>>(GMT +2:00) Kaliningrad, South Africa</option>
					<option value="Asia/Kuwait" <?php selected('Asia/Kuwait', $options['time_zone']); ?>>(GMT +3:00) Baghdad, Riyadh, Moscow, St. Petersburg</option>
					<option value="Asia/Tehran" <?php selected('Asia/Tehran', $options['time_zone']); ?>>(GMT +3:30) Tehran</option>
					<option value="Asia/Muscat" <?php selected('Asia/Muscat', $options['time_zone']); ?>>(GMT +4:00) Abu Dhabi, Muscat, Baku, Tbilisi</option>
					<option value="Asia/Kubal" <?php selected('Asia/Kubal', $options['time_zone']); ?>>(GMT +4:30) Kabul</option>
					<option value="Asia/Yekaterinburg" <?php selected('Asia/Yekaterinburg', $options['time_zone']); ?>>(GMT +5:00) Ekaterinburg, Islamabad, Karachi, Tashkent</option>
					<option value="Asia/Kolkata" <?php selected('Asia/Kolkata', $options['time_zone']); ?>>(GMT +5:30) Bombay, Calcutta, Madras, New Delhi</option>
					<option value="Asia/Katmandu" <?php selected('Asia/Katmandu', $options['time_zone']); ?>>(GMT +5:45) Kathmandu</option>
					<option value="Asia/Dhaka" <?php selected('Asia/Dhaka', $options['time_zone']); ?>>(GMT +6:00) Almaty, Dhaka, Colombo</option>
                    <option value="Asia/Rangoon" <?php selected('Asia/Rangoon', $options['time_zone']); ?>>(GMT +6:30) Rangoon</option>
					<option value="Asia/Krasnoyarsk" <?php selected('Asia/Krasnoyarsk', $options['time_zone']); ?>>(GMT +7:00) Bangkok, Hanoi, Jakarta</option>
					<option value="Asia/Brunei" <?php selected('Asia/Brunei', $options['time_zone']); ?>>(GMT +8:00) Beijing, Perth, Singapore, Hong Kong</option>
					<option value="Asia/Seoul" <?php selected('Asia/Seoul', $options['time_zone']); ?>>(GMT +9:00) Tokyo, Seoul, Osaka, Sapporo, Yakutsk</option>
					<option value="Australia/Darwin" <?php selected('Australia/Darwin', $options['time_zone']); ?>>(GMT +9:30) Adelaide, Darwin</option>
					<option value="Australia/Canberra" <?php selected('Australia/Canberra', $options['time_zone']); ?>>(GMT +10:00) Eastern Australia, Guam, Vladivostok</option>
					<option value="Asia/Magadan" <?php selected('Asia/Magadan', $options['time_zone']); ?>>(GMT +11:00) Magadan, Solomon Islands, New Caledonia</option>
					<option value="Pacific/Fiji" <?php selected('Pacific/Fiji', $options['time_zone']); ?>>(GMT +12:00) Auckland, Wellington, Fiji, Kamchatka</option>
                    <option value="Pacific/Tongatapu" <?php selected('Pacific/Tongatapu', $options['time_zone']); ?>>(GMT +13:00) Tongatapu</option>
				</select>            
                <p><span class="description"><?php _e('This is required to ensure DJ\'s will show up according to your timezone. Default: <code>Central Time (US &amp; Canada)</code>', 'jrwp'); ?></span></p>
                </td>
        </tr>		
		<input type="hidden" name="jrwp_settings[update]" value="UPDATED" />	
	</table>
	<p class="submit">
	<input type="submit" class="button-primary" value="<?php _e('Save Settings') ?>" />
	</form>
	
	<!-- The Reset Option -->
	<form method="post" action="options.php">
	<?php settings_fields( 'jrwp_settings '); ?>
	<?php global $jrwp_defaults; // use the defaults ?>
	<?php foreach((array)$jrwp_defaults as $key => $value) : ?>
	<input type="hidden" name="jrwp_settings[<?php echo $key; ?>]" value="<?php echo $value; ?>" />
	<?php endforeach; ?>
	<input type="hidden" name="jrwp_settings[update]" value="RESET" />
	<input type="submit"  class="button-highlighted" value="<?php _e('Reset Settings') ?>" />
	</form>
	<!-- End Reset Option -->
	</p>
    &nbsp; &nbsp;
    <p><span class="description"><?php _e('Blah Blah Blah a brief FAQ', 'jrwp'); ?></span></p>

<?php
}


/**
 * these two functions sanitize the data before it gets stored in the database via options.php
 * @since 0.0.1
 *
 */

// sanitizes our settings data for storage
function jrwp_settings_validate($input) {
	$input['header_text'] = wp_filter_nohtml_kses($input['header_text']);
	$input['img_width'] = intval($input['img_width']);
	$input['img_height'] = intval($input['img_height']);
	$input['div'] = wp_filter_nohtml_kses($input['div']);
	$input['header_class'] = wp_filter_nohtml_kses($input['header_class']);
	$input['image_class'] = wp_filter_nohtml_kses($input['image_class']);
	$input['desc_class'] = wp_filter_nohtml_kses($input['desc_class']);
	
	return $input;
}
// sanitizes our image data for storage
function jrwp_images_validate($input) {
	foreach((array)$input as $key => $value) {
		if($key != 'update') {
			$input[$key]['file_url'] = clean_url($value['file_url']);
			$input[$key]['thumbnail_url'] = clean_url($value['thumbnail_url']);
			
			if($value['image_links_to'])
			$input[$key]['image_links_to'] = clean_url($value['image_links_to']);
		}
	}
	return $input;
}

/**
 * Generates all hook wrappers
 * @since 0.0.1
 *
 */
function jrwp_before_header() {
	do_action('jrwp_before_header');
}

function jrwp_after_header() {
	do_action('jrwp_after_header');
}

function jrwp_before_image() {
	do_action('jrwp_before_image');
}

function jrwp_after_image() {
	do_action('jrwp_after_image');
}

function jrwp_before_description() {
	do_action('jrwp_before_description');
}

function jrwp_after_description() {
	do_action('jrwp_after_description');
}


/**
 * Generates the <h3>MODULE NAME</h3> area 
 * @since 0.0.1
 *
 */
function jrwp_header() {
	global $jrwp_settings;
	jrwp_before_header();
		echo "\t\t" .'<h3 class="'.$jrwp_settings['header_class'].'">'.$jrwp_settings['header_text'].'</h3>' . "\n"; 
	jrwp_after_header();
}


/**
 * Generates the jock image
 * @since 0.0.1
 *
 */
function jrwp_image() {
	global $jrwp_settings, $jrwp_images;
	
	// set the timezone
	if(function_exists('date_default_timezone_set'))
		date_default_timezone_set($jrwp_settings['time_zone']); 
	
	// get current server time
	$dae = date( 'w' );
	$now = date( 'Hi' ); 
	
	jrwp_before_image();
		foreach((array)$jrwp_images as $image => $data) {
			if($data['days'] == $dae && $data['start_time'] <= $now && $data['end_time'] >= $now)
				echo "\t\t\t" .'<a href="'.$data['image_links_to'].'"><img class="'.$jrwp_settings['image_class'].' '.$data['id'].'" src="'.$data['file_url'].'" width="'.$jrwp_settings['img_width'].'" height="'.$jrwp_settings['img_height'].'" alt="'.$data['desc'].'" title="'.$data['desc'].'" /></a>' . "\n"; 
		
		}
	jrwp_after_image();
}

/**
 * Generates the jock description
 * @since 0.0.1
 *
 */
function jrwp_description() {
	global $jrwp_settings, $jrwp_images;
	jrwp_before_description();
		echo "\t\t\t" .'<p class="'.$jrwp_settings['desc_class'].'">'.$jrwp_images['desc'].'</p>' . "\n";
	jrwp_after_description();
	
}


/**
 * Mash it all together and form our primary function
 * @since 0.0.1
 *
 */
function jrwp($args = array(), $content = null) {
	global $jrwp_settings;
	echo "\t" .'<div id="'.$jrwp_settings['div'].'">' . "\n";
		jrwp_header();
		jrwp_image();
		jrwp_description();
	echo "\t" .'</div>' . "\n";
}

// create the shortcode [jrwp]
add_shortcode( 'jrwp', 'jrwp_shortcode' );
function jrwp_shortcode($atts) {		
	ob_start();
		jrwp();
	return ob_get_clean();			
}