<?php
/**
 * IndexNow (Bing) API client.
 *
 * @package DH\IndexNow
 */

namespace DH\IndexNow;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Handles URL submission to the IndexNow API endpoint.
 */
class IndexNow_Api {

	private const ENDPOINT = 'https://api.indexnow.org/indexnow';

	/**
	 * Submit a batch of URLs to IndexNow.
	 *
	 * @param array  $urls      List of URLs to submit.
	 * @param string $api_key   The IndexNow API key.
	 * @param int    $batch_size Maximum URLs per request.
	 * @return array Array of result arrays with keys: urls, http_code, response, success.
	 */
	public static function submit( array $urls, string $api_key, int $batch_size = 100 ): array {
		$results = array();
		$host    = wp_parse_url( home_url(), PHP_URL_HOST );
		$batches = array_chunk( $urls, $batch_size );

		foreach ( $batches as $batch ) {
			$payload = array(
				'host'        => $host,
				'key'         => $api_key,
				'keyLocation' => home_url( '/' . $api_key . '.txt' ),
				'urlList'     => array_values( $batch ),
			);

			$response = wp_remote_post( self::ENDPOINT, array(
				'timeout' => 10,
				'headers' => array( 'Content-Type' => 'application/json; charset=utf-8' ),
				'body'    => wp_json_encode( $payload ),
			) );

			$result = self::parse_response( $response, $batch );

			// Retry once on 429.
			if ( 429 === $result['http_code'] ) {
				sleep( 5 );
				$response = wp_remote_post( self::ENDPOINT, array(
					'timeout' => 10,
					'headers' => array( 'Content-Type' => 'application/json; charset=utf-8' ),
					'body'    => wp_json_encode( $payload ),
				) );
				$result = self::parse_response( $response, $batch );
			}

			$results[] = $result;
		}

		return $results;
	}

	/**
	 * Parse a wp_remote_post response.
	 *
	 * @param array|\WP_Error $response WP HTTP response.
	 * @param array           $urls     Submitted URLs.
	 * @return array Parsed result with keys: urls, http_code, response, success.
	 */
	private static function parse_response( array|\WP_Error $response, array $urls ): array {
		if ( is_wp_error( $response ) ) {
			return array(
				'urls'      => $urls,
				'http_code' => 0,
				'response'  => $response->get_error_message(),
				'success'   => false,
			);
		}

		$code = (int) wp_remote_retrieve_response_code( $response );
		$body = wp_remote_retrieve_body( $response );

		return array(
			'urls'      => $urls,
			'http_code' => $code,
			'response'  => $body,
			'success'   => ( $code >= 200 && $code < 300 ),
		);
	}
}
