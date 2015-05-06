<?php

namespace Secoya\JSON;

/**
 * Decodes JSON data from a stream (returned by fopen).
 * This avoids reading in the entire data to memory when decoding it.
 * Remember to close the stream yourself after use.
 * @author Kaare Skovgaard <khs@secoya.dk>
 */
class StreamDecoder {
	private $stream;
	private $pos;
	private $token;

	const BEGIN_ARRAY = '[';
	const END_ARRAY = ']';
	const BEGIN_OBJECT = '{';
	const END_OBJECT = '}';
	const COMMA = ',';
	const STRING_DELIM = '"';
	const COLON = ':';

	private $hexdigits;

	private $single_char_tokens;

	private $assoc;

	public function __construct($stream, $assoc = false){
		$this->stream = $stream;
		$this->pos = $this->tell();
		$this->seek(0, SEEK_END);
		$this->end = $this->tell();
		$this->seek($this->pos, SEEK_SET);
		$this->single_char_tokens = [
			self::BEGIN_ARRAY,
			self::END_ARRAY,
			self::BEGIN_OBJECT,
			self::END_OBJECT,
			self::COMMA,
			self::COLON,
		];
		$this->assoc = $assoc;
		$this->hexdigits = ['0', '1', '2', '3', '4', '5', '6', '7', '8', '9', 'a', 'A', 'b', 'B', 'c', 'C', 'd', 'D', 'e', 'E', 'f', 'F'];
	}

	public function decode(){
		$val = $this->decode_single();

		if(!$this->all_tokens_read()){
			throw new IOException("Expected EOF. Is at position {$this->pos} end at position {$this->end}");
		}

		return $val;
	}

	public function decode_single(){
		$tok = $this->next_token();
		$pos = $this->pos;

		if(is_string($tok)){
			switch($tok){
				case self::BEGIN_OBJECT:
					return $this->decode_object();
				case self::BEGIN_ARRAY:
					return $this->decode_array();
				case self::END_ARRAY:
				case self::END_OBJECT:
				case self::COMMA:
				case self::COLON:
					throw new IOException("Unexpected token " . var_export($tok, true) . " at position $pos. Expected '[', '{', 'true', 'false', 'null', number or string");
				default:
					return $tok;
			}
		}

		return $tok;
	}

	private function decode_object(){
		if ($this->assoc){
			$res = [];
		} else {
			$res = new \stdClass();
		}

		while(true){
			$peek = $this->peek_non_whitespace_char();
			switch($peek){
				case self::END_OBJECT:
					$this->next_token();
					return $res;
				case '"':
					$key = $this->next_token();
					if(($t = $this->next_token()) != self::COLON){
						throw new IOException("Unexpected token $t at positon {$this->pos} expected ':'");
					}
					$value = $this->decode_single();
					if($this->assoc){
						$res[$key] = $value;
					} else {
						$res->$key = $value;
					}
			}

			$peek = $this->peek();

			if($peek != self::COMMA && $peek != self::END_OBJECT){
				$this->next_token();
				throw new IOException("Unexpected token $peek at position {$this->pos} expected ',' or '}'");
			}

			if($peek == self::COMMA){
				$this->next_token();
			}
		}
	}

	private function decode_array(){
		$res = [];

		while(true){
			$peek = $this->peek();
			switch($peek){
				case self::END_ARRAY:
					$this->next_token();
					return $res;
				default:
					$value = $this->decode_single();
					$res[] = $value;
			}

			$peek = $this->peek();

			if($peek != self::COMMA && $peek != self::END_ARRAY){
				$this->next_token();
				throw new IOException("Unexpected token " . var_export($peek, true) . " at position {$this->pos} expected ',' or '}'");
			}

			if($peek == self::COMMA){
				$this->next_token();
			}
		}
	}

	private function next_token(){
		// Go through all whitespaces and jump over them
		while(($ch = $this->read_character()) && $this->is_whitespace($ch)){
		}

		if(in_array($ch, $this->single_char_tokens)){
			return $ch;
		}

		// Here are the possible values:
		// - numbers
		// - true
		// - false
		// - null

		switch($ch){
			case self::STRING_DELIM:
				return $this->next_token_string();
			case '-':
			case '0':
			case '1':
			case '2':
			case '3':
			case '4':
			case '5':
			case '6':
			case '7':
			case '8':
			case '9':
			case '.':
				return $this->next_token_number($ch);
			case 't':
				return $this->next_token_true();
			case 'f':
				return $this->next_token_false();
			case 'n':
				return $this->next_token_null();
			default:

				throw new IOException("Unexpected token (" . var_export($ch, true) . ") at position {$this->pos}");
		}
	}

	private function next_token_string(){
		$pos = $this->pos;

		$str = [];

		while(($next = $this->read_character()) || true){
			switch($next){
				case '"':
					return implode('', $str);
				case '\\':
					$escaped = $this->read_character();
					switch($escaped){
						case '"':
							$str[] = '"';
							break;
						case '\\':
							$str[] = "\\";
							break;
						case '/':
							$str[] = '/';
							break;
						case 'b':
							$str[] = chr(8);
							break;
						case 'f':
							$str[] = chr(12);
							break;
						case 'n':
							$str[] = "\n";
							break;
						case 'r':
							$str[] = "\r";
							break;
						case 't':
							$str[] = "\t";
							break;
						case 'u':
							$digit = $this->read_hexdigit() . $this->read_hexdigit() . $this->read_hexdigit() . $this->read_hexdigit();
							$str[] = json_decode('"\\u' . $digit . '"', true, 1);
							break;
						default:
							throw new IOException("Unexpected escape sequence \\" . $escaped . " in string at position $pos");
					}
					break;
				default:
					$str[] = $next;
			}
		}
	}

	private function read_hexdigit(){
		$ch = $this->read_character();

		if(!in_array($ch, $this->hexdigits)){
			throw new IOException("Unexpected character $ch at pos {$this->pos} expected a hexadecimal digit");
		}
		return $ch;
	}

	private function next_token_number($initial){
		$pos = $this->pos;
		$rest = $this->read_while(['0', '1', '2', '3', '4', '5', '6', '7', '8', '9', '.', 'e', 'E', '+', '-']);

		$num = $initial . $rest;


		$number = json_decode($num, true, 1);

		if($number === null){
			throw new IOException("Could not parse number $num at $pos");
		}

		return $number;
	}

	private function next_token_true(){
		$pos = $this->pos;
		if($this->read_character() == 'r' && $this->read_character() == 'u' && $this->read_character() == 'e'){
			return true;
		}

		throw new IOException("Unexpected token at position $pos. Expected 'true'");
	}

	private function next_token_false(){
		$pos = $this->pos;
		if($this->read_character() == 'a' && $this->read_character() == 'l' && $this->read_character() == 's' && $this->read_character() == 'e'){
			return false;
		}

		throw new IOException("Unexpected token at position $pos. Expected 'false'");
	}

	private function next_token_null(){
		$pos = $this->pos;
		if($this->read_character() == 'u' && $this->read_character() == 'l' && $this->read_character() == 'l'){
			return null;
		}

		throw new IOException("Unexpected token at position $pos. Expected 'null'");
	}

	private function is_whitespace($ch){
		return $ch == ' ' || $ch == "\t" || $ch == "\r" || $ch == "\n";
	}

	private function peek(){
		$pos = $this->pos;
		$next = $this->next_token();
		$this->seek($pos);
		$this->pos = $pos;


		return $next;
	}

	private function peek_non_whitespace_char(){
		$pos = $this->pos;
		while(($next = $this->read_character()) && $this->is_whitespace($next)){
		}
		$this->seek($pos);
		$this->pos = $pos;


		return $next;
	}

	private function tell(){
		$pos = ftell($this->stream);
		if($pos === false){
			throw new IOException("Could not get current position of stream");
		}

		return $pos;
	}

	private function seek($offset, $whence = SEEK_SET){
		if(fseek($this->stream, $offset, $whence) !== 0){
			throw new IOException("Could not seek in stream");
		}
	}

	private function read_character(){
		if($this->eof()){
			throw new IOException("Unexpected end of stream. Recorded pos {$this->pos} at pos {$this->tell()}");
		}

		$res = fgetc($this->stream);

		$this->pos++;

		if($res === false){
			throw new IOException("Could not read from stream. At pos {$this->pos}. Real pos {$this->tell()}. End is at {$this->end}");
		}

		return $res;
	}

	private function all_tokens_read(){
		$pos = $this->pos;

		while(($this->pos < $this->end) && ($ch = $this->read_character())){
			if(!$this->is_whitespace($ch)){
				$this->seek($pos);
				$this->pos = $pos;

				return false;
			}
		}

		$this->seek($pos);
		$this->pos = $pos;


		return true;
	}

	private function read_while($chars){
		$res = [];

		while(in_array(($t = $this->read_character()), $chars)){
			$res[] = $t;

			if($this->eof()){
				return implode('', $res);
			}
		}

		$this->pos--;
		$this->seek(-1, SEEK_CUR);

		return implode('', $res);
	}

	private function eof(){
		if($this->pos < $this->end){
			return false;
		}
		return $this->pos == $this->end || feof($this->stream);
	}
}
