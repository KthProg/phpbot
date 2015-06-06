<?php

class MyBot extends PHPBot{
    //command PRIVMSG
    protected function priv_msg_die($parsed_response){
        if($parsed_response["args"][0] == $this->my_data["nick"]){
            $msg_parts = explode(" ", trim($parsed_response["args"][1]), 2);
            if($msg_parts[0] == ":DIE"){
                $this->log_out($msg_parts[1]);
                $this->disconnect();
            }
        }
        return true;
    }
    
    //command PRIVMSG
    protected function priv_msg_leave($parsed_response){
        if($parsed_response["args"][0] == $this->my_data["nick"]){
            $msg_parts = explode(" ", trim($parsed_response["args"][1]), 2);
            if($msg_parts[0] == ":LEAVE"){
                $this->log_out($msg_parts[1]);
            }
        }
        return true;
    }

    protected function priv_msg_recon($parsed_response){
        if($parsed_response["args"][0] == $this->my_data["nick"]){
            $msg_parts = explode(" ", trim($parsed_response["args"][1]), 2);
            if($msg_parts[0] == ":RECONNECT"){
                        $this->log_out($msg_parts[1]);
                        $this->connect();
                        $this->log_in();
            }
        }
    }

    protected function priv_msg_say($parsed_response){
        if($parsed_response["args"][0] == $this->my_data["nick"]){
            $msg_parts = explode(" ", trim($parsed_response["args"][1]), 3);
            print_r($msg_parts);
            if($msg_parts[0] == ":SAY"){
                $this->send_command("PRIVMSG", array($msg_parts[1], $msg_parts[2]));
            }
        }
        return true;
    }
    
    protected function priv_msg_wisdom($parsed_response){
        if($parsed_response["args"][0] == $this->my_data["nick"]){
            $msg_parts = explode(" ", trim($parsed_response["args"][1]), 3);
            print_r($msg_parts);
            if($msg_parts[0] == ":WISDOM"){
                $quote_data = file_get_contents("http://www.swanandmokashi.com/Homepage/Webservices/QuoteOfTheDay.asmx/GetQuote");
                if(!$quote_data){
                    $this->send_command("PRIVMSG", array($msg_parts[1], "Invalid response recieved for quote request."));
                    return false;
                }
                $xml = simplexml_load_string($quote_data);
                if(!$xml){
                    $this->send_command("PRIVMSG", array($msg_parts[1], "Could not load xml."));
                    return false;
                }
                
                print_r($xml);
                
                $quote = $xml->QuoteOfTheDay->__toString();
                $author = $xml->Author->__toString();
                
                $this->send_command("PRIVMSG", array($msg_parts[1], "Quote of the day: ".$author." - ".$quote));
            }
        }
        return true;
    }

    //command (string)ERR_BANNEDFROMCHAN
    protected function ask_for_unban($parsed_response){
        foreach($this->my_data["channels"] as $channel){
            $this->send_command("PRIVMSG", array($channel, "Please unban me from ".$parsed_response["args"][1]."!"));
        }
        return true;
    }

    //command (string)RPL_ENDOFMOTD
    protected function join_chans($parsed_response){
        $this->join_channels();
        return true;
    }

//command (string)ERR_NICKNAMEINUSE
    protected function random_nick($parsed_response){
        $rnd_nick = $this->my_data["nick"].(string)rand();
        $this->my_data["nick"] = $rnd_nick;
        $this->send_command("NICK", array($rnd_nick));
        return true;
    }

    //command PING
    protected function pong($parsed_response){
        $this->send_command("PONG", $parsed_response["args"]);
        return true;
    }

}