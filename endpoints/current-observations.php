<?php
/**
 * The Current Observations WP REST API Endpoint
 *
 * Register and handle the Current Observations WP REST API Endpoint.
 *
 * @package WeatherDotGov\API\CurrentObservations
 * @since 0.1.0
 */

namespace WeatherDotGov\API\CurrentObservations;
use WeatherDotGov\API\XML;

if ( ! defined( 'WPINC' ) ) {
	die;
}

/**
 * Register the "weather/current-observations" endpoint.
 *
 * @since 0.1.0
 *
 * @return void
 */
function register_route() {
	register_rest_route( 'weather/current-observations', '/(?P<reporting_station>\S+)', array(
		'methods'  => 'GET',
		'callback' => __NAMESPACE__ . '\get_weather_current_observations',
	) );
}

/**
 * Get Current Weather Observations.
 *
 * The callback for the "weather/current-observations" WP REST API endpoint.
 *
 * @since 0.1.0
 *
 * @param \WP_REST_Request $request Required.
 * @return XML\XML_Object Alert data.
 */
function get_weather_current_observations( \WP_REST_Request $request ) {

	$reporting_station = $request->get_param( 'reporting_station' );

	if ( empty( $reporting_station ) ) {
		return new \WP_Error( 'error', 'No Reporting Station provided See: http://w1.weather.gov/xml/current_obs/seek.php', $reporting_station );
	}

	$cached_data = get_transient( 'weather-current-observations-' . $reporting_station );

	if ( ! empty( $cached_data ) ) {
		return $cached_data;
	}

	/**
	 * Filter the approved Reporting Stations.
	 *
	 * @since 0.1.0
	 *
	 * @param boolean|string|array  Defaults to true. Can be a reporting station string, or array of reporting stations strings.
	 */
	$approved_reporting_stations = apply_filters( 'weather_current_observations_approved_reporting_stations', true );

	if ( true !== $approved_reporting_stations && $reporting_station !== $approved_reporting_stations && ! in_array( $reporting_station, $approved_reporting_stations, true ) ) {
		return new \WP_Error( 'error', 'Reporting Station not allowed. See: http://w1.weather.gov/xml/current_obs/seek.php', $reporting_station );
	}

	/**
	 * Filter the reporting station just before it is passed to w1.weather.gov.
	 *
	 * @since 0.1.0
	 *
	 * @param string  $zone_id
	 */
	$response = wp_remote_get( esc_url_raw( 'http://w1.weather.gov/xml/current_obs/' . apply_filters( 'weather_current_observations_sanitize_reporting_station', $reporting_station ) . '.xml' ) );

	if ( ! is_array( $response ) ) {
		return new \WP_Error( 'error', 'Problem with request.', $response );
	}

	switch ( $response['response']['code'] ) {
		case 404:
			$current_observation_data = new \WP_Error( 'error', 'Reporting Station not found. See: http://w1.weather.gov/xml/current_obs/seek.php', $reporting_station );
			break;
		default:
			$current_observation_data = XML\xml_to_object( $response['body'] );
			break;
	}

	$cache_expiration = ! empty( $current_observation_data->current_observation ) && ! empty( $current_observation_data->current_observation->observation_time_rfc822 ) && ! empty( $current_observation_data->current_observation->observation_time_rfc822->text ) ? max( 600, HOUR_IN_SECONDS - ( time() - strtotime( $current_observation_data->current_observation->observation_time_rfc822->text ) ) ) : HOUR_IN_SECONDS;

	set_transient( 'weather-current-observations-' . $reporting_station, $current_observation_data, $cache_expiration );

	return $current_observation_data;
}
