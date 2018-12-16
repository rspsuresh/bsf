var socket = io.connect( 'http://192.168.1.8:2000' );

function sendMsg() {
	var nameVal = $( "#nameInput" ).val();
	var username = $( "#uname" ).val();
	var sName = $( "#sName" ).val();
	var msg = $( "#messageInput" ).val();
    if(msg){
        socket.emit('msg', {message: msg, user: nameVal,sender:username,sName:sName});
    }

	// Ajax call for saving datas
	$.ajax({
		url: getBaseURL()+"crm/telecaller/index",
		type: "POST",
		data: { msg: msg,id:nameVal ,mode:'insert'},
		success: function(data) {
            var actualContent = $( "#messages" ).html();
            var newMsgContent = '<li> <strong>Me</strong> : ' + msg + '</li>';
            var content = newMsgContent + actualContent;

            $( "#messages" ).html( content );
		}
	});

	return false;
}
socket.on('newmsg', function(data){
    //if(user){
    //    document.getElementById('message-container').innerHTML += '<div><b>' + data.user + '</b>: ' + data.message + '</div>'
    //}
    var actualContent = $( "#messages" ).html();
    var newMsgContent = '<li> <strong>' + data.sName + '</strong> : ' + data.message + '</li>';
    var content = newMsgContent + actualContent;

    $( "#messages" ).html( content );
});

//index

function setUsername(){
    socket.emit('setUsername', {from:document.getElementById('curUser').value,to:document.getElementById('name').value,fName:document.getElementById('curUserName').value});
}
var user;
socket.on('userExists', function(data){
    document.getElementById('error-container').innerHTML = data;
});
socket.on('userSet', function(data){
    console.log(data);
    user = data.username;
    sender = data.sender;
    sName = data.sName;
    document.body.innerHTML = '<form class="form-inline" onsubmit="return sendMsg();" id="messageForm">  <input type="hidden" id="nameInput" value="'+user+'"/> <input type="hidden" id="sName" value="'+sName+'"/> <input type="hidden" id="uname" value="'+sender+'"/> <input id="messageInput" type="text" class="input-xxlarge" placeHolder="Message" /> <input type="submit" value="Send" /><div><ul id="messages"></ul></div></form>';
    $.ajax({
        url: getBaseURL()+"crm/telecaller/index",
        type: "POST",
        data: {id:user, mode:'setUser' },
        success: function(data) {
            var mes = JSON.parse(data);
            if(mes.length>0) {

                var el ="";
                $.each(mes,function(i,o) {
                    el +='<li> <strong>'+o.EmployeeName+'</strong> : '+ o.Message+'</li>';
                });
            }
            $("#messages").html(el);
        }
    });

});