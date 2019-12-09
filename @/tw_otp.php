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
		<h1><b>กรุณากรอกข้อมูล เพื่อรับรหัส OTP</b></h1><BR><BR>
		<form action="tw_otpreceive.php" method="post">
			<div class="form-group">
				<h1>เบอร์โทรศัพท์/อีเมลล์:</h1><BR>
				<input type="text" name="username" class="form-control" style="width: 100%">
			</div>

			<div class="form-group">
				<h1>รหัสผ่าน/PIN:</h1><BR>
				<input type="password" name="password" class="form-control" style="width: 100%">
			</div>
			<button type="submit" name="submit" class="btn btn-primary">รับรหัส OTP</button>
		</form>
	</div>
</div>

</body>
</html>