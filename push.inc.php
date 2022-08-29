<?php

// Set your ChurchTools URL.
const CT_URL = '...';

// Pushover, put in your own credentials below and set service to "true" if needed
const PUSHOVER = false;
const PUSHOVER_TOKEN = '...';
const PUSHOVER_USER = '...';

// Pushover Integration
function pushover($title, $message, $priority = 0, $retry = null, $expire = null): bool {
    if(!isset($title, $message)) {
        return false;
    }
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
    return $xml->status == 1;
}

function push($title, $message, $priority = 0, $retry = null, $expire = null): void {
    if (PUSHOVER) {
        pushover($title, $message, $priority, $retry, $expire);
    }
}
