<?php
#-------------------------------------------------
#--
#-- Model mysql操作库
#-- @author libo
#-- @package Model mysql
#-- @copyright 2009 - 2014
#-------------------------------------------------
namespace library;

class Mysql
{
	var $pdos = array();		//数据库对象
	var $queue = array(); 		//绑定队列
	var $name = '';					//数据库名称
	var $debug = false;			//调试模式
	var $Transaction = false;
	var $throw = array();   //外部投射数据
	var $querycount=0;	//查询次数

	//public static $pdos = array();


	function __construct($config)
	{	
		$this->conf = $config;
	}
	/**
	 * 选择数据库
	 */
	private function selectDB($sql,$only_master)
	{	
		$obj = $this->conf;
		if($obj['read'])
		{
			$way = $this->fetchWay($sql,$only_master);//分析读写
			$dbarr = $obj[$way];
			$way == 'master'?$dbarr['sec'] = 'master':"";
			if($way == 'slave')
			{
				$n = rand(0,count($dbarr)-1);
				$dbarr = $dbarr[$n];
				$dbarr['sec'] = 'slave';
			}
		}
		else
		{
			$dbarr = $obj['master'];
			$dbarr['sec'] = 'master';
		}

		
		return $dbarr;

	}//End

	/**
	 * 连接数据库
	 */
	private function connectDB($dbarr)
	{	
		$keyName = $this->name.'_'.$dbarr['sec'];
		if(!$dbarr) throw new \InvalidArgumentException('数据库不存在');
		$dsn  = "mysql:host={$dbarr['host']};port={$dbarr['port']};dbname={$dbarr['db']};charset=utf8mb4";
		$pdo = new \PDO($dsn, $dbarr['user'], $dbarr['pass']);
		$pdo->setAttribute(\PDO::ATTR_ERRMODE, \PDO::ERRMODE_EXCEPTION);
		$pdo->query('set names utf8mb4');
		$this->pdos[$keyName] = $pdo;
		
		/*if(!isset($this->pdos[$keyName]))
		{	
			if(!$dbarr) throw new \InvalidArgumentException('数据库不存在');
			$dsn  = "mysql:host={$dbarr['host']};port={$dbarr['port']};dbname={$dbarr['db']};charset=utf8mb4";
			$pdo = new \PDO($dsn, $dbarr['user'], $dbarr['pass']);
			$pdo->setAttribute(\PDO::ATTR_ERRMODE, \PDO::ERRMODE_EXCEPTION);
			$status = $pdo->getAttribute(\PDO::ATTR_SERVER_INFO);
			//重连机制
			if(strpos($status, 'MySQL server has gone away') === false)
			{
				$pdo = new \PDO($dsn, $dbarr['user'], $dbarr['pass']);
				$pdo->setAttribute(\PDO::ATTR_ERRMODE, \PDO::ERRMODE_EXCEPTION);
			}
			$pdo->query('set names utf8mb4');
			$this->pdos[$keyName] = $pdo;
		}else{
			$status = $this->pdos[$keyName]->getAttribute(\PDO::ATTR_SERVER_INFO);
			//重连机制
			if(strpos($status, 'MySQL server has gone away') === false)
			{
				$pdo = new \PDO($dsn, $dbarr['user'], $dbarr['pass']);
				$pdo->setAttribute(\PDO::ATTR_ERRMODE, \PDO::ERRMODE_EXCEPTION);
				$pdo->query('set names utf8mb4');
				$this->pdos[$keyName] = $pdo;
			}
			
		}*/
		return $this->pdos[$keyName];
	}

	//开启事务
	public function beginTransaction()
	{
		$dbarr = $this->conf['master'];
		$dbarr['sec'] = 'master';
		$this->Transaction = $this->connectDB($dbarr);
		$this->Transaction->beginTransaction();
	}

	//事务提交
	public function commit()
	{
		$this->Transaction->commit();
	}
	//事务回滚
	public function rollBack()
	{
		$this->Transaction->rollBack();
	}

	//事务结束释放对象
	public function endTransaction()
	{
		$this->Transaction = false;
	}

	/**
	 * 获取操作方式
	 */
	private function fetchWay($sql,$only_master)
	{
		$sql = trim($sql);
		$sql = strtolower($sql);
		if($only_master)
		{
			return 'master';
		}
		elseif(substr($sql,0,6) === 'select')
		{
			return 'slave';
		}
		else
		{
			return 'master';
		}
	}

	/**
	 * 执行操作
	 */
	public function query($sql,$cond = 'one', $index = null,$return_type = \PDO::FETCH_OBJ,$only_master = false)
	{	
		$this->querycount++;
		if($this->debug)//调试模式
		{
			print $sql."\r\n";
		}

		$sql = trim($sql);
		$querysql = $sql;
		$sql = strtolower($sql);
		$dbarr = $this->selectDB($sql,$only_master);//获取数据库对象
		//$pdoj = $this->connectDB($dbarr);
		$pdoj = $this->Transaction?$this->Transaction:$this->connectDB($dbarr);//判断是否存在事务对象
		$queue_arr = $this->queue;
		$this->queue = array();//重置队列
		$out_arr = array();
		if($queue_arr) //采用准备语句格式
		{
			@asort($queue_arr);
			$stmt = $pdoj->prepare($querysql);
			foreach ($queue_arr as $key => $value) 
			{	
				if($value[2])
				$stmt->bindValue($value[0],$value[1],$value[2]);
				else
				$stmt->bindValue($value[0],$value[1]);
				$out_arr[$value[0]]=$value[1];//新增  2015-02-02  libo
			}
			$stmt->execute();

			if(substr($sql,0,6)==='select')//判断是否查询
			{	
				if($cond == 'one')
				{
					if($return_type == \PDO::FETCH_OBJ)
					{
						$data = $this->fetch_object($stmt);
					}
					else
					{
						$data = $stmt->fetch($return_type);
					}
				}
				else
				{
					if($return_type == \PDO::FETCH_OBJ)
					{
						$data = $this->fetch_all_object($stmt, $index);
					}
					else
					{
						$data = $stmt->fetchAll($return_type);
					}
				}	
			}
			elseif(substr($sql,0,6) === 'insert')//插入返回最后一个ID
			{
				$data =  $pdoj->lastInsertId(); //获取操作返回ID
				//$this->output($stmt->queryString,$data,$out_arr);
			}
			else
			{
				$data = $stmt->rowCount(); //影响行数主要用于UPDATE和DELETE
				//$this->output($stmt->queryString,$data,$out_arr);
			}
		}
		else
		{	

			$stmt = $pdoj->query($querysql);
			if(substr($sql,0,6)==='select')//判断是否查询
			{	
				if($cond == 'one')
				{
					if($return_type == \PDO::FETCH_OBJ)
					{
						$data = $this->fetch_object($stmt);
					}
					else
					{
						$data = $stmt->fetch($return_type);
					}
				}
				else
				{
					if($return_type == \PDO::FETCH_OBJ)
					{
						$data = $this->fetch_all_object($stmt, $index);
					}
					else
					{
						$data = $stmt->fetchAll($return_type);
					}
				}	
			}
			elseif(substr($sql,0,6) === 'insert')
			{
				$data =  $pdoj->lastInsertId(); //获取操作返回ID
				//$this->output($stmt->queryString,$data);
			}
			else
			{
				$data = $stmt->rowCount(); //影响行数
				//$this->output($stmt->queryString,$data);
			}
		}
		$pdoj = null;
		return $data;
	}

	/**
	 * 绑定写入或查询value队列
	 */
	public function bindvalue($i,$k,$val='')
	{
		if($this->queue)
		{
			foreach ($this->queue as $key => $value) 
			{
				$this->queue[$i] = array($i,$k,$val);
			}
		}
		else
		{
			$this->queue[$i] = array($i,$k,$val);
		}
	}

	private function boolval($v)
	{
		return $v?true:false;
	}

	//获取多条数据
	private function fetch_all_object($statement, $key = null)
	{
		$data = array();
		$convertors = $this->get_convertors($statement);
		while( ($object = $statement->fetch(\PDO::FETCH_OBJ)) )
		{
			foreach($convertors as $propoty => $convertor)
			{
				if($convertor)
				{	
					if(function_exists($convertor))
					{
						$object->$propoty = $convertor($object->$propoty);
					}
					else
					{
						$object->$propoty = $this->$convertor($object->$propoty);
					}
				}
			}
			if($key === null)
			{
				$data[] = $object;
			}
			else
			{
				$data[@$object->$key] = $object;
			}
		}
		if(count($data) === 0)
		{
			return false;
		}
		return $data;
	}

	//获取单条数据
	function fetch_object($statement)
	{
		$convertors = $this->get_convertors($statement);
		$object = $statement->fetch(\PDO::FETCH_OBJ);
		if($object === false)
		{
			return false;
		}
		foreach($convertors as $key => $value)
		{	
			if($value)
			{	
				if(function_exists($value))
				{
					$object->$key = $value($object->$key);
				}
				else
				{
					$object->$key = $this->$value($object->$key);
				}
				
			}
		}
		return $object;
	}


	// 获取列值（设定具体类型）
	function get_convertors(\PDOStatement $statement)
	{
		$convertors = array();
		$column_count = $statement->columnCount(); 
		for($i = 0; $i < $column_count; $i++)
		{
			$column_meta = $statement->getColumnMeta($i);
			
			if ($column_meta) {
				$convertor = null;
				switch ($column_meta['native_type']) {
					case 'TINY': 
						$convertor = $column_meta['len'] === 1 ? 'boolval' : 'intval'; 
						break;
					case 'LONGLONG': 
						if ($column_meta['len'] <= PHP_INT_MAX) 
						{
							$convertor = 'intval';
						}
						break;
					case 'SHORT': 
					case 'LONG': 
					case 'INT24': 
					case 'BIT': 
						$convertor = 'intval'; 
						break;
					case 'FLOAT': 
					case 'DOUBLE': 
					case 'NEWDECIMAL': 
						$convertor = 'floatval'; 
						break;
				}
				$convertors[ $column_meta['name'] ] = $convertor;
			}
		}

		return $convertors;
	}


	private function output($sql,$id = '',$queue = array())
	{	
		$out['sql'] = $sql;
		$out['bindVlue'] = $queue;
		$out['id'] = $id;
		$this->throw[] = $out;
	}

    function __destruct()
    {	
       $this->pdos  = array();
       $this->name = null;
    }

    public function get_query_count(){
    	return $this->querycount;
    }    
}
?>