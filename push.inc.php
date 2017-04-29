<?php

// Autoloader
spl_autoload_register(function ($class) {
  include __DIR__ . '/' . strtr($class, ['\\' => '/']) . '.php';
});

// Set your ChurchTools URL.
define ('CT_URL',               '...');

// Pushover, put in your own credentials below and set service to "true" if needed
define ('PUSHOVER_TOKEN',		'...');
define ('PUSHOVER_USER',		'...');
define ('PUSHOVER',             false);

/*
    Pushbullet, put in your own credentials below and set service to "true" if needed

    Our pushbullet integrations requires the pushbullet libary of ivkos:
    https://github.com/ivkos/Pushbullet-for-PHP
    Import the Pushbullet libary into the root of your webspace,
    if you want to use this service and remove the hastag from the subsequent line.
*/
# use Pushbullet\Pushbullet;
define ('PUSHBULLET_TOKEN',		'...');
define ('PUSHBULLET_CHANNEL',	'...');
define ('PUSHBULLET',           false);

// Pushover Integration
function pushover($title, $message, $priority = 0, $retry = null, $expire = null) {
    if (PUSHOVER) {
        if(!isset($title, $message)) return false;

    	$c = curl_init();
    	curl_setopt($c, CURLOPT_URL, 'https://api.pushover.net/1/messages.xml');
    	curl_setopt($c, CURLOPT_HEADER, false);
    	curl_setopt($c, CURLOPT_SSL_VERIFYPEER, false);
    	curl_setopt($c, CURLOPT_RETURNTRANSFER, true);
    	curl_setopt($c, CURLOPT_POSTFIELDS, array(
    		'token' => PUSHOVER_TOKEN,
    		'user' => PUSHOVER_USER,
    		'title' => $title,
    		'message' => $message,
    		'html' => 1,
    		'device' => '',
    		'priority' => $priority,
    		'timestamp' => time(),
    		'expire' => $expire,
    		'retry' => $retry,
    		'callback' => '',
    		'url' => CT_URL,
    		'sound' => '',
    		'url_title' => CT_URL
    	));
    	$response = curl_exec($c);

    	$xml = simplexml_load_string($response);
     	return ($xml->status == 1) ? true : false;
    }
}

// Pushbullet Integration
function pushbullet($title, $message) {
    if ('PUSHBULLET') {
    	$push = new Pushbullet(PUSHBULLET_TOKEN);
    	$channel = $push->channel(PUSHBULLET_CHANNEL);

    	$channel->pushNote($title, $message);
    }
}

// Combined Integration
function push($title, $message, $priority = 0, $retry = null, $expire = null) {
	pushover($title, $message, $priority, $retry, $expire);
	pushbullet(strip_tags($title), strip_tags($message));
}

function clientIp() {
	if (! isset($_SERVER['HTTP_X_FORWARDED_FOR'])) $client_ip = $_SERVER['REMOTE_ADDR'];
	else $client_ip = $_SERVER['HTTP_X_FORWARDED_FOR'];
	return $client_ip;
}
