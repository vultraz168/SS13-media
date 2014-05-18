<?php

class Main {

	public function __construct() {
		require("config.php");
		require("cache.php");
		require("lib/getid3/getid3.php");
		ini_set('display_errors',1);
		header('Content-type: text/json');
	}

	private function getPlaylists() {
		$playlists = array();

		$handle = opendir(Config::getMediaDir());
		while(($entry = readdir($handle)) !== false) {
			if($entry != "." && $entry != ".." && is_dir(Config::getMediaDir().$entry)) {
				$playlists[] = $entry;
			}
		}

		return $playlists;
	}

	private function getAttributes($file) {

		if(Cache::isFileCached($file)) {

			return Cache::getCacheData($file);

		} else {

			$id3 = new getID3;
			$attr = new Stdclass;

			$data = $id3->analyze($file);
			$tags = ($data['tags']['id3v2']) ? $data['tags']['id3v2'] : $data['tags']['id3v1'];

			$attr->title 	= ($tags['title']) ? $tags['title'][0] : "???";
			$attr->artist 	= ($tags['artist']) ? $tags['artist'][0] : "Unknown";
			$attr->album 	= ($tags['album']) ? $tags['album'][0] : "Unknown";

			$attr->url 		= Config::getWebRoot().substr($file, strlen(Config::getMediaDir()));

			if(isset($data['playtime_seconds'])) {
				$attr->length = (string) floor($data['playtime_seconds'] * 10);
			} else {
				$attr->length = "";
			}

			Cache::cacheData($file,$attr);

			return $attr;

		}

	}

	private function getTracks($playlists,$request) {
		if(in_array($request, $playlists)) {

			$files = array();

			foreach(glob(Config::getMediaDir().$request."/*.mp3") as $file) {
				$files[] = self::getAttributes($file);
				if($request == "emagged") {
					foreach($files as $i=>$file) {
						$files[$i]->title = md5($file->title.time());
						$files[$i]->artist = "";
						$files[$i]->album = "";
					}
				}

			}

			return $files;

		} else {

			return false;

		}
	}

	public function run() {
		$playlists = self::getPlaylists();

		if(isset($_GET['playlist'])) {
			$tracks = self::getTracks($playlists,$_GET['playlist']);
			echo json_encode($tracks,JSON_PRETTY_PRINT);
		} else {
			echo json_encode($playlists,JSON_PRETTY_PRINT);
		}
	}

}

$app = new Main;
$app->run();
