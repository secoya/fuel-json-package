<?php

namespace Secoya\JSON;

/**
 * JSON wrapper of PHP's json_encode, json_decode and json_last_error
 *
 * @api
 * @package JSON
 * @author Brian K. Christensen, Secoya A/S <bkc@secoya.dk>
 *
 * @link http://www.php.net/manual/en/ref.json.php
 */
class JSON {
	
	/**
	 * Encodes objects, arrays and primitive types to json-strings
	 *
	 * @api
	 * @param mixed object, array or primity to encode
	 * @param int $options additional options on how to encode
	 * @return string JSON encoded string
	 *
	 * @link http://php.net/json_encode
	 */
	public static function encode($value, $options = 0) {
		$result = json_encode($value, $options);
		if($result === false) {
			$error = json_last_error();
			if($error !== JSON_ERROR_NONE) {
				$msg = self::get_error($error);
				throw new JSONException($msg);
			}
		}
		return $result;
	}

	/**
	 * Decodes string to object
	 *
	 * @api
	 * @param string $json String to decode
	 * @param bool $assoc if true decodes to associative array instead of object
	 * @param int $depth max depth og nested array or objects
	 * @return mixed bbject or array, depending on $assoc is true or false
	 *
	 * @link http://php.net/json_decode
	 *
	 * I know there is a fourth parameter in the php documentation
	 * but my developer machine only takes 3 arguments
	 */
	public static function decode($json, $assoc = false, $depth = 512) {
		$result = json_decode($json, $assoc, $depth);
		if($result === null) {
			$error = json_last_error();
			if($error !== JSON_ERROR_NONE) {
				$msg = self::get_error($error);
				throw new JSONException($msg);
			}
		}
		return $result;
	}

	/**
	 * Converts error codes to messages
	 *
	 * @api
	 * @param int $error The error code from json_last_error()
	 * @return string Error message
	 *
	 * @link http://php.net/manual/en/function.json-last-error.php
	 */
	protected static function get_error($error) {
		switch ($error) {
			case JSON_ERROR_NONE:
				throw new JSONException('This should not happen');
			break;
			case JSON_ERROR_DEPTH:
				return 'Maximum stack depth exceeded';
			break;
			case JSON_ERROR_STATE_MISMATCH:
				return 'Underflow or the modes mismatch';
			break;
			case JSON_ERROR_CTRL_CHAR:
				return 'Unexpected control character found';
			break;
			case JSON_ERROR_SYNTAX:
				return 'Syntax error, malformed JSON';
			break;
			case JSON_ERROR_UTF8:
				return 'Malformed UTF-8 characters, possibly incorrectly encoded';
			break;
			default:
				return 'Unknown error';
			break;
		}
	}

	private function __construct() {}
}

class JSONException extends \FuelException {}