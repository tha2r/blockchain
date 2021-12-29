<?php
include "includes/BlockDatabase.class.php";

$db = new BlockDB;

$address="127.0.0.1";
$port=22290;

$socket=socket_create(AF_INET, SOCK_STREAM, SOL_TCP);
$connect = socket_connect($socket, $address, $port);
$new_block_data=array();
$block_count=$db->block_count();

while(1)
{
    $response = read_response($socket);
    if($response !== "")
    {
        if(!isset($response['command']))
        {
            print_r($response);
        }
        else {
            $new_data =  handle_request($response);
            //echo $response['command'];
            if($response['command']!='block_accepted')
            {
                $new_command= prepare_command($response['command'],$new_data) ;
                socket_write($socket, $new_command);
            }
        }
    }
}
socket_write($socket,  prepare_command("quit"))  ;
socket_close($socket);

function handle_request($data)
{
    if(isset($data['command']))
    {
        if(function_exists($data['command']))
        {
            return call_user_func($data['command'],$data);
        }
        else
        {
            return false;
        }
    }
    return false;
}

function block_accepted($data)
{
    global $db,$block_count;
    $block_count++;
    $db->block_accepted($data['data']);
}
function send_choice($data)
{
    $choice = array_rand($data['data']);
    return $data['data'][$choice];
}

function send_block_count($data)
{
    global $block_count;
    return $block_count;
}

function send_new_block($data)
{
    global $db,$new_block_data;
    $block = $data['data'];
    $block['secret']=isset($block['secret'])?$block['secret']:null;
    $block_data=$db->create_block($block);
    $new_block_data=$block_data;
    return $block_data;
}

function get_last_block($socket)
{
socket_write($socket, prepare_command("get_last_block"))  ;
$last_block = read_response($socket);
return $last_block['data'];
}


function prepare_command($command,$data=array())
{
    if($command == 'send_block_data') print_r($data);
    return json_encode(array("command" => $command,'data' => $data ))."\r\n";
}


function read_response($sock){
    $buffer='';
    while($buf = @socket_read($sock, 2048, PHP_NORMAL_READ))
    {
        $buffer .= $buf;
        if($buf == "\n"){ break; }
    }

    return json_decode($buffer,1);
}


?>