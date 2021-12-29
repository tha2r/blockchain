
<?php
class BlockDB extends SQLite3
{
	public $condition = '00';
	public $last_block =false;

    function __construct()
    {
		//parent::__construct();
		$this->open_db('data/blocks.db');
    }
	function open_db($db_location)
	{
		$init = is_file($db_location)?false:true;
		$this->open($db_location);
		if($init == true)
		{
			$this->create_table();
		}
	}
	
	function get_last_block()
	{
		if(!$this->last_block)
		{
			$result = $this->query('select * from blockchain where blockid = (select max(blockid) from blockchain)');
			$this->last_block = ($result->fetchArray());
		}
		return $this->last_block;
	}

	function block_count()
	{
		$result = $this->query('select count(1) from blockchain');
		$data = $result->fetchArray();
		return (int)$data[0];
	}

	function get_block_by_hash($hash)
	{
		$result = $this->query('select * from blockchain where hash="'.$hash.'"');
		return ($result->fetchArray());
	}
	
	function get_block_by_id($blockid)
	{
		$result = $this->query('select * from blockchain where blockid = '.$blockid.'');
		return ($result->fetchArray());
	}
	
	function get_all_blocks()
	{
		$result = $this->query('select * from blockchain');
		return $result;
	}
	
	function create_table()
	{
		$this->exec('CREATE TABLE blockchain (
    blockid   INTEGER      PRIMARY KEY
                           NOT NULL,
    prev_hash VARCHAR (64),
    hash      VARCHAR (64),
    timestamp DATE,
    data      TEXT,
    nonce    INTEGER
);
');

		$this->exec('
CREATE TABLE pool (
    txid      VARCHAR (64),
    data      TEXT,
    timestamp DATE
);

');
	$genesis=array('blockid'=>1,'prev_hash'=>null,'timestamp'=>date('Y-m-d H:i:s'),'data' => json_encode('initial block'));
	$this->create_block($genesis);
	}
	
	function create_block($block_data)
	{
		$block_data = $this->find_nonce($block_data);
		$this->insert_block($block_data);
		$this->last_block = $block_data;
	}

	function find_nonce($block_data)
	{
		$condlength = strlen($this->condition);
		for($i=0;$i<=pow($condlength,64);$i++)
		{
			$block_data['nonce']=$i;
			$hash = $this->data_hash($block_data);//hash('sha256', $block_data['prev_hash'].$block_data['data'].$nonce);
			if(substr($hash, 0, $condlength) === $this->condition)
			{
				break;
			}
		}
		$block_data['hash']=$hash;
		
		return $block_data;
	}
	
	public function validate_block($block_data)
	{
		$condlength = strlen($this->condition);

		$data_hash  = $this->data_hash($block_data);
		$block_hash = $block_data['hash'];
		$cond1 = substr($block_hash, 0, $condlength) === $this->condition;
		$cond2 = $data_hash === $block_hash;
		
		return ($cond1 && $cond2);
	}
	
	function data_hash($block_data)
	{
		return hash('sha256', $block_data['prev_hash'].$block_data['data'].$block_data['nonce']);
	}
	
	function insert_block($block_data)
	{
		$result = $this->exec("insert into blockchain values ('".$block_data['blockid']."','".$block_data['prev_hash']."','".$block_data['hash']."','".$block_data['timestamp']."','".str_replace("'","''",$block_data['data'])."','".$block_data['nonce']."')");
		if ($result===false) exit($this->lastErrorMsg());
	}

	function send_new_block($data=null,$last_block)
	{
		$block = $data['data'];
		global $db;
		$validate   = $this->validate_block($block);
		//$last_block = $db->get_last_block();
		if(!$validate || ($last_block['hash'] != $block['prev_hash']) || ($last_block['secret'] != $block['secret']) || ($last_block['blockid'] == $block['blockid']))
		{
			return false;
		}
		else
		{
			$this->insert_block($block);
			return true;
		}
	}

}

?>
