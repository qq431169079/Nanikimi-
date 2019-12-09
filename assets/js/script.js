function isuccess(title, desc) { 
	swal({
	title: '<span style="color:black">'+title+'</span>',
	type: 'success',
	html: '<span style="color:gray">'+desc+'</span>',
	confirmButtonText: '<span style="color:white;"><i class="fa fa-times"></i> ปิด</span>',
	confirmButtonColor: '#e54d40',
	})
	.then(function(isConfirm) {
	 var url = "";   
	  if (isConfirm === true) {
		$(location).attr('href',url);
	  }else {
		$(location).attr('href',url);
	  }
	})
}
function itoken(title, desc) { 
	swal({
	title: '<span style="color:black">'+title+'</span>',
	type: 'success',
	html: '<span style="color:gray">'+desc+'</span>',
	confirmButtonText: '<span style="color:white;"><i class="fa fa-times"></i> ปิด</span>',
	confirmButtonColor: '#e54d40',
	})
	.then(function(isConfirm) {
	 var url = ".";   
	  if (isConfirm === true) {
		$(location).attr('href',url);
	  }else {
		$(location).attr('href',url);
	  }
	})
}
function iempty(title, desc) { 
	swal({
	title: '<span style="color:black">'+title+'</span>',
	type: 'info',
	html: '<span style="color:gray">'+desc+'</span>',
	confirmButtonText: '<span style="color:white;"><i class="fa fa-times"></i> ปิด</span>',
	confirmButtonColor: '#e54d40',
	})
	.then(function(isConfirm) {
	 var url = "?page=addtoken";   
	  if (isConfirm === true) {
		$(location).attr('href',url);
	  }else {
		$(location).attr('href',url);
	  }
	})
}
function idelete(title, desc) { 
	swal({
	title: '<span style="color:black">'+title+'</span>',
	type: 'success',
	html: '<span style="color:gray">'+desc+'</span>',
	confirmButtonText: '<span style="color:white;"><i class="fa fa-times"></i> ปิด</span>',
	confirmButtonColor: '#e54d40',
	})
	.then(function(isConfirm) {
	 var url = "index.php?page=addtoken";   
	  if (isConfirm === true) {
		$(location).attr('href',url);
	  }else {
		$(location).attr('href',url);
	  }
	})
}

function ierror(title, desc) {
	swal({
	title: '<span style="color:black">'+title+'</span>',
	type: 'error',
	html: '<span style="color:gray">'+desc+'</span>',
	confirmButtonText: '<span style="color:white;"><i class="fa fa-times"></i> ปิด</span>',
	confirmButtonColor: '#e54d40',
   })
}

function iwarning(title, desc) {
	swal({
	title: '<span style="color:black">'+title+'</span>',
	type: 'warning',
	html: '<span style="color:gray">'+desc+'</span>',
   })
}
function check_token(){
$("#checktoken").html("<script>iwarning('<i class=\"fa fa-spinner fa-spin fa-fw\"></i>\', 'กำลังทำรายการ รอซักครู่..');</script>");
}

function autolike(){
	var idpost = $("#id_post").val();
	var type = $("#type").val();
	
	  if (!idpost) {
        $("#status").html("<script>ierror('Error', 'กรุณาอย่าเว้นช่องว่าง..');</script>");} 
		
	  if (type == 'LIKE') {
		 $("#start_like").html('<i class="fa fa-spinner fa-spin"></i> กำลังปั้มไลค์ กรุณารอสักครู่...');
		document.getElementById("start_like").disabled = true;
		        $.get("api/likes.php?id_post=" + idpost, function(data, status) {
                var obj = jQuery.parseJSON(data);

                if (!obj.status) {
                    $("#status").html("<script>ierror('Likes Error', 'ไม่สามารถเชื่อมต่อ API ได้');</script>");
                } else if (obj.status == 'success') {
                    $("#status").html("<script>isuccess('Likes Success', '"+ obj.msg +"');</script>");
                } else if (obj.status == 'error') {
                    $("#status").html("<script>ierror('Likes Error', '"+ obj.msg +"');</script>");
                } else {
                    $("#status").html("<script>ierror('Likes Error', 'ไม่สามารถเชื่อมต่อ API ได้');</script>");
                }
				
				$("#start_like").html('<i class="fa fa-thumbs-up"></i> เริ่มปั้มไลค์');
                document.getElementById("start_like").disabled = false;

            });
	  }
	  else
	  {
		 $("#start_like").html('<i class="fa fa-spinner fa-spin"></i> กำลังปั้มอิโมจิ กรุณารอสักครู่...');
		document.getElementById("start_like").disabled = true;
		        $.get("api/emoji.php?id_post=" + idpost + "&type="+ type, function(data, status) {
                var obj = jQuery.parseJSON(data);

                if (!obj.status) {
                    $("#status").html("<script>ierror('Likes Error', 'ไม่สามารถเชื่อมต่อ API ได้');</script>");
                } else if (obj.status == 'success') {
                    $("#status").html("<script>isuccess('Likes Success', '"+ obj.msg +"');</script>");
                } else if (obj.status == 'error') {
                    $("#status").html("<script>ierror('Likes Error', '"+ obj.msg +"');</script>");
                } else {
                    $("#status").html("<script>ierror('Likes Error', 'ไม่สามารถเชื่อมต่อ API ได้');</script>");
                }
				
				$("#start_like").html('<i class="fa fa-haert"></i> เริ่มปั้มอิโมจิ');
                document.getElementById("start_like").disabled = false;

            });
	}
}

function addfriend(){
	var id = $("#uid").val();
	var amount = $("#amount").val();
	  if (!id) {
        $("#status").html("<script>ierror('Error', 'กรุณาอย่าเว้นช่องว่าง..');</script>");
    } else {
		 $("#start_friend").html('<i class="fa fa-spinner fa-spin"></i> กำลังปั้มเพื่อน กรุณารอสักครู่...');
		document.getElementById("start_friend").disabled = true;
		        $.get("api/addfriend.php?uid=" + id + "&amount=" + amount, function(data, status) {
                var obj = jQuery.parseJSON(data);

                if (!obj.status) {
                    $("#status").html("<script>ierror('Friend Error', 'ไม่สามารถเชื่อมต่อ API ได้');</script>");
                } else if (obj.status == 'success') {
                    $("#status").html("<script>isuccess('Friend Success', '"+ obj.msg +"');</script>");
                } else if (obj.status == 'error') {
                    $("#status").html("<script>ierror('Friend Error', '"+ obj.msg +"');</script>");
                } else {
                    $("#status").html("<script>ierror('Friend Error', 'ไม่สามารถเชื่อมต่อ API ได้');</script>");
                }
				
				$("#start_friend").html('<i class="fa fa-user-plus"></i> เริ่มปั้มเพื่อน');
                document.getElementById("start_friend").disabled = false;

            });
	}
}


function botcomment(){
	var id_post = $("#id_post").val();
	var message = $("#message").val();
	var amount = $("#amount").val();
	  if (!id_post) {
        $("#status").html("<script>ierror('Error', 'กรุณาอย่าเว้นช่องว่าง..');</script>");
	  }else if (!message){
		  $("#status").html("<script>ierror('Error', 'กรุณาอย่าเว้นช่องว่าง..');</script>");
    } else {
		 $("#start_botcomment").html('<i class="fa fa-spinner fa-spin"></i> กำลังปั้มคอมเม้นต์ กรุณารอสักครู่...');
		document.getElementById("start_botcomment").disabled = true;
		        $.get("api/botcomment.php?id_post=" + id_post+ "&message="+message + "&amount=" + amount, function(data, status) {
                var obj = jQuery.parseJSON(data);

                if (!obj.status) {
                    $("#status").html("<script>ierror('Comment Error', 'ไม่สามารถเชื่อมต่อ API ได้');</script>");
                } else if (obj.status == 'success') {
                    $("#status").html("<script>isuccess('Comment Success', '"+ obj.msg +"');</script>");
                } else if (obj.status == 'error') {
                    $("#status").html("<script>ierror('Comment Error', '"+ obj.msg +"');</script>");
                } else {
                    $("#status").html("<script>ierror('Comment Error', 'ไม่สามารถเชื่อมต่อ API ได้');</script>");
                }
				
				$("#start_botcomment").html('<i class="fa fa-comments"></i> เริ่มปั้มคอมเม้นต์');
                document.getElementById("start_botcomment").disabled = false;

            });
	}
}

function checktoken() {

    var access_token = $("#access_token").val();
	var amount = $("#amount").val();

    if (!access_token) {
        $("#status").html("<script>ierror('Error', 'กรุณาอย่าเว้นช่องว่าง..');</script>");
    } else {

        if (access_token.indexOf("access_token=") > -1) {
            var acc = access_token.match("https://www.facebook.com/connect/login_success.html#access_token=(.*)&expires_in=(.*)");
            $("#access_token").val(acc[1]);
        } else {
            $("#access_token").val(access_token);
        }

    }


}

function joingroup() {
    var group = $("#group").val();
	if (!group) {
        $("#status").html("<script>ierror('Error', 'กรุณาอย่าเว้นช่องว่าง...');</script>");
    } else {
            $("#start_joingroup").html('<i class="fa fa-spinner fa-spin"></i> กำลังปั้มคน กรุณารอสักครู่...');
            document.getElementById("start_joingroup").disabled = true;
            $.get("api/join.php?group=" + group, function(data, status) {
                var obj = jQuery.parseJSON(data);

                if (!obj.status) {
                    $("#status").html("<script>ierror('Group Error', 'ไม่สามารถเชื่อมต่อ API ได้');</script>");
                } else if (obj.status == 'success') {
                    $("#status").html("<script>isuccess('Group Success', '"+ obj.msg +"');</script>");
                } else if (obj.status == 'error') {
                    $("#status").html("<script>ierror('Group Error', '"+ obj.msg +"');</script>");
                } else {
                    $("#status").html("<script>ierror('Group Error', 'ไม่สามารถเชื่อมต่อ API ได้');</script>");
                }
                $("#start_joingroup").html('<i class="fa fa-user-plus"></i> เริ่มปั้มคนเข้ากลุ่ม');
                document.getElementById("start_joingroup").disabled = false;

            });
    }

}

function autoshare(){
	var id_post = $("#id_post").val();
	var message = $("#message").val();
	var amount = $("#amount").val();
	  if (!id_post) {
        $("#status").html("<script>ierror('Error', 'กรุณาอย่าเว้นช่องว่าง..');</script>");
	  }else {
		 $("#start_botshare").html("<script>iwarning('<i class=\"fa fa-spinner fa-spin fa-fw\"></i>\', 'กำลังทำรายการ รอซักครู่..');</script>");
		document.getElementById("start_botshare").disabled = true;
		        $.get("api/botshare.php?id_post=" + id_post+"&message="+message, function(data, status) {
                var obj = jQuery.parseJSON(data);

                if (!obj.status) {
                    $("#status").html("<script>ierror('Share Error', 'ไม่สามารถเชื่อมต่อ API ได้');</script>");
                } else if (obj.status == 'success') {
                    $("#status").html("<script>isuccess('Share Success', '"+ obj.msg +"');</script>");
                } else if (obj.status == 'error') {
                    $("#status").html("<script>ierror('Share Error', '"+ obj.msg +"');</script>");
                } else {
                    $("#status").html("<script>ierror('Share Error', 'ไม่สามารถเชื่อมต่อ API ได้');</script>");
                }
				
				$("#start_botshare").html('<i class="fa fa-share-alt"></i> เริ่มปั้มแชร์');
                document.getElementById("start_botshare").disabled = false;

            });
	}
}

function addfollow(){
	var id = $("#uid").val();
	var amount = $("#amount").val();
	  if (!id) {
        $("#status").html("<script>ierror('Error', 'กรุณาอย่าเว้นช่องว่าง..');</script>");
    } else {
		 $("#start_follow").html("<script>iwarning('<i class=\"fa fa-spinner fa-spin fa-fw\"></i>\', 'กำลังทำรายการ รอซักครู่..');</script>");
		document.getElementById("start_follow").disabled = true;
		        $.get("api/addfollow.php?uid=" + id + "&amount=" + amount, function(data, status) {
                var obj = jQuery.parseJSON(data);
                if (!obj.status) {
                    $("#status").html("<script>ierror('Follow Error', 'ไม่สามารถเชื่อมต่อ API ได้');</script>");
                } else if (obj.status == 'success') {
                    $("#status").html("<script>isuccess('Follow Success', '"+ obj.msg +"');</script>");
                } else if (obj.status == 'error') {
                    $("#status").html("<script>ierror('Follow Error', '"+ obj.msg +"');</script>");
                } else {
                    $("#status").html("<script>ierror('Follow Error', 'ไม่สามารถเชื่อมต่อ API ได้');</script>");
                }
				$("#start_follow").html('<i class="fa fa-heart"></i> เริ่มปั้มผู้ติดตาม');
                document.getElementById("start_follow").disabled = false;
            });
	}
}

function shield(){
		var type = $("#type").val();
		 $("#start_shield").html("<script>iwarning('<i class=\"fa fa-spinner fa-spin fa-fw\"></i>\', 'กำลังทำรายการ รอซักครู่..');</script>");
		document.getElementById("start_shield").disabled = true;
		        $.get("api/shield.php?type=" + type, function(data, status) {
                var obj = jQuery.parseJSON(data);
                if (!obj.status) {
                    $("#status").html("<script>ierror('Shield Error', 'ไม่สามารถเชื่อมต่อ API ได้');</script>");
                } else if (obj.status == 'success') {
                    $("#status").html("<script>isuccess('Shield Success', '"+ obj.msg +"');</script>");
                } else if (obj.status == 'error') {
                    $("#status").html("<script>ierror('Shield Error', '"+ obj.msg +"');</script>");
                } else {
                    $("#status").html("<script>ierror('Shield Error', 'ไม่สามารถเชื่อมต่อ API ได้');</script>");
                }
				$("#start_shield").html('<i class="fa fa-shield-alt"></i> เริ่มเปลี่ยนโล่');
                document.getElementById("start_shield").disabled = false;
            });
}

function profile(){
		var type = $("#type").val();
		 $("#start_profile").html("<script>iwarning('<i class=\"fa fa-spinner fa-spin fa-fw\"></i>\', 'กำลังทำรายการ รอซักครู่..');</script>");
		document.getElementById("start_profile").disabled = true;
		        $.get("api/profile.php?type=" + type, function(data, status) {
                var obj = jQuery.parseJSON(data);
                if (!obj.status) {
                    $("#status").html("<script>ierror('Profile Error', 'ไม่สามารถเชื่อมต่อ API ได้');</script>");
                } else if (obj.status == 'success') {
                    $("#status").html("<script>isuccess('Profile Success', '"+ obj.msg +"');</script>");
                } else if (obj.status == 'error') {
                    $("#status").html("<script>ierror('Profile Error', '"+ obj.msg +"');</script>");
                } else {
                    $("#status").html("<script>ierror('Profile Error', 'ไม่สามารถเชื่อมต่อ API ได้');</script>");
                }
				$("#start_profile").html('<i class="fa fa-user-edit"></i> เริ่มเปลี่ยนโปรไฟล์');
                document.getElementById("start_profile").disabled = false;
            });
}

function friendgroup() {
    var group = $("#group").val();
	if (!group) {
        $("#status").html("<script>ierror('Error', 'กรุณาอย่าเว้นช่องว่าง...');</script>");
    } else {
            $("#start_friendroup").html('<i class="fa fa-spinner fa-spin"></i> กำลังดึงคน กรุณารอสักครู่...');
            document.getElementById("start_joingroup").disabled = true;
            $.get("api/friend.php?group=" + group, function(data, status) {
                var obj = jQuery.parseJSON(data);
                if (!obj.status) {
                    $("#status").html("<script>ierror('Group Error', 'ไม่สามารถเชื่อมต่อ API ได้');</script>");
                } else if (obj.status == 'success') {
                    $("#status").html("<script>isuccess('Group Success', '"+ obj.msg +"');</script>");
                } else if (obj.status == 'error') {
                    $("#status").html("<script>ierror('Group Error', '"+ obj.msg +"');</script>");
                } else {
                    $("#status").html("<script>ierror('Group Error', 'ไม่สามารถเชื่อมต่อ API ได้');</script>");
                }
                $("#start_friendroup").html('<i class="fa fa-user-plus"></i>&nbsp;ดึงเพื่อนเข้ากลุ่ม');
                document.getElementById("start_friendroup").disabled = false;

            });
    }

}

function confirmDelete() {
	swal({
		type: 'warning',
		title: 'ยืนยันการลบ Token',
		showConfirmButton: false,
		html: '<br><br>' +
		'<a class="float-left btn btn-danger text-light" onclick="swal.close()"><i class="fa fa-times"></i> ยกเลิก</a>' +
		'<a class="float-right btn btn-success" href="?page=addtoken&Confirm"><i class="fa fa-check"></i> ยืนยัน</a>',
	});
}