<?php
/**
 * Plugin Name: Weather.gov API
 * Plugin URI: http://github.com/kylereicks/weather-dot-gov-api/
 * Description: A wrapper for the Weather.gov API.
 * Author: Kyle Reicks
 * Version: 0.1.0
 * Author URI: http://github.com/kylereicks
 *
 * @package WeatherDotGov\API
 */

namespace WeatherDotGov\API;

if ( ! defined( 'WPINC' ) ) {
	die;
}

require_once( plugin_dir_path( __FILE__ ) . 'includes/xml.php' );
require_once( plugin_dir_path( __FILE__ ) . 'includes/zip-code.php' );
require_once( plugin_dir_path( __FILE__ ) . 'endpoints/alerts.php' );
require_once( plugin_dir_path( __FILE__ ) . 'endpoints/current-observations.php' );
require_once( plugin_dir_path( __FILE__ ) . 'endpoints/forecast-glance.php' );
require_once( plugin_dir_path( __FILE__ ) . 'endpoints/forecast.php' );

/**
 * Register REST Routes.
 *
 * Register the REST routs for the Alerts, Current Observations, Forecast, and Forecast Glance endpoints.
 *
 * @since 0.1.0
 *
 * @return void
 */
function register_rest_routes() {
	Alerts\register_route();
	CurrentObservations\register_route();
	Forecast\register_route();
	ForecastGlance\register_route();
}

add_action( 'rest_api_init', __NAMESPACE__ . '\register_rest_routes' );
