<?php

namespace Secoya\JSON;

class Base64EncodableFile {
	private $file_name;
	public function __construct($file_name){
		if(!file_exists($file_name)){
			throw new \InvalidArgumentException("The argument \$file_name is not a file - $file_name");
		}
		$this->file_name = $file_name;
	}

	public function get_file_name(){
		return $this->file_name;
	}
}