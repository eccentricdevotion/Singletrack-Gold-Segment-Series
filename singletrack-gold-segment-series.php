<?php
/**
 * Plugin Name: Singletrack Gold Segment Series
 * Plugin URI: https://github.com/eccentricdevotion/Singletrack-Gold-Segment-Series
 * Description: Display monthly segment leaderboards from Strava.
 * Version: 0.0.2
 * Author: Eccentric Devotion & Graeme Woodward
 * Author URI: https://github.com/eccentricdevotion
 * License: GPL2
 */

//**********************************************************************************************
//*
//*  Main Widget function
//*
//**********************************************************************************************

function register_singletrack_gold_widget() {
	register_widget( 'Singletrack_Gold_Segments_Widget' );
}


// Initialise settings when plugin is activated
function singletrack_gold_activate() {
	add_option( 'singletrack_gold_num_activities', 200 );
	add_option( 'singletrack_gold_timezone', 'Pacific/Auckland' );
}

function singletrack_gold_add_stylesheet() {
	// Respects SSL, Style.css is relative to the current file
	wp_register_style( 'prefix-style', plugins_url( 'singletrack-gold-segment-series.css', __FILE__ ) );
	wp_enqueue_style( 'prefix-style' );
}


class Singletrack_Gold_Segments_Widget extends WP_Widget {

	function Singletrack_Gold_Segments_Widget() {
		$singletrack_gold_widget_options = array( 'classname' => 'singletrack_gold_clubs_widget', 'description' => __( 'Display your club\'s recent Strava activities', 'singletrack-gold' ) );
		// widget code goes here
		parent::WP_Widget( false, $name = 'Singletrack Gold Segment Series', $singletrack_gold_widget_options );
	}

	public

	function widget( $args, $instance ) {

		$singletrack_gold_strava_authid = get_option( "singletrack_gold_auth" );
		$singletrack_gold_strava_clubid = get_option( "singletrack_gold_club" );
		$singletrack_gold_strava_segment_ids = array( "Greenwood Climb" => 18563649,
			"Rapaki Climb" => 18593281,
			"Kennedys Climb" => 18897478,
			"Crocodile XC" => 19310391,
			"Living Springs XC" => 18563613,
			"McLeans Island XC" => 18607655,
			"Port Hills XC" => 18792223,
			"Witches Hill Sprint" => 18556346,
			"Taramea Sprint" => 18593289,
			"Bowenvale Sprint" => 18554063
		);

		extract( $args );

		foreach ( $singletrack_gold_strava_segment_ids as $k => $v ) {
			if ( $v != -1 ) {
				echo "\n".'<div class="singletrack_gold_div">';
				if ( empty( $singletrack_gold_strava_authid ) ) {
					echo "<br>No Strava authorisation code entered<br>";
				} elseif ( empty( $singletrack_gold_strava_clubid )or $singletrack_gold_strava_clubid == -1 ) {
					echo "<br>No Strava club found<br>";
				} else {
					echo "\n".'<table class="singletrack_gold_activities">';
					// Display segment name
					echo "\n".'<tr><td colspan="3" class="singletrack_gold_segment">';
					echo "\n".'<a href="https://www.strava.com/segments/' . $v . '" target="_blank">' . $k . '</a>';
					echo "</td></tr>";
					echo "\n".'<tr><th class="singletrack_gold_th">Who</th><th class="singletrack_gold_th">When</th><th class="singletrack_gold_th">Elapsed Time</th></tr>';
					// Display club's latest activities
					$singletrack_gold_output_men = singletrack_gold_call_strava( "https://www.strava.com/api/v3/segments/" . $v . "/leaderboard?club_id=" . $singletrack_gold_strava_clubid . "&date_range=this_month&gender=M&access_token=9f90aad7789de13bd286223d5eabb7aff7023234" );
					$singletrack_gold_output_women = singletrack_gold_call_strava( "https://www.strava.com/api/v3/segments/" . $v . "/leaderboard?club_id=" . $singletrack_gold_strava_clubid . "&date_range=this_month&gender=F&access_token=9f90aad7789de13bd286223d5eabb7aff7023234" );
					echo "\n".'<tr><td colspan="3" class="singletrack_gold_segment">Men</td></tr>';
					singletrack_gold_getActivities( $singletrack_gold_output_men );
					echo "\n".'<tr><td colspan="3" class="singletrack_gold_segment">Women</td></tr>';
					singletrack_gold_getActivities( $singletrack_gold_output_women );
					echo "\n</table>";
				}
				echo "\n</div>";
			}
		}
	} // of widget

	public

	function form( $instance ) {

	}
} // of widget class


//**********************************************************************************************
//*
//*  Curl functions
//*
//**********************************************************************************************

function singletrack_gold_call_strava( $singletrack_gold_url ) {
	if ( singletrack_gold_is_curl_installed() ) {

		$singletrack_gold_access_key = get_option( 'singletrack_gold_auth' );

		$singletrack_gold_params = array( 'per_page' => get_option( 'singletrack_gold_num_activities', '100' ) );
		$singletrack_gold_url .= '?' . http_build_query( $singletrack_gold_params );

		$singletrack_gold_curl = curl_init();
		curl_setopt( $singletrack_gold_curl, CURLOPT_URL, $singletrack_gold_url );
		curl_setopt( $singletrack_gold_curl, CURLOPT_HTTPHEADER, array( 'Authorization: Bearer ' . $singletrack_gold_access_key ) );
		//curl_setopt($singletrack_gold_curl, CURLOPT_VERBOSE, true);
		curl_setopt( $singletrack_gold_curl, CURLOPT_RETURNTRANSFER, true );
		curl_setopt( $singletrack_gold_curl, CURLOPT_SSL_VERIFYPEER, false );

		$singletrack_gold_output = curl_exec( $singletrack_gold_curl );

		if ( $singletrack_gold_output === false ) {
			echo 'Curl error: ' . curl_error( $singletrack_gold_curl ) . " * " . $singletrack_gold_url . " * " . $singletrack_gold_access_key . " *";
		} else {
			$singletrack_gold_json = json_decode( $singletrack_gold_output, true );
		}
		curl_close( $singletrack_gold_curl );

	} else {
		echo "cURL is NOT installed on this server";
	}
	return $singletrack_gold_json;

}

// Check if curl is installed
function singletrack_gold_is_curl_installed() {
	if ( in_array( 'curl', get_loaded_extensions() ) ) {
		return true;
	} else {
		return false;
	}
}


//**********************************************************************************************
//*
//*  Parse Strava API functions
//*
//**********************************************************************************************


function singletrack_gold_getactivities( $json ) {

	$entries = $json[ "entries" ];
	foreach ( $entries as $key => $values ) {
		echo "\n<tr>";
		echo "<td>" . $values[ "athlete_name" ] . "</td>";
		echo "<td>" . format_date( $values[ 'start_date_local' ] ) . "</td>";
		echo "<td>" . seconds_to_time( $values[ 'elapsed_time' ] ) . "</td>";
		echo "</tr>";
	}
}


//**********************************************************************************************
//*
//*  General functions
//*
//**********************************************************************************************

// get a nice date
function format_date( $singletrack_gold_strava_datetime ) {
	$singletrack_gold_timezone = get_option( 'singletrack_gold_timezone' );
	date_default_timezone_set( $singletrack_gold_timezone );
	$singletrack_gold_act_datetime = new DateTime( $singletrack_gold_strava_datetime );
	return $singletrack_gold_act_datetime->format( 'M j, Y' );
}

// convert seconds into hours, minutes, seconds
function seconds_to_time( $seconds ) {
	return gmdate( "H:i:s", $seconds );
}

function singletrack_gold_create_timezone_list() {
	$timezones = DateTimeZone::listAbbreviations( DateTimeZone::ALL );
	$allzones = array();
	foreach ( $timezones as $key => $zones ) {
		foreach ( $zones as $id => $zone ) {
			$allzones[ $zone[ 'timezone_id' ] ] = $zone[ 'timezone_id' ];
		}
	}
	$allzones = array_unique( $allzones );
	asort( $allzones );
	return $allzones;
}

//**********************************************************************************************
//*
//*  Admin page  http://kovshenin.com/2012/the-wordpress-settings-api/
//*
//**********************************************************************************************
function singletrack_gold_admin_menu() {
	add_options_page( 'Singletrack Gold Segment Series Options', 'Singletrack Gold Segment Series', 'manage_options', 'singletrack_gold_slug', 'singletrack_gold_display_settings' );
}

function singletrack_gold_admin_init() {

	if ( !current_user_can( 'manage_options' ) ) {
		wp_die( __( 'You do not have sufficient permissions to access this page.' ) );
	}

	register_setting( 'singletrack-gold-settings-group', 'singletrack_gold_auth' );
	register_setting( 'singletrack-gold-settings-group', 'singletrack_gold_club' );
	register_setting( 'singletrack-gold-settings-group', 'singletrack_gold_num_activities' );
	register_setting( 'singletrack-gold-settings-group', 'singletrack_gold_timezone' );

	// Club settings setion
	add_settings_section( 'club', __( 'Club settings' ), 'club_callback', 'singletrack_gold_slug' );

	// Get clubs
	$clubs_json = singletrack_gold_call_strava( "https://www.strava.com/api/v3/athlete/clubs?access_token=9f90aad7789de13bd286223d5eabb7aff70232" );
	// Get club and and count members
	foreach ( $clubs_json as $key => $values ) {
		if ( $values[ 'message' ] == '' ) {
			$allclubs[ $values[ "id" ] ] = $values[ "name" ];
		} else {
			$allclubs = array( -1 => 'No clubs found on your Strava account' );
		}
	}

	add_settings_field( 'club_display', __( 'Select club to display :' ), 'singletrack_gold_dropdown_input', 'singletrack_gold_slug', 'club',
		array( 'name' => 'singletrack_gold_club', 'options' => $allclubs )
	);

	for ( $i = 5; $i <= 30; $i += 5 ) {
		$selectable_vals[ $i ] = $i;
	}
	add_settings_field( 'num_acts', __( 'Number of activities displayed :' ), 'singletrack_gold_dropdown_input', 'singletrack_gold_slug', 'club',
		array( 'name' => 'singletrack_gold_num_activities', 'options' => $selectable_vals )
	);

	// General settings setion
	add_settings_section( 'general', __( 'General settings' ), '', 'singletrack_gold_slug' );
	add_settings_field( 'timezone', __( 'Select your time zone :' ), 'singletrack_gold_dropdown_input', 'singletrack_gold_slug', 'general',
		array( 'name' => 'singletrack_gold_timezone', 'options' => singletrack_gold_create_timezone_list() )
	);
}

function club_callback() {
	echo _e( 'Select the club you want to display on the widget' );
}

function singletrack_gold_display_settings() {
	?>
	<div class="wrap">
		<div id="icon-options-general" class="icon32">
			<br>
		</div>
		<h2>Singletrack Gold Segment Series Options</h2>
		<form action="options.php" method="POST">
			<?php
			settings_fields( 'singletrack-gold-settings-group' );
			$access_key = get_option( 'singletrack_gold_auth' );

			if ( empty( $access_key ) ) {
				$hide_settings_class = "display:none;";
			} else {
				$hide_settings_class = "display:block;";
			}
			singletrack_gold_auth_form( $access_key );
			echo "<div style='" . $hide_settings_class . "'>";
			do_settings_sections( 'singletrack_gold_slug' );
			echo "</div>";
			submit_button();
			?>
		</form>
	</div>
	<?php
}

function singletrack_gold_text_input( $args ) {
	$name = esc_attr( $args[ 'name' ] );
	$value = esc_attr( $args[ 'value' ] );
	$size = esc_attr( $args[ 'size' ] );
	echo "<input type='text' name='$name' value='$value' size='$size' />";
}

function singletrack_gold_radio_input( $args ) {
	$name = esc_attr( $args[ 'name' ] );
	$options = $args[ 'options' ];
	foreach ( $options as $opt ) {
		echo "<input type='radio' name='$name' value='$opt'";
		if ( get_option( $name ) == $opt ) {
			echo ' checked ';
		}
		echo ">&nbsp;&nbsp;$opt&nbsp;&nbsp;";
	}
}

function singletrack_gold_dropdown_input( $args ) {
	$name = esc_attr( $args[ 'name' ] );
	$options = $args[ 'options' ];
	echo "<select name=\"$name\">";
	echo "<option value=\"-1\">Please select...</option>";
	foreach ( $options as $id => $clubname ) {
		echo "<option value=\"$id\"";
		if ( get_option( $name ) == $id ) {
			echo ' selected ';
		}
		echo ">$clubname</option>";
	}
	echo "</select>";
}

function singletrack_gold_auth_form( $access_key ) {

	echo "<h3>Strava Authorisation</h3>";
	echo "Enter your Strava access token here.";
	echo "<table class=\"form-table\">";
	echo "<tr valign=\"top\"><th scope=\"row\">Your Strava Access Token :</th>";
	echo "<td><input type=\"text\" name='singletrack_gold_auth' value='" . $access_key . "' size='60'/></td>";
	echo "</tr></table>";

}

function singletrack_gold_plugin_action_links( $links, $file ) {
	static $this_plugin;

	if ( !$this_plugin ) {
		$this_plugin = plugin_basename( __FILE__ );
	}

	// check to make sure we are on the correct plugin
	if ( $file == $this_plugin ) {
		// the anchor tag and href to the URL we want. For a "Settings" link, this needs to be the url of your settings page
		$settings_link = '<a href="options-general.php?page=singletrack_gold_slug">Settings</a>';
		// add the link to the list
		array_unshift( $links, $settings_link );
	}

	return $links;
}

function singletrack_gold_sanitize_textinput( $input ) {
	return sanitize_text_field( $input );
}

//**********************************************************************************************
//*
//*  Initialise
//*
//**********************************************************************************************
add_action( 'widgets_init', 'register_singletrack_gold_widget' );
add_filter( 'plugin_action_links', 'singletrack_gold_plugin_action_links', 10, 2 );
add_action( 'admin_menu', 'singletrack_gold_admin_menu' );
add_action( 'admin_init', 'singletrack_gold_admin_init' );
register_activation_hook( __FILE__, 'singletrack_gold_activate' );
add_action( 'wp_enqueue_scripts', 'singletrack_gold_add_stylesheet' );
?>
