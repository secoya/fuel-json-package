<?php
namespace Secoya\JSON;

class JSONFile {
	private $output_file;
	private $file_path;
	private $delete_on_destruct;

	public function __construct($file_path = null, $delete_on_destruct = true){
		if($file_path === null){
			$file_path = tempnam(APPPATH . 'tmp', 'json');
		}
		if(is_resource($file_path)) {
			$this->output_file = $file_path;
		} else if(is_string($file_path)) {
			$this->output_file = @fopen($file_path, 'w');
		}

		if($this->output_file === false){
			throw new IOException("Could not open file $file_path");
		}
		$this->file_path = $file_path;
		$this->delete_on_destruct = $delete_on_destruct;
	}

	public function get_file_path(){
		return $this->file_path;
	}

	public function close(){

		if($this->output_file === null){
			return;
		}

		if(fclose($this->output_file) === false){
			throw new IOException("Could not close file");
		}

		$this->output_file = null;
	}

	public function encode($value){
		if($value instanceof Base64EncodableFile){
			return $this->encode_file($value->get_file_name());
		}

		if(is_array($value)){
			return $this->encode_array($value);
		}

		return $this->encode_scalar($value);
	}

	private function encode_file($file_path){
		$fp = @fopen($file_path, 'r');

		if($fp === false){
			throw new IOException("Cannot open $file_path for reading");
		}

		stream_filter_append($fp, 'convert.base64-encode');

		$this->write('"');

		while(!feof($fp)){
			$contents = fread($fp, 1024);

			$this->write($contents);
		}

		$this->write('"');

		fclose($fp);
	}

	private function encode_scalar($string){
		$content = JSON::encode($string);

		// We use this purely in conjunction with ElasticSearch
		// EL does not like the escaped forward slashes, so revert them
		$this->write(str_replace('\/', '/', $content));
	}

	private function encode_array($array){
		reset($array);


		if(count($array) === 0 || is_int(key($array))){
			$this->encode_real_array($array);
		} else {
			$this->encode_object($array);
		}
	}

	private function encode_real_array($array){
		$this->write('[');

		$first = true;
		foreach($array as $value){
			if(!$first){
				fwrite($this->output_file, ',', 1);
			}
			$first = false;
			$this->encode($value);
		}

		$this->write(']');
	}

	private function encode_object($object){
		$this->write('{');

		$first = true;
		foreach($object as $key => $value){
			if(!$first){
				$this->write(',');
			}
			$first = false;
			$this->encode_scalar($key);
			$this->write(':');
			$this->encode($value);
		}

		$this->write('}');
	}

	private function write($str){
		if(fwrite($this->output_file, $str, strlen($str)) === false){
			throw new IOException("Could not write string to file");
		}
	}

	public function __destruct(){
		$this->close();
		if($this->delete_on_destruct){
			if(file_exists($this->file_path)){
				unlink($this->file_path);
			}
		}
	}
}
