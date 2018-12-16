var socket1 = require( 'socket.io' );
var express = require( 'express' );
var http = require( 'http' );

var app = express();
var server = http.createServer( app );
var io = socket1.listen( server );
users = [];
userSocket = [];
io.sockets.on( 'connection', function( client ) {
    client.on('setUsername', function(data){
        var from = parseInt(data.from);

        userSocket[from]=client.id;
        if(users.indexOf(from) > -1) {

        } else {
            users.push(from);
        }
    });
    client.on('msg', function(data){
        //Send message to everyone
        var so=parseInt(data.user);
        var soc=userSocket[so];
        if(typeof userSocket[so]!='undefined'&& io.sockets.sockets[soc]) {
            io.sockets.socket(soc).emit('newmsg', data);
        }
    });
    client.on('disconnect', function() {
        console.log('disconnect'+ client.id);

    });
});

server.listen( 2002, function() {
    console.log('lisning to port 2002');
});