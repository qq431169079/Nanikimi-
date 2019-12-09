<?php
require '@/config.php';
require '@/init.php';
if ($user -> LoggedIn())
{
header('Location: index.php');
}
?>
<!DOCTYPE html>
<!--[if IE 9]>         <html class="no-js lt-ie10"> <![endif]-->
<!--[if gt IE 9]><!--> <html class="no-js"> <!--<![endif]-->
<head>

	
		<script>
		function login()
		{
		var username=$('#loginusername').val();
		var password=$('#loginpassword').val();
		document.getElementById("logindiv").style.display="none";
		document.getElementById("loginimage").style.display="inline";
		var xmlhttp;
		if (window.XMLHttpRequest)
		  {// code for IE7+, Firefox, Chrome, Opera, Safari
		  xmlhttp=new XMLHttpRequest();
		  }
		else
		  {// code for IE6, IE5
		  xmlhttp=new ActiveXObject("Microsoft.XMLHTTP");
		  }
		xmlhttp.onreadystatechange=function()
		  {
		  if (xmlhttp.readyState==4 && xmlhttp.status==200)
			{
			document.getElementById("logindiv").innerHTML=xmlhttp.responseText;
			document.getElementById("loginimage").style.display="none";
			document.getElementById("logindiv").style.display="inline";
			if (xmlhttp.responseText.search("Redirecting") != -1)
			{
			setInterval(function(){window.location="index.php"},3000);
			}
			}
		  }
		xmlhttp.open("POST","ajax/login.php?type=login",true);
		xmlhttp.setRequestHeader("Content-type","application/x-www-form-urlencoded");
		xmlhttp.send("username=" + username + "&password=" + password);
		}
		</script>
        <meta charset="utf-8">

        <title><?php echo htmlspecialchars($sitename); ?> - Login</title>
        <link rel="stylesheet" href="https://stackpath.bootstrapcdn.com/bootstrap/4.3.1/css/bootstrap.min.css">
        <meta name="author" content="StrikeREAD">
        <meta name="robots" content="noindex, nofollow">
        <meta name="viewport" content="width=device-width,initial-scale=1,maximum-scale=1.0">
        <link rel="shortcut icon" href="img/favicon.png">
        <link rel="apple-touch-icon" href="img/icon57.png" sizes="57x57">
        <link rel="apple-touch-icon" href="img/icon72.png" sizes="72x72">
        <link rel="apple-touch-icon" href="img/icon76.png" sizes="76x76">
        <link rel="apple-touch-icon" href="img/icon114.png" sizes="114x114">
        <link rel="apple-touch-icon" href="img/icon120.png" sizes="120x120">
        <link rel="apple-touch-icon" href="img/icon144.png" sizes="144x144">
        <link rel="apple-touch-icon" href="img/icon152.png" sizes="152x152">
        <link rel="apple-touch-icon" href="img/icon180.png" sizes="180x180">
        <link rel="stylesheet" href="css/bootstrap.min.css">
	   <link rel="stylesheet" href="css/plugins.css">
	   <link rel="stylesheet" href="css/main.css">
	   <link rel="stylesheet" href="css/themes.css">
	   <link rel="stylesheet" href="css/themes/amethyst.css" id="theme-link">
	   <link href="https://fonts.googleapis.com/css?family=Kanit&display=swap" rel="stylesheet">
		<script src="js/vendor/modernizr-2.8.1.min.js"></script>
	    <script src="https://ajax.googleapis.com/ajax/libs/jquery/2.1.3/jquery.min.js"></script>
	   <style type="text/css">.jqstooltip { width: auto !important; height: auto !important; position: absolute;left: 0px;top: 0px;visibility: hidden;background: #000000;color: white;font-size: 11px;text-align: left;white-space: nowrap;padding: 5px;z-index: 10000;}.jqsfield { color: white;font: 10px arial, san serif;text-align: left;}</style>
</head>
<body>
<?php 
include("lolipop.php");
?>
<div id="login-container">
<h1 class="h2 text-light text-center push-top-bottom animation-slideDown">
<strong><?php echo htmlspecialchars($sitename); ?></strong>
<br>
	<div class="col-15">
<div class="input-group mb-3">
<div class="input-group-prepend">
<span class="input-group-text bg-success text-light">&nbsp;ประกาศ</span>
<span class="input-group-text bg-white text-dark">
<marquee onmouseout="if (!window.__cfRLUnblockHandlers) return false; this.start()" onmouseover="if (!window.__cfRLUnblockHandlers) return false; this.stop()" style="font-size:18px;">ประกาศเคลียร์userที่ไม่ได้เช่าออกบางส่วนน่ะครับ กรุณาไปสมัครใหม่ด้วยหากต้องการใช้งาน</marquee>
</span>
</div>
</div>
</div>
</h1>
  <div id="logindiv" style="display:none"></div>
<div class="block animation-fadeInQuickInv">
<div class="block-title">
<div class="block-options pull-right">
<a href="lost.php" class="btn btn-effect-ripple btn-primary" data-toggle="tooltip" data-placement="left" title="ลืมรหัสผ่าน?"><i class="fa fa-exclamation-circle"></i></a>
<a href="register.php" class="btn btn-effect-ripple btn-primary" data-toggle="tooltip" data-placement="left" title="สมัครสมาชิก"><i class="fa fa-plus"></i></a>
</div>
<h2>เข้าสู่ระบบ <img id="loginimage" src="img/jquery.easytree/loading.gif" style="display:none"/></h2>
</div>
<div class="form-horizontal">
<div class="form-group">
<div class="col-xs-12">
<input type="text" id="loginusername" class="form-control" placeholder="ชื่อผู้ใช้งาน">
</div>
</div>
<div class="form-group">
<div class="col-xs-12">
<input type="password" id="loginpassword" class="form-control" placeholder="รหัสผ่าน">
</div>
</div>
<div class="form-group form-actions">
<div class="col-xs-8">
<label class="csscheckbox csscheckbox-primary">
<input type="checkbox" id="login-remember-me" name="login-remember-me" checked>
<span></span>
</label>
ยอมรับ <a href="<?php echo htmlspecialchars($tos); ?>">กฎกติกาในการใช้งาน</a>
</div>
<div class="col-xs-4 text-right">
<button type="button" onclick="login()" class="btn btn-effect-ripple btn-sm btn-primary"><i class="fa fa-sign-in"></i> เข้าสู่ระบบ</button>
</div>
</div>
</div>
</div>
<footer class="text-muted text-center animation-pullUp">
<small><span id="year-copy"></span> &copy; <a href="<?php echo htmlspecialchars($siteurl); ?>" target="_blank"><?php echo htmlspecialchars($sitename); ?></a></small>
</footer>
</div>
<script src="js/vendor/jquery.min.js"></script>
<script src="js/vendor/bootstrap.min.js"></script>
<script src="js/plugins.js"></script>
<script src="js/app.js"></script><script src="js/pages/readyLogin.js"></script>
<script type="text/javascript">
        $("#signinForm").validate({
		rules: {
			login: "required",
			password: "required"
		},
		messages: {
			firstname: "Please enter your login",
			lastname: "Please enter your password"			
		}
	});            
</script>
	
	<audio autoplay loop>
<source src="login.mp3" type="audio/mpeg">
</audio>
	
</body>
</html>