<?php

require_once("irc_constants.php");

class PHPBot {
    public $is_connected = false;
    
    protected $connection_data; // assoc array ("host" => x, "port" => y)
    protected $my_data; //assoc array ("nick" => x, user, pass, realname, array channels etc)
    protected $connection;
    protected $errors;
    protected $response_methods; // array("name" => "", "command" => "", premissions" => float)
    
    /*
     * 
     */
    public function __construct(array $con_data, array $bot_data){
        libxml_use_internal_errors(true);
        $this->connection_data = $con_data;
        $this->my_data = $bot_data;
        $this->errors = new BotError();
    }
    
    public function __destruct(){
        $this->log_out();
        $this->disconnect();
    }
    
    public function disconnect(){
        if($this->is_connected = !fclose($this->connection)){
            $this->errors->set_errors("Could not close connection");
        }
    }
    
    public function get_errors(){
        return $this->errors;
    }
    
    public function connect(){
        $this->disconnect();
        $this->is_connected = $this->connection = 
                fsockopen(
                        $this->connection_data["host"], 
                        $this->connection_data["port"], 
                        $this->errors->fsock_error_num, //set error number var
                        $this->errors->fsock_error_str);//set error string var
        $this->errors->set_errors($this->is_connected ? "" : "Failed to connect");
        return $sent !== false;
    }
    
    public function log_in(){
        /*
           The RECOMMENDED order for a client to register is as follows:
           1. Pass message
           2. Nick message                 2. Service message
           3. User message
         */
        $sent = $this->send_command("PASS", array($this->my_data["pass"]));
        $sent = $sent and $this->send_command("NICK", array($this->my_data["nick"]));
        $sent = $sent and $this->send_command("USER", array($this->my_data["user"], "8", "*", $this->my_data["realname"]));
        // because 'and' is used and not '&&', it will still attempt
        // to send subsequent commands if the prior command fails
        // '&&' would short-circuit and not call the functions
        // one of the few times side-effects is a desired behavior
        $this->errors->set_errors($sent ? "" : "Failed to log in");
        return $sent;
    }
    
    public function log_out($msg = ""){
        $sent = $this->send_command("QUIT", array($msg));
        $this->errors->set_errors($sent ? "" : "Failed to log out [QUIT]");
    }
    
    public function leave_channels(){
        $this->send_command("JOIN", array("0"));
    }
    
    public function join_channels(){
        foreach($this->my_data["channels"] as $channel){
            $this->send_command("JOIN", array($channel));
        }
    }
    
    protected function get_user_permissions($user){
        if($xml = simplexml_load_file(dirname(__FILE__)."\\xml\\users.xml")){
            // get the role of the user with this nick
            if($role = $xml->xpath("/users/user[nick='".$user."']/permissions")){
                return (float)($role[0]->__toString());
            }else{
                $this->errors->set_errors("", libxml_get_errors());
                return 0.0;
            }
        }else{
            $this->errors->set_errors("", libxml_get_errors());
            return 0.0;
        }
        return 0.0;
    }
    
    public function send_command($command_name, array $args){
        if(!($xml = simplexml_load_file(dirname(__FILE__)."\\xml\\commands.xml"))){
            $this->errors->set_errors("", libxml_get_errors());
            return false;
        }
        if(!($xml_args = $xml->xpath("/commands/command[@name='".$command_name."']/args/arg"))){
            $this->errors->set_errors("Could not retrieve regex for command ".$command_name);
            return false;
        }
        
        $arg_text = implode(" ",$args);
        $regex = "";
        foreach($xml_args as $xml_arg){
            $regex .= str_replace("`", "", $xml_arg->__toString());
        }
        $regex = "`".$regex."`";
        
        if(!preg_match($regex, $arg_text)){
            $this->errors->set_errors("Args '".$arg_text."' do not match regex ".$regex." for command ".$command_name);
            return false;
        }
        
        $full_cmd = $command_name." ".$arg_text."\r\n";
        
        if(strlen($full_cmd) > MAX_MSG_LENGTH){
            $this->errors->set_errors("Command exceeds max length (".(string)MAX_MSG_LENGTH.")");
            return false;
        }
        
        print("Sending Command: ".$full_cmd);
        
        if(!fwrite($this->connection, $full_cmd)){
            $this->errors->set_errors("Failed to send command ".$command_name);
            return false;
        }
        
        $this->errors->set_errors();
        return true;
    }
    
    public function register_response_method($name, $cmd_trigger, $permissions){
        $this->response_methods[] = array(
            "name" => $name, 
            "command" => $cmd_trigger,
            "permissions" => $permissions);
    }
    
    public function deregister_response_method($name){
        foreach($this->response_methods as $response_method){
            if($response_method["name"] == $name){
                unset($response_method);
                break;
            }
        }
        //untested
    }
    
    public function get_server_data(){
        $responses = array();
        if($line = fgets($this->connection)){
            print("Server Said: ".$line);
            $responses[] = $line;
        }
        return $responses;
    }
    
    private static function get_prefix_data($prefix){
        // if prefix is a user
        if(strpos($prefix, "!") !== false){
            $user_data = PHPBot::split_user_prefix($prefix);
        }else{ // if prefix is a server / service
            $user_data = array("server" => $prefix);
        }
        return $user_data;
    }
    
    private static function split_user_prefix($user_prefix){
        // split prefix around ! and @
        $user_temp = preg_split("`[!@]`", $user_prefix, 3, PREG_SPLIT_NO_EMPTY);
        $nick = trim($user_temp[0]);
        $user = trim($user_temp[1]);
        $host = trim($user_temp[2]);
        $user_data = array("user" => $user, "nick" => $nick, "host" => $host);
        return $user_data;
    }
    
    public function parse_server_data(array $responses){
        $parsed_responses = array();
        foreach($responses as $response){
            if($response[0] == ":"){
                $response[0] = ""; //remove ':' character (prefix) fastest (not best) way to do this
                $parsed_response = explode(" ", $response, 3);
                $prefix = $parsed_response[0];
                $cmd = $parsed_response[1];
                if($xml = simplexml_load_file(dirname(__FILE__)."\\xml\\commands.xml")){
                    if($xml_args = $xml->xpath("/commands/command[@name='".$cmd."']/args/arg")){
                        $args = array();
                        foreach($xml_args as $xml_arg){
                            $arg_regex = $xml_arg->__toString();
                            $matches = array();
                            if(preg_match($arg_regex, $parsed_response[2], $matches)){
                                $args[] = $matches[0];
                                $parsed_response[2] = preg_replace($arg_regex, "", $parsed_response[2], 1);
                            }else{
                                if($matched === false){
                                    $this->errors->set_errors("Matching failed for command ".$cmd." regex ".$arg_regex);
                                }else
                                if($matched === 0){
                                    $this->errors->set_errors("No matches found for command ".$cmd." regex ".$arg_regex);
                                }
                            }
                        }
                    }else{
                        $this->errors->set_errors("Could not find args for command ".$cmd, libxml_get_errors());
                        $args = explode(" ", $parsed_response[2], MAX_PARAMETERS);
                    }
                }else{
                    $this->errors->set_errors("", libxml_get_errors());
                }
                $user_data = PHPBot::get_prefix_data($prefix);
                $parsed_responses[] = array("from" => $user_data, "command" => $cmd, "args" => $args);
            }else{ // no prefix, just command and args (happens with PING)
                $parsed_response = explode(" ", $response, 2);
                $cmd = $parsed_response[0];
                $args = explode(" ", $parsed_response[1], MAX_PARAMETERS);
                $parsed_responses[] = array("from" => array("server" => "?"), "command" => $cmd, "args" => $args);
            }
        }
        return $parsed_responses;
    }
    
    public function respond(array $parsed_responses){
        foreach($parsed_responses as $parsed_response){
            foreach($this->response_methods as $response_method){
                if($response_method["command"] == $parsed_response["command"]){
                    if($this->get_user_permissions($parsed_response["from"]["nick"]) < $response_method["permissions"]){
                        $this->send_command("PRIVMSG", array($parsed_response["from"]["nick"], "You have insufficient priviledges to run this command"));
                        continue;
                    }
                    if(!$this->$response_method["name"]($parsed_response)){
                        $this->errors->set_errors("Problem calling response function");
                        continue;
                    }
                    print("Ran response method ".$response_method["name"].PHP_EOL);
                    print("Args: ".print_r($parsed_response["args"], true));
                }
            }
        }
    }
}

class BotError{
    public $fsock_error_num;
    public $fsock_error_str;
    
    public $internal;
    public $xml;
    public $fsock;
    
    public function set_errors($internal = "", $xml = ""){
        $this->internal = $internal;
        $this->xml = $xml;
        $this->fsock = array(
            $this->fsock_error_num,
            $this->fsock_error_str);
        print_r($this);
    }
}