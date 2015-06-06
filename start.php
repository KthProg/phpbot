<?php

require_once("Bot.php");
require_once("MyBot.php");

$con_data = array("host" => "irc.hackthissite.org", "port" => "6667");
$bot_data = array("user" => "KthProg", "pass" => "Kth@#5711131719232931", "nick" => "ProgBot", "realname" => "A Guy", "channels" => array("#bots"));

$bot = new MyBot($con_data, $bot_data);

$bot->register_response_method("pong", "PING", 0);
$bot->register_response_method("random_nick", (string)ERR_NICKNAMEINUSE, 0);
$bot->register_response_method("join_channels", (string)RPL_ENDOFMOTD, 0);
$bot->register_response_method("ask_for_unban", (string)ERR_BANNEDFROMCHAN, 0);
$bot->register_response_method("priv_msg_die", "PRIVMSG", 100);
$bot->register_response_method("priv_msg_leave", "PRIVMSG", 100);
$bot->register_response_method("priv_msg_recon", "PRIVMSG", 100);
$bot->register_response_method("priv_msg_say", "PRIVMSG", 0);
$bot->register_response_method("priv_msg_wisdom", "PRIVMSG", 0);

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
