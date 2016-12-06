<?php
/**
 * Zip code Helper Functions
 *
 * Helper functions for working with zip codes.
 *
 * @package WeatherDotGov\API\ZipCode
 * @since 0.1.0
 */

namespace WeatherDotGov\API\ZipCode;
use WeatherDotGov\API\XML;

if ( ! defined( 'WPINC' ) ) {
	die;
}

/**
 * Converts an XML_Object into a simple object with latitude and longitude data.
 *
 * @since 0.1.0
 */
class Latitude_Longitude {

	/**
	 * A latitude value.
	 *
	 * @since 0.1.0
	 * @access public
	 * @var string $latitude A string representing a float value -90 to 90.
	 */
	public $latitude;

	/**
	 * A longitude value.
	 *
	 * @since 0.1.0
	 * @access public
	 * @var string $longitude A string representing a float value -180 to 180.
	 */
	public $longitude;

	/**
	 * Construct the Latitude_Longitude object.
	 *
	 * @since 0.1.0
	 *
	 * @param XML\XML_Object $weather_latitude_longitude_soap_response Required. XML_Object.
	 * @return void
	 */
	public function __construct( XML\XML_Object $weather_latitude_longitude_soap_response ) {
		if ( ! empty( $weather_latitude_longitude_soap_response )
		&& ! empty( $weather_latitude_longitude_soap_response->dwml )
		&& ! empty( $weather_latitude_longitude_soap_response->dwml->latLonList )
		&& ! empty( $weather_latitude_longitude_soap_response->dwml->latLonList->text ) ) {
			$latitude_longitude_array = explode( ',', $weather_latitude_longitude_soap_response->dwml->latLonList->text );
			if ( 2 === count( $latitude_longitude_array ) ) {
				$this->latitude = $latitude_longitude_array[0];
				$this->longitude = $latitude_longitude_array[1];
			}
		}
	}
}

/**
 * Validate a zip code.
 *
 * Checks for a 5 digit zip code.
 *
 * @since 0.1.0
 *
 * @param string|int $zip_code Required.
 * @return boolean
 */
function is_zip_code( $zip_code ) {
	/**
	 * Filter the default zip code regular expression.
	 *
	 * @since 0.1.0
	 *
	 * @param string  $regex Default matches a 5 digit number.
	 */
	if ( preg_match( apply_filters( 'weather_zip_code_regex', '/\d{5}/' ), $zip_code ) ) {
		return true;
	}

	return false;
}

/**
 * Convert a zip code to a Latitude_Longitude object.
 *
 * @since 0.1.0
 *
 * @param string|int $zip_code Required.
 * @return object Latitude_Longitude
 */
function zip_code_to_latitude_longitude( $zip_code ) {

	$cached_data = get_transient( 'weather-zip-code-lat-lon-' . $zip_code );

	if ( false !== $cached_data ) {
		return $cached_data;
	}

	if ( ! is_zip_code( $zip_code ) ) {
		return false;
	}

	$weather_soap_client = new \SoapClient( 'http://graphical.weather.gov/xml/DWMLgen/wsdl/ndfdXML.wsdl' );

	$latitude_longitude_object = new Latitude_Longitude( XML\xml_to_object( $weather_soap_client->__soapCall( 'LatLonListZipCode', array( 'zipCodeList' => $zip_code ) ) ) );

	if ( empty( $latitude_longitude_object ) || empty( $latitude_longitude_object->latitude ) || empty( $latitude_longitude_object->longitude ) ) {
		return false;
	}

	$cached_data = set_transient( 'weather-zip-code-lat-lon-' . $zip_code, $latitude_longitude_object );

	return $latitude_longitude_object;
}
