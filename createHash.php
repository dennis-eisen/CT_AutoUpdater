<?php

print getHash('PUT IN YOUR OWN PASSWORD HERE');

// Create hash for password_verify
function getHash($password) {
	if (isset($password)) return password_hash($password, PASSWORD_BCRYPT, array('cost' => 12));
}
