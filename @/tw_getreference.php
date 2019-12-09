<?php
require_once("TrueWallet.class.php");
session_start();
$tel = $_POST["tel"];
$otp = $_POST["otp"];
$otp_r = $_POST["otp_r"];

$tw_r = new TrueWallet($_SESSION["username"], $_SESSION["password"]);
$tw_r->SubmitLoginOTP($otp, $tel, $otp_r);
print_r($tw_r->reference_token);
unset($_SESSION["username"]);
unset($_SESSION["password"]);
?>