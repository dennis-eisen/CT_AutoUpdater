<?php
/**
* Echos a bcrypt-encrypted password hash of the password passed in as query string
*/

echo password_hash($_SERVER['QUERY_STRING'], PASSWORD_BCRYPT, array('cost' => 12));
