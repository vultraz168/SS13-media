<?php

class Cache
{

    public static function isCached($objname)
    {
        return file_exists(self::getFileName($objname));
    }

    private static function getFileName($objname)
    {
        return Config::getCacheDir() . "/{$objname}.json";
    }

    public static function setCacheData($objname, $data)
    {
        $file = self::getFileName($objname);

        file_put_contents($file, json_encode($data));
    }

    public static function getCacheData($objname)
    {
        $file = self::getFileName($objname);

        return json_decode(file_get_contents($file), true);
    }

}
