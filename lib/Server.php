<?php

/**
 * Created by PhpStorm.
 * User: yuyc
 * Date: 2016/8/19
 * Time: 14:28
 */
include __DIR__ . '/CrontabParse.php';
include __DIR__ . '/Job.php';
include __DIR__ . '/Admin.php';

class Server
{

    /**
     * @var swoole_server
     */
    public static $server;
    protected $serverPort;

    public function __construct()
    {

    }

    public function start()
    {

        $table = new swoole_table(1024);
        $table->column('name', swoole_table::TYPE_STRING, 128);
        $table->column('command', swoole_table::TYPE_STRING, 256);
        $table->column('schedule', swoole_table::TYPE_STRING, 64);
        $table->column('hostname', swoole_table::TYPE_STRING, 64);
        $table->create();

        $this->createServer();
        // 事件绑定
        $this->bind();
        self::$server->shareTable = $table;

        self::$server->start();
    }

    public function createServer()
    {
        $host = '0.0.0.0';
        $port = 8000;

        self::$server = new swoole_http_server($host, $port, SWOOLE_BASE);

        //启用一个tcp端口
        $this->serverPort = self::$server->listen($host, 8100, SWOOLE_SOCK_TCP);

        $config = [
            'open_eof_check' => true,
            'open_eof_split' => false,
            'package_eof'    => "\n",
        ];
        $this->serverPort->set($config);

        $this->serverPort->on('connect', function (swoole_server $server, $fd, $fromId)
        {
            info('socket connect', INFO);
        });

        $this->serverPort->on('receive', function(swoole_server $server, $fd, $fromId, $data)
        {
//            print_r($server->shareTable->get(1));
            $unpackData = json_decode($data, true);
            $server->shareTable->set(4, $unpackData);
            info('socket receive:' . $data, INFO);
        });

        $this->serverPort->on('close', function (swoole_server $server, $fd, $fromId)
        {
            info('socket close', INFO);
        });

        self::$server->set([
            'worker_num' => 1,
            'daemonize'  => false,
        ]);
    }

    protected function bind()
    {
//        self::$server->on('Start', [$this, 'onStart']);
//        self::$server->on('Shutdown', [$this, 'onShutdown']);
//        self::$server->on('WorkerStart', [$this, 'onWorkerStart']);
//        self::$server->on('ManagerStart', [$this, 'onManagerStart']);
//        self::$server->on('ManagerStop', [$this, 'onManagerStop']);

        self::$server->on('Start', [$this, 'onStart']);
        self::$server->on('Shutdown', [$this, 'onShutdown']);

        self::$server->on('WorkerStart', [$this, 'onWorkerStart']);
        self::$server->on('ManagerStart', [$this, 'onManagerStart']);
        self::$server->on('ManagerStop', [$this, 'onManagerStop']);

        self::$server->on('Request', [$this, 'onManagerRequest']);

        return $this;
    }

    public function onStart(swoole_server $server)
    {
        info('server start', DEBUG);
    }

    public function onShutdown(swoole_server $server)
    {
        info('server shutdown', DEBUG);
    }

    public function onWorkerStart(swoole_server $server, $workerId)
    {
        global $argv;
        $dataList = [];
        self::setProcessName("php ". implode(' ', $argv) ." [worker#{$workerId}]");
        info("worker #$workerId start", DEBUG);

//        swoole_timer_tick(1000, function() use ($workerId) {
//            info("[worker#{$workerId}]- timer", DEBUG);
//        });
        $crontab = new CrontabParse();
        $job = Job::factory();
        $this->manager = new Admin($server, $workerId);

        $mysqli = new mysqli('127.0.0.1', 'root', '111111', 'test');
        $mysqli->set_charset('utf8');
        if ($mysqli->connect_error) {
            throw new RuntimeException('Mysql Connect Error:(' . mysqli_connect_errno() . ') '. mysqli_connect_error());
        }

        $sql = 'SELECT id,name,command,schedule,hostname FROM crontab';
        $result = $mysqli->query($sql);
        if ($result) {
            while ($row = $result->fetch_array(MYSQLI_ASSOC)) {
                $server->shareTable->set($row['id'], [
                    'name' => $row['name'],
                    'command' => $row['command'],
                    'schedule' => $row['schedule'],
                    'hostname' => $row['hostname']
                ]);
                $timestamp = $crontab->parse($row['schedule'], time());
                $job->set($timestamp, $row);
            }
        }

        // 做任务检测
        swoole_timer_tick(1000 * 60, function() use ($crontab, $job, $server) {
            // todo  周期性配置任务
            if ($server->shareTable) {
                foreach ($server->shareTable as $row) {
                    $timestamp = $crontab->parse($row['schedule'], time());
                    $job->set($timestamp, $row);
                }
            }
        });

        swoole_timer_tick(1000, function() use ($workerId, $job) {
            // todo  任务执行
            $jobs = $job->get();

            if ($jobs) {
                foreach ($jobs as $job) {
                    $process = new swoole_process(function(swoole_process $worker) use ($job){
//                            echo date('[Y-m-d H:i:s]', $job['starttime']) . 'perform Job:' . $job['name'] . PHP_EOL;
                        info($job['name'] . ' perform Job, begin in : ' . date('Y-m-d H:i:s', $job['starttime']) , DEBUG);
                        $worker->exit(1);
                    }, false);
                    $pid = $process->start();
                }

                while ($ret = swoole_process::wait(false)) {
                    echo "PID={$ret['pid']}\n";
                }
            }
//                info("[worker#{$workerId}]- timer", DEBUG);
        });

    }

    public function onManagerStart(swoole_server $server, $workerId)
    {
        global $argv;
        self::setProcessName("php ". implode(' ', $argv) ." [manager]");
        info('manager start', DEBUG);
    }

    public function onManagerStop(swoole_server $server)
    {
        info('manager stop', DEBUG);
    }

    public function onManagerRequest(swoole_http_request $request, swoole_http_response $response)
    {
        $this->manager->onRequest($request, $response);
    }

    /**
     * 设置进程名称
     * @param $name
     */
    public static function setProcessName($name)
    {
        if (function_exists('cli_set_process_title')) {
            @cli_set_process_title($name);
        } else {
            if (function_exists('swoole_set_process_name')) {
                @swoole_set_process_name($name);
            } else {
                trigger_error(__METHOD__ . ' failed. require cli_set_process_title or swoole_set_process_name.');
            }
        }
    }


}