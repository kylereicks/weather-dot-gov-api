<?php
/**
 * XML Helper Functions
 *
 * Helper functions for working with XML.
 *
 * @package WeatherDotGov\API\XML
 * @since 0.1.0
 */

namespace WeatherDotGov\API\XML;

if ( ! defined( 'WPINC' ) ) {
	die;
}

/**
 * Parses a DOMDocument node into a object.
 *
 * @since 0.1.0
 */
class XML_Object {

	/**
	 * Construct the XML_Object object.
	 *
	 * @since 0.1.0
	 *
	 * @param object $node Required. DOMDocuemnt or DOMNode.
	 * @return void
	 */
	public function __construct( $node ) {

		if ( ! empty( $node->tagName ) || '#document' === $node->nodeName ) {
			if ( $node->hasAttributes() ) {
				$this->attributes = array();
				foreach ( $node->attributes as $attr ) {
					$this->attributes[ $attr->nodeName ] = $attr->nodeValue;
				}
			}
			if ( $node->hasChildNodes() ) {
				foreach ( $node->childNodes as $child_node ) {
					if ( ! empty( $child_node->tagName ) ) {
						if ( empty( $this->{$child_node->tagName} ) ) {
							$this->{$child_node->tagName} = new self( $child_node );
						} elseif ( is_array( $this->{$child_node->tagName} ) ) {
							$this->{$child_node->tagName}[] = new self( $child_node );
						} else {
							$this->{$child_node->tagName} = array( $this->{$child_node->tagName} );
							$this->{$child_node->tagName}[] = new self( $child_node );
						}
					} elseif ( '#text' === $child_node->nodeName && ! empty( trim( $child_node->textContent ) ) ) {
						$this->text = $child_node->textContent;
					}
				}
			}
		} elseif ( '#text' === $node->nodeName && ! empty( $node->textContent ) ) {
			$this->text = $node->textContent;
		}
	}
}

/**
 * Convert and XML string to an object.
 *
 * @since 0.1.0
 *
 * @param string $xml_string Required.
 * @return object XML_Object.
 */
function xml_to_object( string $xml_string ) {
	$xml_document = new \DOMDocument();
	$use_internal_errors = libxml_use_internal_errors( true );
	$xml_document->loadXML( $xml_string );

	$libxml_errors = libxml_get_errors();

	libxml_clear_errors();
	libxml_use_internal_errors( $use_internal_errors );

	/**
	 * Do something with libxml errors.
	 *
	 * @since 0.1.0
	 *
	 * @param array  $libxml_errors An array of LibXMLError objects, or an empty array.
	 */
	do_action( 'weather_libxml_errors', $libxml_errors );

	return new XML_Object( $xml_document );
}
