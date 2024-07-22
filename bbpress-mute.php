<?php
/**
 * Plugin Name: BBPress Mute
 * Description: Let members mute other members so that they no longer see their posts.
 * Version: 1.0.0
 * Text Domain: bbp-mute
 * License: GPLv2 or later
 * License URI: http://www.gnu.org/licenses/gpl-2.0.txt
 */

/**
 * Enqueue scripts
 */

function bbpm_create_db_table() {

	global $wpdb;

	$table_name = $wpdb->prefix . 'bbpm_ban_users';
	$charset_collate = $wpdb->get_charset_collate();

	$sql = "CREATE OR REPLACE TABLE $table_name (
		id bigint(20) NOT NULL AUTO_INCREMENT,
		loggedin_id bigint(20) NOT NULL,
		profile_id bigint(20) NOT NULL,
		date_recorded TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
		PRIMARY KEY  (id)
	) $charset_collate;";

	require_once ( ABSPATH . 'wp-admin/includes/upgrade.php');
	dbDelta( $sql );
}
register_activation_hook( __FILE__, 'bbpm_create_db_table' );

function bbpm_enqueue_custom_scripts() {
    wp_enqueue_script('bbp-mute-scripts', plugin_dir_url(__FILE__) . '/assets/js/bbp-mute-script.js', array('jquery'), time(), true);
    wp_enqueue_script('bbpm-sweet-alerts-js', plugin_dir_url(__FILE__) . '/assets/js/sweetalert2.all.min.js', array('jquery'), time(), true);

     wp_enqueue_style('bbpm-sweet-alerts-css', plugin_dir_url(__FILE__) . '/assets/js/sweetalert2.min.css', array('jquery'), time(), true);
    // Localize the script with new data
    $ajax_params = array(
        'ajax_url' => admin_url('admin-ajax.php'),
        'nonce'    => wp_create_nonce('my-ajax-nonce')
    );
    wp_localize_script('bbp-mute-scripts', 'ajax_object', $ajax_params);
}
add_action('wp_enqueue_scripts', 'bbpm_enqueue_custom_scripts');

/**
 * Add mute button to the profile header
 */

function bbpm_get_button( $user_profile_id ) {

	global $bp, $members_template;

	if ( ! $user_profile_id ) {
		return;
	}

	$logged_in_user = bp_loggedin_user_id();

	$is_muted = bbpm_is_banned( $user_profile_id );

	if( !empty( $is_muted) ){
		$profile_id = $is_muted->profile_id;
		$id = $is_muted->id;
	}


	$button = array(
		'id'                => $is_muted->id ? 'Unban' : 'Ban',
		'component'         => 'members',
		'link_class'        => $is_muted->id ? 'bbpm-unban-user' : 'bbpm-ban-user',
		'link_id'           => $is_muted->id ? 'bbpm-unban-'.$user_profile_id.'' : 'bbpm-ban-'.$user_profile_id.'',
		'button_element'    => 'button',
		'link_title'        => $is_muted->id ? _x( 'Unban', 'Button', 'bbpm-ban' ) : _x( 'Shadow Ban', 'Button', 'bbpm-ban' ),
		'link_text'         => $is_muted->id ? _x( 'Unban', 'Button', 'bbpm-ban' ) : _x( 'Shadow Ban', 'Button', 'bbpm-ban' ),
		'button_attr'       => array(
			'data-profile_id' => $user_profile_id,
			'data-user_id'    => $logged_in_user,
			'class'			  => $is_muted->id ? 'bbpm-unban-user' : 'bbpm-ban-user',
		),
		'wrapper_id'        => 'bbpm-button-'.$user_profile_id  ,
		'must_be_logged_in' => true,
	);

	if( current_user_can('administrator') ){
		return bp_get_button( $button );

	}
}

/**
 * Check if the user is banned
 */
function bbpm_is_banned( $bbpm_user_id ){

	global $wpdb;

	$table_name = $wpdb->prefix . 'bbpm_ban_users';


	return $wpdb->get_row( $wpdb->prepare( "SELECT profile_id,id FROM {$table_name} WHERE profile_id = %d", $bbpm_user_id ) );

}


add_action( 'bp_directory_members_actions','bbpm_add_member_dir_button', 99 );
function bbpm_add_member_dir_button(){

	global $members_template;

	echo bbpm_get_button( $members_template->member->id );

}

add_action( 'bp_group_members_list_item_action', 'bbpm_add_group_member_dir_button', 99 );
function bbpm_add_group_member_dir_button() {
	global $members_template;

	echo bbpm_get_button( $members_template->member->user_id );
}


add_action( 'bp_member_header_actions', 'bbpm_add_member_header_button', 99 );
function bbpm_add_member_header_button() {
	echo bbpm_get_button( bp_displayed_user_id() );
}

/**
 * Set up navigation Items
 * 
 * 
 */
function bbpm_directory_nav_item( $button ) {
    global $bp;

    // Add custom main nav item
	$class = ( $count === 0 ) ? 'no-count' : 'count';

    bp_core_new_nav_item(
        array(
          	'name'                    => sprintf( __( 'Banned Users <span class="%s">%s</span>', 'bbpm-mute' ), esc_attr( 'bbpm-main-nav' ), number_format_i18n( $count ) ),
			'position'                => 80,
			'default_subnav_slug'     => 'bbpm-all',
			'slug'                    => 'bbpm-ban',
			'item_css_id'             => 'bbpm-ban',
			'show_for_displayed_user' => false,
			'screen_function'         => 'bbpm_ban_all_screen',
			'site_admin_only'		  => true	
        )
    );

    // Add custom sub nav item
    bp_core_new_subnav_item(
        array(
            'name'            => __( 'All', 'bbpm-ban' ),
			'slug'            => 'bbpm-all',
			'position'        => 10,
			'parent_slug'     => 'bbpm-ban',
			'parent_url'      => trailingslashit( bp_displayed_user_domain() . 'bbpm-ban' ),
			'user_has_access' => bp_core_can_edit_settings(),
			'screen_function' => 'bbpm_ban_all_screen'
        )
    );

    return bp_get_button( $button );
}
add_action('bp_setup_nav', 'bbpm_directory_nav_item', 99);


/**
 * Get the muted members by the user
 */

function get_muting( $loggedin_id ) {

	global $wpdb;

	$table_name = $wpdb->prefix . 'bbpm_ban_users';

	return $wpdb->get_col( $wpdb->prepare( "SELECT profile_id FROM {$table_name} WHERE loggedin_id = %d", $loggedin_id ) );
}

/**
 * Filter the shadow banned members in the member list

 */
add_filter( 'bp_after_has_members_parse_args', 'bbpm_filter_members_all');

function bbpm_filter_members_all( $loggedin_id ){

	if ( bp_is_current_action( 'bbpm-all' )  ) {
		
		
		$muted_ids = get_muting( bp_loggedin_user_id() );
	
		if ( empty( $muted_ids ) ) {
			$loggedin_id['include'] = 0;
		} else {
			$loggedin_id['include'] = $muted_ids;
		}
	}
	return $loggedin_id;
}
/**
 * Render the ban screen in the members directory
 */
function bbpm_ban_all_screen(){
	// add_action( 'bp_after_member_plugin_template', 'mute_disable_members_loop_ajax' );

	add_action( 'bp_template_content', 'bbpm_all_sub_nav_screen_content');
    bp_core_load_template('members/single/plugins');
}

function bbpm_all_sub_nav_screen_content(){
	bp_get_template_part( 'members/members-loop' );
	
}

add_action('wp_footer','get_user_meta_ib');
function get_user_meta_ib(){
	$user_id = get_current_user_id();

	$logged_in_user = bp_loggedin_user_id();
	$user_profile_id = bp_displayed_user_id();

	echo $logged_in_user;
	
	global $post;
	echo $post->post_type;
	global $wpdb;

	$table_name = $wpdb->prefix . 'bbpm_ban_users';

	// SQL query to get table schema
	$query = "SELECT * from $table_name";

	// Get table schema
	$table_schema = $wpdb->get_results($query);

	echo "<table>";

	// echo "<pre>".print_r( $results,1 )."</pre>";
	foreach( $table_schema as $key => $value ){
		echo "<tr>
				<td>".$value->id."</td>
				<td>".$value->loggedin_id."</td>
				<td>".$value->profile_id."</td>
				<td>".$value->date_recorded."</td>



		</tr>";
	
	
	}
	
	
	echo "</table>";

	$excluded_ids = $wpdb->get_col( $wpdb->prepare( "SELECT profile_id FROM {$table_name} ") );
	
	$curr_user_id = get_current_user_id();

	if( in_array( 2764, $excluded_ids ) ){

		$excluded_ids = array_diff($excluded_ids, [$curr_user_id]);
		echo "<pre> excluded ".print_r( $excluded_ids,1 )."</pre>";
	}

		$banned_id = bbpm_is_banned( 89 );

		if( $banned_id->profile_id == 89 ){

	    	$echo = ' (Banned)';
		}else{

		}

}

// Hide shadow banned users topic if the topic is started by the user
function bbpm_exclude_topic_by_user_id( $query ) {
    // Check if the query is for bbPress topic archive


	global $post,$wpdb;
	 if ( !is_admin() && is_bbpress() && !empty( $post ) ) {
		$post_type = get_post_type($post->ID);

		// Hide the topics if the post type is forum, topic and the user view is not admin. Show all the users for admin
	    if( $post_type == 'forum' && $query->get('post_type') == 'topic' ){


			$table_name = $wpdb->prefix . 'bbpm_ban_users';

			$excluded_ids = $wpdb->get_col( $wpdb->prepare( "SELECT profile_id FROM {$table_name} ") );
	        
	        // Exclude topics by a specific user results obtained
	        $curr_user_id = get_current_user_id();
			if( in_array( $curr_user_id, $excluded_ids ) ){
				
				// Show the logged in user's topic to the user if he is logged in
				$excluded_ids = array_diff($excluded_ids, [$curr_user_id]);
	        	$query->set( 'author__not_in', $excluded_ids ); 
			}else{
				// Hide the topic from all the users
	        	$query->set( 'author__not_in', $excluded_ids ); 
				
			}
	        
	    }
    }
}
add_action( 'pre_get_posts', 'bbpm_exclude_topic_by_user_id' ,10,1);

Hide the users replies in topic archive
function bp_hide_user_replies(  $query = array() ){
  
    // Modify the query to exclude replies from the specified user
  	$bbPress_post_id = get_the_ID();
	$bbPress_post_type = get_post_type( $bbPress_post_id );

	global $post, $wpdb;
	
	if( $bbPress_post_type == 'topic' ){

		$table_name = $wpdb->prefix . 'bbpm_ban_users';

		$excluded_ids = $wpdb->get_col( $wpdb->prepare( "SELECT profile_id FROM {$table_name} ") ); 

		$curr_user_id = get_current_user_id();
		
		if ( in_array( $curr_user_id, $excluded_ids ) ) {
				
			// Show the logged in user's topic to the user if he is logged in
			$excluded_ids = array_diff($excluded_ids, [$curr_user_id]);
        	$query['author__not_in'] = $excluded_ids;

		} else {
			// Hide the topic from all the users
        	$query['author__not_in'] = $excluded_ids;

			
		}
		
	}
    
    return $query;
}

add_filter( 'bbp_has_replies_query', 'bp_hide_user_replies' );

function add_suffix_to_topic_starter_meta($author_link, $args, $author_id) {
    
    $reply_id = $args['post_id'];

    $topic_id = bbp_get_reply_topic_id($reply_id);

    // Get the topic author ID
    $topic_author_id = bbp_get_topic_author_id($topic_id);

    $banned_id = bbpm_is_banned( $topic_author_id );

   	if( $banned_id->profile_id == $topic_author_id ){

   		$suffix = " ( Banned )". $author_id;
        // Append the suffix to the author link
        $author_link .= $suffix;

    }

    return $author_link;
}

add_filter('bbp_get_topic_author_link', 'add_suffix_to_topic_starter_meta', 10, 3);

// Add a ban suffix to banned users
function add_suffix_to_bbpress_username( $author_name, $reply_id ) {
    // Define your suffix

	$banned_id = bbpm_is_banned( bp_displayed_user_id() );

	$user_id = bbp_get_reply_author_id( $reply_id );
    
    // Get the user object
    $user = get_userdata($user_id);

	if( in_array('administrator', $user->roles) && $banned_id->profile_id == bbp_get_reply_author_id( $reply_id )  ){

	    $suffix = '- (Banned)';
	}

    // Append the suffix to the display name
    return $author_name . $suffix;
}
add_filter('bbp_get_reply_author_display_name', 'add_suffix_to_bbpress_username', 10, 2);

function bbpm_ban_user() {
    // Check for nonce security
    check_ajax_referer('my-ajax-nonce', 'nonce');

    // profileID = The user's ID to be banned
    // loggedin_id = The user who is banning the user
    $profile_id = absint($_POST['profileID']);
    $loggedin_id = absint($_POST['loggedinID']);

    if( !empty( $profile_id ) &&  !empty( $loggedin_id ) ){

    	global $wpdb;

		// Your table name
		$table_name = $wpdb->prefix . 'bbpm_ban_users';

		// Data to insert
		$query = array(
			
		    'loggedin_id' => $loggedin_id,
		    'profile_id' => $profile_id,
		    
		   
		);

	
		// Insert data into the table
		$result = $wpdb->insert($table_name, $query );

		if ($result) {
		    // Data inserted successfully
		   
		    $data = array(
		        'message' => 'success',
		        'user_profile'=> $profile_id,
		        'loggedin_id'	=> $loggedin_id
		        
		    );
		} else {
		    // Error occurred
		  
		    $data = array(
		        'message' => 'error',
		        'user_profile'=> $user_profile_id,
		        'loggedin_id'	=> $loggedin_id,
		        'error'		=> $wpdb->last_error
		        
		    );
		}
	  
    }

    

    // Encode data to JSON
    $json_response = json_encode($data);

   	echo $json_response;

    // Always exit when done
    wp_die();
}
add_action('wp_ajax_bbpm_ban_user', 'bbpm_ban_user');

add_action('wp_ajax_bbpm_unban_user', 'bbpm_unban_user');
function bbpm_unban_user(){
	// Check for nonce security
    check_ajax_referer('my-ajax-nonce', 'nonce');

    // profileID = The user's ID to be banned
    // loggedin_id = The user who is banning the user
    $profile_id = absint($_POST['profileID']);
    $loggedin_id = absint($_POST['loggedinID']);

    if( !empty( $profile_id ) &&  !empty( $loggedin_id ) ){

    	global $wpdb;

		// Your table name
		$table_name = $wpdb->prefix . 'bbpm_ban_users';

		// Data to insert
		$where = array(
			
		    'loggedin_id' => $loggedin_id,
		    'profile_id' => $profile_id,
		    
		   
		);

	
		// Insert data into the table
		$deleted = $wpdb->delete($table_name, $where);

		if ($deleted) {
		    // Data inserted successfully
		   
		    $data = array(
		        'message' => 'success',
		        'user_profile'=> $profile_id,
		        'loggedin_id'	=> $loggedin_id
		        
		    );
		} else {
		    // Error occurred
		  
		    $data = array(
		        'message' => 'error',
		        'user_profile'=> $user_profile_id,
		        'loggedin_id'	=> $loggedin_id,
		        'error'		=> $wpdb->last_error
		        
		    );
		}
	  
    }

    

    // Encode data to JSON
    $json_response = json_encode($data);

   	echo $json_response;

    // Always exit when done
    wp_die();


}