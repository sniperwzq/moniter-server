<?php
namespace conf;

/**
 * Class Config
 * @author wangzhiqiang
 * @package conf
 */
class Config{
    /** 弃用
     * get_db_config [mysql配置]
     * @author wangzhiqiang
     * @return array
     */
    public static function get_db_config()
    {
        return [
            'host' => 'localhost',
            'db' => '',
            'user' => 'root',
            'password' => '051228sn',
            'port' => 33066
        ];
    }

    /**
     * get_redis_config [redis配置]
     * @author wangzhiqiang
     * @return array
     */
    public static function get_redis_config()
    {
        return [
            'host' => '127.0.0.1',
            'port' => 6379,
            'password' => 'quB1BY3njv0eq1N4e3d9Q570V4mYHldDeb7BFw92'
        ];
    }

    /**
     * get_swoole_config [udp server 配置]
     * @author wangzhiqiang
     * @return array
     */
    public static function get_swoole_config()
    {
        return [
            'worker_num' => 8,
            //包长检测
            /*'open_length_check' => true,
            'package_length_type' => 'N',
            'package_length_offset' => 0,
            'package_body_start' => 4,
            'package_max_length' => 8192,*/
            'open_eof_check' => true,
            'package_eof' => "\r\n",
            // 'task_ipc_mode' => 2,
            'task_worker_num' => 2,
            //'log_file' => 'log/swoole.log',
            //'heartbeat_check_interval' => 300,
            //'heartbeat_idle_time' => 300,
            'daemonize' => true
        ];
    }

    /**
     * get_webserver_config [websocket server 配置]
     * @author wangzhiqiang
     * @return array
     */
    public static function get_webserver_config(){
        return [
            'worker_num' => 1,
            'daemonize' => true,
            'max_request' => 1000,
            //'log_file' => 'log/swoole.log',
            'dispatch_mode' => 1,
        ];
    }

    /**
     * get_mysql_config [mysql配置]
     * @author wangzhiqiang
     * @return array
     */
    public static function get_mysql_config()
    {
        return [
            'master' => [
                'host' => '192.168.1.49',
                'port' => 33066,
                'db'   => 'statistics',
                'user' => 'wangzq',
                'pass' => 'vdl9R3vVY2g1Bgl7'
            ],
            'slave' => [
                'host' => '192.168.1.49',
                'port' => 33066,
                'db'   => 'statistics',
                'user' => 'wangzq',
                'pass' => 'vdl9R3vVY2g1Bgl7'
            ]
        ];
    }
}