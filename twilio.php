<?php

/**
 * Plugin Name: Twilio for WordPress
 * Plugin URI: http://marcusbattle.com/plugins/twilio-for-wordpress
 * Description: Allows developers to extend the Twilio API into WordPress and build exciting communication based themes and plugins. Comes with SMS support to text users from your themes or plugins. VoIP coming soon.
 * Version: 0.1.0
 * Author: Marcus Battle
 * Author URI: http://marcusbattle.com/plugins
 * License: GPLv2 or later
 */

include_once('includes/twilio-php/Services/Twilio.php');
include_once('includes/activation.php');

/**
* Sends a standard text message to the supplied phone number
* @param $to | Recipient of sms message
* @param $message | Message to recipient
* @param $from | Twilio number for WordPress to send message from
* @return array | $response
* @since 0.1.0
*/
function twilio_send_sms( $to, $message, $from = '' ) { 

	$AccountSID = get_option( 'twilio_account_sid', '' );;
    $AuthToken = get_option( 'twilio_auth_token', '' );;

    if ( empty($from) ) 
    	$from = get_option( 'twilio_number', '' );

    $client = new Services_Twilio( $AccountSID, $AuthToken );

	$response = $client->account->messages->sendMessage(
		$from, // From a valid Twilio number
	  	$to, // Text this number
	  	$message
	);

	$response = json_decode( $response );

	return $response;

}


/**
* Builds the Twilio settings menus 
* @since 0.1.0
*/
function twilio_admin_menu() {
	add_options_page( 'Twilio', 'Twilio', 'manage_options', 'twilio', 'twilio_page_settings');
	add_submenu_page( NULL, 'Twilio Features', 'Twilio Features', 'manage_options', 'twilio-features', 'twilio_page_features');
	add_submenu_page( NULL, 'Twilio Developers', 'Twilio Developers', 'manage_options', 'twilio-api', 'twilio_page_api');
	add_submenu_page( NULL, 'Twilio SMS', 'Twilio SMS', 'manage_options', 'twilio-sms', 'twilio_page_sms');
	// add_submenu_page( NULL, 'Twilio Voice', 'Twilio Voice', 'manage_options', 'twilio-voice', 'twilio_page_voice');
	add_submenu_page( NULL, 'Twilio Support', 'Twilio Support', 'manage_options', 'twilio-support', 'twilio_page_support');
}

add_action( 'admin_menu', 'twilio_admin_menu' );


/**
* Displays the 'Home' page in settings
* @since 0.1.0
*/
function twilio_page_settings() {
	include_once( 'pages/settings.php' );
}


/**
* Displays the 'Home' page in settings
* @since 0.1.0
*/
function twilio_page_features() {
	include_once( 'pages/features.php' );
}


/**
* Displays the 'Developers' page in settings
* @since 0.1.0
*/
function twilio_page_api() {
	include_once( 'pages/api.php' );
}


/**
* Displays the 'SMS' page in settings
* @since 0.1.0
*/
function twilio_page_sms() {
	include_once( 'pages/sms.php' );
}


/**
* Displays the 'SMS' page in settings
* @since 0.1.0
*/
function twilio_page_support() {
	include_once( 'pages/support.php' );
}


/**
* Saves the settings from the options pages for Twilio
* @since 0.1.0
*/
function twilio_page_save_settings() {

	if ( isset($_GET['action']) && ( $_GET['action'] == 'update' ) ) {

		if ( $_GET['page'] == 'twilio' ) {
			
			update_option( 'twilio_account_sid', $_GET['accountSID'] );
			update_option( 'twilio_auth_token', $_GET['authToken'] );
			update_option( 'twilio_number', $_GET['twilio_number'] );

		}

		// Redirect back to settings page after processing
		$goback = add_query_arg( 'settings-updated', 'true',  wp_get_referer() );
		wp_redirect( $goback );

	}

}

add_action( 'init', 'twilio_page_save_settings' );


/**
* Adds a mobil phone field to each user profile
* @since 0.1.0
*/
function twilio_user_contactmethods( $user_contact ) {

	$user_contact['mobile'] = __('Mobile'); 

	return $user_contact;
}

add_filter('user_contactmethods', 'twilio_user_contactmethods');


/**
* Load the admin scripts to power plugin
* @since 0.1.0
*/
function twilio_admin_scripts() {

	wp_register_script( 'twilio', plugins_url( '/assets/js/twilio.admin.js', __FILE__ ), array('jquery'), '', true );
	wp_enqueue_script( 'twilio' );

	wp_register_script( 'ace-editor', plugins_url( '/assets/js/ace/src-min/ace.js', __FILE__ ), '', '' );
	wp_enqueue_script( 'ace-editor' );

}

add_action( 'admin_enqueue_scripts', 'twilio_admin_scripts' );


/**
* Setups up routing to process the callbacks from Twilio
* @since 0.1.0
*/
function twilio_callbacks() {

	$sms = $_REQUEST;
	
	if ( is_twilio() ) {

		// Define subscriber
		$subscriber = array(
			'mobile_number' => twilio_format_number($sms['From']),
			'mobile_city' => isset($sms['FromCity']) ? $sms['FromCity'] : '',
			'mobile_state' => isset($sms['FromState']) ? $sms['FromState'] : '',
			'mobile_zip' => isset($sms['FromZip']) ? $sms['FromZip'] : '',
			'mobile_country' => isset($sms['FromCountry']) ? $sms['FromCountry'] : ''
		);

		// Add subscriber 
		$subscriber_id = twilio_add_subscriber( $subscriber );

	}

	// Callback for Twilio SMS
	if ( is_twilio_sms() ) {
		
		$sms['Body'] = isset($sms['Body']) ? $sms['Body'] : '';
		$twixml = '';

		// do_action( 'twilio_sms_callback', $query, $twixml );
		$twixml = apply_filters( 'twilio_sms_callback', $twixml, $sms );

		header("Content-type: text/xml");

		echo "<?xml version=\"1.0\" encoding=\"UTF-8\"?>\n";
		echo "<Response>";
		echo $twixml;
		echo "</Response>";

		exit;

	// Callback for Twilio Voice
	} else if ( is_twilio_voice() ) {

		header("Content-type: text/xml");

		echo "<?xml version=\"1.0\" encoding=\"UTF-8\"?>";
		echo "<Response>";
		echo $twixml;
		echo "</Response>";

		exit;

	}

}

add_action( 'init', 'twilio_callbacks' );

/**
* Checks to see if Twilio was called
* @since 0.1.1
*/	
function is_twilio() {

	// Parse the uri, remove the site path if any and remove leading slash
	$uri = parse_url( $_SERVER['REQUEST_URI'] );
	$uri_path = str_replace( home_url( '', 'relative' ) , '', $uri['path'] );
	$uri_path = ltrim( $uri_path,'/' );

	$twilio_request = explode( '/', $uri_path );
	
	if ( $twilio_request && $twilio_request[0] == 'twilio' ) 
		return $twilio_request;
	else
		return false;

}

/**
* Checks to see if Twilio request was for SMS
* @since 0.1.1
*/
function is_twilio_sms() {

	if ( $twilio_request = is_twilio() ) {

		if ( isset($twilio_request[1]) && $twilio_request[1] == 'sms' )
			return true;
		else
			return false;

	}

}

/**
* Checks to see if Twilio request was for Voice 
* @since 0.1.1
*/
function is_twilio_voice() {

	if ( $twilio_request = is_twilio() ) {

		if ( isset($twilio_request[1]) && $twilio_request[1] == 'voice' )
			return true;
		else
			return false;
		
	}

}

/**
* Standard call back that converts SMS replies into a conversation
* @since 0.1.0
*/
function twilio_sms_callback( $twixml, $sms ) {

	return $twixml;
	
}

add_filter( 'twilio_sms_callback', 'twilio_sms_callback', 99, 2 );


/**
* Demo response for SMS callback. Activated by texting "DEMO" to Twilio Number
* @since 0.1.0
*/
function twilio_sms_callback_demo( $twixml, $sms ) {

	if ( strcasecmp( $sms['Body'], 'demo' ) == 0 ) {

		$site_name = get_bloginfo('name');
		$site_url = home_url();

		$twixml .= "<Message>You just received a text message from $site_name. Read more at $site_url</Message>";
	}

	return $twixml;
	
}

add_filter( 'twilio_sms_callback', 'twilio_sms_callback_demo', 98, 2 );

/**
* Adds a new Twilio subscriber 
* @since 0.1.1
*/
function twilio_add_subscriber( $subscriber ) {

	global $wpdb;

	if ( !$subscriber_id = twilio_subscriber_exists( $subscriber['mobile_number'] ) ) {

		$subscribers_table = $wpdb->prefix . "twilio_subscribers";

		$subscriber_id = $wpdb->insert(
			$subscribers_table,
			$subscriber
		);

		return $subscriber_id;

	}

	return false;

}

/**
* Checks to see if mobile number is already subscriberd 
* @since 0.1.1
*/
function twilio_subscriber_exists( $mobile_number ) {

	global $wpdb;

	$mobile_number = twilio_format_number( $mobile_number );

	$subscribers_table = $wpdb->prefix . "twilio_subscribers";

	$subscriber = $wpdb->get_row("SELECT * FROM $subscribers_table WHERE mobile_number = '$mobile_number'");

	return $subscriber;

}

/**
* Converts mobile number to E.164
* @since 0.1.1
*/
function twilio_format_number( $mobile_number, $format = 'E.164' ) {

	if ( $format == 'E.164' ) {
		
		$mobile_number = trim(str_replace( array('(',')','-'), '', $mobile_number ));

		if ( stripos( $mobile_number, '+' ) !== 0 )
			$mobile_number = '+' . $mobile_number;

	}

	return $mobile_number;

}