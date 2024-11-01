<?php
/*
Plugin Name: Tel-Publish
Description: Плагин отправляет записи в телеграм
Author: Pechenki
Version: 0.0.1
Author URI: https://pechenki.top/
*/
//////////////////////////////////
if (!defined('ABSPATH')) {
    exit;
}

include_once __DIR__ . '/TelPublish.php';

$data = [
    'token' => get_option('tel_pub_token'),
    'chatId' => get_option('tel_pub_chat_id'),
    'rhash' => get_option('tel_pub_rhash'),
    'html' => get_option('tel_pub_html')
];

$TelPublish = TelPublish::init($data);
