<?php

include "includes/BlockDatabase.class.php";

$db = new BlockDB;

ini_set('error_reporting', E_ALL ^ E_NOTICE);
ini_set('display_errors', 1);

// Set time limit to indefinite execution
set_time_limit(0);

// Set the ip and port we will listen on
$address = '127.0.0.1';
$port = 22290;

ob_implicit_flush();

// Create a TCP Stream socket
$sock = socket_create(AF_INET, SOCK_STREAM, 0);

// Bind the socket to an address/port
socket_bind($sock, $address, $port) or die('Could not bind to address');

// Start listening for connections
socket_listen($sock);

// Non block socket type
socket_set_nonblock($sock);

// Clients
$clients  = [];
$commands  = [];
$seconds  = 0;
$cseconds = 0;
$method=0;

$last_block = $db->get_last_block();

// Loop continuously
while (true) {
    // Accept new connections
    if ($newsock = socket_accept($sock)) {
        if (is_resource($newsock)) {
            // Write something back to the user
            //socket_write($newsock, ">", 1).chr(0);
            // Non bloco for the new connection
            socket_set_nonblock($newsock);
            // Do something on the server side
            echo "New client connected\n";
            // Append the new connection to the clients array
            $clients[] = $newsock;
        }
    }

    // Polling for new messages
    if (count($clients)) {

        foreach ($clients AS $k => $v) {
            // Check for new messages
            /*
            $string="";
            if ($char = @socket_read($v, 1024)) {
                $string .= $char;
            }
            */
            $string="";
            while ($char = @socket_read($v, 1024)) {
                $string .= $char;
            }

            // New string for a connection
            if ($string) {
               // echo "$k:$string\n";
              $com = handle_input($string);
              if($com['command'] == 'quit')
              {
                socket_write($v, parse_response("data","Oh... Goodbye..."))  ;
                socket_close($clients[$k]);
                // Remove from active connections array
                unset($clients[$k]);
              }
              elseif($com['command'] == 'help')
              {
                  socket_write($v, parse_response("data",$com))  ;
              }
              else
              {
                $commands[$k]=$com;
              }
            } else {
                if ($seconds > 60) {
                    // Ping every 5 seconds for live connections
                    if (false === @socket_write($v, 'PING')) {
                        // Close non-responsive connection
                         echo " client Diconnected\n";
                        socket_close($clients[$k]);
                        // Remove from active connections array
                        unset($clients[$k]);
                    }
                    // Reset counter
                    $seconds = 0;
                }
            }



        }
            if($cseconds >= 30)
            {
                if($method == 1)
                {
                    $com_v_keys=array_keys($commands);
                    shuffle($com_v_keys);
                    $total_new=count($com_v_keys)-1;
                    $rand_choice=rand(0,$total_new);
                    $v_lucky=$com_v_keys[$rand_choice];
                    $new_block_command = $commands[$v_lucky];
                }
                elseif($method == 2)
                {
                    $total_blocks=$db->block_count();
                    $choices=array();
                    foreach($commands as $kk => $data)
                    {
                        $percentage=(int)($data['data'])/$total_blocks;
                        $total_chance=ceil($percentage*100);
                        for($i=1;$i<=$total_chance;$i++)
                        {
                            $choices[]=$kk;
                        }
                    }
                    $rand_choice = rand(0,(count($choices)-1));
                    $v_lucky=$choices[$rand_choice];
                }
                elseif($method == 3)
                {
                    $choices=array();
                    foreach($commands as $kk => $data)
                    {
                        $choices[$data['data']]++;
                    }
                    $v_lucky = null;
                    while(!isset($clients[$v_lucky]))
                    {
                        unset($choices[$v_lucky]);
                        $v_lucky = array_key_max_value($choices);
                        if(count($choices) < 1)
                        {
                            $v_lucky=null;
                            break;
                        }
                    }
                }

                if(isset($clients[$v_lucky]))
                {
                    if($method > 1)
                    {
                        $last_block['secret'] = rand(1000000000,10000000000);
                        socket_write($clients[$v_lucky], parse_response('send_new_block',$last_block));
                        sleep(1);
                        $string='';
                        while ($s= socket_read($clients[$v_lucky], 1024)) {
                            $string.=$s;
                        }
                        $command=json_decode($string,1);
                        $new_block_command=$command;

                    }
                    else {
                       // print_r($new_block_command);
                    }
                    $new_block_command['method']=$method;

                    echo "\nNew Block Accepted, Method : ".$method.', Last Block ID : '.$last_block['blockid'].', New Block ID : '.$new_block_command['data']['blockid'].', Node : '.$v_lucky;
                    $block_status = $db->send_new_block($new_block_command,$last_block);
                    if($block_status != false)
                    {
                        $last_block=$new_block_command['data'];
                        socket_write($clients[$v_lucky], parse_response('block_accepted',$last_block));
                    }
                }

                $last_block['secret'] = rand(1000000000,10000000000);

                $method=rand(1,3);
                echo "\n".$method."\n";

                foreach ($clients AS $kk => $vv)
                {
                    if($method == 1)
                    {
                        // Proof of luck
                        socket_write($vv, parse_response('send_new_block',$last_block));
                    }
                    elseif($method == 2)
                    {
                        // Proof of stake
                        socket_write($vv, parse_response('send_block_count'));
                    }
                    elseif($method == 3)
                    {
                        // Proof of vote
                        socket_write($vv, parse_response('send_choice',array_keys($clients)));
                    }
                    //socket_write($vv, $seconds.":".$cseconds.':'.$method."\r\n");
                }
                $cseconds=0;
            }


    }

    sleep(1);

    $seconds++;
    $cseconds++;
}

// Close the master sockets
socket_close($sock);

function array_key_max_value($array)
{
    $max = null;
    $result = null;
    foreach ($array as $key => $value) {
        if ($max === null || $value > $max) {
            $result = $key;
            $max = $value;
        }
    }
    return $result;
}
function handle_input($input)
{
    // You probably want to sanitize your inputs here
    
    $com = json_decode(trim($input),1); // Trim the input, Remove Line Endings and Extra Whitespace.

    if((!isset($com['command'] ))|| ($com['command'] == "help")) // User Wants to quit the server
    {
        $output = array('command' => 'help',"data" => "available commands\nJSON Input for control\navailable commands\nhelp for this screen\nquit to exit the connection");
    }
    else
    {
        $output = $com;
    }
    return $output;
}


function parse_input_command($data)
{
    try
    {
        if(function_exists($data['command']))
        {
            return call_user_func($data['command'],$data);
        }
        return "command not found";
    }
    catch(Exception $e)
    {
        return false;
    }
}

function parse_response($command,$data=null)
{
    return json_encode(array("command" => $command,"data" => $data))."\r\n";
}
//{"command":"get_last_block"}
function get_last_block($data=null)
{
    global $last_block;
    return $last_block;
}

//{"command":"get_connected_nodes"}
function get_connected_nodes($data=null)
{
    $connected_nodes="";
    return $connected_nodes;
}

//{"command":"get_block_by_id","blockid" : 1}
function get_block_by_id($data=null)
{
    global $db;
    $block = $db->get_block_by_id($data['blockid']);
    return $block;
}


//{"command":"get_block_by_hash","hash" : "00f2887e0999bb0568bcf5575aeffc2588628a4fa8ec670accc064b4f17c83a8"}
function get_block_by_hash($data=null)
{
    global $db;
    $block = $db->get_block_by_hash($data['hash']);
    return $block;
}

//{"command":"hello","hash" : ""}
function hello($data=null)
{
    return 'Welcome To TBLOCKCHAIN Server';
}


