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

    /**
     * @var tcp 端口
     */
    protected $serverPort;

    /**
     * @var 服务配置
     */
    protected $serConfig;

    protected static $_masterPid;

    protected static $daemonize = false;

    protected static $pidFile = '/var/run/php_crond.pid';

    public function __construct()
    {
        set_error_handler([$this, 'displayErrorHandler'], E_WARNING);
    }

    /**
     * 启动服务
     * @return bool
     */
    public function start()
    {

        if (is_file(self::$pidFile)) {
            echo "Service already running..." . PHP_EOL;
            return false;
        }

        echo "Service starting..." . PHP_EOL;
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

    /**
     * 停止服务
     */
    public function stop() {
        if (!file_exists(self::$pidFile)) {
            echo "Service not running" . PHP_EOL;
            exit;
        }
        $pids = explode(',', file_get_contents(self::$pidFile));
        $master_pid = $pids[0];
        @unlink(self::$pidFile);
        echo "Service is stopping..." . PHP_EOL;
        // Send stop signal to master process.
        info("masterPid:" . $master_pid, DEBUG);
        $master_pid && posix_kill($master_pid, SIGTERM);
        // Timeout.
        $timeout = 5;
        $start_time = time();
        // Check master process is still alive?
        while (1) {
            $master_is_alive = self::$_masterPid && posix_kill(self::$_masterPid, 0);
            if ($master_is_alive) {
                // 超时?
                if (time() - $start_time >= $timeout) {
                    echo "Service stop fail  [fail]" . PHP_EOL;
                    exit;
                }
                // Waiting amoment.
                usleep(10000);
                continue;
            }
            // Stop success.
            echo "Service stop success  [ok]" . PHP_EOL;
            break;
        }
        exit(0);
    }

    /**
     * 重新加载配置
     */
    public function reload() {
        if (!file_exists(self::$pidFile)) {
            echo "Service not running" . PHP_EOL;
            exit;
        }
        $pids = explode(',', file_get_contents(self::$pidFile));
        // Get master process PID.
        $manager_pid = $pids[1];
        posix_kill($manager_pid, SIGUSR1);
        echo "Service reload" . PHP_EOL;
    }

    public function createServer()
    {
        $this->serConfig = parse_ini_file(CONFIG_FILE, true);
        self::$server = new swoole_http_server($this->serConfig['server']['host'], $this->serConfig['server']['port'], SWOOLE_PROCESS);
        //解析配置文件
        self::$server->servConfig = $this->serConfig;
        //启用一个tcp端口
        $this->serverPort = self::$server->listen(self::$server->servConfig['server']['host'], self::$server->servConfig['serverPort']['port'], SWOOLE_SOCK_TCP);

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
            //todo  增加回调，返回值
            $unpackData = json_decode($data, true);
            $flags = isset($unpackData['flags']) ? $unpackData['flags'] : null;
            switch ($flags) {
                case 'delete':
                    $jobData = $unpackData['data'];
                    $server->shareTable->del($jobData['id']);
                    break;
                default:
                    $jobData = $unpackData['data'];
                    if ( isset($unpackData['id']) ) {  # update
                        $jobId = $unpackData['id'];
                    } else {  # insert
                        $jobId = $jobData['id'];
                    }

                    unset($jobData['id']);
                    $server->shareTable->set($jobId, $jobData);
            }

            info('socket receive:' . $data, INFO);
        });

        $this->serverPort->on('close', function (swoole_server $server, $fd, $fromId)
        {
            info('socket close', INFO);
        });

        if ($this->serConfig['server']['daemonize']) self::$daemonize = true;
        self::$server->set([
            'worker_num' => 1,
            'daemonize'  => self::$daemonize,
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
        info('Service start ok', DEBUG);
        self::$_masterPid = $server->master_pid;
        file_put_contents(self::$pidFile, self::$_masterPid);
        file_put_contents(self::$pidFile, ',' . $server->manager_pid, FILE_APPEND);
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

        $mysqli = new mysqli('127.0.0.1', 'crontab', '123456', 'test');
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
            if ($server->shareTable) {
                foreach ($server->shareTable as $row) {
                    $timestamp = $crontab->parse($row['schedule'], time());
                    $job->set($timestamp, $row);
                }
            }
        });

        swoole_timer_tick(1000, function() use ($workerId, $job) {
            $jobs = $job->get();

            if ($jobs) {
                foreach ($jobs as $job) {
                    $process = new swoole_process(function(swoole_process $worker) use ($job){
//                            echo date('[Y-m-d H:i:s]', $job['starttime']) . 'perform Job:' . $job['name'] . PHP_EOL;
                        info($job['name'] . ' perform Job,' . $job['command'].' begin in : ' . date('Y-m-d H:i:s', $job['starttime']) , INFO);

                        # 目前不支持多条命令语句， 只支持单条语句 e.g: /usr/bin/php  /data/test/info.php
                        $jobData = preg_split('/\s+/i', $job['command']);
                        $execFile = array_shift($jobData);
                        try {
                            $worker->exec($execFile, $jobData);
                        } catch (Exception $e) {
                            info('Warning:' . $e->getMessage(), WARN);
                        }
                        $worker->exit(1);
                    }, false);
                    $pid = $process->start();

                    # 事件监听，输出重定向
//                    swoole_event_add($process->pipe, function($pipe) use ($process) {
//                        $data = $process->read();
//                        error_log($data, 3, '/tmp/debug.log');
//                    });
                }

                while ($ret = swoole_process::wait(false)) {
                    echo "PID={$ret['pid']}\n";
                }
            }
//                info("[worker#{$workerId}]- timer", DEBUG);
        });

    }

    public function onManagerStart(swoole_server $server)
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

    /**
     * warning 错误监听
     * @param $error
     * @param $error_string
     * @param $filename
     * @param $line
     * @param $symbols
     */
    public function displayErrorHandler($errno, $errstr, $errfile, $errline, array $errcontext) {
        if (!(error_reporting() & $errno)) {
            // This error code is not included in error_reporting
            return;
        }

        throw new ErrorException($errstr, 0, $errno, $errfile, $errline);
    }


}