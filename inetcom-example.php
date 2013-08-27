<?php
require_once "inetcom.class.php";

$inetcom = new Inetcom('username', 'password', TRUE);
$inetcom->login();
$tvchannels = $inetcom->getfulllist();
echo var_export($tvchannels);