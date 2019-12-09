<?php

$paginaname = 'Purchase';


?>
<!DOCTYPE html>
<!--[if IE 9]>         <html class="no-js lt-ie10"> <![endif]-->
<!--[if gt IE 9]><!--> <html class="no-js"> <!--<![endif]-->
			<?php 
			
			include("@/header.php");

			?>
			<script>
					

					function topup()
					{
					document.getElementById("topup_btn").setAttribute("disabled", true);
					var card = document.getElementById("tmn").value;
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
						document.getElementById("topup_btn").setAttribute("disabled", false);
						document.getElementById("topupdiv").innerHTML=xmlhttp.responseText;
			document.getElementById("loginimage").style.display="none";
			document.getElementById("topupdiv").style.display="inline";
						}
					  }
					xmlhttp.open("GET","ajax/topup.php?card="+card,true);
					xmlhttp.send();
					}

					function truewallet()
					{
					document.getElementById("truewallet_btn").setAttribute("disabled", true);
					var card = document.getElementById("truewallet").value;
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
						document.getElementById("truewallet_btn").setAttribute("disabled", false);
						document.getElementById("topupdiv").innerHTML=xmlhttp.responseText;
			document.getElementById("loginimage").style.display="none";
			document.getElementById("topupdiv").style.display="inline";
						}
					  }
					xmlhttp.open("GET","ajax/topup.php?card="+card,true);
					xmlhttp.send();
					}

					</script>

                    <div id="page-content">
            
                        <div class="row">	
						<div class="col-sm-6">
							<div class="widget">
								<div class="widget-content widget-content-mini themed-background-dark text-light-op">
								<span class="pull-right text-muted"><?php echo $date1; ?></span>
								<i class="fa fa-tasks"></i> <b>วิธีการใช้งาน</b>
								</div>
								
								<div style="position: relative; wid  th: auto" class="slimScrollDiv">
								<iframe width="530" height="295" src="https://www.youtube.com/embed/<?php echo $video1; ?>" frameborder="0" allowfullscreen></iframe>	
								</div>
							</div>
						</div>
						
						<div class="col-sm-6">
							<div class="widget">
								<div class="widget-content widget-content-mini themed-background-dark text-light-op">
								<span class="pull-right text-muted"><?php echo $date2; ?></span>
								<i class="fa fa-tasks"></i> <b>วิธีเติมเงิน</b>
								</div>
								
								<div style="position: relative; width: auto" class="slimScrollDiv">
										<iframe width="530" height="295" src="https://www.youtube.com/embed/<?php echo $video2; ?>" frameborder="0" allowfullscreen></iframe>	
									
								</div>
							</div>
						</div>
						
						<div class="col-sm-12">
							<div class="widget">
								<div class="widget-content widget-content-mini themed-background-dark text-light-op">
								<span class="pull-right text-muted"></span>
								<i class="fa fa-credit-card"></i> <b>เติมเงิน Truemony</b>
								</div>
								
								<div style="position: relative; width: auto;padding: 10px 10%;" class="slimScrollDiv">
									
<div class="alert alert-danger" role="alert" id="loginimage">
	<center>กรุณาใส่รหัสบัตร TrueMony!!</center>
</div>
<div id="topupdiv" style="display:none"></div>
									<form method="post" action="">
										<div class="input-group">
  <span class="input-group-addon" id="basic-addon1">รหัสบัตรเงินสด</span>
  <input type="text" class="form-control" maxlength="14" id="tmn" placeholder="Truemoney">
</div>

<button type="button" class="btn btn-success btn-block" style="margin-top: 10px;" id="topup_btn" onclick="topup()">เติมเงิน</button>
									</form>


								</div>
							</div>
						</div>

<div class="col-sm-12">
							<div class="widget">
								<div class="widget-content widget-content-mini themed-background-dark text-light-op">
								<span class="pull-right text-muted"></span>
								<i class="fa fa-shopping-cart"></i> <b>เแพลนทั้งหมด</b>
								</div>
								
								<div style="position: relative; width: auto" class="slimScrollDiv">
									<div id="stats">
										<table class="table table-striped">
											<tbody>
												<tr>
													<th><center>แพลน</center></th>
													<th><center>ราคา</center></th>
													<th><center>ระยะเวลา</center></th>
													<th><center>เวลายิง</center></th>
												</tr>
												<?php
												$SQLGetPlans = $odb -> query("SELECT * FROM `plans` WHERE `private` = 0 ORDER BY `price` ASC");
												while ($getInfo = $SQLGetPlans -> fetch(PDO::FETCH_ASSOC))
												{
													$name = $getInfo['name'];
													$price = $getInfo['price'];
													$length = $getInfo['length'];
													$unit = $getInfo['unit'];
													$concurrents = $getInfo['concurrents'];
													$mbt = $getInfo['mbt'];
													$ID = $getInfo['ID'];
													
													echo '
													<tr>
														<td><center>'.htmlspecialchars($name).'</center></td>
														<td><center>'.$price.' Truemoney</td>
														<td><center>'.$length.' '.htmlspecialchars($unit).'</center></td>
														<td><center>'.$mbt.'วินาที</center></td>
														<td><center>
														</center></td>
													</tr>';
												}
												?>
												
											</tbody>
										</table>
									</div>
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