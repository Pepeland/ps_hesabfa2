<?php

class LogService
{
    private static $fileName = _PS_MODULE_DIR_ . 'ps_hesabfa/' . "hesabfa-log.txt";

    public static function writeLogStr($logStr)
    {
        $file = fopen(self::$fileName, "a");
        fwrite($file, $logStr . "\n");
        fclose($file);
    }

    public static function writeLogObj($logObj)
    {
        ob_start();
        var_dump($logObj);
        file_put_contents(self::$fileName, PHP_EOL . ob_get_flush(), FILE_APPEND);
    }

    public static function readLog()
    {
        return file_get_contents(self::$fileName);
    }

    public static function clearLog() {
        if (file_exists(self::$fileName))
            file_put_contents(self::$fileName, "");
    }

    public static function getLogFilePath() {
        return self::$fileName;
    }
}