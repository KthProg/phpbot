<?php
//command PRIVMSG
$priv_msg_leave = <<<'EOA'
switch($parsed_response["args"][0]){
    case $this->my_data["nick"]:
        switch($parsed_response["args"][1]){
        case ":LEAVE":
            $this->log_out($parsed_response["args"][2]);
            break;
        }
    break;
}
EOA;

$priv_msg_recon = <<<'EOB'
switch($parsed_response["args"][0]){
    case $this->my_data["nick"]:
        switch($parsed_response["args"][1]){
            case ":RECONNECT":
                $this->log_out($parsed_response["args"][2]);
                $this->connect();
                $this->log_in();
            break;
        }
    break;
}
EOB;

$priv_msg_say = <<<'EOC'
switch($parsed_response["args"][0]){
    case $this->my_data["nick"]:
        switch($parsed_response["args"][1]){
            case ":SAY":
                $this->send_command("PRIVMSG", array($parsed_response["args"][2], $parsed_response["args"][3]));
                break;
        }
    break;
}
EOC;

//command (string)ERR_BANNEDFROMCHAN
$ask_for_unban = <<<'EOD'
foreach($this->my_data["channels"] as $channel){
    $this->send_command("PRIVMSG", array($channel, "Please unban me from ".$parsed_response["args"][1]."!"));
}
EOD;

//command (string)RPL_ENDOFMOTD
$join_chans = <<<'EOE'
$this->join_channels();
EOE;

//command (string)ERR_NICKNAMEINUSE
$random_nick = <<<'EOF'
$rnd_nick = $this->my_data["nick"].(string)rand();
$this->my_data["nick"] = $rnd_nick;
$this->send_command("NICK", array($rnd_nick));
EOF;

//command PING
$pong = <<<'EOG'
$this->send_command("PONG", $parsed_response["args"]);
EOG;

