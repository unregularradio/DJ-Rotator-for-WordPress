<?php
/*
Plugin Name: DJ Rotator for WordPress
Plugin URI: http://gregrickaby.com/go/dj-rotator-for-wordpress
Description: Easily create a DJ Rotator to display which personality is currently on-air. You can upload/delete deejays via the <a href="options-general.php?page=dj-rotator">options panel</a>. To display the DJ Rotator in your theme, use one of the following: 1) <a href="widgets.php">Widget</a>, 2) Template Tag <code>&lt;?php djwp(); ?&gt;</code>, or 3) Shortcode <code>[djwp]</code>.
Author: Greg Rickaby
Version: 0.0.8
Author URI: http://gregrickaby.com
Notes: Big thanks to Nathan Rice and his WP-Cycle Plugin which got me started in the right direction.
Copyright 2011 Greg Rickaby (gregrickaby@gmail.com)

    This program is free software; you can redistribute it and/or modify
    it under the terms of the GNU General Public License, version 2, as 
    published by the Free Software Foundation.

    This program is distributed in the hope that it will be useful,
    but WITHOUT ANY WARRANTY; without even the implied warranty of
    MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
    GNU General Public License for more details.

    You should have received a copy of the GNU General Public License
    along with this program; if not, write to the Free Software
    Foundation, Inc., 51 Franklin St, Fifth Floor, Boston, MA  02110-1301  USA

*/

/**
 * Check PHP Version
 *
 * @author Greg Rickaby
 * @since 0.0.1
 */
if ( version_compare( PHP_VERSION, '5.1.0', '<' ) )
	die( 'DJ Rotator requires at least PHP 5.1. Your server currently has version '. PHP_VERSION .' installed' );


/**
 * Check for GD
 *
 * @author Greg Rickaby
 * @since 0.0.8
 */
if ( !extension_loaded( 'gd' ) )
	die( 'DJ Rotator requires the GD library support for image manipulation. Please contact your sever administrator and have it activated.' );


/**
 * Defines the default variables that will be used throughout the plugin
 *
 * @author Greg Rickaby
 * @since 0.0.1
 */
$djwp_defaults = apply_filters( 'djwp_defaults', 
	array(
		'header_text' => 'On-Air Now',
		'img_width' => 250,
		'img_height' => 125,
		'div' => 'dj-rotator',
		'header_class' => 'dj-header',
		'image_class' => 'dj-image',
		'desc_class' => 'dj-desc',
		'time_zone' => 'America/Chicago'
	) );


/**
 * Pull the settings from the DB
 *
 * @author Greg Rickaby
 * @since 0.0.1
 */

// Pull the settings from the db
$djwp_settings = get_option( 'djwp_settings' );
$djwp_images = get_option( 'djwp_images' );

// Fallback
$djwp_settings = wp_parse_args( $djwp_settings, $djwp_defaults );


/**
 * The following section registers settings, adds a link to the options page, and a link
 * on the plugin page to "settings"
 *
 * @author Greg Rickaby
 * @since 0.0.1
 */
add_action( 'admin_init', 'djwp_register_settings' );
// Register settings with WordPress
function djwp_register_settings() {

	register_setting( 'djwp_images', 'djwp_images', 'djwp_images_validate' );
	register_setting( 'djwp_settings', 'djwp_settings', 'djwp_settings_validate' );

}

add_action( 'admin_menu', 'add_djwp_menu' );
// Add Options page
function add_djwp_menu() {

	add_submenu_page( 'options-general.php', 'DJ Roator', 'DJ Rotator', 'manage_options', 'dj-rotator', 'djwp_admin_page' );

}

add_filter( 'plugin_action_links_' . plugin_basename(__FILE__) , 'djwp_plugin_action_links' );
// Add plugin action links to plugins page
function djwp_plugin_action_links( $links ) {

	$djwp_settings_link = sprintf( '<a href="%s">%s</a>', admin_url( 'options-general.php?page=dj-rotator' ), __( 'Settings' ) );
	array_unshift( $links, $djwp_settings_link );
	
	return $links;

}


/**
 * This function is the code that gets loaded when the settings page gets loaded by the browser.
 * It calls functions that handle image uploads and image settings changes, as well as producing the visible page output.
 *
 * @author Greg Rickaby
 * @since 0.0.1
 */
function djwp_admin_page() {

	echo '<div class="wrap">';
	
		// handle image upload, if necessary
		if ( 'wp_handle_upload' == $_REQUEST['action'] )
			djwp_handle_upload();
		
		// delete an image, if necessary
		if ( isset( $_REQUEST['delete'] ) )
			djwp_delete_upload( $_REQUEST['delete'] );
		
		// the image management form
		djwp_images_admin();
		
		// the settings management form
		djwp_settings_admin();

	echo '</div>';

}


/**
 * This section handles uploading images, addingthe image data to the database, deleting images, and deleting image data from the database.
 *
 * @author Greg Rickaby
 * @since 0.0.1
 */
function djwp_handle_upload() {

	global $djwp_settings, $djwp_images;
	
	// Upload the image
	$upload = wp_handle_upload( $_FILES['djwp'], 0 );
	
	// Extract the $upload array
	extract( $upload );
	
	// The URL of the directory the file was loaded in
	$upload_dir_url = str_replace( basename( $file ), '', $url );
	
	// Get the image dimensions
	list( $width, $height ) = getimagesize( $file );
	
	// If the uploaded file is NOT an image
	if ( false === strpos( $type, 'image' ) ) {
		unlink( $file ); // delete the file
		echo '<div class="error" id="message"><p>Sorry, but the file you uploaded does not seem to be a valid image. Please try again.</p></div>';
		return;
	}
	
	// If the image doesn't meet the minimum width/height requirements ...
	if ( $width < $djwp_settings['img_width'] || $height < $djwp_settings['img_height'] ) {
		unlink( $file ); // delete the image
		echo '<div class="error" id="message"><p>Sorry, but this image does not meet the minimum height/width requirements. Please upload another image</p></div>';
		return;
	}
	
	// If the image is larger than the width/height requirements, then scale it down.
	if ( $width > $djwp_settings['img_width'] || $height > $djwp_settings['img_height'] ) {
		// Resize the image
		$resized = image_resize($file, $djwp_settings['img_width'], $djwp_settings['img_height'], true, 'resized' );
		$resized_url = $upload_dir_url . basename( $resized );
		// Delete the original
		unlink( $file );
		$file = $resized;
		$url = $resized_url;
	}
	
	// Make the thumbnail
	$thumb_height = round( ( 100 * $djwp_settings['img_height']) / $djwp_settings['img_width'] );
	if ( isset( $upload['file'] ) ) {
		$thumbnail = image_resize( $file, 100, $thumb_height, true, 'thumb' );
		$thumbnail_url = $upload_dir_url . basename( $thumbnail );
	}
	
	// Use the timestamp as the array key and id
	$time = date( 'YmdHis' );
	
	// Add the image data to the array
	$djwp_images[$time] = array(
		'id' => $time,
		'file' => $file,
		'file_url' => $url,
		'thumbnail' => $thumbnail,
		'thumbnail_url' => $thumbnail_url,
		'image_links_to' => ''
	);
	
	// Add the image information to the database
	$djwp_images['update'] = 'Added';
	update_option( 'djwp_images', $djwp_images );

}

// Delete the image, and removes the image data from the db
function djwp_delete_upload( $id ) {

	global $djwp_images;
	
	// If the ID passed to this function is invalid, halt the process, and don't try to delete.
	if( !isset( $djwp_images[$id] ) ) 
		return;
	
	// Delete the image and thumbnail
	unlink( $djwp_images[$id]['file'] );
	unlink( $djwp_images[$id]['thumbnail'] );
	
	// Indicate that the image was deleted
	$djwp_images['update'] = 'Deleted';
	
	// Remove the image data from the db
	unset( $djwp_images[$id] );
	update_option( 'djwp_images', $djwp_images );

}


/**
 * These two functions check to see if an update to the data just occurred. if it did, then they
 * will display a notice, and reset the update option.
 *
 * @author Greg Rickaby
 * @since 0.0.1
 */
function djwp_images_update_check() {

	global $djwp_images;

	if ( 'Added' == $djwp_images['update'] || 'Deleted' == $djwp_images['update'] || 'Updated' == $djwp_images['update'] ) {
		echo '<div class="updated fade" id="message"><p>' . $djwp_images['update'] . ' Successfully</p></div>';
		unset( $djwp_images['update'] );
		update_option( 'djwp_images', $djwp_images );
	}

}


function djwp_settings_update_check() {

	global $djwp_settings;

	if ( isset( $djwp_settings['update'] ) ) {
		echo '<div class="updated fade" id="message"><p><strong>DJ Settings ' . $djwp_settings['update'] . '</strong></p></div>';
		unset( $djwp_settings['update'] );
		update_option( 'djwp_settings', $djwp_settings );
	}

}


/**
 * Display the DJ image settings on the options page
 *
 * @author Greg Rickaby
 * @since 0.0.1
 */
function djwp_images_admin() {

	global $djwp_images; 
	djwp_images_update_check(); ?>

	<h2><?php _e( 'Upload DJ Photo', 'djwp' ); ?></h2>
	<table class="form-table">
		<tr valign="top"><th scope="row"><?php _e( 'Upload a photo', 'djwp' ); ?></th>
			<td>
			<form enctype="multipart/form-data" method="post" action="?page=dj-rotator">
				<input type="hidden" name="post_id" id="post_id" value="0" />
				<input type="hidden" name="action" id="action" value="wp_handle_upload" />
				<label for="djwp"><?php _e( 'Select a File:', 'djwp' ); ?></label>
				<input type="file" name="djwp" id="djwp" />
				<input type="submit" class="button-primary" name="html-upload" value="Upload" />
			</form>
			</td>
		</tr>
	</table>

	<?php 
	// If no images, display a helper message.
	if ( empty( $djwp_images ) ) {
		echo '<p><strong>To begin, upload an image.</strong></p>';
	}

	// If no images, don't display options
	if ( !empty( $djwp_images ) ) { ?>

	<h2><?php _e( 'DJ Information', 'djwp' ); ?></h2>
	<table class="widefat fixed" cellspacing="0">
		<thead>
			<tr>
				<th scope="col"><?php _e( 'DJ Image', 'djwp' ); ?></th>
				<th scope="col"><?php _e( 'Description', 'djwp' ); ?></th>
				<th scope="col"><?php _e( 'Image URL', 'djwp' ); ?></th>
				<th scope="col"><?php _e( 'Days On-Air', 'djwp' ); ?></th>
				<th scope="col"><?php _e( 'Start Time', 'djwp' ); ?></th>
				<th scope="col"><?php _e( 'End Time', 'djwp' ); ?></th>
				<th scope="col"><?php _e( 'Actions', 'djwp' ); ?></th>
			</tr>
		</thead>

		<tfoot>
			<tr>
				<th scope="col"><?php _e( 'DJ Image', 'djwp' ); ?></th>
				<th scope="col"><?php _e( 'Description', 'djwp' ); ?></th>
				<th scope="col"><?php _e( 'Image URL', 'djwp' ); ?></th>
				<th scope="col"><?php _e( 'Days On-Air', 'djwp' ); ?></th>
				<th scope="col"><?php _e( 'Start Time', 'djwp' ); ?></th>
				<th scope="col"><?php _e( 'End Time', 'djwp' ); ?></th>
				<th scope="col"><?php _e( 'Actions', 'djwp' ); ?></th>
			</tr>
		</tfoot>

		<tbody>

		<form method="post" action="options.php">
		<?php settings_fields( 'djwp_images' ); 
		foreach ( ( array )$djwp_images as $image => $data ) { ?>

		<tr>
		<div style="display:none;visibility:hidden;">
			<input type="hidden" name="djwp_images[update]" value="Updated" />
			<input type="hidden" name="djwp_images[<?php echo esc_attr( $image ); ?>][id]" value="<?php echo esc_attr( $data['id'] ); ?>" />
			<input type="hidden" name="djwp_images[<?php echo esc_attr( $image ); ?>][file]" value="<?php echo esc_attr( $data['file'] ); ?>" />
			<input type="hidden" name="djwp_images[<?php echo esc_attr( $image ); ?>][file_url]" value="<?php echo esc_url( $data['file_url'] ); ?>" />
			<input type="hidden" name="djwp_images[<?php echo esc_attr( $image ); ?>][thumbnail]" value="<?php echo esc_attr( $data['thumbnail'] ); ?>" />
			<input type="hidden" name="djwp_images[<?php echo esc_attr( $image ); ?>][thumbnail_url]" value="<?php echo esc_url( $data['thumbnail_url'] ); ?>" />
			<input type="hidden" name="djwp_images[<?php echo esc_attr( $image ); ?>][desc]" value="<?php echo wp_kses_post( $data['desc'] ); ?>" />
			<input type="hidden" name="djwp_images[<?php echo esc_attr( $image ); ?>][monday]" value="0" />
			<input type="hidden" name="djwp_images[<?php echo esc_attr( $image ); ?>][tuesday]" value="0" />
			<input type="hidden" name="djwp_images[<?php echo esc_attr( $image ); ?>][wednesday]" value="0" />
			<input type="hidden" name="djwp_images[<?php echo esc_attr( $image ); ?>][thursday]" value="0" />
			<input type="hidden" name="djwp_images[<?php echo esc_attr( $image ); ?>][friday]" value="0" />
			<input type="hidden" name="djwp_images[<?php echo esc_attr( $image ); ?>][saturday]" value="0" />
			<input type="hidden" name="djwp_images[<?php echo esc_attr( $image ); ?>][sunday]" value="0" />
			<input type="hidden" name="djwp_images[<?php echo esc_attr( $image ); ?>][start_time]" value="<?php echo esc_attr( $data['start_time'] ); ?>" />
			<input type="hidden" name="djwp_images[<?php echo esc_attr( $image ); ?>][end_time]" value="<?php echo esc_attr( $data['end_time'] ); ?>" />
		</div>
			<td class="column-slug">
				<img src="<?php echo esc_url( $data['file_url'] ); ?>" width="200" height="100" />
			</td>

			<td>
				<textarea name="djwp_images[<?php echo esc_attr( $image ); ?>][desc]"rows="6" cols="35" class="regular-text" /><?php echo wp_kses_post( $data['desc'] ); ?></textarea>
				<p><small><em><?php _e( 'Basic HTML allowed', 'djwp' ); ?></em></small></p>
			</td>

			<td>
				<input type="text" name="djwp_images[<?php echo esc_attr( $image ); ?>][image_links_to]" value="<?php echo esc_url( $data['image_links_to'] ); ?>" size="25" />
				<p><small><em><?php _e( 'http://boortz.com', 'djwp' ); ?></em></small></p>
                <input name="djwp_images[<?php echo esc_attr( $image ); ?>][_blank]" type="checkbox" value="_blank" <?php checked( '_blank', $data['_blank'] ); ?> /> <label for="djwp_images[_blank]"><?php _e( 'Open link in new window?', 'djwp' ); ?><br />
			</td>

			<td>
				<input name="djwp_images[<?php echo esc_attr( $image ); ?>][monday]" type="checkbox" value="Mon" <?php checked( 'Mon', $data['monday'] ); ?> /> <label for="djwp_images[monday]"><?php _e( 'Monday', 'djwp' ); ?><br />
				<input name="djwp_images[<?php echo esc_attr( $image ); ?>][tuesday]" type="checkbox" value="Tue" <?php checked( 'Tue', $data['tuesday'] ); ?> /> <label for="djwp_images[tuesday]"><?php _e( 'Tuesday', 'djwp' ); ?><br />
				<input name="djwp_images[<?php echo esc_attr( $image ); ?>][wednesday]" type="checkbox" value="Wed" <?php checked( 'Wed', $data['wednesday'] ); ?> /> <label for="djwp_images[wednesday]"><?php _e( 'Wednesday', 'djwp' ); ?><br />
				<input name="djwp_images[<?php echo esc_attr( $image ); ?>][thursday]" type="checkbox" value="Thu" <?php checked( 'Thu', $data['thursday'] ); ?> /> <label for="djwp_images[thursday]"><?php _e( 'Thursday', 'djwp' ); ?><br />
				<input name="djwp_images[<?php echo esc_attr( $image ); ?>][friday]" type="checkbox" value="Fri" <?php checked( 'Fri', $data['friday'] ); ?> /> <label for="djwp_images[friday]"><?php _e( 'Friday', 'djwp' ); ?><br />
				<input name="djwp_images[<?php echo esc_attr( $image ); ?>][saturday]" type="checkbox" value="Sat" <?php checked( 'Sat', $data['saturday'] ); ?> /> <label for="djwp_images[saturday]"><?php _e( 'Saturday', 'djwp' ); ?><br />
				<input name="djwp_images[<?php echo esc_attr( $image ); ?>][sunday]" type="checkbox" value="Sun" <?php checked( 'Sun', $data['sunday'] ); ?> /> <label for="djwp_images[sunday]"><?php _e( 'Sunday', 'djwp' ); ?>
			</td>

			<td><input type="text" name="djwp_images[<?php echo esc_attr( $image ); ?>][start_time]" value="<?php echo esc_attr( $data['start_time'] ); ?>" class="small-text" />
				<p><span class="description"><?php _e( '24-hour time only <code>15:00</code>', 'djwp' ); ?></span></p>
			</td>

			<td><input type="text" name="djwp_images[<?php echo esc_attr( $image ); ?>][end_time]" value="<?php echo esc_attr( $data['end_time'] ); ?>" class="small-text" />
				<p><span class="description"><?php _e( '24-hour time only <code>19:00</code>', 'djwp' ); ?></span></p>
			</td>

			<td class="column-slug">
				<input type="submit" class="button-primary" value="Update" /> <a href="?page=dj-rotator&amp;delete=<?php echo esc_attr( $image ); ?>" class="button"><?php _e( 'Delete', 'djwp' ); ?></a>
			</td>
		</tr>
		<?php } ?>

		</form>
		</tbody>
	</table>
	<?php }
}


/**
 * Display the DJ settings on the options page
 *
 * @author Greg Rickaby
 * @since 0.0.1
 */
function djwp_settings_admin() {

	djwp_settings_update_check(); ?>
	<form method="post" action="options.php">
	<?php settings_fields( 'djwp_settings' ); ?>
	<?php global $djwp_settings; $options = $djwp_settings; ?>

	<h2><?php _e( 'DJ Rotator Settings', 'djwp' ); ?></h2>
	<form method="post" action="options.php">
	<h3><?php _e( 'Name', 'djwp' ); ?></h3>
	<table class="form-table"> 
		<tbody>
		<tr valign="top">
		<th scope="row"><?php _e( 'Module Name', 'djwp' ); ?></th>
			<td>
				<input type="text" name="djwp_settings[header_text]" value="<?php echo esc_attr( $options['header_text'] ); ?>" class="regular-text" />
				<p><span class="description"><?php _e( 'Give the module a name. Only applies to Template Tag and Shortcode. Default: <code>On-Air Now</code>', 'djwp' ); ?></span></p>
			</td>
		</tr>
		<input type="hidden" name="djwp_settings[update]" value="UPDATED" />
	</table>

	<h3><?php _e( 'Image Dimensions', 'djwp' ); ?></h3>
	<table class="form-table">	
		<tbody>
		<tr valign="top">
		<th scope="row"><?php _e( 'The minimum photo dimensions that will be allowed to be uploaded', 'djwp' ); ?></th>
			<td>
				<label for="djwp_settings[img_width]"><?php _e( 'Width', 'djwp' ); ?> </label><input type="text" name="djwp_settings[img_width]" value="<?php echo esc_attr( $options['img_width'] ); ?>" class="small-text" />
				<label for="djwp_settings[img_height]"><?php _e( 'Height', 'djwp' ); ?> </label><input type="text" name="djwp_settings[img_height]" value="<?php echo esc_attr( $options['img_height'] ); ?>" class="small-text" />
				<p><span class="description"><?php _e( 'Large photos will be scaled both automatically and proportionally. Default: <code>250x125</code>', 'djwp' ); ?></span></p>
			</td>
		</tr>
		</tbody>
	</table>

	<h3><?php _e( 'CSS Options', 'djwp' ); ?></h3>
	<table class="form-table">
		<tbody>
		<tr valign="top">
		<th scope="row"><?php _e( 'Main DIV ID', 'djwp' ); ?></th>
			<td>
				<input type="text" name="djwp_settings[div]" value="<?php echo esc_attr( $options['div'] ); ?>" class="regular-text code" />
				<p><span class="description"><?php _e( 'Set the CSS <code>ID</code> of the module. Only applies to Template Tag and Shortcode. Default: <code>dj-rotator</code>', 'djwp' ); ?></span></p>
			</td>
		</tr>
		<tr valign="top">
		<th scope="row"><?php _e( 'Header Class', 'djwp' ); ?></th>
			<td>
				<input type="text" name="djwp_settings[header_class]" value="<?php echo esc_attr( $options['header_class'] ); ?>" class="regular-text code" />
				<p><span class="description"><?php _e( 'Set the CSS <code>class</code> of the <code>&lt;h3&gt;</code>. Only applies to Template Tag and Shortcode. Default: <code>dj-header</code>', 'djwp' ); ?></span></p>
			</td>
		</tr>
		<tr valign="top">
		<th scope="row"><?php _e( 'Image Class', 'djwp' ); ?></th>
			<td>
				<input type="text" name="djwp_settings[image_class]" value="<?php echo esc_attr( $options['image_class'] ); ?>" class="regular-text code" />
				<p><span class="description"><?php _e( 'Set the CSS <code>class</code> of the photo. Default: <code>dj-image</code>', 'djwp' ); ?></span></p>
			</td>
		</tr>
		<tr valign="top">
		<th scope="row"><?php _e( 'Description Class', 'djwp' ); ?></th>
			<td>
				<input type="text" name="djwp_settings[desc_class]" value="<?php echo esc_attr( $options['desc_class'] ); ?>" class="regular-text code" />
				<p><span class="description"><?php _e( 'Set the CSS <code>class</code> of the description. Default: <code>dj-desc</code>', 'djwp' ); ?></span></p>
			</td>
		</tr>
	</tbody>
	</table>

	<h3><?php _e( 'Timezone', 'djwp' ); ?></h3>
	<table class="form-table">
		<tbody>
		<tr valign="top">
		<th scope="row"><?php _e( 'Select your timezone', 'djwp' ); ?></th>
			<td>
				<select name="djwp_settings[time_zone]">
				<option value="Kwajalein" <?php selected( 'Kwajalein', esc_attr( $options['time_zone'] ) ); ?>>(GMT -12:00) Eniwetok, Kwajalein</option>
				<option value="Pacific/Midway" <?php selected( 'Pacific/Midway', esc_attr( $options['time_zone'] ) ); ?>>(GMT -11:00) Midway Island, Samoa</option>
				<option value="Pacific/Honolulu" <?php selected( 'Pacific/Honolulu', esc_attr( $options['time_zone'] ) ); ?>>(GMT -10:00) Hawaii</option>
				<option value="America/Anchorage" <?php selected( 'America/Anchorage', esc_attr( $options['time_zone'] ) ); ?>>(GMT -9:00) Alaska</option>
				<option value="America/Los_Angeles" <?php selected( 'America/Los_Angeles', esc_attr( $options['time_zone'] ) ); ?>>(GMT -8:00) Pacific Time (US &amp; Canada)</option>
				<option value="America/Denver" <?php selected( 'America/Denver', esc_attr( $options['time_zone'] ) ); ?>>(GMT -7:00) Mountain Time (US &amp; Canada)</option>
				<option value="America/Chicago" <?php selected( 'America/Chicago', esc_attr( $options['time_zone'] ) ); ?>>(GMT -6:00) Central Time (US &amp; Canada), Mexico City</option>
				<option value="America/New_York" <?php selected( 'America/New_York', esc_attr( $options['time_zone'] ) ); ?>>(GMT -5:00) Eastern Time (US &amp; Canada), Bogota, Lima</option>
				<option value="America/Caracas" <?php selected( 'America/Caracas', esc_attr( $options['time_zone'] ) ); ?>>(GMT -4:30) Caracas</option>
				<option value="America/Halifax" <?php selected( 'America/Halifax', esc_attr( $options['time_zone'] ) ); ?>>(GMT -4:00) Atlantic Time (Canada), Caracas, La Paz</option>
				<option value="America/St_Johns" <?php selected( 'America/St_Johns', esc_attr( $options['time_zone'] ) ); ?>>(GMT -3:30) Newfoundland, St. Johns</option>
				<option value="America/Argentina/Buenos_Aires" <?php selected( 'America/Argentina/Buenos_Aires', esc_attr( $options['time_zone'] ) ); ?>>(GMT -3:00) Brazil, Buenos Aires, Georgetown</option>
				<option value="Atlantic/South_Georgia" <?php selected( 'Atlantic/South_Georgia', esc_attr( $options['time_zone'] ) ); ?>>(GMT -2:00) Mid-Atlantic</option>
				<option value="Atlantic/Azores" <?php selected( 'Atlantic/Azores', esc_attr( $options['time_zone'] ) ); ?>>(GMT -1:00) Azores, Cape Verde Islands</option>
				<option value="Europe/Dublin" <?php selected( 'Europe/Dublin', esc_attr( $options['time_zone'] ) ); ?>>(GMT) Western Europe Time, London, Lisbon, Casablanca</option>
				<option value="Europe/Belgrade" <?php selected( 'Europe/Belgrade', esc_attr( $options['time_zone'] ) ); ?>>(GMT +1:00) Brussels, Copenhagen, Madrid, Paris</option>
				<option value="Europe/Minsk" <?php selected( 'Europe/Minsk', esc_attr( $options['time_zone'] ) ); ?>>(GMT +2:00) Kaliningrad, South Africa</option>
				<option value="Asia/Kuwait" <?php selected( 'Asia/Kuwait', esc_attr( $options['time_zone'] ) ); ?>>(GMT +3:00) Baghdad, Riyadh, Moscow, St. Petersburg</option>
				<option value="Asia/Tehran" <?php selected( 'Asia/Tehran', esc_attr( $options['time_zone'] ) ); ?>>(GMT +3:30) Tehran</option>
				<option value="Asia/Muscat" <?php selected( 'Asia/Muscat', esc_attr( $options['time_zone'] ) ); ?>>(GMT +4:00) Abu Dhabi, Muscat, Baku, Tbilisi</option>
				<option value="Asia/Kubal" <?php selected( 'Asia/Kubal', esc_attr( $options['time_zone'] ) ); ?>>(GMT +4:30) Kabul</option>
				<option value="Asia/Yekaterinburg" <?php selected( 'Asia/Yekaterinburg', esc_attr( $options['time_zone'] ) ); ?>>(GMT +5:00) Ekaterinburg, Islamabad, Karachi, Tashkent</option>
				<option value="Asia/Kolkata" <?php selected( 'Asia/Kolkata', esc_attr( $options['time_zone'] ) ); ?>>(GMT +5:30) Bombay, Calcutta, Madras, New Delhi</option>
				<option value="Asia/Katmandu" <?php selected( 'Asia/Katmandu', esc_attr( $options['time_zone'] ) ); ?>>(GMT +5:45) Kathmandu</option>
				<option value="Asia/Dhaka" <?php selected( 'Asia/Dhaka', esc_attr( $options['time_zone'] ) ); ?>>(GMT +6:00) Almaty, Dhaka, Colombo</option>
				<option value="Asia/Rangoon" <?php selected( 'Asia/Rangoon', esc_attr( $options['time_zone'] ) ); ?>>(GMT +6:30) Rangoon</option>
				<option value="Asia/Krasnoyarsk" <?php selected( 'Asia/Krasnoyarsk', esc_attr( $options['time_zone'] ) ); ?>>(GMT +7:00) Bangkok, Hanoi, Jakarta</option>
				<option value="Asia/Brunei" <?php selected( 'Asia/Brunei', esc_attr( $options['time_zone'] ) ); ?>>(GMT +8:00) Beijing, Perth, Singapore, Hong Kong</option>
				<option value="Asia/Seoul" <?php selected( 'Asia/Seoul', esc_attr( $options['time_zone'] ) ); ?>>(GMT +9:00) Tokyo, Seoul, Osaka, Sapporo, Yakutsk</option>
				<option value="Australia/Darwin" <?php selected( 'Australia/Darwin', esc_attr( $options['time_zone'] ) ); ?>>(GMT +9:30) Adelaide, Darwin</option>
				<option value="Australia/Canberra" <?php selected( 'Australia/Canberra', esc_attr( $options['time_zone'] ) ); ?>>(GMT +10:00) Eastern Australia, Guam, Vladivostok</option>
				<option value="Asia/Magadan" <?php selected( 'Asia/Magadan', esc_attr( $options['time_zone'] ) ); ?>>(GMT +11:00) Magadan, Solomon Islands, New Caledonia</option>
				<option value="Pacific/Fiji" <?php selected( 'Pacific/Fiji', esc_attr( $options['time_zone'] ) ); ?>>(GMT +12:00) Auckland, Wellington, Fiji, Kamchatka</option>
				<option value="Pacific/Tongatapu" <?php selected( 'Pacific/Tongatapu', esc_attr( $options['time_zone'] ) ); ?>>(GMT +13:00) Tongatapu</option>
				</select>
				<p><span class="description"><?php _e( 'This is required to ensure Deejay\'s will show up according to your timezone. Default: <code>Central Time (US &amp; Canada)</code>', 'djwp' ); ?></span></p>
			</td>
		</tr>
		<input type="hidden" name="djwp_settings[update]" value="UPDATED" />	
	</table>
	<p class="submit">
		<input type="submit" class="button-primary" value="<?php _e('Save Settings') ?>" />
		</form>

		<form method="post" action="options.php">
		<?php settings_fields( 'djwp_settings' ); 
		global $djwp_defaults; // use the defaults
		foreach( ( array )$djwp_defaults as $key => $value ) { ?>
			<input type="hidden" name="djwp_settings[<?php echo $key; ?>]" value="<?php echo $value; ?>" />
		<?php } ?>
		<input type="hidden" name="djwp_setting[update]" value="RESET" />
		<input type="submit" class="button" value="<?php _e( 'Reset Settings' ) ?>" />
		</form>
	</p>
	&nbsp; &nbsp;
	<p><span class="description"><?php _e( 'To display the DJ Rotator in your theme, use one of the following: 1) <a href="widgets.php">Widget</a>, 2) Template Tag <code>&lt;?php djwp(); ?&gt;</code>, or 3) Shortcode <code>[djwp]</code><br />HTML is allowed in the description field.<br />For support visit: <a href="http://wordpress.org/tags/dj-rotator-for-wordpress" target="_blank">http://wordpress.org/tags/dj-rotator-for-wordpress</a>', 'djwp' ); ?></span></p>

	<?php
}


/**
 * These two functions sanitize the data before it gets stored in the database.
 *
 * @author Greg Rickaby
 * @since 0.0.1
 */

// sanitizes our settings data for storage
function djwp_settings_validate( $input ) {

	$input['img_width'] = intval( $input['img_width'] );
	$input['img_height'] = intval( $input['img_height'] );
	$input['header_text'] = wp_filter_nohtml_kses( $input['header_text'] );
	$input['div'] = wp_filter_nohtml_kses( $input['div'] );
	$input['header_class'] = wp_filter_nohtml_kses( $input['header_class'] );
	$input['image_class'] = wp_filter_nohtml_kses( $input['image_class'] );
	$input['desc_class'] = wp_filter_nohtml_kses( $input['desc_class'] );

	return $input;
}

// sanitizes our image data for storage
function djwp_images_validate( $input ) {

	foreach ( ( array )$input as $key => $value ) {

		if ( 'update' != $key ) {
			$input[$key]['start_time'] = wp_filter_nohtml_kses( $value['start_time'] );
			$input[$key]['end_time'] = wp_filter_nohtml_kses( $value['end_time'] );
			$input[$key]['file_url'] = esc_url( $value['file_url'] );
			$input[$key]['thumbnail_url'] = esc_url( $value['thumbnail_url'] );
			$input[$key]['desc'] = wp_kses_post( $value['desc'] );
			$input[$key]['image_links_to'] = esc_url( $value['image_links_to'] );
		}

	}

	return $input;
}


/**
 * Generates all hook wrappers.
 *
 * @author Greg Rickaby
 * @since 0.0.2
 */
function djwp_before_header() {
	do_action( 'djwp_before_header' );
}

function djwp_after_header() {
	do_action( 'djwp_after_header' );
}

function djwp_before_image() {
	do_action( 'djwp_before_image' );
}

function djwp_after_image() {
	do_action( 'djwp_after_image' );
}

function djwp_before_description() {
	do_action( 'djwp_before_description' );
}

function djwp_after_description() {
	do_action( 'djwp_after_description' );
}


/**
 * Generates the header area for use with Template Tag and Shortcode.
 *
 * @author Greg Rickaby
 * @since 0.0.2
 */
function djwp_header() {

	global $djwp_settings;

	djwp_before_header(); #hook
		echo "\t\t\t\t\t" . '<h3 class="widget-title ' . $djwp_settings['header_class'] . '">' . $djwp_settings['header_text'] . '</h3>' . "\n"; 
	djwp_after_header(); #hook

}


/**
 * Generates the DJ image.
 *
 * @author Greg Rickaby
 * @since 0.0.2
 */
function djwp_rotator() {

	global $djwp_settings, $djwp_images;
	
	// set the timezone
	if ( function_exists( 'date_default_timezone_set' ) )
		date_default_timezone_set( $djwp_settings['time_zone'] );
	
	// get current server time
	$djday = date( 'D' );
	$djnow = date( 'H:i' ); 
	
		foreach ( ( array )$djwp_images as $image => $data ) {
			
			if ( $data['monday'] === $djday && $data['start_time'] <= $djnow && $data['end_time'] >= $djnow ) {
				djwp_before_image(); #hook
					echo "\t\t\t\t\t" . '<a href="' . $data['image_links_to'] . '" target="' . $data['_blank'] . '"><img class="' . $djwp_settings['image_class'] . ' ' . $data['id'] . '" src="' . $data['file_url'] . '" width="' . $djwp_settings['img_width'] . '" height="' . $djwp_settings['img_height'] . '" alt="On-Air Now" title="On-Air Now" /></a>' . "\n";  
				djwp_after_image(); #hook
					echo "\t\t\t\t\t" . '<p class="' . $djwp_settings['desc_class'] . '">' . $data['desc'] . '</p>' . "\n";
				djwp_after_description(); #hook
			}

			if ( $data['tuesday'] === $djday && $data['start_time'] <= $djnow && $data['end_time'] >= $djnow ) {
				echo "\t\t\t\t\t" . '<a href="' . $data['image_links_to'] . '" target="' . $data['_blank'] . '"><img class="' . $djwp_settings['image_class'] . ' ' . $data['id'] . '" src="' . $data['file_url'] . '" width="' . $djwp_settings['img_width'] . '" height="' . $djwp_settings['img_height'] . '" alt="On-Air Now" title="On-Air Now"' . "\n"; 
				djwp_before_image(); #hook
					echo "\t\t\t\t\t" . '<a href="' . $data['image_links_to'] . '" target="' . $data['_blank'] . '"><img class="' . $djwp_settings['image_class'] . ' ' . $data['id'] . '" src="' . $data['file_url'] . '" width="' . $djwp_settings['img_width'] . '" height="' . $djwp_settings['img_height'] . '" alt="On-Air Now" title="On-Air Now" /></a>' . "\n";  
				djwp_after_image(); #hook
					echo "\t\t\t\t\t" . '<p class="' . $djwp_settings['desc_class'] . '">' . $data['desc'] . '</p>' . "\n";
				djwp_after_description(); #hook
			}

			if ( $data['wednesday'] === $djday && $data['start_time'] <= $djnow && $data['end_time'] >= $djnow ) {
				echo "\t\t\t\t\t" . '<a href="' . $data['image_links_to'] . '" target="' . $data['_blank'] . '"><img class="' . $djwp_settings['image_class'] . ' ' . $data['id'] . '" src="' . $data['file_url'] . '" width="' . $djwp_settings['img_width'] . '" height="' . $djwp_settings['img_height'] . '" alt="On-Air Now" title="On-Air Now"' . "\n";  
				djwp_before_image(); #hook
					echo "\t\t\t\t\t" . '<a href="' . $data['image_links_to'] . '" target="' . $data['_blank'] . '"><img class="' . $djwp_settings['image_class'] . ' ' . $data['id'] . '" src="' . $data['file_url'] . '" width="' . $djwp_settings['img_width'] . '" height="' . $djwp_settings['img_height'] . '" alt="On-Air Now" title="On-Air Now" /></a>' . "\n";  
				djwp_after_image(); #hook
					echo "\t\t\t\t\t" . '<p class="' . $djwp_settings['desc_class'] . '">' . $data['desc'] . '</p>' . "\n";
				djwp_after_description(); #hook
			}

			if ( $data['thursday'] === $djday && $data['start_time'] <= $djnow && $data['end_time'] >= $djnow ) {
				echo "\t\t\t\t\t" . '<a href="' . $data['image_links_to'] . '" target="' . $data['_blank'] . '"><img class="' . $djwp_settings['image_class'] . ' ' . $data['id'] . '" src="' . $data['file_url'] . '" width="' . $djwp_settings['img_width'] . '" height="' . $djwp_settings['img_height'] . '" alt="On-Air Now" title="On-Air Now"' . "\n";  
				djwp_before_image(); #hook
					echo "\t\t\t\t\t" . '<a href="' . $data['image_links_to'] . '" target="' . $data['_blank'] . '"><img class="' . $djwp_settings['image_class'] . ' ' . $data['id'] . '" src="' . $data['file_url'] . '" width="' . $djwp_settings['img_width'] . '" height="' . $djwp_settings['img_height'] . '" alt="On-Air Now" title="On-Air Now" /></a>' . "\n";  
				djwp_after_image(); #hook
					echo "\t\t\t\t\t" . '<p class="' . $djwp_settings['desc_class'] . '">' . $data['desc'] . '</p>' . "\n";
				djwp_after_description(); #hook
			}

			if ( $data['friday'] === $djday && $data['start_time'] <= $djnow && $data['end_time'] >= $djnow ) {
				echo "\t\t\t\t\t" . '<a href="' . $data['image_links_to'] . '" target="' . $data['_blank'] . '"><img class="' . $djwp_settings['image_class'] . ' ' . $data['id'] . '" src="' . $data['file_url'] . '" width="' . $djwp_settings['img_width'] . '" height="' . $djwp_settings['img_height'] . '" alt="On-Air Now" title="On-Air Now"' . "\n"; 
				djwp_before_image(); #hook
					echo "\t\t\t\t\t" . '<a href="' . $data['image_links_to'] . '" target="' . $data['_blank'] . '"><img class="' . $djwp_settings['image_class'] . ' ' . $data['id'] . '" src="' . $data['file_url'] . '" width="' . $djwp_settings['img_width'] . '" height="' . $djwp_settings['img_height'] . '" alt="On-Air Now" title="On-Air Now" /></a>' . "\n";  
				djwp_after_image(); #hook
					echo "\t\t\t\t\t" . '<p class="' . $djwp_settings['desc_class'] . '">' . $data['desc'] . '</p>' . "\n";
				djwp_after_description(); #hook
			}

			if ( $data['saturday'] === $djday && $data['start_time'] <= $djnow && $data['end_time'] >= $djnow ) {
				echo "\t\t\t\t\t" . '<a href="' . $data['image_links_to'] . '" target="' . $data['_blank'] . '"><img class="' . $djwp_settings['image_class'] . ' ' . $data['id'] . '" src="' . $data['file_url'] . '" width="' . $djwp_settings['img_width'] . '" height="' . $djwp_settings['img_height'] . '" alt="On-Air Now" title="On-Air Now"' . "\n";  
				djwp_before_image(); #hook
					echo "\t\t\t\t\t" . '<a href="' . $data['image_links_to'] . '" target="' . $data['_blank'] . '"><img class="' . $djwp_settings['image_class'] . ' ' . $data['id'] . '" src="' . $data['file_url'] . '" width="' . $djwp_settings['img_width'] . '" height="' . $djwp_settings['img_height'] . '" alt="On-Air Now" title="On-Air Now" /></a>' . "\n";  
				djwp_after_image(); #hook
					echo "\t\t\t\t\t" . '<p class="' . $djwp_settings['desc_class'] . '">' . $data['desc'] . '</p>' . "\n";
				djwp_after_description(); #hook
			}

			if ( $data['sunday'] === $djday && $data['start_time'] <= $djnow && $data['end_time'] >= $djnow ) {
				echo "\t\t\t\t\t" . '<a href="' . $data['image_links_to'] . '" target="' . $data['_blank'] . '"><img class="' . $djwp_settings['image_class'] . ' ' . $data['id'] . '" src="' . $data['file_url'] . '" width="' . $djwp_settings['img_width'] . '" height="' . $djwp_settings['img_height'] . '" alt="On-Air Now" title="On-Air Now"' . "\n";  
				djwp_before_image(); #hook
					echo "\t\t\t\t\t" . '<a href="' . $data['image_links_to'] . '" target="' . $data['_blank'] . '"><img class="' . $djwp_settings['image_class'] . ' ' . $data['id'] . '" src="' . $data['file_url'] . '" width="' . $djwp_settings['img_width'] . '" height="' . $djwp_settings['img_height'] . '" alt="On-Air Now" title="On-Air Now" /></a>' . "\n";  
				djwp_after_image(); #hook
					echo "\t\t\t\t\t" . '<p class="' . $djwp_settings['desc_class'] . '">' . $data['desc'] . '</p>' . "\n";
				djwp_after_description(); #hook
			}

		}

}



/**
 * Mash it all together and form our Template Tag <?php djwp(); ?> & Shortcode function [djwp]
 *
 * @author Greg Rickaby
 * @since 0.0.1
 */
function djwp( $args = array(), $content = null ) {

	global $djwp_settings;

	echo "\t" .'<div id="' . $djwp_settings['div'] . '" class="'. $djwp_settings['div'] . '">' . "\n";
		djwp_header();
		djwp_rotator();
	echo "\t\t\t\t" . '</div>' . "\n";

}

add_shortcode( 'djwp', 'djwp_shortcode' );
/**
 * Create the shortcode [djwp]
 *
 * @author Greg Rickaby
 * @since 0.0.1
 */
function djwp_shortcode( $atts ) {	

	ob_start();
	djwp();
	return ob_get_clean();	

}


/**
 * DJ Rotator Widget
 *
 * @since 0.0.4
 */
class DJ_Rotator_Widget extends WP_Widget {


	/**
	 * Register widget with WordPress.
	 */
	public function __construct() {

		parent::__construct(
			'dj_rotator_widget', // Base ID
			'DJ Rotator', // Name
			array( 'description' => __( 'Place the DJ Rotator in your Sidebar(s)', 'djwp' ), ) 
		);

	}


	/**
	 * Front-end display of widget.
	 *
	 * @see WP_Widget::widget()
	 *
	 * @param array $args     Widget arguments.
	 * @param array $instance Saved values from database.
	 */
	public function widget( $args, $instance ) {

		extract( $args );

		// Grab settings
		$title = $instance['title'];

		echo djwp_before_header(); #hook
		echo $before_widget;
		if ( $title )
			echo $before_title . $title . $after_title; 
			echo djwp_after_header(); #hook
			echo djwp_rotator();
		
		echo $after_widget;

	}


	/**
	 * Back-end widget form with defaults
	 *
	 * @see WP_Widget::form()
	 *
	 * @param array $instance Previously saved values from database.
	 */
	public function form( $instance ) {

		$instance = wp_parse_args( (array) $instance, array( 'title' => 'On-Air Now' ) ); ?>

		<p><label for="<?php echo $this->get_field_name( 'title' ); ?>"><?php _e( 'Title:', 'djwp' ); ?></label>
			<input type="text" class="widefat" id="<?php echo $this->get_field_id( 'title' ); ?>" name="<?php echo $this->get_field_name( 'title' ); ?>" value="<?php echo esc_attr( $instance['title'] ); ?>"></p>
		</p>

	<?php }


	/**
	 * Update form values as they are saved.
	 *
	 * @see WP_Widget::update()
	 *
	 * @param array $new_instance Values just sent to be saved.
	 * @param array $old_instance Previously saved values from database.
	 *
	 * @return array Updated values to be saved.
	 */
	public function update( $new_instance, $old_instance ) {

		$instance = array();

		$instance['title'] = sanitize_text_field( $new_instance['title'] );

		return $instance;

	}

} // end DJ_Rotator_Widget

// Start the widget
add_action( 'widgets_init', function() { register_widget( 'DJ_Rotator_Widget' ); } );