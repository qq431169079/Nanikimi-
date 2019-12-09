<?php
session_start();
require_once("TrueWallet.class.php");

$username = $_POST["username"];
$password = $_POST["password"];

$_SESSION["username"] = $username;
$_SESSION["password"] = $password;
$tw = new TrueWallet($username, $password);
$tw->RequestLoginOTP();
?>
<!DOCTYPE html>
<html>
<head>
	<title>TrueWallet - Request OTP</title>
	<link rel="stylesheet" href="https://maxcdn.bootstrapcdn.com/bootstrap/4.3.1/css/bootstrap.min.css">
	<link rel="stylesheet" href="https://www.w3schools.com/w3css/4/w3.css">
</head>
<body>

<div class="container" align="middle">
	<div class=" w3-display-container w3-display-middle">
		<h1><b>กรุณากรอกข้อมูล OTP ที่ได้รับในโทรศัพท์</b></h1><BR><BR>
		<form method="post" action="tw_getreference.php">
			<div class="form-group">
				<h1>เบอร์โทรศัพท์</h1><BR>
				<input type="tel" name="tel" class="form-control" style="width: 100%">
			</div>

			<div class="form-group">
				<h1>รหัส OTP:</h1><BR>
				<input type="otp" name="otp" class="form-control" style="width: 100%">
			</div>

			<div class="form-group">
				<h1>OTP Reference:</h1><BR>
				<input type="otp_r" name="otp_r" class="form-control" style="width: 100%">
			</div>
			<button type="submit" name="submit" class="btn btn-primary">รับค่า Reference Token</button>
		</form>
	</div>
</div>

</body>
</html>