
<?php

include "includes/BlockDatabase.class.php";

$db = new BlockDB;

$blocks = ($db->get_all_blocks());

while ($block = $blocks->fetchArray())
{
	$hash = $db->data_hash($block);
	if($block['hash'] == $hash)
	{
		echo "Block ".$block['blockid']." is valid \n";
	}
	else
	{
		echo "Block ".$block['blockid']." is Not valid \n";
	}
}


?>
