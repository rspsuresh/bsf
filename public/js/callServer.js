var socket = require( 'socket.io' );
var express = require( 'express' );
var http = require( 'http' );

var app = express();
var server = http.createServer( app );
var io = socket.listen( server );
users = [];
userSocket = [];
io.sockets.on( 'connection', function( client ) {
    client.on('setUsername', function(data){
        var from =  data.from;

        userSocket[from]=client.id;
        if(users.indexOf(from) > -1){

        }
        else{
            users.push(from);
        }
    });
    client.on('msg', function(data){
        //Send message to everyone
        if(data.mode=="single") {
            var so=parseInt(data.user);
            var soc=userSocket[so];
            if(typeof userSocket[so]!='undefined'&& io.sockets.sockets[soc]) {
                io.sockets.socket(soc).emit('newmsg', data);
            }
        } else {
            io.sockets.emit('newmsg', data);
        }

    });

    client.on('end', function(data) {
        var from =  data.from;
        var sockId=userSocket[from];

        if (typeof sockId!='undefined' && io.sockets.sockets[sockId]) {
            io.sockets.sockets[sockId].disconnect();
            delete userSocket[from];

            var i = users.indexOf(from);
            if(i != -1) {
                users.splice(i, 1);
            }
        }

    });
    client.on('disconnect', function(data) {

    });
});

server.listen( 2001, function() {
    console.log('lisning to port 2001');
});