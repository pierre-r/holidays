<?php

namespace Holidays;

use DateTime;
use Exception;

/**
 * Version : 1.1.1
 */

/**
 * Return the holiday name in specific locale.
 * If locale does not exist, it returns its english name
 *
 * @param   string  $holiday  Name of the holiday in English
 * @param   string  $locale   ISO-2 of the language to return
 *
 * @return string Holiday name
 */
function get_holiday_name( $holiday, $locale = "fr" ) {
	$translations = include __DIR__ . '/locales.php';

	return ( isset( $translations[ $locale ][ $holiday ] ) ) ? $translations[ $locale ][ $holiday ] : $holiday;
}

/**
 * Return a list of all holidays for the specified country
 *
 * @param   string  $year     year to retrieve from
 * @param   string  $locale   ISO-2 of the language of holiday name
 * @param   string  $country  ISO-2 of the country to retrieve from
 *
 * @return array list of holidays of the country and year
 * @throws Exception
 */
function get_holidays( $year = "", $locale = "fr", $country = "BE" ) {
	if ( empty( $year ) ) {
		$year = date( 'Y' );
	}

	$cache_dir       = __DIR__ . '/cache/';
	$cache_file      = "holidays_" . $year . "_" . $country . "_" . $locale . ".txt";
	$cache_separator = " | ";

	if ( ! is_dir( $cache_dir ) ) {
		mkdir( $cache_dir, 0755, TRUE );
	}

	$holidays = [];

	if ( file_exists( $cache_dir . $cache_file ) ) {
		$cache = file( $cache_dir . $cache_file );
		foreach ( $cache as $row ) {
			$row                 = array_map( 'trim', explode( $cache_separator, $row ) );
			$holidays[ $row[0] ] = $row[1];
		}
	} else {
		if ( ! defined( 'RAPID_API_KEY' ) ) {
			throw new Exception( 'La clé RAPID_API_KEY doit être définie' );
		}

		$rapidApiLib  = 'public-holidays7';
		$rapidApiHost = "{$rapidApiLib}.p.rapidapi.com";

		$curl         = curl_init();
		$curl_options = [
			CURLOPT_URL            => "https://{$rapidApiHost}/{$year}/{$country}",
			CURLOPT_RETURNTRANSFER => TRUE,
			CURLOPT_FOLLOWLOCATION => TRUE,
			CURLOPT_ENCODING       => "",
			CURLOPT_MAXREDIRS      => 10,
			CURLOPT_TIMEOUT        => 30,
			CURLOPT_HTTP_VERSION   => CURL_HTTP_VERSION_1_1,
			CURLOPT_CUSTOMREQUEST  => "GET",
			CURLOPT_HTTPHEADER     => [
				"X-RapidAPI-Host: " . $rapidApiHost,
				"X-RapidAPI-Key: " . RAPID_API_KEY,
			],
		];
		curl_setopt_array( $curl, $curl_options );

		$response = curl_exec( $curl );
		$err      = curl_error( $curl );

		curl_close( $curl );

		if ( $err ) {
			throw new Exception( "cURL Error #:" . $err );
		}

		$response = json_decode( $response );

		if ( empty( $response ) ) {
			throw new Exception( "Aucun jour férié retourné par l'API" );
		}

		foreach ( $response as $v ) {
			if ( $v->name !== "Easter Sunday"
			     && $v->name !== "St. Stephen's Day"
			     && $v->name !== "Day after Ascension Day"
			     && $v->name !== "Good Friday" ) {
				$holidays[ $v->date ] = get_holiday_name( $v->name, $locale );
			}
		}

		// Save in cache
		$cache_content = "";
		foreach ( $holidays as $k => $v ) {
			$cache_content .= $k . $cache_separator . $v . PHP_EOL;
		}
		file_put_contents( $cache_dir . $cache_file, $cache_content );
	}

	return $holidays;
}

/**
 * Check if a specific date is a public holiday or not
 *
 * @param   string  $date_str  date of the day to check
 * @param   string  $locale    ISO-2 of the language of holiday name
 * @param   string  $country   ISO-2 of the country to retrieve from
 *
 * @return bool|string return the holiday name or false if not holiday
 * @throws Exception
 */
function is_holiday( $date_str = "", $locale = "fr", $country = "BE" ) {
	$date     = new DateTime( $date_str );
	$date_ymd = $date->format( 'Y-m-d' );

	$holidays = get_holidays( $date->format( 'Y' ), $locale, $country );

	if ( empty( $holidays ) ) {
		throw new Exception( "Les jours fériés ne sont pas définis pour l'année " . $date->format( 'Y' ) . " (" . $country . ")" );
	}

	return ( array_key_exists( $date_ymd, $holidays ) ) ? $holidays[ $date_ymd ] : FALSE;
}
