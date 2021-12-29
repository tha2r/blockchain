
<?php

include "includes/BlockDatabase.class.php";

$db = new BlockDB;

$last_block = ($db->get_last_block());

$last_block_id=$last_block['blockid'];

$input_block=(isset($_GET['blockid']) && intval($_GET['blockid'])>0)?(int)$_GET['blockid']:1;

$block=($input_block >= $last_block_id)?$last_block:$db->get_block_by_id($input_block);

$prevblock=($input_block <= 1)?1:$input_block-1;
$nextblock=($input_block >= $last_block_id)?$last_block_id:$input_block+1;
Echo "<table>
<tr>
<td>Block ID</td>
<td>".$block['blockid']."</td>
</tr>
<tr>
<td>Block Hash</td>
<td>".$block['hash']."</td>
</tr>
<tr>
<td>Block Data</td>
<td>".$block['data']."</td>
</tr>
<tr>
<td>Block Date</td>
<td>".$block['timestamp']."</td>
</tr>
<tr>
<td>Block nonce</td>
<td>".$block['nonce']."</td>
</tr>
<tr>
<td><a href='explorer.php?blockid=$prevblock'>Previus</a></td>
<td><a href='explorer.php?blockid=$nextblock'>Next</a></td>
</tr>

</table>";