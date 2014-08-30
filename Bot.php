<?php

require_once("irc_constants.php");

class PHPBot {
    protected $connection_data; // assoc array ("host" => x, "port" => y)
    protected $my_data; //assoc array ("nick" => x, user, pass, realname, array channels etc)
    protected $connection;
    
    protected $response_methods; // array("name" => "", "command" => "", "code" => "", "premissions" => float)
    protected $error_str;
    protected $error_num;
    protected $errors = array("connection" => "", "bot" => "");
    /*
     * 
     */
    public function __construct(array $con_data, array $bot_data){
        libxml_use_internal_errors(true);
        $this->connection_data = $con_data;
        $this->my_data = $bot_data;
    }
    
    public function __destruct(){
        $this->log_out();
        $this->disconnect();
    }
    
    public function disconnect(){
        fclose($this->connection);
    }
    
    public function get_errors(){
        return $this->errors;
    }
    
    public function connect(){
        $this->disconnect();
        $sent = $this->connection = fsockopen($this->connection_data["host"], $this->connection_data["port"], $this->error_num, $this->error_str);
        $this->errors["connection"] = (string)$this->error_num." : ".$this->error_str;
        $this->errors["bot"] = $sent ? "" : "Failed to connect";
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
        $this->errors["bot"] = $sent ? "" : "Failed to log in";
        return $sent;
    }
    
    public function log_out($msg = ""){
        $sent = $this->send_command("QUIT", array($msg));
        $this->errors["bot"] = $sent ? "" : "Failed to log out [QUIT]";
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
                return (float)$role[0]->__toString();
            }else{
                $this->errors["bot"] = libxml_get_errors();
                print_r($this->get_errors());
                return 0.0;
            }
        }else{
            $this->errors["bot"] = libxml_get_errors();
            print_r($this->get_errors());
            return 0.0;
        }
    }
    
    public function send_command($command_name, array $args){
        if($xml = simplexml_load_file(dirname(__FILE__)."\\xml\\commands.xml")){
            if($cmd_el = $xml->xpath("/commands/command[@name='".$command_name."']")){
                $arg_text = implode(" ",$args);
                $regex = $cmd_el[0]->__toString();
                if(preg_match($regex, $arg_text)){
                    $full_cmd = $command_name." ".$arg_text."\r\n";
                    if(strlen($full_cmd) <= MAX_MSG_LENGTH){
                        print("Sending Command: ".$full_cmd);
                        if(fwrite($this->connection, $full_cmd)){
                            return true;
                        }else{
                            $this->errors["bot"] = "Failed to send command ".$command_name;
                        }
                    }else{
                        $this->errors["bot"] = "Command exceeds max length (".(string)MAX_MSG_LENGTH.")";
                    }
                }else{
                    $this->errors["bot"] = "Args '".$arg_text."' do not match regex ".$regex." for command ".$command_name;
                }
            }else{
                $this->errors["bot"] = "Could not retrieve regex for command ".$command_name;
            }
        }else{
            $this->errors["bot"] = libxml_get_errors();
        }
        $this->errors["connection"] = (string)$this->error_num." : ".$this->error_str;
        print_r($this->get_errors());
        return false;
    }
    
    public function register_response_method($name, $cmd_trigger, $code, $permissions){
        $this->response_methods[] = array("name" => $name, "command" => $cmd_trigger, "code" => $code, "permissions" => $permissions);
        //untested
    }
    
    public function deregister_response_method($name){
        foreach($this->response_methods as $response_method){
            if($response_method["name"] == $name){
                unset($response_method);
                break;
            }
        }
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
    
    public static function parse_server_data(array $responses){
        $parsed_responses = array();
        foreach($responses as $response){
            if($response[0] == ":"){
                $response[0] = ""; //remove ':' character (prefix) fastest (not best) way to do this
                $parsed_response = explode(" ", $response, 3);
                $prefix = $parsed_response[0];
                $cmd = $parsed_response[1];
                // rewrite to look up command and split by command regex
                $args = explode(" ", $parsed_response[2], MAX_PARAMETERS);
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
                    if($this->get_user_permissions($parsed_response["from"]["nick"]) >= $response_method["permissions"]){
                        if(eval($response_method["code"])){
                            print("Ran response method ".$response_method["name"]);
                        }else{
                            $this->errors["bot"] = "Parse error in eval'd code";
                        }
                    }else{
                        $this->send_command("PRIVMSG", array($parsed_response["from"]["nick"], "You have insufficient priviledges to run this command"));
                    }
                }
            }
        }
    }
}