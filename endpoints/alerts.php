<?php
/**
 * The Alerts WP REST API Endpoint
 *
 * Register and handle the Alerts WP REST API Endpoint.
 *
 * @package WeatherDotGov\API\Alerts
 * @since 0.1.0
 */

namespace WeatherDotGov\API\Alerts;
use WeatherDotGov\API\XML;

if ( ! defined( 'WPINC' ) ) {
	die;
}

/**
 * Register the "weather/alerts" endpoint.
 *
 * @since 0.1.0
 *
 * @return void
 */
function register_route() {
	register_rest_route( 'weather/alerts', '/(?P<zone_id>\S+)', array(
		'methods'  => 'GET',
		'callback' => __NAMESPACE__ . '\get_weather_alerts',
	) );
}

/**
 * Get Weather Alerts.
 *
 * The callback for the "weather/alerts" WP REST API endpoint.
 *
 * @since 0.1.0
 *
 * @param \WP_REST_Request $request Required.
 * @return XML\XML_Object Alert data.
 */
function get_weather_alerts( \WP_REST_Request $request ) {

	$zone_id = $request->get_param( 'zone_id' );

	if ( empty( $zone_id ) ) {
		return new \WP_Error( 'error', 'No Zone ID provided See: https://alerts.weather.gov/', $zone_id );
	}

	$cached_data = get_transient( 'weather-alerts-' . $zone_id );

	if ( ! empty( $cached_data ) ) {
		return $cached_data;
	}

	/**
	 * Filter the approved Zone IDs.
	 *
	 * @since 0.1.0
	 *
	 * @param boolean|string|array  Defaults to true. Can be a zone id string, or array of zone id strings.
	 */
	$approved_zone_ids = apply_filters( 'weather_alerts_approved_zone_ids', true );

	if ( true !== $approved_zone_ids && $zone_id !== $approved_zone_ids && ! in_array( $zone_id, $approved_zone_ids, true ) ) {
		return new \WP_Error( 'error', 'Zone ID not allowed. See: https://alerts.weather.gov/', $zone_id );
	}

	$response = wp_remote_get( 'http://alerts.weather.gov/cap/wwaatmget.php?' . http_build_query( array(
		/**
		 * Filter the Zone ID just before it is passed to alerts.weather.gov.
		 *
		 * @since 0.1.0
		 *
		 * @param string  $zone_id
		 */
		'x' => apply_filters( 'weather_alerts_sanitize_zone_id', $zone_id ),
	) ) );

	if ( ! is_array( $response ) ) {
		return new \WP_Error( 'error', 'Problem with request.', $response );
	}

	switch ( $response['body'] ) {
		case '? invalid arg x':
			$alert_data = new \WP_Error( 'error', 'Zone ID invalid. See: https://alerts.weather.gov/', $zone_id );
			break;
		default:
			$alert_data = XML\xml_to_object( $response['body'] );
			break;
	}

	$cache_expiration = ! empty( $alert_data->feed ) && ! empty( $alert_data->feed->updated ) && ! empty( $alert_data->feed->updated->text ) ? max( 600, HOUR_IN_SECONDS - ( time() - strtotime( $alert_data->feed->updated->text ) ) ) : HOUR_IN_SECONDS;

	set_transient( 'weather-alerts-' . $zone_id, $alert_data, $cache_expiration );

	return $alert_data;
}
