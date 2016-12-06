<?php
/**
 * The Forecast WP REST API Endpoint
 *
 * Register and handle the Forecast WP REST API Endpoint.
 *
 * @package WeatherDotGov\API\Forecast
 * @since 0.1.0
 */

namespace WeatherDotGov\API\Forecast;
use WeatherDotGov\API\XML;
use WeatherDotGov\API\ZipCode;

if ( ! defined( 'WPINC' ) ) {
	die;
}

/**
 * Register the "weather/forecast" endpoint.
 *
 * @since 0.1.0
 *
 * @return void
 */
function register_route() {
	register_rest_route( 'weather/forecast', '/(?P<zip_code>\S+)', array(
		'methods'  => 'GET',
		'callback' => __NAMESPACE__ . '\get_weather_forecast',
	) );
}

/**
 * Get Weather Forecast.
 *
 * The callback for the "weather/forecast" WP REST API endpoint.
 *
 * @since 0.1.0
 *
 * @param \WP_REST_Request $request Required.
 * @return XML\XML_Object Alert data.
 */
function get_weather_forecast( \WP_REST_Request $request ) {

	$zip_code = $request->get_param( 'zip_code' );

	if ( ! ZipCode\is_zip_code( $zip_code ) ) {
		return new WP_Error( 'error', 'Zip code provided does not validate.', $zip_code );
	}

	$cached_data = get_transient( 'weather-forecast-' . $zip_code );

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
	$approved_zip_codes = apply_filters( 'weather_forecast_approved_zip_codes', true );

	if ( true !== $approved_zip_codes && $zip_code !== $approved_zip_codes && ! in_array( $zip_code, $approved_zip_codes, true ) ) {
		return new WP_Error( 'error', 'Zip code not allowed.', $zip_code );
	}

	/**
	 * Filter the Zip Code just before it is passed to weather.gov.
	 *
	 * @since 0.1.0
	 *
	 * @param string  $zip_code
	 */
	$latitude_longitude_object = ZipCode\zip_code_to_latitude_longitude( apply_filters( 'weather_forecast_sanitize_zip_code', apply_filters( 'weather_sanitize_zip_code', $zip_code ) ) );

	if ( false === $latitude_longitude_object ) {
		return new WP_Error( 'error', 'Latitude-Longitude lookup unsuccessful.', array( 'zip_code' => $zip_code, 'lookup' => $latitude_longitude_object ) );
	}

	$forecast_soap_client = new \SoapClient( 'http://graphical.weather.gov/xml/DWMLgen/wsdl/ndfdXML.wsdl', array(
		'trace' => 1,
		'stream_context' => stream_context_create( array( 'http' => array( 'protocol_version' => 1.0 ) ) ),
	) );

	$forecast_data = XML\xml_to_object($forecast_soap_client->__soapCall( 'NDFDgen', array(
		'latitude' => $latitude_longitude_object->latitude,
		'longitude' => $latitude_longitude_object->longitude,
		/**
		 * Filter the product to be requested.
		 *
		 * @since 0.1.0
		 *
		 * @param string "time-series" or "glance". See http://graphical.weather.gov/xml/docs/
		 */
		'product' => apply_filters( 'weather_forecast_weather_product', 'time-series' ),
		/**
		 * Filter the unit to be requested.
		 *
		 * @since 0.1.0
		 *
		 * @param string "e" or "m". See http://graphical.weather.gov/xml/docs/
		 */
		'Unit' => apply_filters( 'weather_forecast_weather_unit', 'e' ),
		/**
		 * Filter the start time to be requested.
		 *
		 * @since 0.1.0
		 *
		 * @param string Date string. An empty string defaults to the earliest available. See http://graphical.weather.gov/xml/docs/
		 */
		'startTime' => apply_filters( 'weather_forecast_weather_starttime', '' ),
		/**
		 * Filter the end time to be requested.
		 *
		 * @since 0.1.0
		 *
		 * @param string Date string. An empty string defaults to the latest available. See http://graphical.weather.gov/xml/docs/
		 */
		'endTime' => apply_filters( 'weather_forecast_weather_endtime', '' ),
		/**
		 * Filter the weatherParameters to be requested.
		 *
		 * @since 0.1.0
		 *
		 * @param array See http://graphical.weather.gov/xml/docs/
		 */
		'weatherParameters' => apply_filters( 'weather_forecast_weather_parameters', array(
			'maxt' => true,
			'mint' => true,
			'temp' => true,
			'dew' => true,
			'appt' => true,
			'pop12' => true,
			'qpf' => true,
			'snow' => true,
			'sky' => true,
			'rh' => true,
			'wspd' => true,
			'wdir' => true,
			'wx' => true,
			'icons' => true,
			'waveh' => true,
			'incw34' => true,
			'incw50' => true,
			'incw64' => true,
			'cumw34' => true,
			'cumw50' => true,
			'cumw64' => true,
			'wgust' => true,
			'critfireo' => true,
			'dryfireo' => true,
			'conhazo' => true,
			'ptornado' => true,
			'phail' => true,
			'ptstmwinds' => true,
			'pxtornado' => true,
			'pxhail' => true,
			'pxtstmwinds' => true,
			'ptotsvrtstm' => true,
			'pxtotsvrtstm' => true,
			'tmpabv14d' => true,
			'tmpblw14d' => true,
			'tmpabv30d' => true,
			'tmpblw30d' => true,
			'tmpabv90d' => true,
			'tmpblw90d' => true,
			'prcpabv14d' => true,
			'prcpblw14d' => true,
			'prcpabv30d' => true,
			'prcpblw30d' => true,
			'prcpabv90d' => true,
			'prcpblw90d' => true,
			'precipa_r' => true,
			'sky_r' => true,
			'td_r' => true,
			'temp_r' => true,
			'wdir_r' => true,
			'wspd_r' => true,
			'wwa' => true,
			'iceaccum' => true,
			'maxrh' => true,
			'minrh' => true,
		) ),
	)));

	$cache_expiration = ! empty( $forecast_data->dwml ) && ! empty( $forecast_data->dwml->head ) && ! empty( $forecast_data->dwml->head->product ) && ! empty( $forecast_data->dwml->head->product->{'creation-date'} ) && ! empty( $forecast_data->dwml->head->product->{'creation-date'}->text ) ? max( 600, HOUR_IN_SECONDS - ( time() - strtotime( $forecast_data->dwml->head->product->{'creation-date'}->text ) ) ) : HOUR_IN_SECONDS;

	set_transient( 'weather-forecast-' . $zip_code, $forecast_data, $cache_expiration );

	return $forecast_data;
}
