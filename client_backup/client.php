<?php

require __DIR__ . '/backup-server.php';

$config = file_get_contents('client_config.json');
$config = json_decode($config, true);
$secret_key = $config['secret_key'];
if (!empty($_POST) && $_POST['secret_key'] === $config['secret_key']) { // protection
    WpBackup::init($config, $_POST);
}
