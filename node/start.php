<?php

include "includes/BlockDatabase.class.php";

$db = new BlockDB;

ini_set('error_reporting', E_ALL ^ E_NOTICE);
ini_set('display_errors', 1);

// Set time limit to indefinite execution
set_time_limit(0);

// Set the ip and port we will listen on
$address = '127.0.0.1';
$port = 22290+$db->nodeid;

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
$seconds  = 0;

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

                  socket_write($v, parse_response("data",parse_input_command($com)))  ;
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

    }

    sleep(1);

    $seconds++;
}

// Close the master sockets
socket_close($sock);

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

//{"command":"hello"}
function hello($data=null)
{
    return 'Welcome To TBLOCKCHAIN Server';
}


//{"command":"send_data","data":"#$$DATA"}
function send_data($data=null)
{
    global $db;
    $db->insert_data($data['data']);
    return 'Data Accepted';
}


