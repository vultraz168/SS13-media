<?php

class Cache {

	public function isFileCached($path) {

		$md5 = md5($path);
		return (glob(Config::getCacheDir().$md5.".json.cache")) ? true : false;

	}

	public function cacheData($path,$data) {

		$md5 = md5($path);
		$file = Config::getCacheDir().$md5.".json.cache";

		$handle = fopen($file,"w");
		fwrite($handle, json_encode($data));

	}

	public function getCacheData($path) {

		$md5 = md5($path);
		$file = Config::getCacheDir().$md5.".json.cache";

		$handle = fopen($file,"r");

		return json_decode(fread($handle,filesize($file)));

	}

}