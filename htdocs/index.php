<?php

class Main
{
    public $playlists;

    public function __construct()
    {
        require ("../config.php");
        require ("../cache.php");
        ini_set('display_errors', 1);
        $this->playlists = json_decode(file_get_contents(Config::ROOT_PATH . '/playlists.json'), true);
    }

    private function calcAccessKey($md5)
    {
        return md5($md5 . Config::API_KEY);
    }

    private function loadFileMetadata()
    {
        /*
         md5 : {
         'title': '',
         'album': '',
         'artist': '',
         'playtime_seconds': ''
         }
         */
        $file = Config::getPoolDir() . '/fileData.json';
        if (!file_exists($file))
            $this->error('Waiting for upload.');
        foreach (json_decode(file_get_contents($file),true) as $md5 => $fileInfo) {
            $cacheKey = "{$md5}.mp3";
            $file = $this->getPoolFilename($md5);

            $attr = new stdClass();

            $attr->title = isset($fileInfo['title']) ? $fileInfo['title'][0] : '';
            $attr->artist = isset($fileInfo['artist']) ? $fileInfo['artist'][0] : '';
            $attr->album = isset($fileInfo['album']) ? $fileInfo['album'][0] : '';

            if (isset($fileInfo['playtime_seconds'])) {
                $attr->length = (string) floor(floatval($fileInfo['playtime_seconds']) * 10);
            } else {
                $attr->length = "";
            }

            $attr->url = Config::ROOT_URL . '/index.php?key=' . $this->calcAccessKey($md5) . '&get=' . $md5 . '&filetype=.mp3';

            foreach ($fileInfo['playlists'] as $playlist) {
                if (!isset($this->playlists[$playlist]['tracks']))
                    $this->playlists[$playlist]['tracks'] = array();
                $this->playlists[$playlist]['tracks'][] = $attr;
            }
        }
    }

    private function getPoolFilename($md5)
    {
        $a = substr($md5, 0, 1);
        $b = substr($md5, 1, 1);
        return Config::getPoolDir() . "/{$a}/{$b}/{$md5}.mp3";
    }

    private function error($msg)
    {
        header($_SERVER['SERVER_PROTOCOL'] . ' 500 Internal Server Error', true, 500);
        die($msg);
    }

    private function readFile($filename)
    {
        if (!file_exists($filename)) {
            header('HTTP/1.1 404 Not Found');
            die('Nope');
        }
        //header('Content-Description: File Transfer');
        header('Content-Type: audio/mpeg');
        header('Content-Disposition: inline; filename=' . basename($filename)); // attachment
        header('Expires: 0');
        header('Cache-Control: must-revalidate');
        header('Pragma: public');
        header('Content-Length: ' . filesize($filename));
        readfile($filename, false);
    }

    public function run()
    {

        if (isset($_GET['playlist'])) {
            if (Config::API_KEY != '') {
                if (!isset($_GET['key']) || $_GET['key'] != Config::API_KEY) {
                    $this->error('Need key.');
                }
            }
            $plID = $_GET['playlist'];
            if (preg_match('/^[a-zA-Z0-9]+$/', $plID) == 0) {
                $this->error('Bad request.');
            }
            $plData = array();
            if (Cache::isCached($plID)) {
                $plData = Cache::getCacheData($plID);
            } else {
                $this->loadFileMetadata();
                $plData = $this->playlists[$plID]['tracks'];
                Cache::setCacheData($plID, $plData);
            }
            header('Content-type: application/json');
            echo json_encode($plData, JSON_PRETTY_PRINT);
        } elseif (isset($_GET['get'])) {
            $md5=$_GET['get'];
            if (!isset($_GET['key']) || $this->calcAccessKey($md5) != $_GET['key']) {
                $this->error('Need key.');
            }
            if (preg_match('/^[A-F0-9]+$/', $md5) == 0) {
                $this->error('Bad request.');
            }
            $filename = $this->getPoolFilename($md5);
            $this->ReadFile($filename);
        } else {
            header('Content-type: application/json');
            echo json_encode(array_keys($this->playlists), JSON_PRETTY_PRINT);
        }
    }

}

$app = new Main;
$app->run();
