<?php

require_once("Bot.php");

$con_data = array("host" => "irc.hackthissite.org", "port" => "6667");
$bot_data = array("user" => "KthProg", "pass" => "Kth@#5711131719232931", "nick" => "ProgBot", "realname" => "KTH", "channels" => array("#bots"));

$bot = new PHPBot($con_data, $bot_data);

require_once("MyFunctions.php");
$bot->register_response_method("pong", "PING", $pong, 0);
$bot->register_response_method("random_nick", (string)ERR_NICKNAMEINUSE, $random_nick, 0);
$bot->register_response_method("join_channels", (string)RPL_ENDOFMOTD, $join_chans, 0);
$bot->register_response_method("ask_for_unban", (string)ERR_BANNEDFROMCHAN, $ask_for_unban, 0);
$bot->register_response_method("priv_msg_leave", "PRIVMSG", $priv_msg_leave, 100);
$bot->register_response_method("priv_msg_recon", "PRIVMSG", $priv_msg_recon, 100);
$bot->register_response_method("priv_msg_say", "PRIVMSG", $priv_msg_say, 0);
// you can register a response method by specifying a method name, the IRC command
// it should reply to, a code string to be eval'd, and the permissions required to
// run the command (stored as a float) these permissions are set by nick in users.xml

if($bot->connect()){
    if($bot->log_in()){
        while(true){
            $raw_data = $bot->get_server_data();
            $parsed_data = PHPBot::parse_server_data($raw_data);
            $bot->respond($parsed_data);
        }
    }else{
        print_r($bot->get_errors());
    }
}else{
    print_r($bot->get_errors());
}