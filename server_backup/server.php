<?php
error_reporting(E_ALL);
ini_set('display_errors', '1');


class ServerBackup
{
    public static function init()
    {
        //self::$backup_id= $backup_id;
      
        set_error_handler(function ($errno, $errstr, $errfile, $errline) {
            throw new ErrorException($errstr, $errno, 0, $errfile, $errline);
        });
        echo 'start ---'."<br>\r\n";
        try {
        } catch (ErrorException $e) {
            self::log($e->getMessage().' on line '.$e->getLine());
        }
    }

    /*
     * 
     * 
     * 
     */
    public static function startBackup($url, $backup_id) {
        $data = array(
                    'host_backup' => 'server.com',
                    'host_backup_user' => 'user',
                    'host_backup_pass' => 'pass',
                    'host_backup_dir'  => 'dir',
                  );

                  self::postData($url, $data);
    }
    /*
    *  envoie la partie client sur le serveur à sauvegarder
    * 
    */
    public static  function installClient($backup_id) {

    }

    public static function postData($url, $data)
    {
        $options = array(
            'http' => array(
                'header'  => "Content-type: application/x-www-form-urlencoded\r\n",
                'method'  => 'POST',
                'content' => http_build_query($data)
            )
        );
        $context  = stream_context_create($options);
        $result = file_get_contents($url, false, $context);
        if ($result === false) {
            throw new ErrorException('Post request fail: '.$url);
        }
        return $result;
    }

    public static function log($message)
    {
        $m = $message."\r\n";
        echo $m."<br>";
        file_put_contents(__DIR__.'/log.txt', $m, FILE_APPEND);
    }
}
