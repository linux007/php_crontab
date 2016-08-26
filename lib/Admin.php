<?php
/**
 * Created by PhpStorm.
 * User: yuyc
 * Date: 2016/8/25
 * Time: 13:54
 */
class Admin {

    protected $request;
    protected $response;

    public function __construct(swoole_server $server, $workerId)
    {
        $this->httpServer = $server;
        $this->wokerId = $workerId;

    }

    public function onRequest(swoole_http_request $request, swoole_http_response $response) {
//        info('Admin onRequest', DEBUG);
        $this->request  = $request;
        $this->response = $response;

        $uri    = trim($request->server['request_uri'], ' /');
        $uriArr = explode('/', $uri);
        $type   = array_shift($uriArr);
        switch ($type) {
            case 'admin':
                $this->admin(implode('/', $uriArr));
                break;
            case 'api':
                try  {
                    $this->api(implode('/', $uriArr));
                } catch (RuntimeException $e) {
                    info($e->getMessage(), WARN);
                }

                break;
            case 'public':
                $this->assets(implode('/', $uriArr));
                break;
            default:
                $response->status(404);
                $response->end('not found');
        }
        $this->request  = null;
        $this->response = null;

        return true;
    }

    protected function api($uri) {
        info($uri, DEBUG);
        $mysqli = new mysqli('127.0.0.1', 'root', 'cheyun@20140616', 'test');
        $mysqli->set_charset('utf8');
        if ($mysqli->connect_error) {
            throw new RuntimeException('Mysql Connect Error:(' . mysqli_connect_errno() . ') '. mysqli_connect_error());
        }

        switch ($uri) {
            case 'job/save':
                info(var_export($this->request->post, true), DEBUG);
                extract($this->request->post);
                $createAt = $updateAt = time();
                $hostname = '127.0.0.1';

                if (isset($id) && $id) {
                    $sql = 'UPDATE crontab SET name=?,command=?,schedule=?,hostname=?,updateAt=? WHERE id=?';
                } else {
                    $sql = "INSERT INTO crontab(name,command,schedule,hostname,createAt,updateAt) VALUES (?,?,?,?,?,?)";
                }

                $stmt = $mysqli->prepare($sql);
                if (!$stmt) {
                    throw new RuntimeException('SQL syntax:' . mysqli_error($mysqli));
                }

                info($sql, DEBUG);
                if (isset($id) && $id) {
                    $bind_param = $stmt->bind_param('ssssii', $name, $command, $schedule, $hostname, $createAt, $id);
                } else {
                    $bind_param = $stmt->bind_param('ssssii', $name, $command, $schedule, $hostname, $createAt, $updateAt);
                }

                if (!$bind_param) {
                    info('Binding parameters failed:' . $stmt->error, WARN);
                }
                if (!$stmt->execute()) {
                    info('Execute failed:' . $stmt->error, WARN);
                }
                $stmt->close();
                break;

            default:
                $this->response->status(404);
                $this->response->end('not found');
                break;
        }

        $mysqli->close();
        return true;
    }

    protected function admin($uri) {
        $mysqli = new mysqli('127.0.0.1', 'root', 'cheyun@20140616', 'test');
        $mysqli->set_charset('utf8');
        if (empty($uri)) {
            $uri = 'index';
        }
        $tpl = __DIR__ . '/../admin/' . $uri . '.php';

        if ( ! file_exists($tpl) ) {
            $this->response->status(404);
            $this->response->end('not found');
            return false;
        }
        $dataList = [];

        ob_start();
        $sql = 'SELECT * FROM crontab';
        $result = $mysqli->query($sql);
        if ($result) {
            while ($row = $result->fetch_array(MYSQLI_ASSOC)) {
                $dataList[] = $row;
            }
        }
        include $tpl;
        $content = ob_get_clean();

        $mysqli->close();

        $this->response->end($content);
    }

    protected function assets($uri) {
        $uri  = str_replace(['\\', '../'], ['/', '/'], $uri);
        $rPos = strrpos($uri, '.');
        if (false === $rPos)
        {
            # 没有任何后缀
            $this->response->status(404);
            $this->response->end('not found');
            return;
        }

        $type = strtolower(substr($uri, $rPos + 1));

        $header = [
            'js'    => 'application/x-javascript',
            'css'   => 'text/css',
            'png'   => 'image/png',
            'jpg'   => 'image/jpeg',
            'jpeg'  => 'image/jpeg',
            'gif'   => 'image/gif',
            'json'  => 'application/json',
            'svg'   => 'image/svg+xml',
            'woff'  => 'application/font-woff',
            'woff2' => 'application/font-woff2',
            'ttf'   => 'application/x-font-ttf',
            'eot'   => 'application/vnd.ms-fontobject',
        ];

        if (isset($header[$type])) {
            $this->response->header('Content-Type', $header[$type]);
        }

        $file = __DIR__ .'/../public/'. $uri;
        info($file, DEBUG);
        if (is_file($file)) {
            # 设置缓存头信息
            $time = 86400;
            $this->response->header('Cache-Control', 'max-age='. $time);
            $this->response->header('Last-Modified', date('D, d M Y H:i:s \G\M\T', filemtime($file)));
            $this->response->header('Expires'      , date('D, d M Y H:i:s \G\M\T', time() + $time));
            $this->response->header('Pragma'       , 'cache');

            $this->response->end(file_get_contents($file));
        } else {
            $this->response->status(404);
            $this->response->end('assets not found');
        }
    }
}