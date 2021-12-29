
<?php
class BlockDB extends SQLite3
{
	public $condition = '00';
	public $last_block =false;
	public $max_block_data_size=4*1024*1024;
	public $nodeid=0;

    function __construct()
    {
        $this->open_db('data/blocks.db');
    }
	private function open_db($db_location)
	{
		$init = is_file($db_location)?false:true;
		$this->open($db_location);
		if($init == true)
		{
			$this->create_table();
		}
	}
	private function create_table()
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
    dataid      INTEGER PRIMARY KEY AUTOINCREMENT,
    data_hash VARCHAR (64),
    data      TEXT,
    timestamp DATE
);

');
	}

	public function get_last_block()
	{
		if(!$this->last_block)
		{
			$result = $this->query('select * from blockchain where blockid = (select max(blockid) from blockchain)');
			$this->last_block = ($result->fetchArray());
		}
		return $this->last_block;
	}
	
	public function get_block_by_hash($hash)
	{
		$result = $this->query('select * from blockchain where hash="'.$hash.'"');
		return ($result->fetchArray());
	}

	public function block_count()
	{
		$result = $this->query('select count(1) from blockchain');
		$data = $result->fetchArray();
		return $data[0];
	}
	
	public function get_block_by_id($blockid)
	{
		$result = $this->query('select * from blockchain where blockid = '.$blockid.'');
		return ($result->fetchArray());
	}
	
	public function get_all_blocks()
	{
		$result = $this->query('select * from blockchain');
		return $result;
	}

	public function block_accepted($data)
	{
		$this->remove_pool_data($data);
		$this->insert_block($data);
	}

	public function remove_pool_data($data)
	{
		$data_array=json_decode($data['data'],1);
		foreach($data_array as $data_line)
		{
			$this->remove_pool_data_by_hash($data_line['data_hash']);
		}
	}

	public function remove_pool_data_by_hash($hash)
	{
		$this->exec("delete from pool where data_hash='$hash'");
	}

	public function insert_data($data)
	{
		$timestamp=date('Y-m-d H:i:s');
		$data_hash=hash('sha256', $data.$timestamp);
		$result = $this->exec("insert into pool ('data_hash','data','timestamp') values ('".$data_hash."','".str_replace("'","''",$data)."','".$timestamp."')");
		if ($result===false) exit($this->lastErrorMsg());
	}

	private function get_data_for_block()
	{
		$pool_data = $this->query('select * from pool order by dataid asc limit 0,100 ');
		$block_data = array();
		$total_size=0;

		while ($pdata = $pool_data->fetchArray())
		{
			unset($pdata['dataid']);
			$data_size=strlen(json_encode($pdata));
			if(($total_size + $data_size) < $this->max_block_data_size)
			{
				$total_size+=$data_size;
				$block_data[]=$pdata;
			}
			else
			{
			    break;
			}
		}

		return $block_data;

	}
	
	public function create_block($block)
	{

		$block_data = array('blockid'=>$block['blockid']+1,'prev_hash'=>$block['hash'],'timestamp'=>date('Y-m-d H:i:s'),'data' => json_encode( $this->get_data_for_block()),'secret' => $block['secret']);
		$block_data=$this->find_nonce($block_data);

		return $block_data;
	}
	
	public function find_nonce($block_data)
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
	
	public function data_hash($block_data)
	{
		return hash('sha256', $block_data['prev_hash'].$block_data['data'].$block_data['nonce']);
	}
	
	public function insert_block($block_data)
	{
		$result = $this->exec("insert into blockchain values ('".$block_data['blockid']."','".$block_data['prev_hash']."','".$block_data['hash']."','".$block_data['timestamp']."','".str_replace("'","''",$block_data['data'])."','".$block_data['nonce']."')");
		if ($result===false) exit($this->lastErrorMsg()); 
	}
}

?>
