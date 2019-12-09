<?php
//phone number truewallet
$twreference = "EIEI";
//pin code truewallet
$twpassword = "EIEI";
//Token treuwallet
$twreference = "d73bfda1f30327a3|50CQDDHLP6PzLarUbpUS0MbHHXzUvx3FiLxuSEOxXsZw9GXzx2e98g==";
//จำนวน เงินเติมคูณด้วยเลขที่ต้องการ (เช่น เงินเติม 50 บาท x 1 = 50 บาท)
$wallet_x = "1";

//database config

define('DB_HOST', 'localhost');
define('DB_NAME', 'DDOS');
define('DB_USERNAME', 'root');
define('DB_PASSWORD', '14587931');
define('ERROR_MESSAGE', 'Oops, we ran into a problem here :(');

try {
$odb = new PDO('mysql:host=' . DB_HOST . ';dbname=' . DB_NAME . ';charset=utf8', DB_USERNAME, DB_PASSWORD);
}
catch( PDOException $Exception ) {
	error_log('ERROR: '.$Exception->getMessage().' - '.$_SERVER['REQUEST_URI'].' at '.date('l jS \of F, Y, h:i:s A')."\n", 3, 'error.log');
	die(ERROR_MESSAGE);
}

function error($string)
{
return '<div class="alert alert-danger alert-dismissable"><button type="button" class="close" data-dismiss="alert" aria-hidden="true">&times;</button><strong>ผิดพลาด : </strong> '.$string.'</div>';
}

function success($string)
{
return '<div class="alert alert-success alert-dismissable"><button type="button" class="close" data-dismiss="alert" aria-hidden="true">&times;</button><strong>สำเร็จ : </strong> '.$string.'</div>';
}
?>
