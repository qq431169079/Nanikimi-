<?php

$paginaname = 'ยิงเบอร์ (ฟรี)';


?>
<!DOCTYPE html>
<!--[if IE 9]>         <html class="no-js lt-ie10"> <![endif]-->
<!--[if gt IE 9]><!--> <html class="no-js"> <!--<![endif]-->
			<?php 
			
			include("@/header.php");

			?><center>
 </form>
  </button>
               <div id="page-content">
            
                        <div class="row">	
						
						<div class="col-xs-12">
							<div id="divall" style="display:inline"></div>
						<div id="div" style="display:inline"></div>
							<div class="widget">
								<div class="widget-content widget-content-mini themed-background-dark text-light-op">
								<span class="pull-right text-muted"></span>
								<marquee behavior="SLIDE" scrollamount="65" ><i class="fa fa-phone"></i> <b><?php echo htmlspecialchars($paginaname); ?></b></marquee>
								</div>
<div class="row justify-content-md-center" class="form-group">
  <div class="col-md-4">
 <div class="alert alert-success" role="alert" id="running"><i class="fas fa-sync-alt fa-spin"></i>
  &nbsp;กำลังทำงาน...
</div>
</div>

</div><br>
<center>
  <div class="widget-content">
                  <div class="form-horizontal form-bordered">
<div class="form-group">
  
<label class="col-md-3 control-label">เบอร์</label>
<div class="col-md-9">
<input type="text" name="number" class="form-control" placeholder="กรอกเบอร์ เช่น 0999999999" id="number">
</div>
</div>
<div class="form-group">
<label class="col-md-3 control-label">รูปแบบการโจมตี</label>
<div class="col-md-9">
<select class="form-control" id="type" style="margin-top: 15px;">
      <option value="SMS">SMS</option>
      <option value="CALL">CALL</option>
      <option value="ALL">SMS AND CALL</option>
    </select>
</div>
</div>
<div class="form-group">
  <label class="col-md-3 control-label">เวลา (วินาที)</label>
  <div class="col-md-9">
  <input type="text" class="form-control" placeholder="ความหน่วงเวลา เเนะนำ 5 วิ" id="time" value="5">
</div>
</div>
  <center>
<button type="button" class="btn btn-success" id="start">Start เริ่มต้น</button>
<button type="button" class="btn btn-danger" id="stop">ยกเลิก</button>
</center>
</div>
</div>
</div>
<script type="text/javascript">
$( "#running" ).hide();
$( "#stop" ).hide();
function spammer() {
 var num = $( "#number" ).val();
 var tpe = $( "#type" ).val();
 var tme = $( "#time" ).val();
 var today = new Date();
 var now = today.getHours() + ":" + today.getMinutes() + ":" + today.getSeconds();
 var date = today.getFullYear()+'-'+(today.getMonth()+1)+'-'+today.getDate();
    $.post("grab.php",
  {
    number: num,
    type: tpe
  },
  function(data, status){
    setTimeout(spammer, tme * 1000);
  });
  //end
}

$(document).ready(function(){
$( "#start" ).click(function() {
spammer();
  $( "#running" ).show();
  $( "#start" ).hide();
  $( "#stop" ).show();
});
$( "#stop" ).click(function() {
	window.location.href = '';
});
});
</script>	<br>
	</center>
  </center>
	</div>
	</div>
	</div>
	<footer class="animated fadeInUp" style="position: relative;">
	 <div class="text-center text-dark" style="padding:9px;">
	 </div>
   </footer>
		<?php include("@/script.php"); ?>
    </body>

</html>