<?php

/*
 * @author Kyle Hooks
 */

require_once("irc_constants.php");

class PHPBot {
    /**
     *
     * @var bool Indicates if bot has established a connection with a server yet 
     */
    public $is_connected = false;
    /**
     *
     * @var ConnectionData information used to conect to IRC server
     */
    protected $connection_data;
    /**
     *
     * @var BotData Information about the bot
     */
    protected $my_data;
    /**
     *
     * @var resource Resource holding connection to IRC server 
     */
    protected $connection;
    /**
     *
     * @var BotError Set during most methods. Used to track errors without triggering failure.
     */
    protected $errors;
    /**
     *
     * @var array Array of ResponseMethod objects. These are all of the registered response methods
     */
    protected $response_methods;
    
    /**
     * Constructor for PHPBot
     * @param array $con_data Connection data for the bot. {@link $connection_data}
     * @param array $bot_data Login and other info for the bot. {@link $my_data}
     */
    public function __construct(ConnectionData $con_data, BotData $bot_data){
        libxml_use_internal_errors(true);
        $this->connection_data = $con_data;
        $this->my_data = $bot_data;
        $this->errors = new BotError();
    }
    
    /**
     * On destruction, logs out and disconnects
     */
    public function __destruct(){
        $this->log_out();
        $this->disconnect();
    }
    
    /**
     * Close connection if possible
     */
    public function disconnect(){
        if($this->is_connected = !fclose($this->connection)){
            $this->errors->set_errors("Could not close connection");
        }
    }
    
    /**
     * Get errors object
     */
    public function get_errors(){
        return $this->errors;
    }
    
    /**
     * Connect to server. Requires that connection_data has been set during initialization
     * @return bool Indicates if the connection was create successfully or not
     */
    public function connect(){
        $this->disconnect();
        $this->is_connected = $this->connection = 
                fsockopen(
                        $this->connection_data->host, 
                        $this->connection_data->port, 
                        $this->errors->fsock_error_num, //set error number var
                        $this->errors->fsock_error_str);//set error string var
        $this->errors->set_errors($this->is_connected ? "" : "Failed to connect");
        return $this->is_connected;
    }
    
    /**
     * Logs in to the IRC server specified in 
     * @return type
     */
    public function log_in(){
        /*
           The RECOMMENDED order for a client to register is as follows:
           1. Pass message
           2. Nick message                 2. Service message
           3. User message
         */
        $sent = $this->send_command("PASS", array($this->my_data->pass));
        $sent = $sent and $this->send_command("NICK", array($this->my_data->nick));
        $sent = $sent and $this->send_command("USER", array($this->my_data->user, "8", "*", $this->my_data->realname));
        // because 'and' is used and not '&&', it will still attempt
        // to send subsequent commands if the prior command fails
        // '&&' would short-circuit and not call the functions
        // one of the few times side-effects is a desired behavior
        $this->errors->set_errors($sent ? "" : "Failed to log in");
        return $sent;
    }
    
    /**
     * Logs out and displays an optional exit message.
     * @param string $msg Message output to IRC server on quit. Defaults to empty string.
     */
    public function log_out($msg = ""){
        $sent = $this->send_command("QUIT", array($msg));
        $this->errors->set_errors($sent ? "" : "Failed to log out [QUIT]");
    }
    /**
     * Effectively leaves all channels by sending JOIN 0 command. May not work on all servers.
     */
    public function leave_channels(){
        $this->send_command("JOIN", array("0"));
    }
    /**
     * Joins all channels in my_data->channels. {@link $my_data}
     */
    public function join_channels(){
        foreach($this->my_data->channels as $channel){
            $this->send_command("JOIN", array($channel));
        }
    }
    
    /**
     * Returns user permissions level from users.xml
     * @param string $user Username to check permissions for (IRC Nick).
     * @return real User permissions level
     */
    protected function get_user_permissions($user){
        if(!($xml = simplexml_load_file(dirname(__FILE__)."\\xml\\users.xml"))){
            $this->errors->set_errors("", libxml_get_errors());
            return 0.0;
        }
        // get the role of the user with this nick
        if(!($role_nodes = $xml->xpath("/users/user[nick='".$user."']/permissions"))){
            $this->errors->set_errors("", libxml_get_errors());
            return 0.0;
        }
        
        return (float)($role_nodes[0]->__toString());
    }
    /**
     * 
     * @param type $command_name
     * @return boolean
     */
    private function get_regex_for_command($command_name){
        if(!($xml = simplexml_load_file(dirname(__FILE__)."\\xml\\commands.xml"))){
            $this->errors->set_errors("", libxml_get_errors());
            return false;
        }
        
        if(!($xml_args = $xml->xpath("/commands/command[@name='".$command_name."']/args/arg"))){
            $this->errors->set_errors("Could not retrieve regex for command ".$command_name, libxml_get_errors());
            return false;
        }
        
        $regex = "";
        foreach($xml_args as $xml_arg){
            $regex .= str_replace("`", "", $xml_arg->__toString());
        }
        $regex = "`".$regex."`";
        
        return $regex;
    }
    
    /**
     * 
     * @param string $command_name IRC command to send
     * @param array $args List of arguments for command. Must match regex in commands.xml
     * @return boolean Indicates if the command was sent successfully or not
     */
    protected function send_command($command_name, array $args){
        if(!($regex = $this->get_regex_for_command($command_name))){
            return false;
        }
        
        // TODO: check each arg against each arg regex
        $arg_text = implode(" ",$args);
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
        
        // make sure nick reflects nick sent to server
        if($command_name == "NICK"){
            $this->my_data->nick = $args[0];
        }
        
        $this->errors->set_errors();
        return true;
    }
    
    /**
     * Register a response method for this bot. Methods will not be called unless they are registered
     * @param ResponseMethod $response_method Response method to register
     */
    public function register_response_method(ResponseMethod $response_method){
        $this->response_methods[] = $response_method;
    }
    
    /**
     * This method is untested. It removes the response method specified from the list of registered response methods.
     * @param string $name Name of registered response method to remove.
     * 
     * @return bool Indicates success
     */
    public function deregister_response_method($name){
        foreach($this->response_methods as $response_method){
            if($response_method->name == $name){
                unset($response_method);
                return true;
            }
        }
        return false;
    }
    /**
     * Fetch server messages from connection
     * @return array An array of all server responses.
     */
    public function get_server_data(){
        $responses = array();
        if($line = fgets($this->connection)){
            print("Server Said: ".$line);
            $responses[] = $line;
        }
        return $responses;
    }
    /**
     * Returns information about the first part of the IRC message (source: user or server)
     * @param string $prefix The first part of an IRC message. Indicates the source of the message.
     * @return ServerInfo|UserInfo May specify the server, or it may specify information about the user, depending on the source.
     */
    private static function get_prefix_data($prefix){
        // if prefix is a user
        if(strpos($prefix, "!") !== false){
            $prefix_data = PHPBot::split_user_prefix($prefix);
        }else{ // if prefix is a server / service
            //for a server, the whole prefix is the server itself
            $prefix_data = new ServerInfo($prefix);
        }
        return $prefix_data;
    }
    /**
     * Splits user prefix (first part of IRC message) into useful parts
     * @param string $user_prefix
     * @return UserInfo Specifies information about the user
     */
    private static function split_user_prefix($user_prefix){
        // for a user, command prefix is Nick!User@host, 
        // so split prefix around ! and @
        $user_temp = preg_split("`[!@]`", $user_prefix, 3, PREG_SPLIT_NO_EMPTY);
        $nick = trim($user_temp[0]);
        $user = trim($user_temp[1]);
        $host = trim($user_temp[2]);
        return new UserInfo($nick, $user, $host);
    }
    
    /**
     * Parses all of the responses from the server
     * @param array $responses All of the responses from the server
     * @return array Array of {@link ParsedResponse} objects
     */
    public function parse_server_data(array $responses){
        $parsed_responses = array();
        foreach($responses as $response){
            $parsed_responses[] = $this->parse_response($response);
        }
        return $parsed_responses;
    }
    /**
     * 
     * @param type $response
     * @return type
     */
    private function parse_response($response){
        if($response[0] != ":"){
            // no prefix, just command and args (happens with PING)
            return PHPBOT::parse_response_no_prefix($response);
        }
        
        return $this->parse_response_with_prefix($response);
    }
    /**
     * 
     * @param array $response
     * @return \ParsedResponse
     */
    private function parse_response_with_prefix($response){
        $response[0] = ""; //remove ':' character (prefix) fastest (not best) way to do this
        $parsed_response = explode(" ", $response, 3);
        $prefix = $parsed_response[0];
        $cmd = $parsed_response[1];
        $args = array();
        
        if(!($xml = simplexml_load_file(dirname(__FILE__)."\\xml\\commands.xml"))){
            $this->errors->set_errors("", libxml_get_errors());
            return new ParsedResponse(new ServerInfo("?"), "?", array());
        }
        
        if(!($xml_args = $xml->xpath("/commands/command[@name='".$cmd."']/args/arg"))){
            $this->errors->set_errors("Could not find args for command ".$cmd, libxml_get_errors());
            // split args up to max IRC args if could not find command
            $args = explode(" ", $parsed_response[2], MAX_PARAMETERS);
        }else{
            // otherwise parse by specified regex
            $args = $this->parse_response_args($parsed_response[2], $xml_args, $cmd);
        }
        
        $user_data = PHPBot::get_prefix_data($prefix);
        return new ParsedResponse($user_data, $cmd, $args);
    }
    /**
     * 
     * @param type $response
     * @return \ParsedResponse
     */
    private static function parse_response_no_prefix($response){
        $parsed_response = explode(" ", $response, 2);
        $cmd = $parsed_response[0];
        $args = explode(" ", $parsed_response[1], MAX_PARAMETERS);
        return new ParsedResponse(new ServerInfo("?"), $cmd, $args);
    }
    /**
     * 
     * @param type $args_string
     * @param type $xml_args_regex
     * @param type $cmd
     */
    private function parse_response_args($args_string, $xml_args_regex, $cmd = "UNSPECIFIED"){
        foreach($xml_args_regex as $xml_arg_regex){
            $arg_regex = $xml_arg_regex->__toString();
            $args[] = $this->parse_response_arg($args_string, $arg_regex, $cmd);
            $args_string = preg_replace($arg_regex, "", $args_string, 1);
        }
        return $args;
    }
    /**
     * 
     * @param type $args_string
     * @param type $arg_regex
     * @param type $cmd
     * @return boolean|array
     */
    private function parse_response_arg($args_string, $arg_regex, $cmd = "UNSPECIFIED"){
        $matches = array();
        if(!($matched = preg_match($arg_regex, $args_string, $matches))){
            if($matched === false){
                $this->errors->set_errors("Matching failed for command ".$cmd." regex ".$arg_regex);
            }else if($matched === 0){
                $this->errors->set_errors("No matches found for command ".$cmd." regex ".$arg_regex);
            }
            return false;
        }
        return $matches[0];
    }
    
    /**
     * Calls methods as registered based on parsed server responses
     * @param array $parsed_responses Array of {@link ParsedResponse} objects
     */
    public function respond(array $parsed_responses){
        foreach($parsed_responses as $parsed_response){
            foreach($this->response_methods as $response_method){
                if($response_method->command == $parsed_response->command){
                    if($this->get_user_permissions($parsed_response->from->nick) < $response_method->permissions){
                        $this->send_command("PRIVMSG", array($parsed_response->from->nick, "You have insufficient priviledges to run this command"));
                        continue;
                    }
                    $method_to_call = $response_method->name;
                    if(!$this->$method_to_call($parsed_response)){
                        $this->errors->set_errors("Problem calling response method '".$method_to_call."'");
                        continue;
                    }
                    print("Ran response method ".$response_method->name.PHP_EOL);
                    print("Args: ".print_r($parsed_response->args, true));
                }
            }
        }
    }
}
/**
 * Class to hold error info for PHPBot
 */
class BotError{
    /**
     *
     * @var int Stores socket error number 
     */
    public $fsock_error_num;
    /**
     *
     * @var string Stores socket error description 
     */
    public $fsock_error_str;
    /**
     *
     * @var string Internal errors (PHPBot)
     */
    public $internal;
    /**
     *
     * @var string XML file parse errors 
     */
    public $xml;
    /**
     *
     * @var array (fsock_error_num, fsock_error_str) Array that holds socket error info
     */
    public $fsock;
    
    /**
     * Sets errors, and outputs. Primarily for debugging, should be used anywhere there's an error.
     * @param string $internal Any internal errors (PHPBot)
     * @param string $xml Any XML parsing errors
     */
    public function set_errors($internal = "", $xml = ""){
        $this->internal = $internal;
        $this->xml = $xml;
        $this->fsock = array(
            $this->fsock_error_num,
            $this->fsock_error_str);
        print_r($this);
    }
}

/**
 * Hold information about parsed response from server. All registered response methods are passed this object
 */
class ParsedResponse{
    /**
     *
     * @var UserInfo|ServerInfo Information about the source of the response
     */
    public $from;
    /**
     *
     * @var string IRC command received
     */
    public $command;
    /**
     *
     * @var array Arguments sent with IRC command
     */
    public $args;
    
    /**
     * Sets response data
     * @param UserInfo|ServerInfo $from Information about the source of the response
     * @param string $command IRC command received
     * @param array $args Arguments sent with IRC command
     */
    public function __construct($from, $command, $args){
        $this->from = $from;
        $this->command = $command;
        $this->args = $args;
    }
}

/**
 * Holds information about an IRC user
 */
class UserInfo{
    /**
     *
     * @var string User nick
     */
    public $nick;
    /**
     *
     * @var string User username
     */
    public $user;
    /**
     *
     * @var string User host (IP or hostname)
     */
    public $host;
    
    /**
     * Sets user info
     * @param string $nick This user's IRC nick
     * @param string $user This user's username
     * @param string $host This user's hostname or IP address
     */
    public function __construct($nick, $user, $host){
        $this->nick = $nick;
        $this->user = $user;
        $this->host = $host;
    }
}

/**
 * Holds info about server.
 */
class ServerInfo{
    /**
     *
     * @var string Server hostname
     */
    public $host;
    
    /**
     * Sets server data
     * @param string $host Server host name
     */
    public function __construct($host){
        $this->host = $host;
    }
}

/**
 * Holds data the bot will use to log in to an IRC server
 */
class ConnectionData{
    /**
     *
     * @var string Hostname or IP address used for login
     */
    public $host;
    /**
     *
     * @var string Port to log in on, defaults to 6667
     */
    public $port = "6667";
    
    /**
     * Sets connection data
     * @param string $host Hostname or IP address used for login
     * @param string $portPort to log in on, defaults to 6667
     */
    public function __construct($host, $port = "6667"){
        $this->host = $host;
        $this->port = $port;
    }
}

/**
 * Holds data the bot will use on IRC server
 */
class BotData{
    /**
     *
     * @var string IRC User, used during log in
     */
    public $user;
    /**
     *
     * @var string IRC Password, used with Nickserv
     */
    public $pass;
    /**
     *
     * @var string IRC Nick 
     */
    public $nick;
    /**
     *
     * @var string IRC Realname 
     */
    public $realname;
    /**
     *
     * @var array List of channels (string) to join 
     */
    public $channels;
    
    /**
     * Sets data for bot to use in IRC chat and during log-in
     * @param string $user Username for this bot
     * @param string $pass Password for this bot
     * @param string $nick IRC Nick for this bot
     * @param string $realname "Real name" for this bot
     * @param array $channels List of channels this bot will join
     */
    public function __construct($user, $pass, $nick, $realname, $channels){
        $this->user = $user;
        $this->pass = $pass;
        $this->nick = $nick;
        $this->realname = $realname;
        $this->channels = $channels;
    }
}

/**
 * Holds a response method to register with the bot
 */
class ResponseMethod{
    /**
     *
     * @var string Name of method to call for response
     */
    public $name;
    /**
     *
     * @var string name of IRC command which triggers this method
     */
    public $command;
    /**
     *
     * @var real Permissions level needed to execute this method via IRC
     */
    public $permissions;
    
    /**
     * Set response method data
     * @param string $name Name of the method to register
     * @param string $command IRC command which this method will respond to
     * @param real $permissions Permissions level required to run this method
     */
    public function __construct($name, $command, $permissions){
        $this->name = $name;
        $this->command = $command;
        $this->permissions = $permissions;
    }
}