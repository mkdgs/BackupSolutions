<?php

error_reporting(E_ALL);
ini_set('display_errors', '1');

class WpBackup {

    public static $dir_from;
    public static $dir_to_backup;
    public static $backup_date;
    public static $backup_id;
    public static $host_backup = ''; // host du serveur backup
    public static $host_backup_user = ''; // acces sur le serveur de backup
    public static $host_backup_pass = ''; // acces sur le serveur de backup
    public static $host_backup_dir = ''; // repertoire sur le serveur de backup

    public static function init($config_client, $config_server) {
        $dir_from = $config_client['dir_from'];
        $dir_to_backup = $config_client['dir_to_backup'];
        $backup_id = $config_client['backup_id'];
        $backup_date = $config_client['backup_date'];
        self::$backup_id = $backup_id;
        self::$backup_date = (empty($backup_date)) ? date('Y-m-d_H-i-s') : $backup_date;
        self::$dir_from = rtrim($dir_from, '/');
        self::$dir_to_backup = rtrim($dir_to_backup, '/') . '/' . $backup_id;

        self::$host_backup = $config_server['host_backup']; // host du serveur backup
        self::$host_backup_user = $config_server['host_backup_user']; // acces sur le serveur de backup
        self::$host_backup_pass = $config_server['host_backup_pass']; // acces sur le serveur de backup
        self::$host_backup_dir = $config_server['host_backup_dir']; // repertoire sur le serveur de backup


        set_error_handler(function ($errno, $errstr, $errfile, $errline) {
            throw new ErrorException($errstr, $errno, 0, $errfile, $errline);
        });
        echo 'start ---' . "<br>\r\n";
        try {
            if (!is_readable(self::$dir_from)) {
                throw new ErrorException('not readable dir_from: ' . self::$dir_from);
            }
            if (!file_exists(self::$dir_to_backup)) {
                mkdir(self::$dir_to_backup, 0777, true);
            }
            if (!is_readable(self::$dir_to_backup)) {
                throw new ErrorException('not readable dir_from: ' . self::$dir_to_backup);
            }

            // extract data from wp-config.php
            $config = array();
            $config_file = file_get_contents($dir_from . '/wp-config.php');
            preg_match_all("/^define\('([^']+)',[\s]*'([^']+)'\);/mi", $config_file, $values);
            foreach ($values[1] as $k => $v) {
                $config[$v] = $values[2][$k];
            }
            if (empty($config) || count($config) < 4) {
                throw new ErrorException('Parse config fail');
            }

            // test connection
            mysqli_connect($config['DB_HOST'], $config['DB_USER'], $config['DB_PASSWORD'], $config['DB_NAME']);
            if (mysqli_connect_errno()) {
                throw new ErrorException('mysql failed:' . mysqli_connect_error());
            }

            // dump, dump, dump around
            $dump_file = self::$dir_to_backup . '/' . self::$backup_id . '-' . self::$backup_date . '-dump-' . $config['DB_NAME'] . '.sql.gz';
            system("mysqldump --host=" . $config['DB_HOST'] . " --user=" . $config['DB_USER'] . " --password=" . $config['DB_PASSWORD'] . " " . $config['DB_NAME'] . " | gzip -9 > " . $dump_file);

            // vérifie la taille du fichier
            clearstatcache();
            if (filesize($dump_file) < 5) { // moins de 5 octets, c'est très suspect
                throw new ErrorException('mysql dump archive seems empty:' . $dump_file);
            }

            // compress
            //system('zip '.$dump_file.'.zip '.$dump_file);
            //if(filesize($dump_file.'.zip') < 5 ) { // moins de 5 octets, c'est très suspect
            //  throw new Exception('mysql dump archive seems empty:'.$dump_file.'.zip');
            //}
            
            // backup all file
            $file_backup_zip = self::$dir_to_backup . '/' . self::$backup_id . '-' . self::$backup_date . '.zip';
            //system('cp -R '.$dir_from.' '.$dir_to_backup);
            ob_start();
            system('zip ' . $file_backup_zip . ' -r ' . $dir_from);
            ob_end_clean();
            echo '-- Copy end OK' . "<br>\r\n";
            if (filesize($file_backup_zip) < 5) { // moins de 5 octets, c'est très suspect
                throw new ErrorException('mysql dump archive seems empty:' . $file_backup_zip);
            }
            $files = [
                $file_backup_zip,
                $dump_file,
                self::$dir_to_backup . '/log.txt'
            ];

            self::transfert('ftp', $files);

            unlink($dump_file);
            unlink($file_backup_zip);
        } catch (ErrorException $e) {
            self::log($e->getMessage() . ' on line ' . $e->getLine());
        }
    }

    public static function transfert($mode, $files) {
        $remote_path = self::$backup_id . '/' . self::$backup_id . '/';
        $conn_id = ftp_connect(self::$host_backup);

        // Identification avec un nom d'utilisateur et un mot de passe
        $login_result = ftp_login($conn_id, self::$host_backup_user, self::$host_backup_pass);

        // Vérification de la connexion
        if ((!$conn_id) || (!$login_result)) {
            echo "La connexion FTP a échoué !";
            exit;
        } else {
            echo "Connexion au serveur";
        }

        if (ftp_nlist($conn_id, $remote_path) == false) {
            ftp_mkdir($conn_id, $remote_path);
        }

        // Chargement d'un fichier
        foreach ($files as $source_file) {
            $destination_file = $remote_path . basename($source_file);
            $upload = ftp_put($conn_id, $destination_file, $source_file, FTP_BINARY);
            // Vérification du status du chargement
            if (!$upload) {
                echo "Le chargement FTP a échoué!";
            } else {
                echo "Chargement de $source_file vers -- en tant que $destination_file";
            }
        }

        // Fermeture du flux FTP
        ftp_close($conn_id);
    }

    public static function transfertRsync($mode) {
        echo 'rsync start' . "<br>\r\n";
        $remote_path = self::$backup_id . '/' . self::$backup_id . '/';
        //ob_start();
        //system('rsync -crahvPe"ssh -i /path/to/privateKey'
        system('rsync -crahvP --password-file=<(echo "' . self::$host_backup_pass . '")'
                . self::$dir_to_backup
                . ' ' . self::$host_backup_user . '@' . self::$host_backup . ':'
                . $remote_path);
        echo 'rsync -crahvP --password-file=<(echo "' . self::$host_backup_pass . '")'
        . self::$dir_to_backup
        . ' ' . self::$host_backup_user . '@' . self::$host_backup . ':'
        . $remote_path;
        echo 'rsync end' . "<br>\r\n";
        //ob_end_clean();
    }

    public static function log($message) {
        $m = $message . "\r\n";
        echo $m . "<br>";
        file_put_contents(self::$dir_to_backup . '/log.txt', $m, FILE_APPEND);
    }

}
