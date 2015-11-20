<?php
namespace core;

use conf\Config;
use library\Mysql;

/**
 * Class Server
 * @author wangzhiqiang
 * @package core
 */
class Server
{
    private $redis;
    private $mysql;
    public $table;
    public static $dmain = [];
    private $redis_arr = [
        'count' => 0,
        'spend' => 0,
        'get' => 0,
        'post' => 0,
        'options' => 0,
        'head' => 0,
        'put' => 0,
        'delete' => 0,
        'trace' => 0,
        'z200' => 0,
        'z201' => 0,
        'z202' => 0,
        'z204' => 0,
        'z303' => 0,
        'z404' => 0,
        'other' => 0
    ];


    public function __construct(){
        $table = new \swoole_table(128);
        //$table->column('statistics', \swoole_table::TYPE_STRING, 64);
        $table->column('count', \swoole_table::TYPE_INT, 8);
        $table->column('spend', \swoole_table::TYPE_FLOAT);
        $table->column('get', \swoole_table::TYPE_INT, 8);
        $table->column('post', \swoole_table::TYPE_INT, 8);
        $table->column('options', \swoole_table::TYPE_INT, 8);
        $table->column('head', \swoole_table::TYPE_INT, 8);
        $table->column('put', \swoole_table::TYPE_INT, 8);
        $table->column('delete', \swoole_table::TYPE_INT, 8);
        $table->column('trace', \swoole_table::TYPE_INT, 8);
        $table->column('z200', \swoole_table::TYPE_INT, 8);
        $table->column('z201', \swoole_table::TYPE_INT, 8);
        $table->column('z202', \swoole_table::TYPE_INT, 8);
        $table->column('z204', \swoole_table::TYPE_INT, 8);
        $table->column('z303', \swoole_table::TYPE_INT, 8);
        $table->column('z404', \swoole_table::TYPE_INT, 8);
        $table->column('other', \swoole_table::TYPE_INT, 8);
        $table->create();

        $this->table = $table;
    }


    public function run($ip, $port = 9501, $model = SWOOLE_PROCESS, $type = SWOOLE_SOCK_UDP)
    {
        $serv = new \swoole_server($ip, $port, $model, $type);
        $serv->config = Config::get_swoole_config();
        $serv->set($serv->config);
        $serv->on('Start', array($this, 'onStart'));
        $serv->on('WorkerStart', array($this, 'onWorkerStart'));
        $serv->on('Connect', array($this, 'onConnect'));
        $serv->on('Receive', array($this, 'onReceive'));
        $serv->on('Task', array($this, 'onTask'));
        $serv->on('Finish', array($this, 'onFinish'));
        $serv->on('WorkerError', array($this, 'onWorkerError'));
        $serv->on('Close', array($this, 'onClose'));
        $serv->on('WorkerStop', array($this, 'onWorkerStop'));
        $serv->on('Shutdown', array($this, 'onShutdown'));
        $serv->on('ManagerStart', function ($serv) {
            global $argv;
            swoole_set_process_name("php {$argv[0]}: manager");
        });
        $serv->start();

        
        

    }

    public function onStart(\swoole_server $serv)
    {
        global $argv;
        swoole_set_process_name("php {$argv[0]}: statistics_master");

        //初始化统计
        $this->table->set('real_statistics',$this->redis_arr);
        //$this->table->set('time_statistics',$this->redis_arr);

        

        //table数据写入redis共享给websocket
        $serv->tick(1000, function(){
            $redis = $this->getRedis();

            $statistics = [];
            $real_st = $this->table->get('real_statistics');
            //重置计数
            $this->table->set('real_statistics', $this->redis_arr);
            $statistics['real_statistics'] = $real_st ? $real_st : $this->redis_arr;

            $redis = $this->getRedis();
            $dmain = $redis->get('dmain');

            if($dmain){
                $dmain = explode('|', $dmain);
                foreach ($dmain as $value) {
                    //echo $value;
                    $dmain_st = $this->table->get($value);
                    $statistics[$value] = $dmain_st ? $dmain_st : $this->redis_arr;
                    //重置计数
                    $this->table->set($value,$this->redis_arr);
                }
            }

   
            //共享到redis
            $statistics = json_encode($statistics);
            $redis->set('real_statistics', $statistics);

        });

        //定时器记录统计到数据库
        $serv->tick(600000, function()use($serv){
            //获取统计记录
            /*$data = $this->table->get('time_statistics');
            if ($data) {
                $data['dmain'] = 'time_statistics';
                $data['time'] = date('Y-m-d H:i:s');
                $data['task_type'] = 'statistic_log';
                $serv->task($data);
            }*/

            $data['time'] = time();
            $data['task_type'] = 'write_interface_log';
            $serv->task($data);

            $data['task_type'] = 'clean_interface_log';
            $serv->task($data);

            //初始化统计
            //$this->table->set('time_statistics', $this->redis_arr);
            
            $redis = $this->getRedis();
            $dmain = $redis->get('dmain');
            //print_r($dmain);
            if ($dmain) {
                $dmain = explode('|', $dmain);

                foreach ($dmain as $value) {
                    $dmain_data = $this->table->get('t_'.$value);
                    if ($dmain_data) {
                        //echo 2222;
                        $dmain_data['dmain'] = $value;
                        $dmain_data['time'] = date('Y-m-d H:i:s');
                        $dmain_data['task_type'] = 'statistic_log';
                        $serv->task($dmain_data);

                    }
                    $this->table->set('t_'.$value, $this->redis_arr);
                }
            }
        
        });
    }


    /**
     * onWorkerStart [worker拉起回调]
     * @author wangzhiqiang
     */
    public function onWorkerStart()
    {
        //初始化监听
        $redis = $this->getRedis();
        $dmain = $redis->get('dmain');
        if (!empty($dmain)) {
            self::$dmain = explode('|', $dmain);
            
            foreach (self::$dmain as $value) {
                $this->table->set($value,$this->redis_arr);
                $this->table->set('t_'.$value,$this->redis_arr);
            }
        }else{
            self::$dmain = [];
        }
    }


    /**
     * onConnect [连接建立回调]
     * @author wangzhiqiang
     * @param \swoole_server $serv
     * @param $fd
     * @param $from_id
     */
    public function onConnect(\swoole_server $serv, $fd, $from_id)
    {
        //echo "Worker#{$serv->worker_pid} Client[$fd@$from_id]: Connect.\n";
    }


    /**
     * onReceive [请求回调]
     * @author wangzhiqiang
     * @param \swoole_server $serv
     * @param $fd
     * @param $from_id
     * @param $data
     */
    public function onReceive(\swoole_server $serv, $fd, $from_id, $data)
    {
        //$data = $this->decode($data);
        $data = rtrim($data,'\r\n');
        //echo $data."\n";
        //print_r(self::$dmain);
        $data = json_decode($data, true);
        //echo $data."\n";
        //print_r($data);
        if(!empty($data) && count($data) == 7)
        {
            //记录统计
            $res = $this->pushStatistics($data);

            //投递日志任务
            /*$data['task_type'] = 'log';
            $serv->task($data);*/

            //记录到redis有序集合
            $redis = $this->getRedis();
            if ($redis->exists($data['interface'])) {
                $redis->hIncrBy($data['interface'],'num',1);
                $redis->hIncrBy($data['interface'],'z'.$data['status'],1);
                $redis->hIncrByFloat($data['interface'],'spend',$data['spend']);
            }else{
                $redis->hIncrBy($data['interface'],'num',1);
                $redis->hIncrBy($data['interface'],'z'.$data['status'],1);
                $redis->hIncrByFloat($data['interface'],'spend',$data['spend']);
                $redis->setTimeout($data['interface'],600000);
            }
            

            /*$ip_key = 'ip'.$data['clientip'];
            $redis->zIncrBy('ips',1,$ip_key);*/

            $redis->zIncrBy('interfaces',1,$data['interface']);
            
            $serv->send($fd, $res);
        }elseif ($data['control'] == 'reload') {
            $redis = $this->getRedis();
            foreach (self::$dmain as $value) {
                $this->table->del($value);
                $this->table->del('t_'.$value);
            }

            $new_dmain = $redis->get('dmain');
            if (!empty($new_dmain)) {
                $new_dmain = explode('|', $new_dmain);
                foreach ($new_dmain as $v) {
                    $res = $this->table->set($v,$this->redis_arr);
                    //echo $res;
                }
                self::$dmain = $new_dmain;
                
            }else{
                self::$dmain = [];
            }
        }
    }


    /**
     * onTask [任务回调]
     * @author wangzhiqiang
     * @param \swoole_server $serv
     * @param $task_id
     * @param $from_id
     * @param $data
     */
    public function onTask(\swoole_server $serv, $task_id, $from_id, $data)
    {
        switch($data['task_type']){
            //redis异步统计  弃用
            case 'log':
                $redis_ip = $this->getRedis(1);
                $key = 'ip'.$data['clientip'];
                if($redis_ip->exists($key)){
                    $redis_ip->incr($key);
                }else{
                    $redis_ip->set($key, 0, 300);
                }

                $redis_interface = $this->getRedis(2);
                $key = $data['interface'];
                if($redis_interface->exists($key)){
                    $redis_interface->incr($key);
                }else{
                    $redis_interface->set($key, 0, 300);
                }
                break;
            case 'statistic_log':
                //记录写入数据库
                $time_statistics_db = $this->getMysql();
                $time_statistics_db->bindvalue(1, $data['count'], \PDO::PARAM_INT);
                $time_statistics_db->bindvalue(2, $data['spend'], \PDO::PARAM_INT);
                $time_statistics_db->bindvalue(3, $data['get'], \PDO::PARAM_INT);
                $time_statistics_db->bindvalue(4, $data['post'], \PDO::PARAM_INT);
                $time_statistics_db->bindvalue(5, $data['options'], \PDO::PARAM_INT);
                $time_statistics_db->bindvalue(6, $data['head'], \PDO::PARAM_INT);
                $time_statistics_db->bindvalue(7, $data['put'], \PDO::PARAM_INT);
                $time_statistics_db->bindvalue(8, $data['delete'], \PDO::PARAM_INT);
                $time_statistics_db->bindvalue(9, $data['trace'], \PDO::PARAM_INT);
                $time_statistics_db->bindvalue(10, $data['z200'], \PDO::PARAM_INT);
                $time_statistics_db->bindvalue(11, $data['z201'], \PDO::PARAM_INT);
                $time_statistics_db->bindvalue(12, $data['z202'], \PDO::PARAM_INT);
                $time_statistics_db->bindvalue(13, $data['z204'], \PDO::PARAM_INT);
                $time_statistics_db->bindvalue(14, $data['z303'], \PDO::PARAM_INT);
                $time_statistics_db->bindvalue(15, $data['z404'], \PDO::PARAM_INT);
                $time_statistics_db->bindvalue(16, $data['other'], \PDO::PARAM_INT);
                $time_statistics_db->bindvalue(17, $data['time']);
                $time_statistics_db->bindvalue(18, $data['dmain']);

                $time_statistics_db->query('insert into time_statistics(`count`,`spend`,`get`,`post`,`options`,`head`,`put`,`delete`,`trace`,`z200`,`z201`,`z202`,`z204`,`z303`,`z404`,`other`,`time`,`dmain`) values(?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)');

                break;
            //记录ip日志到数据库
            case 'write_ip_log':
                $ip_log_db = $this->getMysql();
                $redis_ip = $this->getRedis();

                $ips = $redis_ip->zRevRange('ips', 0, 50, true);
                //$now = date('Y-m-d H:i:s');
                foreach($ips as $k => $v){
                    $ip_log_db->bindvalue(1, $k, \PDO::PARAM_STR);
                    $ip_log_db->bindvalue(2, $v, \PDO::PARAM_INT);
                    $ip_log_db->bindvalue(3, $data['time']);
                    $ip_log_db->query('insert into ip_logs(ip,num,time) values(?,?,?)');
                }

                /*$keys = $redis_ip->keys('ip*');
                foreach($keys as $values){
                    $num = $redis_ip->get($values);
                    $ip_log_db->bindvalue(1, $values, PDO::PARAM_STR);
                    $ip_log_db->bindvalue(2, $num, PDO::PARAM_INT);
                    $ip_log_db->bindvalue(3, $data['time']);
                    $ip_log_db->query('insert into ip_logs(ip,num,time) values(?,?,?)');
                }*/
                $redis_ip->delete('ips');

                break;
            //记录接口日志到数据库
            case 'write_interface_log':
                $interface_log_db = $this->getMysql();
                $redis_interface = $this->getRedis();

                $interfaces = $redis_interface->zRevRange('interfaces', 0, 100, true);
                $redis_interface->delete('interfaces');

                if (is_array($interfaces) && count($interfaces) > 0) {
                    $interface_infos = [];
                    foreach($interfaces as $k => $v){

                        $info = $redis_interface->hGetAll($k);
                        $redis_interface->delete($k);
                        //$info['time'] = $data['time'];
                        $interface_infos[$k] = $info;
                    }

                    foreach ($interface_infos as $key => $value) {
                        $interface_log_db->bindvalue(1,$key);
                        $interface_log_db->bindvalue(2,$value['num']);
                        $interface_log_db->bindvalue(3,$value['spend']);
                        $interface_log_db->bindvalue(4,isset($value['z200'])?$value['z200']:0);
                        $interface_log_db->bindvalue(5,isset($value['z201'])?$value['z201']:0);
                        $interface_log_db->bindvalue(6,isset($value['z202'])?$value['z202']:0);
                        $interface_log_db->bindvalue(7,isset($value['z204'])?$value['z204']:0);
                        $interface_log_db->bindvalue(8,isset($value['z303'])?$value['z303']:0);
                        $interface_log_db->bindvalue(9,isset($value['z404'])?$value['z404']:0);
                        $interface_log_db->bindvalue(10,isset($value['zother'])?$value['zother']:0);
                        $interface_log_db->bindvalue(11,$data['time']);
                        
                        $interface_log_db->query('insert into interface_logs(interface,num,spend,z200,z201,z202,z204,z303,z404,other,log_time) values(?,?,?,?,?,?,?,?,?,?,?)');
                    }
                }

                /*$keys = $redis_interface->keys('*');
                foreach($keys as $values){
                    $num = $redis_interface->get($values);
                    $interface_log_db->bindvalue(1, $values, PDO::PARAM_STR);
                    $interface_log_db->bindvalue(2, $num, PDO::PARAM_INT);
                    $interface_log_db->bindvalue(3, $data['time']);
                    $interface_log_db->query('insert into interface_logs(interface,num,time) values(?,?,?)');
                }*/
                
                break;

            case 'clean_interface_log':
                $redis_interface = $this->getRedis();
                $interfaces = $redis_interface->zRevRange('interfaces', 100, -1, true);

                if (is_array($interfaces) && count($interfaces) > 0) {
                    foreach ($interfaces as $key => $value) {
                        $redis_interface->delete($key);
                    }
                }
                break;
        }
    }


    /**
     * onFinish [任务完成回调]
     * @author wangzhiqiang
     * @param \swoole_server $serv
     * @param $task_id
     * @param $data
     */
    public function onFinish(\swoole_server $serv, $task_id, $data)
    {
    }


    /**
     * onWorkerError [worker错误回调]
     * @author wangzhiqiang
     * @param \swoole_server $serv
     * @param $worker_id
     * @param $worker_pid
     * @param $exit_code
     */
    public function onWorkerError(\swoole_server $serv, $worker_id, $worker_pid, $exit_code)
    {
        echo "worker abnormal exit. WorkerId=$worker_id|Pid=$worker_pid|ExitCode=$exit_code\n";
    }


    /**
     * onClose [连接关闭回调]
     * @author wangzhiqiang
     * @param $serv
     * @param $fd
     * @param $from_id
     */
    public function onClose($serv, $fd, $from_id)
    {
        //echo "Worker#{$serv->worker_pid} Client[$fd@$from_id]: fd=$fd is closed";
    }


    /**
     * onWorkerStop [worker停止回调]
     * @author wangzhiqiang
     * @param $serv
     * @param $worker_id
     */
    public function onWorkerStop($serv, $worker_id)
    {
        echo "WorkerStop[$worker_id]|pid=" . $serv->worker_pid . ".\n";
    }


    /**
     * onShutdown [server关闭回调]
     * @author wangzhiqiang
     * @param $serv
     */
    public function onShutdown($serv)
    {
        echo "Server: onShutdown\n";
    }

    public function decode($buffer)
    {
        //echo $buffer."\n";
        $length = unpack('N', $buffer)[1];
        $string = substr($buffer, -$length);
        $data = json_decode($string, true);
        return $data;
    }

    /**
     * getRedis [reids实例]
     * @author wangzhiqiang
     * @param int $id
     * @return mixed
     */
    public function getRedis($id = 0)
    {
        if(empty($this->redis[$id]) || !$this->redis[$id]->info()){
            $this->redis[$id] = new \Redis();
            $config = Config::get_redis_config();
            $res = $this->redis[$id]->connect($config['host'],$config['port']);
            $this->redis[$id]->auth($config['password']);
            $this->redis[$id]->select($id);
            if(!$res){

            }
        }

        return $this->redis[$id];
    }

    /**
     * getMysql [mysql实例]
     * @author wangzhiqiang
     * @return Mysql
     */
    public function getMysql(){
        if(empty($this->mysql) ){
            $this->mysql = new Mysql(Config::get_mysql_config());
        }
        return $this->mysql;
    }


    /**
     * pushStatistics [推送消息统计]
     * @author wangzhiqiang
     * @param $data
     * @return mixed
     */
    public function pushStatistics($data){
        $dmain = trim($data['dmain']);

        //请求总数
        //$statistics['count']++;
        $count = $this->table->incr('real_statistics', 'count');
        $this->table->incr('time_statistics', 'count');
        

        //响应总时间
        $this->table->incr('real_statistics', 'spend', $data['spend']);
        $this->table->incr('time_statistics', 'spend', $data['spend']);
        

        //响应方式
        if(in_array($data['method'], ['GET','POST','OPTIONS','HEAD','PUT','DELETE','TRACE'])){
            $method = strtolower($data['method']);
            $this->table->incr('real_statistics', $method);
            $this->table->incr('time_statistics', $method);
        }



        //响应状态
        if(in_array($data['status'], [200, 201, 202, 204, 303, 404])){
            $status = 'z'.$data['status'];
            $this->table->incr('real_statistics', $status);
            $this->table->incr('time_statistics', $status);
        }else{
            $this->table->incr('real_statistics', 'other');
            $this->table->incr('time_statistics', 'other');
        }

        
        /*$redis = $this->getRedis();
        $dmain_list = $redis->get('dmain');
        if ($dmain_list) {
            $dmain_list = explode('|', $dmain_list);
        }else{
            $dmain_list = [];
        }*/
        if (in_array($dmain, self::$dmain)) {
            //echo 'in array';
            //模块计数
            $this->table->incr($dmain, 'count');
            $this->table->incr('t_'.$dmain, 'count');

            //模块响应时间
            $this->table->incr($dmain, 'spend', $data['spend']);
            $this->table->incr('t_'.$dmain, 'spend', $data['spend']);

            //响应方式
            if(in_array($data['method'], ['GET','POST','OPTIONS','HEAD','PUT','DELETE','TRACE'])){
                $method = strtolower($data['method']);

                $this->table->incr($dmain, $method);
                $this->table->incr('t_'.$dmain, $method);
            }

            //响应状态
            if(in_array($data['status'], [200, 201, 202, 204, 303, 404])){
                $status = 'z'.$data['status'];
                
                $this->table->incr($dmain, $status);
                $this->table->incr('t_'.$dmain, $status);
            }else{
                
                $this->table->incr($dmain, 'other');
                $this->table->incr('t_'.$dmain, 'other');
            }
        }
        
        return $count;
    }


    /** 弃用
     * recordStatistics [统计记录]
     * @author wangzhiqiang
     * @param $data
     */
    public function recordStatistics($data){
        $redis = $this->getRedis();

        $statistics = $redis->get('timing_statistics');
        $statistics = $statistics ? json_decode($statistics,true) : $this->redis_arr;

        //请求总数
        $statistics['count']++;

        //响应总时间
        $statistics['spend'] = $statistics['spend'] + $data['spend'];

        //响应方式
        switch($data['method']){
            case 'GET':
                $statistics['get']++;
                break;

            case 'POST':
                $statistics['post']++;
                break;

            case 'OPTIONS':
                $statistics['options']++;
                break;

            case 'HEAD':
                $statistics['head']++;
                break;

            case 'PUT':
                $statistics['put']++;
                break;

            case 'DELETE':
                $statistics['delete']++;
                break;

            case 'TRACE':
                $statistics['trace']++;
                break;
        }



        //响应状态
        switch($data['status']){
            case 200:
                $statistics['200']++;
                break;

            case 201:
                $statistics['201']++;
                break;

            case 202:
                $statistics['202']++;
                break;

            case 204:
                $statistics['204']++;
                break;

            case 303:
                $statistics['303']++;
                break;

            case 404:
                $statistics['404']++;
                break;
            default:
                $statistics['other']++;
                break;
        }

        $new_data = json_encode($statistics);
        $redis->set('timing_statistics', $new_data);
    }
}
