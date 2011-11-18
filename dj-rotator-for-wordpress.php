<?php
/*
Plugin Name: DJ Rotator for WordPress
Plugin URI: http://gregrickaby.com/go/dj-rotator-for-wordpress
Description: Easily create a Deejay Rotator to display which personality is currently on-air. You can upload/delete deejays via the options panel. <strong>Display the DJ Rotator by using either the <code>djwp();</code> template tag or a <code>[djwp]</code> shortcode in your theme.</strong>
Author: Greg Rickaby
Version: 0.0.1
Author URI: http://gregrickaby.com
Notes: Big thanks to Nathan Rice and his WP-Cycle Plugin which got me started in the right direction. I love open-source and the GPL.
*/


/**
 * Defines the default variables that will be used throughout the plugin
 * @since 0.0.1
 */
$djwp_defaults = apply_filters( 'djwp_defaults', array(
	'header_text' => 'On-Air Now',
	'img_width' => 250,
	'img_height' => 125,
	'div' => 'dj-rotator',
	'header_class' => 'dj-header',
	'image_class' => 'dj-image',
	'desc_class' => 'dj-desc',
	'time_zone' => 'America/Chicago'
));


/**
 * Pull the settings from the DB
 * @since 0.0.1
 */
$djwp_settings = get_option( 'djwp_settings' );
$djwp_images = get_option( 'djwp_images' );
$djwp_settings = wp_parse_args($djwp_settings, $djwp_defaults);


/**
 * The following section registers settings, adds a link to the options page, and a link
 * on the plugin page to "settings"
 * @since 0.0.1
 */
add_action( 'admin_init', 'djwp_register_settings' );
function djwp_register_settings() {
	register_setting( 'djwp_images', 'djwp_images', 'djwp_images_validate' );
	register_setting( 'djwp_settings', 'djwp_settings', 'djwp_settings_validate' );
}

add_action( 'admin_menu', 'add_djwp_menu' );
function add_djwp_menu() {
		add_submenu_page( 'options-general.php', 'DJ Roator', 'DJ Rotator', 8, 'dj-rotator', 'djwp_admin_page' );
}

add_filter( 'plugin_action_links_' . plugin_basename(__FILE__) , 'djwp_plugin_action_links' );
function djwp_plugin_action_links($links) {
	$djwp_settings_link = sprintf( '<a href="%s">%s</a>', admin_url( 'options-general.php?page=dj-rotator' ), __( 'Settings' ) );
	array_unshift($links, $djwp_settings_link);
	return $links;
}


/**
 * This function is the code that gets loaded when the settings page gets loaded by the browser.
 * It calls functions that handle image uploads and image settings changes, as well as producing the visible page output.
 * @since 0.0.1
 */
function djwp_admin_page() {
	echo '<div class="wrap">';
	
		// handle image upload, if necessary
		if($_REQUEST['action'] == 'wp_handle_upload')
			djwp_handle_upload();
		
		// delete an image, if necessary
		if(isset($_REQUEST['delete']))
			djwp_delete_upload($_REQUEST['delete']);
		
		// the image management form
		djwp_images_admin();
		
		// the settings management form
		djwp_settings_admin();

	echo '</div>';
}


/**
 * this section handles uploading images, addingthe image data to the database, deleting images, and deleting image data from the database.
 * @since 0.0.1
 */
function djwp_handle_upload() {
	global $djwp_settings, $djwp_images;
	
	// upload the image
	$upload = wp_handle_upload($_FILES['djwp'], 0);
	
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
	if($width < $djwp_settings['img_width'] || $height < $djwp_settings['img_height']) {
		unlink($file); // delete the image
		echo '<div class="error" id="message"><p>Sorry, but this image does not meet the minimum height/width requirements. Please upload another image</p></div>';
		return;
	}
	
	// if the image is larger than the width/height requirements, then scale it down.
	if($width > $djwp_settings['img_width'] || $height > $djwp_settings['img_height']) {
		//	resize the image
		$resized = image_resize($file, $djwp_settings['img_width'], $djwp_settings['img_height'], true, 'resized');
		$resized_url = $upload_dir_url . basename($resized);
		//	delete the original
		unlink($file);
		$file = $resized;
		$url = $resized_url;
	}
	
	// make the thumbnail
	$thumb_height = round((100 * $djwp_settings['img_height']) / $djwp_settings['img_width']);
	if(isset($upload['file'])) {
		$thumbnail = image_resize($file, 100, $thumb_height, true, 'thumb');
		$thumbnail_url = $upload_dir_url . basename($thumbnail);
	}
	
	// use the timestamp as the array key and id
	$time = date('YmdHis');
	
	// add the image data to the array
	$djwp_images[$time] = array(
		'id' => $time,
		'file' => $file,
		'file_url' => $url,
		'thumbnail' => $thumbnail,
		'thumbnail_url' => $thumbnail_url,
		'image_links_to' => ''
	);
	
	// add the image information to the database
	$djwp_images['update'] = 'Added';
	update_option('djwp_images', $djwp_images);
}

// delete the image, and removes the image data from the db
function djwp_delete_upload($id) {
	global $djwp_images;
	
	// if the ID passed to this function is invalid,
	// halt the process, and don't try to delete.
	if(!isset($djwp_images[$id])) return;
	
	// delete the image and thumbnail
	unlink($djwp_images[$id]['file']);
	unlink($djwp_images[$id]['thumbnail']);
	
	// indicate that the image was deleted
	$djwp_images['update'] = 'Deleted';
	
	// remove the image data from the db
	unset($djwp_images[$id]);
	update_option('djwp_images', $djwp_images);
}


/**
 * These two functions check to see if an update to the data just occurred. if it did, then they
 * will display a notice, and reset the update option.
 * @since 0.0.1
 */
function djwp_images_update_check() {
	global $djwp_images;
	if($djwp_images['update'] == 'Added' || $djwp_images['update'] == 'Deleted' || $djwp_images['update'] == 'Updated') {
		echo '<div class="updated fade" id="message"><p>DJ(s) '.$djwp_images['update'].' Successfully</p></div>';
		unset($djwp_images['update']);
		update_option('djwp_images', $djwp_images);
	}
}


function djwp_settings_update_check() {
	global $djwp_settings;
	if(isset($djwp_settings['update'])) {
		echo '<div class="updated fade" id="message"><p><strong>DJ Settings <strong>'.$djwp_settings['update'].'</strong></p></div>';
		unset($djwp_settings['update']);
		update_option('djwp_settings', $djwp_settings);
	}
}


/**
 * Display the dj image settings on the options page
 * @since 0.0.1
 */
function djwp_images_admin() { ?>
	<?php global $djwp_images; ?>
	<h2><?php _e( 'DJ Images', 'djwp' ); ?></h2>
	<table class="form-table">
		<tr valign="top"><th scope="row">Upload a photo</th>
			<td>
			<form enctype="multipart/form-data" method="post" action="?page=dj-rotator">
				<input type="hidden" name="post_id" id="post_id" value="0" />
				<input type="hidden" name="action" id="action" value="wp_handle_upload" />
				
				<label for="djwp">Select a File: </label>
				<input type="file" name="djwp" id="djwp" />
				<input type="submit" class="button-primary" name="html-upload" value="Upload" />
			</form>
			</td>
		</tr>
	</table>
	
    <h2><?php _e( 'DJ Information', 'djwp' ); ?></h2>
    <?php djwp_images_update_check();
	
	// check to see if there are DJ's. If not display a quick message
	if(empty($djwp_images)) : 
    	echo '<div class="updated fade" id="message"><p><strong>There\'s nothing here yet. Try uploading an image of a DJ first.</strong></p></div>';
    endif; ?>
    
	<table class="widefat fixed" cellspacing="0">
		<thead>
			<tr>
				<th scope="col">DJ Photo</th>
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
				<th scope="col">DJ Picture</th>
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
		<?php settings_fields( 'djwp_images' ); ?>
		<?php foreach((array)$djwp_images as $image => $data) : ?>
			<tr>
            	<input type="hidden" name="djwp_images[update]" value="Updated" />
				<input type="hidden" name="djwp_images[<?php echo $image; ?>][id]" value="<?php echo $data['id']; ?>" />
				<input type="hidden" name="djwp_images[<?php echo $image; ?>][file]" value="<?php echo $data['file']; ?>" />
				<input type="hidden" name="djwp_images[<?php echo $image; ?>][file_url]" value="<?php echo $data['file_url']; ?>" />
				<input type="hidden" name="djwp_images[<?php echo $image; ?>][thumbnail]" value="<?php echo $data['thumbnail']; ?>" />
				<input type="hidden" name="djwp_images[<?php echo $image; ?>][thumbnail_url]" value="<?php echo $data['thumbnail_url']; ?>" />
                <input type="hidden" name="djwp_images[<?php echo $image; ?>][desc]" value="<?php echo $data['desc']; ?>" />
                <input type="hidden" name="djwp_images[<?php echo $image; ?>][days]" value="<?php echo $data['days']; ?>" />
                <input type="hidden" name="djwp_images[<?php echo $image; ?>][start_time]" value="<?php echo $data['start_time']; ?>" />
                <input type="hidden" name="djwp_images[<?php echo $image; ?>][end_time]" value="<?php echo $data['end_time']; ?>" />
				<th scope="row" class="column-slug"><img src="<?php echo $data['thumbnail_url']; ?>" /></th>
                <td><textarea name="djwp_images[<?php echo $image; ?>][desc]" cols="20" rows="3" /><?php echo $data['desc']; ?></textarea></td>
				<td><input type="text" name="djwp_images[<?php echo $image; ?>][image_links_to]" value="<?php echo $data['image_links_to']; ?>" size="25" /></td>
                <td>
                	<select style="height:65px;" name="djwp_images[<?php echo $image; ?>][days][]" multiple="multiple" size="5">
						<option value="0" <?php selected('0', $data['days']); ?>>Sunday</option>
						<option value="1" <?php selected('1', $data['days']); ?>>Monday</option>
						<option value="2" <?php selected('2', $data['days']); ?>>Tuesday</option>
						<option value="3" <?php selected('3', $data['days']); ?>>Wednesday</option>
						<option value="4" <?php selected('4', $data['days']); ?>>Thursday</option>
						<option value="5" <?php selected('5', $data['days']); ?>>Friday</option>
						<option value="6" <?php selected('6', $data['days']); ?>>Saturday</option>
					</select>
            	</td>
                <td><input type="text" name="djwp_images[<?php echo $image; ?>][start_time]" value="<?php echo $data['start_time']; ?>" size="5" /></td>
                <td><input type="text" name="djwp_images[<?php echo $image; ?>][end_time]" value="<?php echo $data['end_time']; ?>" size="5" /></td>
				<td class="column-slug"><input type="submit" class="button-primary" value="Update" /> <a href="?page=dj-rotator&amp;delete=<?php echo $image; ?>" class="button">Delete</a></td>
			</tr>
		<?php endforeach; ?>
		</form>		
		</tbody>
	</table>


<?php
}


/**
 * Display the DJ settings on the options page
 * @since 0.0.1
 */
function djwp_settings_admin() { ?>
	<h2><?php _e( 'DJ Rotator Settings', 'djwp' ); ?></h2>
    <?php djwp_settings_update_check(); ?>
	<form method="post" action="options.php">
	<?php settings_fields( 'djwp_settings' ); ?>
	<?php global $djwp_settings; $options = $djwp_settings; ?>

    <h3>Name</h3>
    <table class="form-table"> 
    	<tbody>         
        	<tr valign="top">
            	<th scope="row"><?php _e( 'Module Name', 'djwp' ); ?></th>
				<td>                
                <input type="text" name="djwp_settings[header_text]" value="<?php echo $options['header_text'] ?>" />
                <p><span class="description"><?php _e( 'Give the module a name. Default: <code>On-Air Now</code>', 'djwp' ); ?></span></p>
                </td>
        </tr>		
		<input type="hidden" name="djwp_settings[update]" value="UPDATED" />	
	</table>

    <h3>Photo Dimensions</h3>
    <table class="form-table">	
    	<tbody>
			<tr valign="top">
            	<th scope="row"><?php _e( 'The minimum photo dimensions that will be allowed to be uploaded', 'djwp' ); ?></th>
				<td>
                <label for="djwp_settings[img_width]">Width </label><input type="text" name="djwp_settings[img_width]" value="<?php echo $options['img_width'] ?>" class="small-text" />
				<label for="djwp_settings[img_height]">Height </label><input type="text" name="djwp_settings[img_height]" value="<?php echo $options['img_height'] ?>" class="small-text" />
                <p><span class="description"><?php _e( 'Large photos will be scaled both automatically and proportionally. Default: <code>250x125</code>', 'djwp' ); ?></span></p>
				</td>
        	</tr>
       </tbody>
	</table>

    <h3>CSS Options</h3>
    <table class="form-table">	
    	<tbody>
			<tr valign="top">
            	<th scope="row"><?php _e( 'Main DIV ID', 'djwp' ); ?></th>
				<td>            		
            		<input type="text" name="djwp_settings[div]" value="<?php echo $options['div'] ?>" />
                    <p><span class="description"><?php _e( 'Set the CSS <code>ID</code> of the module. Default: <code>dj-rotator</code>', 'djwp' ); ?></span></p>
                </td>
        	</tr>
            <tr valign="top">
            	<th scope="row"><?php _e( 'Header Class', 'djwp' ); ?></th>
				<td>            		
            		<input type="text" name="djwp_settings[header_class]" value="<?php echo $options['header_class'] ?>" />
                    <p><span class="description"><?php _e( 'Set the CSS <code>class</code> of the <code>&lt;h3&gt;</code>. Default: <code>dj-header</code>', 'djwp' ); ?></span></p>
                </td>
        	</tr>
			<tr valign="top">
            	<th scope="row"><?php _e( 'Image Class', 'djwp' ); ?></th>
				<td>            		
            		<input type="text" name="djwp_settings[image_class]" value="<?php echo $options['image_class'] ?>" />
                    <p><span class="description"><?php _e( 'Set the CSS <code>class</code> of the photo. Default: <code>dj-image</code>', 'djwp' ); ?></span></p>
                </td>
        	</tr>
            <tr valign="top">
            	<th scope="row"><?php _e( 'Description Class', 'djwp' ); ?></th>
				<td>            		
            		<input type="text" name="djwp_settings[desc_class]" value="<?php echo $options['desc_class'] ?>" />
                    <p><span class="description"><?php _e( 'Set the CSS <code>class</code> of the description. Default: <code>dj-desc</code>', 'djwp' ); ?></span></p>
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
                <select name="djwp_settings[time_zone]">
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
                <p><span class="description"><?php _e('This is required to ensure Deejay\'s will show up according to your timezone. Default: <code>Central Time (US &amp; Canada)</code>', 'djwp'); ?></span></p>
                </td>
        </tr>		
		<input type="hidden" name="djwp_settings[update]" value="UPDATED" />	
	</table>
	<p class="submit">
	<input type="submit" class="button-primary" value="<?php _e('Save Settings') ?>" />
	</form>
	
	<!-- The Reset Option -->
	<form method="post" action="options.php">
	<?php settings_fields( 'djwp_settings '); ?>
	<?php global $djwp_defaults; // use the defaults ?>
	<?php foreach((array)$djwp_defaults as $key => $value) : ?>
	<input type="hidden" name="djwp_settings[<?php echo $key; ?>]" value="<?php echo $value; ?>" />
	<?php endforeach; ?>
	<input type="hidden" name="djwp_settings[update]" value="RESET" />
	<input type="submit"  class="button-highlighted" value="<?php _e( 'Reset Settings' ) ?>" />
	</form>
	<!-- End Reset Option -->
	</p>
    &nbsp; &nbsp;
    <p><span class="description"><?php _e('Blah Blah Blah a brief FAQ', 'djwp'); ?></span></p>

<?php
}


/**
 * These two functions sanitize the data before it gets stored in the database via options.php
 * @since 0.0.1
 *
 */

// sanitizes our settings data for storage
function djwp_settings_validate($input) {
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
function djwp_images_validate($input) {
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
 * @since 0.0.2
 *
 */
function djwp_before_header() {
	do_action('djwp_before_header');
}

function djwp_after_header() {
	do_action('djwp_after_header');
}

function djwp_before_image() {
	do_action('djwp_before_image');
}

function djwp_after_image() {
	do_action('djwp_after_image');
}

function djwp_before_description() {
	do_action('djwp_before_description');
}

function djwp_after_description() {
	do_action('djwp_after_description');
}


/**
 * Generates the <h3>MODULE NAME</h3> area 
 * @since 0.0.2
 *
 */
function djwp_header() {
	global $djwp_settings;
	djwp_before_header();
		echo "\t\t" .'<h3 class="'.$djwp_settings['header_class'].'">'.$djwp_settings['header_text'].'</h3>' . "\n"; 
	djwp_after_header();
}


/**
 * Generates the dj image
 * @since 0.0.2
 *
 */
function djwp_image() {
	global $djwp_settings, $djwp_images;
	
	// set the timezone
	if(function_exists('date_default_timezone_set'))
		date_default_timezone_set($djwp_settings['time_zone']); 
	
	// get current server time
	$dae = date( 'w' );
	$now = date( 'Hi' ); 
	
	djwp_before_image();
		foreach((array)$djwp_images as $image => $data) {
			if($data['days'] == $dae && $data['start_time'] <= $now && $data['end_time'] >= $now)
				echo "\t\t\t" .'<a href="'.$data['image_links_to'].'"><img class="'.$djwp_settings['image_class'].' '.$data['id'].'" src="'.$data['file_url'].'" width="'.$djwp_settings['img_width'].'" height="'.$djwp_settings['img_height'].'" alt="'.$data['desc'].'" title="'.$data['desc'].'" /></a>' . "\n"; 
		
		}
	djwp_after_image();
}


/**
 * Generates the dj description
 * @since 0.0.2
 *
 */
function djwp_description() {
	global $djwp_settings, $djwp_images;
	djwp_before_description();
		echo "\t\t\t" .'<p class="'.$djwp_settings['desc_class'].'">'.$djwp_images['desc'].'</p>' . "\n";
	djwp_after_description();
	
}


/**
 * Mash it all together and form our primary function
 * @since 0.0.1
 *
 */
function djwp($args = array(), $content = null) {
	global $djwp_settings;
	echo "\t" .'<div id="'.$djwp_settings['div'].'">' . "\n";
		djwp_header();
		djwp_image();
		djwp_description();
	echo "\t" .'</div>' . "\n";
}


/**
 * Create the shortcode [djwp]
 * @since 0.0.1
 *
 */
add_shortcode( 'djwp', 'djwp_shortcode' );
function djwp_shortcode($atts) {		
	ob_start();
		djwp();
	return ob_get_clean();			
}