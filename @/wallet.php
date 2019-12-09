<?php

require_once("TrueWallet.class.php");
require_once 'config.php';
require_once 'init.php';



//From truewallet.php, POST results.

$ref = $_POST['wallet'];

$member = $_POST['member'];



// Login with Access Token.

$tw = new TrueWallet($twusername, $twpassword, $twreference); // Login Credentials + Reference Token

$tw->Login();







//Check TXIDs in database.

$strSQL2 = "SELECT * FROM tw_transactions WHERE numreference = '". $ref ."'";

$objQuery2 = $mysqli->query($strSQL2);

$objResult2 = $objQuery2->fetch_assoc();



// Fetch last 5 transactions.

$transactions = $tw->getTransaction(5);

foreach ($transactions["data"]["activities"] as $report) {

	$data = $tw->GetTransactionReport($report["report_id"]);



	if (@$data['data']['service_code'] == 'creditor' ) {

		$tx['id'] = $data['data']['section4']['column2']['cell1']['value'];

		$tx['amount'] = str_replace(',', '', $data['data']['section3']['column1']['cell1']['value']);

		

		//Add TrueWallet transactions history to database to prevent abuse of transaction number.

    	@$tw_history = "INSERT INTO tw_transactions (name, numreference, point) VALUES ('". $member ."', '". $ref ."', '". $tx['amount'] ."')";

    	//Point multiply

    	$dbpoint = $tx['amount']*$wallet_x;

    	$addpoint = "UPDATE tw_transactions SET point = point +'". $dbpoint ."' WHERE name = ". $member ." ";



		if ($objResult2) {

    		echo "<script language=\"JavaScript\">

                	alert('ล้มเหลว เลขอ้างอิง ". $ref ." ถูกใช้งานไปแล้ว');

                	window.history.go(-1);

              	  </script>";

    		break;

   		} elseif ($ref == $tx['id']) {

    		echo "<script language=\"JavaScript\">

                	alert('ยินดีด้วยคุณได้เติมเงินเข้าระบบ ". $tx['amount'] ." บาท(อ่านประกาศผู้โชคดีที่หน้าเว็บ)');

                	window.history.go(-1);

              	  </script>";

    		mysqli_query($mysqli, $tw_history);

    		mysqli_query($mysqli, $addpoint);

    		break;

   		} else {

        	echo "<script language=\"JavaScript\">

                	alert('ผิดพลาดเลขอ้างอิงดังกล่าวไม่ถูกต้อง');

                	window.history.go(-1);

                  </script>";

    		break;

    	}

   	}

}



mysqli_close($mysqli);

?>