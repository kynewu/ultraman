<?php 

/**
 * App 基础类
 *
 * @package   ultraman\Foundation
 * @copyright Copyright (c) 2017, ultraman
 * @var 基础服务入口文件
 */


namespace ultraman;

use ultraman\Foundation\DI;
use League\CLImate\CLImate;

class App
{
    /**
     * 构造函数 入口加载配置文件进行注入
     *
     * @param db    数据库配置
     * @param main  服务配置项
     * @param api   外部接口配置项
     */

    protected $climate;

    /**
     *  入口文件加载
     */

    public function __construct($config = [])
    {
        require dirname(dirname(__FILE__)."/../").'/vendor/autoload.php';
        if (count($config) == 0) {
            return true;
        }
        $this->injection($config);
    }

    /**
     *  依赖文件注入
     */
    
    public function injection($params)
    {
        foreach ($params as $key => $class) {
            $f = pathinfo($class, PATHINFO_EXTENSION);
            if ($f === 'php') {
                $conf = @include $class;
                if ($conf == "") {
                    continue;
                }
            }
            if ($f === 'ini') {
                $conf = @parse_ini_file($class, true);
                if ($conf == "") {
                    continue;
                }
            }
            DI::set($key, $conf);
        }
        return true;
    }

    /**
     *  服务器启动
     */

    public function ServiceRun($params)
    {
        $this->climate = new CLImate();
        $service = strtolower(trim(isset($params[1])?$params[1]:''));
        $command = strtolower(trim(isset($params[2])?$params[2]:''));
        $env = strtolower(trim(isset($params[3])?$params[3]:''));
        $port = strtolower(trim(isset($params[4])?$params[4]:''));
        \ultraman\Foundation\DI::set('port', $port);
        
        if (($env == '') || ($service != '-h' && $service!='-t')) {
            return true;
        }

        $this->configure($env, $command);
        if ($service == '-h') {
            if ($command == 'start') {
                $app = new \ultraman\Http\HttpYafServer();
            }
            if ($command == 'reload') {
                $this->reloadSwoole($service, $env);
            }
        }
        if ($service == '-t') {
            if ($command == 'start') {
                $app = new \ultraman\Tcp\SwooleServer($params);
            }

            if ($command == 'reload') {
                $this->reloadSwoole($service, $env);
            }
        }
        
        return true;
    }
    
    /**
     *  服务重启
     */


    private function reloadSwoole($service, $env)
    {
        $os = strtoupper(substr(PHP_OS, 0, 3));
        if ($os == 'DAR') {
            $climate = new climate();
            $climate->error('reload 只支持linux电脑 mac 直接强杀');
            exit(0);
        }

        $config = DI::get('main');
        $class = dirname(dirname(dirname(dirname(__FILE__)))).'/env/'.$env.'/main.ini';
        $config = @parse_ini_file($class, true);
        
        $name = $config['common']['application.service_name'];
        if ($name == '') {
            return true;
        }

        if ($service === '-t') {
            $name = $name.'tcp';
            $pid = exec('pidof'.' '.$name);
            if ($pid != '') {
                exec("kill -TERM ".$pid);
                sleep(1);
                $app = new \ultraman\Tcp\SwooleServer();
            }
        } elseif ($service === '-h') {
            $pid = exec('pidof'.' '.$name);
            if ($pid !='') {
                exec("kill -USR1 ".$pid);
            } else {
                $app = new \ultraman\Http\HttpYafServer();
            }
        }
        exit;
    }
    
        

    /**
     *  配置文件环境加载
     */

    private function configure($env, $command)
    {
        $config = dirname(dirname(dirname(dirname(__FILE__))));
        $conf = dir($config.'/conf');
        $env = dir($config.'/env/'.$env);
        
        while (false != ($item = $conf->read())) {
            if ($item == '.' || $item == '..') {
                continue;
            }
            unlink($conf->path.'/'.$item);
        }
   
        while (false != ($item = $env->read())) {
            if ($item == '.' || $item == '..') {
                continue;
            }
            copy($env->path.'/'.$item, $conf->path.'/'.$item);
            
            $houzhui = substr(strrchr($item, '.'), 1);
            $keys = basename($item, ".".$houzhui);
            $params[$keys] = $conf->path.'/'.$item;
        }
        //加载配置文件
        $this->injection($params);
        return true;
    }
}
