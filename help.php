<?php
echo <<<HELP
$nick Help
==============================
This bot is pretty cool. Here are its commands: 
HELP: Responds to PRIVMSG $nick HELP. Causes the bot to send the user this message.
-HELP: Responds to PRIVMSG [room] $nick HELP. Causes the bot to output this message to the room.
DIE: Responds to PRIVMSG $nick DIE. Causes the bot to log out and disconnect.
-DIE: Responds to PRIVMSG [room] $nick DIE. Causes the bot to log out and disconnect.
LEAVE: Responds to PRIVMSG $nick LEAVE. Causes the bot to log out and stay connected.
-LEAVE: Responds to PRIVMSG [room] $nick LEAVE. Causes the bot to log out and stay connected.
RECONNECT: Responds to PRIVMSG $nick RECONNECT. Causes the bot to log out and log back in.
-RECONNECT: Responds to PRIVMSG [room] $nick RECONNECT. Causes the bot to log out and log back in.
SAY [room] [message]: Responds to PRIVMSG $nick SAY. Causes the bot to repeat user input, with a destination specified.
-SAY [message]: Responds to PRIVMSG [room] $nick SAY. Causes the bot to repeat user input to the room.
WISDOM [room]: Responds to PRIVMSG $nick WISDOM. Causes the bot to output a daily quote to the specified room.
-WISDOM: Responds to PRIVMSG [room] $nick WISDOM. Causes the bot to output a daily quote to the room.
NICK [nick]: Responds to PRIVMSG $nick NICK. Causes the bot to change to the nick specified.
-NICK [nick]: Responds to PRIVMSG [room] $nick NICK. Causes the bot to change to the nick specified.
PONG: Responds to PING $nick. Causes to bot to PONG the originator of the PING message.
==============================
If there's anything else you'd like to see this bot do, email me at kthprog@gmail.com.
GitHub repo is at https://github.com/KthProg/phpbot
HELP;
?>