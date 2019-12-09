<?php
$paginaname = 'Code Refill';
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
					var code = document.getElementById("tmn").value;
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
						document.getElementById("topupdiv").innerHTML=xmlhttp.responseText;
			document.getElementById("loginimage").style.display="none";
			document.getElementById("topupdiv").style.display="inline";
						document.getElementById("topup_btn").removeAttribute("disabled");
						}
					  }
					xmlhttp.open("GET","ajax/code.php?code="+code,true);
					xmlhttp.send();
					}

					</script>

                    <div id="page-content">
            
                        <div class="row">	
						
						
						
						<div class="col-sm-12">
							<div class="widget">
								<div class="widget-content widget-content-mini themed-background-dark text-light-op">
								<span class="pull-right text-muted"></span>
								<i class="fa fa-barcode"></i> <b>เติมโค็ต</b>
								</div>
								
								<div style="position: relative; width: auto;padding: 10px 10%;" class="slimScrollDiv">
									
<div class="alert alert-danger" role="alert" id="loginimage">
	1 Code ใช้ได้ครั้งเดียวนะครับ
</div>
<div id="topupdiv" style="display:none"></div>
									<form method="post" action="">
										<div class="input-group">
  <span class="input-group-addon" id="basic-addon1">Code</span>
  <input type="text" class="form-control" id="tmn" placeholder="Refill Code">
</div>

<button type="button" class="btn btn-success btn-block" style="margin-top: 10px;" id="topup_btn" onclick="topup()">เติมโค๊ต</button>
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