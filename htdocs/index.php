<?php
// I *fucking hate* CORS.
if(isset($_SERVER['HTTP_ORIGIN']))
    header('Access-Control-Allow-Origin: '.$_SERVER['HTTP_ORIGIN']);

function str_shuffle_unicode($str)
{
    $tmp = preg_split("//u", $str, -1, PREG_SPLIT_NO_EMPTY);
    shuffle($tmp);
    return join("", $tmp);
}

// ----------------------------------------------------------------------------------------------------
// - Error Handler
// ----------------------------------------------------------------------------------------------------
$_ERRORTYPES = array(
    0x0001 => 'E_ERROR',
    0x0002 => 'E_WARNING',
    0x0004 => 'E_PARSE',
    0x0008 => 'E_NOTICE',
    0x0010 => 'E_CORE_ERROR',
    0x0020 => 'E_CORE_WARNING',
    0x0040 => 'E_COMPILE_ERROR',
    0x0080 => 'E_COMPILE_WARNING',
    0x0100 => 'E_USER_ERROR',
    0x0200 => 'E_USER_WARNING',
    0x0400 => 'E_USER_NOTICE',
    0x0800 => 'E_STRICT',
    0x1000 => 'E_RECOVERABLE_ERROR',
    0x2000 => 'E_DEPRECATED',
    0x4000 => 'E_USER_DEPRECATED'
);
$_USE_JSON=false;
$_ERRORS=[];
function startsWith($haystack, $needle) {
    return substr_compare($haystack, $needle, 0, strlen($needle)) === 0;
}
function endsWith($haystack, $needle) {
    return substr_compare($haystack, $needle, -strlen($needle)) === 0;
}
if (!defined('ERROR_HANDLER_SET')) {
    function ErrorHandler($type, $message, $file, $line)
    {
        global $_ERRORTYPES,$_USE_JSON, $_ERRORS;
        if (!@is_string($name = @array_search($type, @array_flip($_ERRORS)))) {
            $name = 'E_UNKNOWN';
        };
        $msg = ['name'=>$name, 'file'=>@basename($file), 'line'=>$line, 'message'=>$message];
        if (endsWith($name, "_ERROR") || !$_USE_JSON) {
            $msg=@sprintf("<div>%s in file <b>%s</b> at line %d: %s\n</div>", $name, @basename($file), $line, $message);
        }
        if (endsWith($name, "_ERROR")) {
            die(json_encode(['errors'=>[$msg]]));
        }
        if ($_USE_JSON) {
            $_ERRORS[]=$msg;
        } else {
            print($msg);
        }
    };

    $old_error_handler = set_error_handler("ErrorHandler");
}
set_exception_handler(function ($exception) {
    die(json_encode(['errors'=>[['name'=>'EXCEPTION','message'=>$exception->getMessage(),'file'=>@basename($exception->getFile()), 'line'=>$exception->getLine()]]]));
});

class Main
{
    public $playlists;

    public function __construct()
    {
        require("../config.php");
        require("../cache.php");
        ini_set('display_errors', 1);
        $this->playlists = json_decode(file_get_contents(Config::ROOT_PATH . '/playlists.json'), true);
    }

    private function calcAccessKey($md5)
    {
        return strtoupper(md5($md5 . Config::API_KEY));
    }

    private function getSetting($playlist, $key, $default)
    {
        if (!isset($this->playlists[$playlist][$key])) {
            return $default;
        }
        return $this->playlists[$playlist][$key];
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
        /*if (!file_exists($file)) {
            $this->error("Waiting for upload ($file missing).");
        }*/

        foreach (json_decode(file_get_contents($file), true) as $md5 => $fileInfo) {
            $cacheKey = "{$md5}.".Config::EXT;
            $file = $this->getPoolFilename($md5);

            $attr = new stdClass();

            if (isset($_GET['test'])) {
                var_dump($fileInfo);
            }

            $attr->title = isset($fileInfo['title']) ? $fileInfo['title'] : '';
            $attr->artist = isset($fileInfo['artist']) ? $fileInfo['artist'] : '';
            $attr->album = isset($fileInfo['album']) ? $fileInfo['album'] : '';

            if (isset($fileInfo['playtime_seconds'])) {
                $attr->length = (string) floor(floatval($fileInfo['playtime_seconds']) * 10);
            } else {
                $attr->length = "";
            }

            $attr->url = Config::ROOT_URL . '/index.php?key=' . $this->calcAccessKey($md5) . '&get=' . $md5 . '&filetype=.'.Config::EXT;

            foreach ($fileInfo['playlists'] as $playlist) {
                if (!isset($this->playlists[$playlist]['tracks'])) {
                    $this->playlists[$playlist]['tracks'] = array();
                }
                if (!isset($this->playlists[$playlist]['orig-tracks'])) {
                    $this->playlists[$playlist]['orig-tracks'] = array();
                }
                $cfgObfuscate = $this->getSetting($playlist, 'obfuscate', 'false') == 'true';
                if ($cfgObfuscate) {
                    $pl_attr = new stdClass();
                    $pl_attr->length = $attr->length;
                    $pl_attr->url = $attr->url;
                    $pl_attr->title = $md5;
                    $pl_attr->artist = isset($fileInfo['artist']) ? str_shuffle_unicode($fileInfo['artist']) : 'NULL';
                    $pl_attr->album = isset($fileInfo['album']) ? str_shuffle_unicode($fileInfo['album']) : 'NULL';

                    $this->playlists[$playlist]['tracks'][] = $pl_attr;
                } else {
                    $this->playlists[$playlist]['tracks'][] = $attr;
                }
                $attr->md5 = $md5;
                $this->playlists[$playlist]['orig-tracks'][] = $attr;
            }
        }
    }

    private function getPoolFilename($md5)
    {
        $a = substr($md5, 0, 1);
        $b = substr($md5, 1, 1);
        $filename = substr($md5, 2);
        return Config::getPoolDir() . "/{$a}/{$b}/{$filename}.".Config::EXT;
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
            die("File $filename not found.");
        }
        //header('Content-Description: File Transfer');
        header('Content-Type: '.Config::MIME_TYPE);
        //header('Content-Type: audio/mp4');
        header('Content-Disposition: inline; filename=' . basename($filename));
        // attachment
        header('Expires: 0');
        header('Cache-Control: must-revalidate');
        header('Pragma: public');
        header('Content-Length: ' . filesize($filename));
        readfile($filename, false);
    }

    private function playlistAsJson(array $plData)
    {
        header('Content-type: application/json');
        $enc = json_encode($plData, JSON_PRETTY_PRINT);
        if (!$enc) {
            $this->error(json_last_error_msg());
        }
        return $enc;
    }

    private function playlistAsHTML($playlistID, array $plData)
    {
        header('Content-type: text/html');
        $o = <<<HEAD
<html>
    <head>
        <title>Playlist {$playlistID}</title>
        <link href="/css/clean.css" rel="stylesheet" />
    </head>
    <body>
        <h1>{$playlistID}</h1>
        <table>
            <tr>
                <th>#</th>
                <th>Title</th>
                <th>Artist</th>
                <th>Album</th>
                <th>MD5</th>
            </tr>
HEAD;
        //var_dump($plData);
        foreach ($plData as $k => $entry) {
            //var_dump($entry);
            $title = !empty($entry['title']) ? htmlentities($entry['title']) : "&nbsp;";
            $artist = !empty($entry['artist']) ? htmlentities($entry['artist']) : "&nbsp;";
            $album = !empty($entry['album']) ? htmlentities($entry['album']) : "&nbsp;";
            $md5 = $entry['md5'];
            if (isset($_GET['key']) && $_GET['key']==Config::API_KEY) {
                $md5='<a href="'.$entry['url'].'">'.$md5.'</a>';
            }
            $o .= "<tr><th>$k</th><td>{$title}</td><td>{$artist}</td><td>{$album}</td><td>{$md5}</td></tr>";
        }
        $o .= '</table></body></html>';
        return $o;
    }

    public function run()
    {
        if (isset($_GET['playlist'])) {
            $type = filter_input(INPUT_GET, 'type');
            $plID = filter_input(INPUT_GET, 'playlist');

            if (Config::API_KEY != '' && $type != 'html') {
                if (!isset($_GET['key'])) {
                    $this->error('Need key.');
                }
                if($_GET['key'] != Config::API_KEY) {
                    $this->error('Bad key.');
                }
            }
            if (preg_match('/^[a-zA-Z0-9]+$/', $plID) == 0) {
                $this->error('Bad request. Playlist ID does not match [a-zA-Z0-9]+');
            }
            if(!array_key_exists($plID, $this->playlists)){
                $this->error('Playlist does not exist.');
            }
            $cache = array();
            if (isset($_GET['reset_cache']) || !Cache::isCached($plID)) {
                $this->loadFileMetadata();
                Cache::setCacheData($plID, $this->playlists[$plID]);
            }
            $cache = Cache::getCacheData($plID);

            $plData = $cache['tracks'];
            $origData = $cache['orig-tracks'];
            $enc = '';
            switch ($type) {
                case 'html':
                    $enc = $this->playlistAsHTML($plID, $origData);
                    break;
                case 'json':
                default:
                    $enc = $this->playlistAsJson($plData);
                    break;
            }
            echo $enc;
        } elseif (isset($_GET['get'])) {
            $md5 = strtoupper($_GET['get']);
            if(CONFIG::API_KEY != '') {
                if (!isset($_GET['key'])) {
                    $this->error('Need key.');
                }
                if($this->calcAccessKey($md5) != strtoupper($_GET['key'])) {
                    $this->error('Bad key.');
                }
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
