<?php

require_once("Bot.php");
require_once("MyBot.php");

$con_data = new ConnectionData("irc.hackthissite.org"); // port is 6667 by default
$bot_data = new BotData("PHPBot", "No Pass", "PHPBot", "PHP Bot", array("#bots"));

$bot = new MyBot($con_data, $bot_data);

$bot->register_response_method(new ResponseMethod("pong", "PING", 0));
$bot->register_response_method(new ResponseMethod("random_nick", (string)ERR_NICKNAMEINUSE, 0));
$bot->register_response_method(new ResponseMethod("join_channels", (string)RPL_ENDOFMOTD, 0));
$bot->register_response_method(new ResponseMethod("ask_for_unban", (string)ERR_BANNEDFROMCHAN, 0));
// PRIVMSG BotNick CMD args
$bot->register_response_method(new ResponseMethod("help", "PRIVMSG", 0));
$bot->register_response_method(new ResponseMethod("irc_die", "PRIVMSG", 100));
$bot->register_response_method(new ResponseMethod("leave", "PRIVMSG", 100));
$bot->register_response_method(new ResponseMethod("recon", "PRIVMSG", 100));
$bot->register_response_method(new ResponseMethod("say", "PRIVMSG", 0));
$bot->register_response_method(new ResponseMethod("wisdom", "PRIVMSG", 0));
$bot->register_response_method(new ResponseMethod("change_nick", "PRIVMSG", 100));
// PRIVMSG #room BotNick CMD args
$bot->register_response_method(new ResponseMethod("help_2", "PRIVMSG", 0));
$bot->register_response_method(new ResponseMethod("irc_die_2", "PRIVMSG", 100));
$bot->register_response_method(new ResponseMethod("leave_2", "PRIVMSG", 100));
$bot->register_response_method(new ResponseMethod("recon_2", "PRIVMSG", 100));
$bot->register_response_method(new ResponseMethod("say_2", "PRIVMSG", 0));
$bot->register_response_method(new ResponseMethod("wisdom_2", "PRIVMSG", 0));
$bot->register_response_method(new ResponseMethod("change_nick_2", "PRIVMSG", 100));

if($bot->connect()){
    if($bot->log_in()){
        while($bot->is_connected){
            $raw_data = $bot->get_server_data();
            $parsed_data = $bot->parse_server_data($raw_data);
            $bot->respond($parsed_data);
        }
    }else{
        print_r($bot->get_errors());
    }
}else{
    print_r($bot->get_errors());
}
