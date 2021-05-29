<?php

global $nonce;

if (isset($_COOKIE["Nonce"]) && !empty($_COOKIE["Nonce"])) {
  $nonce = $_COOKIE["Nonce"];
} else {
  $nonce = randomstring();
    setcookie("Nonce", $nonce, 0, URIBASE);
}
/* return a random ascii string */
function randomstring($length = 32) {
  $chars = "abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ1234567890";
  mt_srand((double)microtime()*1000000);
  $pass = "";
  for ($i = 0; $i < $length; $i++) {
    $num = random_int(0, strlen($chars)-1);
    $pass .= $chars[$num];
  }
  return $pass;
}

