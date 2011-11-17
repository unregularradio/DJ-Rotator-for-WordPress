<?php
/*
Plugin Name: Jock Rotator for WordPress
Plugin URI: http://gregrickaby.com/2011/11/jock-rotator-for-wordpress
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
	'img_width' => 250,
	'img_height' => 150,
	'div' => 'jock-rotator',
	'header_text' => 'On-Air Now'
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
                	<select name="jrwp_images[<?php echo $image; ?>][days]" size="5" multiple style="height:65px;">
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
	<h2><?php _e('Jock Rotator Settings', 'jock-rotator'); ?></h2>
    <?php jrwp_settings_update_check(); ?>
	<form method="post" action="options.php">
	<?php settings_fields('jrwp_settings'); ?>
	<?php global $jrwp_settings; $options = $jrwp_settings; ?>
	
    <h3>Photo Dimensions</h3>
    <table class="form-table">	
    	<tbody>
			<tr valign="top">
            	<th scope="row">The sizes listed determine the minimum dimensions in pixels.</th>
				<td>
                <label for="jrwp_settings[img_width]">Width </label><input type="text" name="jrwp_settings[img_width]" value="<?php echo $options['img_width'] ?>" class="small-text" />
				<label for="jrwp_settings[img_height]">Height </label><input type="text" name="jrwp_settings[img_height]" value="<?php echo $options['img_height'] ?>" class="small-text" />
                <p><span class="description">Large photos will be scaled both automatically and proportionally</span></p>
				</td>
        	</tr>
       </tbody>
	</table>
    <h3>CSS Options</h3>
    <table class="form-table">	
    	<tbody>
			<tr valign="top">
            	<th scope="row">Jock Rotator DIV ID</th>
				<td>            		
            		<input type="text" name="jrwp_settings[div]" value="<?php echo $options['div'] ?>" />
                    <p><span class="description">Set the CSS <code>ID</code> of the module for CSS customization</span></p>
                </td>
        	</tr>
        </tbody>
    </table>
    <h3>Name</h3>
    <table class="form-table"> 
    	<tbody>         
        	<tr valign="top">
            	<th scope="row">Module Name</th>
				<td>                
                <input type="text" name="jrwp_settings[header_text]" value="<?php echo $options['header_text'] ?>" />
                <p><span class="description"><?php _e('Give the module a name', 'jrwp'); ?></span></p>
                </td>
        </tr>		
		<input type="hidden" name="jrwp_settings[update]" value="UPDATED" />	
	</table>
	<p class="submit">
	<input type="submit" class="button-primary" value="<?php _e('Save Settings') ?>" />
	</form>
	
	<!-- The Reset Option -->
	<form method="post" action="options.php">
	<?php settings_fields('jrwp_settings'); ?>
	<?php global $jrwp_defaults; // use the defaults ?>
	<?php foreach((array)$jrwp_defaults as $key => $value) : ?>
	<input type="hidden" name="jrwp_settings[<?php echo $key; ?>]" value="<?php echo $value; ?>" />
	<?php endforeach; ?>
	<input type="hidden" name="jrwp_settings[update]" value="RESET" />
	<input type="submit"  class="button-highlighted" value="<?php _e('Reset Settings') ?>" />
	</form>
	<!-- End Reset Option -->
	</p>

<?php
}


/**
 * these two functions sanitize the data before it gets stored in the database via options.php
 * @since 0.0.1
 *
 */

// sanitizes our settings data for storage
function jrwp_settings_validate($input) {
	$input['rotate'] = ($input['rotate'] == 1 ? 1 : 0);
	$input['effect'] = wp_filter_nohtml_kses($input['effect']);
	$input['img_width'] = intval($input['img_width']);
	$input['img_height'] = intval($input['img_height']);
	$input['div'] = wp_filter_nohtml_kses($input['div']);
	
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
 * Generates all the code that is displayed in the WP Theme
 * @since 0.0.1
 *
 */
function jrwp1($args = array(), $content = null) {
	global $jrwp_settings, $jrwp_images;
	
	$newline = "\n"; // line break
	
	echo '<div id="'.$jrwp_settings['div'].'">'.$newline;
	
	foreach((array)$jrwp_images as $image => $data) {
		
		echo '<h4 class="jock-header">'.$jrwp_settings['header_text'].'</h4>'.$newline;
		
		if($data['image_links_to'])
		echo '<a href="'.$data['image_links_to'].'">';
		
		echo '<img src="'.$data['file_url'].'" width="'.$jrwp_settings['img_width'].'" height="'.$jrwp_settings['img_height'].'" class="'.$data['id'].'" alt="'.$data['desc'].'" title="'.$data['desc'].'" /><p class="jock-desc">'.$data['desc'].'</p>';
		
		if($data['image_links_to'])
		echo '</a>';
		
		echo $newline;
	}
	
	echo '			</div>'.$newline;
}

// create the template tag jrwp(); and shortcode [jrwp]
add_shortcode( 'jrwp', 'jrwp_shortcode' );
function jrwp_shortcode($atts) {		
	ob_start();
		jrwp();
	return ob_get_clean();			
}

// FUTURE USE
add_action( 'wp_footer', 'jrwp_args', 99 );
function jrwp_args() {
	global $jrwp_settings; ?>

<?php if($jrwp_settings['rotate']) : ?>
	<!-- DO SOMETHING -->
<?php endif; ?>

<?php }
