<?php
/**
 * The Forecast Glance WP REST API Endpoint
 *
 * Register and handle the Forecast Glance WP REST API Endpoint.
 *
 * @package WeatherDotGov\API\ForecastGlance
 * @since 0.1.0
 */

namespace WeatherDotGov\API\ForecastGlance;
use WeatherDotGov\API\XML;
use WeatherDotGov\API\ZipCode;

if ( ! defined( 'WPINC' ) ) {
	die;
}

/**
 * Register the "weather/forecast-glance" endpoint.
 *
 * @since 0.1.0
 *
 * @return void
 */
function register_route() {
	register_rest_route( 'weather/forecast-glance', '/(?P<zip_code>\S+)', array(
		'methods'  => 'GET',
		'callback' => __NAMESPACE__ . '\get_weather_forecast_glance',
	) );
}

/**
 * Get Weather Forecast Glance.
 *
 * The callback for the "weather/forecast-glance" WP REST API endpoint.
 *
 * @since 0.1.0
 *
 * @param \WP_REST_Request $request Required.
 * @return XML\XML_Object Alert data.
 */
function get_weather_forecast_glance( \WP_REST_Request $request ) {

	$zip_code = $request->get_param( 'zip_code' );

	if ( ! ZipCode\is_zip_code( $zip_code ) ) {
		return new \WP_Error( 'error', 'Zip code provided does not validate.', $zip_code );
	}

	$cached_data = get_transient( 'weather-forecast-glance-' . $zip_code );

	if ( ! empty( $cached_data ) ) {
		return $cached_data;
	}

	/**
	 * Filter the approved Zip Codes.
	 *
	 * @since 0.1.0
	 *
	 * @param boolean|string|array  Defaults to true. Can be a zip code string, or array of zip code strings.
	 */
	$approved_zip_codes = apply_filters( 'weather_forecast_glance_approved_zip_codes', true );

	if ( true !== $approved_zip_codes && $zip_code !== $approved_zip_codes && ! in_array( $zip_code, $approved_zip_codes, true ) ) {
		return new \WP_Error( 'error', 'Zip code not allowed.', $zip_code );
	}

	/**
	 * Filter the Zip Code just before it is passed to weather.gov.
	 *
	 * @since 0.1.0
	 *
	 * @param string  $zip_code
	 */
	$latitude_longitude_object = ZipCode\zip_code_to_latitude_longitude( apply_filters( 'weather_forecast_glance_sanitize_zip_code', apply_filters( 'weather_sanitize_zip_code', $zip_code ) ) );

	if ( false === $latitude_longitude_object ) {
		return new \WP_Error( 'error', 'Latitude-Longitude lookup unsuccessful.', array( 'zip_code' => $zip_code, 'lookup' => $latitude_longitude_object ) );
	}

	$response = wp_remote_get( 'http://forecast.weather.gov/MapClick.php?' . http_build_query( array(
		'lat' => $latitude_longitude_object->latitude,
		'lon' => $latitude_longitude_object->longitude,
		/**
		 * Filter the forecast unit to be requested.
		 *
		 * @since 0.1.0
		 *
		 * @param int 0 for English or 1 for Metric.
		 */
		'unit' => apply_filters( 'weather_forecast_glance_unit', 0 ),
		/**
		 * Filter the language requested.
		 *
		 * @since 0.1.0
		 *
		 * @param string Default is "english".
		 */
		'lg' => apply_filters( 'weather_forecast_glance_lg', 'english' ),
		'FcstType' => 'xml',
	) ) );

	if ( ! is_array( $response ) ) {
		return new \WP_Error( 'error', 'Problem with request.', $response );
	}

	$forecast_glance_data = XML\xml_to_object( $response['body'] );

	$cache_expiration = ! empty( $forecast_glance_data->Forecast ) && ! empty( $forecast_glance_data->Forecast->creationTime ) && ! empty( $forecast_glance_data->Forecast->creationTime->text ) ? max( 600, HOUR_IN_SECONDS - ( time() - strtotime( $forecast_glance_data->Forecast->creationTime->text ) ) ) : HOUR_IN_SECONDS;

	set_transient( 'weather-forecast-glance-' . $zip_code, $forecast_glance_data, $cache_expiration );

	return $forecast_glance_data;
}
