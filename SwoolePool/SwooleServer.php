<?php
namespace SwoolePool;

trait SwooleServer
{
    private $pid_file; //master pid和manager pid保存目录

    private $log_file;//swoole执行输出信息

    private $server;

    private $http_config = [
        'open_cpu_affinity'  => true, //打开cpu亲和设置
        'reactor_num'        => 2,    //reactor线程数，不得大于worker_num，超8核默认设置为8
        'worker_num'         => 4,    //worker进程数；全异步服务建议设为cpu核数1-4倍，同步需要自定义调整，最大是cpu核数*1000，注意内存
        'max_request'        => 10000,//进程最大请求数
        'max_conn'           => 10000,//最大允许连接数，默认ulimit -n返回数
        'task_worker_num'    => 2,    //配置了就会启用异步task进程，需要注册onTask、onFinish回调函数
        // 'daemonize'          => false,//守护线程
        // 'log_file'           => '',   //守护进程输出文件
        'log_level'          => 2,    //错误日志等级，参考https://wiki.swoole.com/#/consts?id=日志等级
        'dispatch_mode'      => 3,    //数据包分发策略，3抢占模式会自动寻找闲置worker
        'task_ipc_mode'      => 1,    //task与work通信方式，1是unix socket
        'backlog'            => 2000, //最多有多少个连接等待
        'task_max_request'   => 10000,//task进程的最大任务数
        'buffer_output_size' => 8 * 1024 *1024,
    ];

    public function __construct()
    {
        //简单检测运行环境
        if('cli' !== php_sapi_name()){
            exit('服务只能运行在cli sapi模式下' . PHP_EOL);
        }
        if(!extension_loaded('swoole')){
            exit('请安装swoole扩展' . PHP_EOL);
        }

        $ulimit = shell_exec('ulimit -n');

        //自动适配cpu核数并设置监听地址、端口
        $this->setConfig('http', [
            'reactor_num'     => swoole_cpu_num() * 2, //reactor线程数为cpu核数的2倍
            'worker_num'      => swoole_cpu_num() * 2, //worker进程数为cpu核数的2倍
            'task_worker_num' => swoole_cpu_num() * 2, //task进程数为cpu核数的2倍
            'host'            => '127.0.0.1',
            'port'            => 8901,
            'max_conn'        => $ulimit > 10000 ? 10000 : $ulimit,//增加文件打开数适配
        ]);

        //在cli执行文件的目录下预设文件
        $this->application = 'application';
        $this->pid_file = '/var/log/swoole_pid_' . $this->http_config['port'] . '.log';
        $this->log_file = '/var/log/swoole_' . $this->http_config['port'] . '.log';

    }

    /************************************************** 设置函数 ***************************************************/

    /**
     * 设置master pid和manager pid存放文件路径及名称；绝对路径
     * @param $file_name
     */
    public final function setPidFile($file_name)
    {
        $this->pid_file = $file_name;
    }

    /**
     * 设置swoole日志存放路径；绝对路径
     * @param $file_name
     */
    public final function setLogFile($file_name)
    {
        $this->log_file = $file_name;
    }

    /**
     * 检测文件是否有写入权限
     * @param string $filename
     */
    private function checkDir($filename)
    {
        $dir = substr($filename, 0, strrpos($filename, '/'));
        if (!is_writable($dir)) {
            echo $filename . " is not writable." . PHP_EOL;
            exit;
        }
    }

    /**
     * 设置server配置
     * @param $type string 配置的类型，可以选择http、tcp
     * @param array $configs
     */
    private function setConfig($type, $configs = [])
    {
        $config_name = $type . '_config';

        $this->$config_name = array_merge($this->$config_name, $configs);
    }

    /************************************************** 自定义函数 **************************************************/

    /**
     * 在worker进程启动时触发，可覆盖重写，如加载一些配置文件
     * @param $server
     * @param $worker_id
     */
    public function customWorkerStart($server, $worker_id) { }

    /**
     * 在task任务完成后触发，可覆盖重写
     * @param $server
     * @param $task_id
     * @param $result
     */
    public function customFinish($server, $task_id, $result) { }

    /**
     * 真正处理程序
     * @param array $data 访问最基本的数据结构，如下
     *[
     *    'c' => 'class_name',//处理文件类
     *    'a' => 'function_name',//处理文件类的方法名
     *    'params' => array(),//处理方法的参数
     *]
     * @return array
     */
    public final function dispatch($data)
    {
        $abstract = $this->application . DIRECTORY_SEPARATOR . $data['c'];//用斜杠/的方式连接，用反斜杠\会有问题
        $class_name = '\\' . $this->application . '\\' . ucfirst($data['c']);//自动加载用反斜杠
        try {
            $container = Container::instance();
            if (!$container->hasConcrete($abstract)) {
                $container->bind($abstract, $class_name);
            }
            $object = $container->make($abstract);
            $args = $container->buildParameter($object, $data['a'], $data['params']);

            $result = [
                'code' => 0,
                'data' => call_user_func_array([$object, $data['a']], $args)
            ];
        } catch (\Exception $e) {
            $result = [
                'code' => $e->getCode(),
                'message' => $e->getMessage()
            ];

            Logger::error($e->getMessage());
        }

        return $result;
    }

    /************************************************ Swoole回调函数 **************************************************/

    /**
     * 与workerStart并发进行
     * @param $server
     */
    public final function onStart($server) {
        //把master pid与manager pid写进文件，方便手动关闭与重启
        file_put_contents($this->pid_file, json_encode([
            'master_pid' => $server->master_pid,
            'manager_pid' => $server->manager_pid,
        ]));
	
	    Container::Instance();
        swoole_set_process_name("swoole" . $this->http_config['port']);//给进程起别名，方便监听服务
    }

    /**
     * worker进程启动时触发
     * @param $server
     * @param $worker_id
     */
    public final function onWorkerStart($server, $worker_id)
    {
        $this->customWorkerStart($server, $worker_id);
    }

    /**
     * task任务处理
     * @param $server
     * @param $task_id
     * @param $worker_id
     * @param $data
     * @return array
     */
    public final function onTask($server, $task_id, $worker_id, $data) {
        $result = $this->custom($data);
        return $result;
    }

    /**
     * 当task任务完成时触发，告诉worker task处理完成
     * @param $server
     * @param $task_id
     * @param $result
     */
    public final function onFinish($server, $task_id, $result) {
        $this->customFinish($server, $task_id, $result);
    }

    /**http请求回调
     * @param $request
     * @param $response
     */
    public final function onRequest($request, $response)
    {
        //解决部分浏览器二次访问的问题
        if ($request->server['path_info'] == '/favicon.ico' || $request->server['request_uri'] == '/favicon.ico') {
            $response->end();
            return;
        }
        //简单处理跨域OPTIONS返回
        if ($request->server['request_method'] == 'OPTIONS') {
            $response->header('Access-Control-Allow-Origin', '*');
            $response->header('Access-Control-Allow-Methods', 'POST, OPTIONS');
            $response->header('Access-Control-Allow-Headers', 'Authorization, User-Agent, Keep-Alive, Content-Type, X-Requested-With');
            $response->status(http_response_code());
            $response->end();
            return;
        }

        $response->header("Content-Type", "application/json; charset=utf-8");

        if ($request->server['request_method'] !== 'POST') {
            $response->end(json_encode([
                'code' => -1,
                'message' => 'Invalid request method, only accept POST.'
            ]));
            return;
        }

        $data = $request->post ? $request->post : json_decode($request->rawContent(), true);

        //将$fd赋为null，区别于tcp，并把$request->server赋给新的一个变量
        global $g_c;
        $g_c['swoole']['fd'] = null;
        $g_c['swoole']['http_server'] = $request->server;

        if (isset($data['sync']) && $data['sync'] === false) {
            //异步处理，注意在onTask回调方法里不能使用协程
            $this->server->task($data);//会触发onFinish回调
            $response->end(json_encode([
                'code' => 0,
                'message' => '任务投递成功'
            ]));
        } else {
            //同步处理，只有php7+及swoole4.2+版本才能使用协程
            $response->end(EncodeJson($this->custom($data)));
        }
    }

    /*********************************************** swoole控制函数 ************************************************/

    /**
     * swoole server控制函数
     * 可以平滑重启及关闭，避免使用kill的粗暴方式终止进程而导致数据处理不完整
     */
    public function run()
    {
        //获取cli模式下输入的参数，$argc是参数数量，$argv是获取到的参数
        global $argv, $argc;

        $command = isset($argv[1]) ? $argv[1] : 'start';
        $option  = isset($argv[2]) ? $argv[2] : null;

        switch ($command) {
            case 'start':
                if ($option === '-d') {
                    $this->setConfig('http', [
                        'daemonize' => 'true',
                        'log_file'  => $this->log_file
                    ]);
                    //当后台运行时才对pid文件、log文件的目录进行权限检测
                    $this->checkDir($this->pid_file);
                    $this->checkDir($this->log_file);
                }
                $this->start();
                break;
            case 'reload':
                if(file_exists($this->pid_file)) {
                    $content = file_get_contents($this->pid_file);
                    $pids    = json_decode($content, true);

                    $flag = posix_kill($pids['manager_pid'], SIGUSR1);
                    if ($flag) {
                        echo date('Y-m-d H:i:s') . ' Swoole server has reloaded.' . PHP_EOL;
                    }
                }
                break;
            case 'stop':
                if(file_exists($this->pid_file)) {
                    $content = file_get_contents($this->pid_file);
                    $pids    = json_decode($content, true);
                    $flag = posix_kill($pids['master_pid'], SIGTERM);
                    if ($flag) {//真正关闭了服务才删除pid文件
                        @unlink($this->pid_file);
                        echo date('Y-m-d H:i:s') . ' Swoole server has stopped.' . PHP_EOL;
                    }
                }
                break;
            default:
                $this->start();
                break;
        }
    }

    private function start()
    {
        $this->server = new \Swoole\Http\Server($this->http_config['host'], $this->http_config['port'], SWOOLE_PROCESS, SWOOLE_SOCK_TCP);
        $this->server->set($this->http_config);
        $this->server->on('start',       [$this, 'onStart']);
        $this->server->on('WorkerStart', [$this, 'onWorkerStart']);
        $this->server->on('request',     [$this, 'onRequest']);
        $this->server->on('task',        [$this, 'onTask']);
        $this->server->on('finish',      [$this, 'onFinish']);
        $this->server->start();
    }
}
