<?php
namespace core;

use conf\Config;

/**
 * Class Webserver
 * @author wangzhiqiang
 * @package core
 */
class Webserver{
    private $redis;
    private $derv;
    private $redis_arr = [
        'count'=>0,
        'spend'=>0,
        'get'=>0,
        'post'=>0,
        'options'=>0,
        'head'=>0,
        'put'=>0,
        'delete'=>0,
        'trace'=>0,
        'z200'=>0,
        'z201'=>0,
        'z202'=>0,
        'z204'=>0,
        'z303'=>0,
        'z404'=>0,
        'other'=>0
    ];

    public function run($ip, $port = 9601){
        $this->serv = $serv = new \swoole_websocket_server($ip, $port);
        //开进程事件监听的方式不可用
        /*$process = new \swoole_process(function(\swoole_process $worker) use($serv){
            \swoole_timer_tick(1000, function () {
                //$redis = $this->getRedis();
                //$num = $redis->get(real_count_all);

                $data = [
                    'num' => 3,
                    'time' => date('H:i:s')
                ];
                $cmd = json_encode($data);
                echo $cmd . "\n";

                $conn_list = $this->serv->connection_list();
                if (!empty($conn_list)) {
                    foreach ($conn_list as $fd) {
                        $this->serv->push($fd, $cmd);
                    }
                }
            });
        }, true);
        $pid = $process->start();*/

        $this->serv->set(Config::get_webserver_config());

        $this->serv->on('start',function(){
            \swoole_timer_tick(1000, function () {
                $redis = $this->getRedis();
                $statistics = $redis->get('real_statistics');
                $statistics = $statistics ? json_decode($statistics, true) : ['real_statistics'=>$this->redis_arr];


                $statistics['time'] = date('H:i:s');
                $cmd = json_encode($statistics);
                //初始化统计
                //$redis->set('real_statistics', json_encode($this->redis_arr));

                //echo $cmd . "\n";

                $start_fd = 0;
                while(true){
                    $conn_list = $this->serv->connection_list($start_fd,10);
                    if($conn_list === false || count($conn_list) == 0)break;
                    $start_fd = end($conn_list);
                    if (!empty($conn_list)) {
                        foreach ($conn_list as $fd) {
                            //echo $cmd;
                            $this->serv->push($fd, $cmd);
                        }
                    }
                }


            });
        });

        $this->serv->on('open',function(\swoole_websocket_server $serv, $request){});

        $this->serv->on('message', function(\swoole_websocket_server $serv, $frame){});

        $this->serv->on('close', function($serv, $fd){});

        $this->serv->on('workerStart', function($serv, $worker_id){});

        $this->serv->start();
    }


    /**
     * getRedis [redis实例]
     * @author wangzhiqiang
     * @return \Redis
     */
    private function getRedis()
    {
        if(empty($this->redis) || !$this->redis->info()){
            $this->redis = new \Redis();
            $config = Config::get_redis_config();
            $res = $this->redis->connect($config['host'],$config['port']);
            $this->redis->auth($config['password']);
            if(!$res){

            }
        }
        return $this->redis;
    }
}