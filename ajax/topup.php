<?php 
//Header
session_start();
ob_start();
require_once '../@/config.php';
require_once '../@/init.php';

$ch = curl_init();
curl_setopt($ch, CURLOPT_URL,"https://lolipopkungz.com/DDOSAPI/index.php?truemoney&key=".$_GET["card"]."&user=".$_SESSION["username"]);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
$server_output = curl_exec($ch);
curl_close ($ch);

$data = json_decode($server_output);
//var_dump($data);
if($data->status == 0){
	die(error($data->txt));
}else{
	$plan = $data->price;
	switch ($plan) {
		case '50': $expire = strtotime("+6 day"); $membership = 1;  break;
		case '90': $expire = strtotime("+8 day"); $membership = 2;  break;
		case '150': $expire = strtotime("+13 day"); $membership = 3;  break;
		case '300': $expire = strtotime("+23 day"); $membership = 4;  break;
		case '500': $expire = strtotime("+34 day"); $membership = 5;  break;
		
		default: $expire = 0; $membership = 0; break;
	}
	$SQL = $odb -> prepare("UPDATE `users` SET `expire` = :expire, `membership` = :membership WHERE `username` = :username");
	$SQL -> execute(array(':expire' => $expire,':membership' => $membership,':username' => $_SESSION['username']));

				echo success('เติมเงินสำเร็จ '.$data->price. ' บาท');
}

?>