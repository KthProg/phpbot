<?php

class MyBot extends PHPBot{
    /**
     *
     * @var string Help message for users on IRC 
     */
    private function get_help_doc(){
        $nick = $this->my_data->nick;
        return <<<HELP
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
WISDOM [room]: Responds to PRIVMSG $nick WISDOM. Causes the bot to output a daily quote.
-WISDOM [room]: Responds to PRIVMSG [room] $nick WISDOM. Causes the bot to output a daily quote to the room.
PONG: Responds to PING $nick. Causes to bot to PONG the originator of the PING message.
==============================
If there's anything else you'd like to see this bot do, email me at kthprog@gmail.com.
GitHub repo is at https://github.com/KthProg/phpbot
HELP;
    }
    
    private function get_priv_msg_parts($parsed_response){
        return explode(" ", trim($parsed_response->args[1]), 3);
    }
    private function check_is_cmd($parsed_response, $cmd){
        if($parsed_response->args[0] != $this->my_data->nick){
            return false;
        }
        $msg_parts = $this->get_priv_msg_parts($parsed_response);
        if($msg_parts[0] != ":".$cmd){
            return false;
        }
        return true;
    }
    private function check_is_cmd_2($msg_parts, $cmd){
        if($msg_parts[0] != ":".$this->my_data->nick){
            return false;
        }
        if($msg_parts[1] != $cmd){
            return false;
        }
        return true;
    }
    
    private function _help($room_or_user){
        $help_lines = explode(PHP_EOL, $this->get_help_doc());
        foreach($help_lines as $help_line){
            $this->_say($room_or_user, $help_line);
        }
    }
    protected function help($parsed_response){
        if(!$this->check_is_cmd($parsed_response, "HELP")){
            return false;
        }
        $this->_help($parsed_response->from->nick);
        return true;
    }
    
    protected function help_2($parsed_response){
        $msg_parts = $this->get_priv_msg_parts($parsed_response);
        if(!$this->check_is_cmd_2($msg_parts, "HELP")){
            return false;
        }
        //TODO: ensure that this is a room
        $room_or_user = $parsed_response->args[0];
        $this->_help($room_or_user);
        return true;
    }
    
    private function _irc_die($msg = ""){
        $this->log_out($msg);
        $this->disconnect();
    }
    /**
     * 
     * @param ParsedResponse $parsed_response
     * @return boolean
     */
    protected function irc_die($parsed_response){
        if(!$this->check_is_cmd($parsed_response, "DIE")){
            return false;
        }
        $msg_parts = $this->get_priv_msg_parts($parsed_response);
        $this->_irc_die($msg_parts[1]);
        return true;
    }
    /**
     * 
     * @param ParsedResponse $parsed_response
     * @return boolean
     */
    protected function irc_die_2($parsed_response){
        $msg_parts = $this->get_priv_msg_parts($parsed_response);
        if(!$this->check_is_cmd_2($msg_parts, "DIE")){
            return false;
        }
        $this->_irc_die($msg_parts[2]);
        return true;
    }
    /**
     * 
     * @param ParsedResponse $parsed_response
     * @return boolean
     */
    protected function leave($parsed_response){
        if(!$this->check_is_cmd($parsed_response, "LEAVE")){
            return false;
        }
        $msg_parts = $this->get_priv_msg_parts($parsed_response);
        $this->log_out($msg_parts[1]);
        return true;
    }
    /**
     * 
     * @param ParsedResponse $parsed_response
     * @return boolean
     */
    protected function leave_2($parsed_response){
        $msg_parts = $this->get_priv_msg_parts($parsed_response);
        if(!$this->check_is_cmd_2($msg_parts, "LEAVE")){
            return false;
        }
        $this->log_out($msg_parts[2]);
        return true;
    }
    private function _recon($msg){
        $this->log_out($msg);
        $this->connect();
        $this->log_in();
    }
    /**
     * 
     * @param ParsedResponse $parsed_response
     * @return boolean
     */
    protected function recon($parsed_response){
        if(!$this->check_is_cmd($parsed_response, "RECONNECT")){
            return false;
        }
        $msg_parts = $this->get_priv_msg_parts($parsed_response);
        $this->_recon($msg_parts[1]);
        return true;
    }
    /**
     * 
     * @param ParsedResponse $parsed_response
     * @return boolean
     */
    protected function recon_2($parsed_response){
        $msg_parts = $this->get_priv_msg_parts($parsed_response);
        if(!$this->check_is_cmd_2($msg_parts, "RECONNECT")){
            return false;
        }
        $this->_recon($msg_parts[2]);
        return true;
    }
    private function _say($destination, $message){
        $this->send_command("PRIVMSG", array($destination, $message));
    }
    /**
     * 
     * @param ParsedResponse $parsed_response
     * @return boolean
     */
    protected function say($parsed_response){
        if(!$this->check_is_cmd($parsed_response, "SAY")){
            return false;
        }
        $msg_parts = $this->get_priv_msg_parts($parsed_response);
        $this->_say($msg_parts[1], $msg_parts[2]);
        return true;
    }
    /**
     * 
     * @param ParsedResponse $parsed_response
     * @return boolean
     */
    protected function say_2($parsed_response){
        $msg_parts = $this->get_priv_msg_parts($parsed_response);
        print_r($msg_parts);
        if(!$this->check_is_cmd_2($msg_parts, "SAY")){
            return false;
        }
        //TODO: ensure that this is a room
        $room_or_user = $parsed_response->args[0];
        print($room_or_user);
        $this->_say($room_or_user, $msg_parts[2]);
        return true;
    }
    private function _wisdom(){
        $quote_data = file_get_contents("http://www.swanandmokashi.com/Homepage/Webservices/QuoteOfTheDay.asmx/GetQuote");
        if(!$quote_data){
            $this->errors->set_errors("No quote data received.");
            return "Invalid response recieved for quote request.";
        }

        $xml = simplexml_load_string($quote_data);
        if(!$xml){
            $this->errors->set_errors("", libxml_get_errors());
            return "Could not load xml.";
        }

        try{
            $quote = $xml->QuoteOfTheDay->__toString();
            $author = $xml->Author->__toString();
        }catch (Exception $e){
            $this->errors->set_errors($e->getMessage(), libxml_get_errors());
            return $e->getMessage();
        }
        
        return "Quote of the day: ".$author." - ".$quote;
    }
    /**
     * 
     * @param ParsedResponse $parsed_response
     * @return boolean
     */
    protected function wisdom($parsed_response){
        if(!$this->check_is_cmd($parsed_response, "WISDOM")){
            return false;
        }
        $msg_parts = $this->get_priv_msg_parts($parsed_response);
        $this->_say($msg_parts[1], $this->_wisdom());
        return true;
    }
    /**
     * 
     * @param ParsedResponse $parsed_response
     * @return boolean
     */
    protected function wisdom_2($parsed_response){
        $msg_parts = $this->get_priv_msg_parts($parsed_response);
        if(!$this->check_is_cmd_2($msg_parts, "WISDOM")){
            return false;
        }
        //TODO: ensure that this is a room
        $room_or_user = $parsed_response->args[0];
        $this->_say($room_or_user, $this->_wisdom());
        return true;
    }
    /**
     * 
     * @param ParsedResponse $parsed_response
     * @return boolean
     */
    protected function ask_for_unban($parsed_response){
        foreach($this->my_data->channels as $channel){
            $this->_say($channel, "Please unban me from ".$parsed_response->args[1]."!");
        }
        return true;
    }

    /**
     * 
     * @param ParsedResponse $parsed_response
     * @return boolean
     */
    protected function join_chans($parsed_response){
        $this->join_channels();
        return true;
    }

    /**
     * 
     * @param ParsedResponse $parsed_response
     * @return boolean
     */
    protected function random_nick($parsed_response){
        $rnd_nick = $this->my_data->nick.(string)rand();
        $this->my_data->nick = $rnd_nick;
        $this->send_command("NICK", array($rnd_nick));
        return true;
    }

    /**
     * 
     * @param ParsedResponse $parsed_response
     * @return boolean Success of PONG response
     */
    protected function pong($parsed_response){
        $this->send_command("PONG", $parsed_response->args);
        return true;
    }
}