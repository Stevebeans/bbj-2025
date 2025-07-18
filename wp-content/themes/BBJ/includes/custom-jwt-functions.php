<?php

use \Firebase\JWT\JWT;

function verify_jwt_token($token) {
  $secret_key = 'your_secret_key'; // Replace with your actual secret key
  try {
    $decoded = JWT::decode($token, $secret_key, array('HS256'));
    return (array) $decoded;
  } catch (Exception $e) {
    return null;
  }
}
