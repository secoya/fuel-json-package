<?php

namespace Secoya\JSON;

use ArrayAccess;
use DateTime;
use Orbit\Core\Encodable as CoreEncodable;
use Secoya\DoctrineFuel\Encodable;
use Secoya\DoctrineFuel\Model_Base;

class Helper {
	public static function unpack_value($value){
		if($value instanceof Model_Base){
			return $value->get_encodable_properties();
		} elseif($value instanceof DateTime) {
			return $value->getTimestamp();
		} elseif(is_array($value) || $value instanceof ArrayAccess) {
			return self::unpack_array($value);
		} elseif($value instanceof Encodable || $value instanceof CoreEncodable){
			return $value->get_encodable();
		} else {
			return $value;
		}
	}

	private static function unpack_array($array) {
		$result = array();
		foreach($data as $key => $value){
			$result[$key] = self::unpack_value($value);
		}

		return $result;
	}
}
