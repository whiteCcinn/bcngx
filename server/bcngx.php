<?php
namespace bcngx;

use bcngx\swoole\HttpServer\HttpServer;
use bcngx\swoole\Memory\BcngxBuffer;
use bcngx\swoole\Memory\BcngxLock;
use bcngx\swoole\Memory\BcngxSerialize;

/**
 * Class bcngx
 */
class bcngx
{
  /**
   * swoole_http_server object
   *
   * @var null
   */
  public static $HttpServer = null;

  /**
   * HttpServer Config File
   *
   * @var array
   */
  private $HttpServerConfig = [];

  private $BcngxConfig = [];

  private $ServerConfig = [];

  private $WebConfig = [];

  private $response = null;

  private static $pidFile = null;

  /**
   * Access Index File
   *
   * @var string
   */
  private $AccessIndex = 'index.php';

  public function __construct()
  {
    if (isset($argv[1]))
    {
      self::$pidFile = $argv[1];
    }

    if (self::$pidFile == null)
    {
      self::$pidFile = dirname(__DIR__) . DIRECTORY_SEPARATOR . 'pid' . DIRECTORY_SEPARATOR . 'bcngx.pid';
    }

  }

  private function _setPidFile()
  {

  }

  /**
   * web server access log
   */
  function access_log()
  {
    $args = func_get_args();
    if (self::$HttpServer)
    {
      self::$HttpServer->task($args);
    } else
    {
      throw new \Exception('服务创建失败');
    }
    unset($args);
  }


  /**
   * Fatal Error的捕获
   *
   * @codeCoverageIgnore
   */
  public function handleFatal()
  {
    $error = error_get_last();
    if (!isset($error['type'])) return;
    switch ($error['type'])
    {
      case E_ERROR :
      case E_PARSE :
      case E_DEPRECATED:
      case E_CORE_ERROR :
      case E_COMPILE_ERROR :
        break;
      default:
        return;
    }
    $message = $error['message'];
    $file    = $error['file'];
    $line    = $error['line'];
    $log     = "\n异常提示：$message ($file:$line)\nStack trace:\n";
    $trace   = debug_backtrace(1);


    foreach ($trace as $i => $t)
    {
      if (!isset($t['file']))
      {
        $t['file'] = 'unknown';
      }
      if (!isset($t['line']))
      {
        $t['line'] = 0;
      }
      if (!isset($t['function']))
      {
        $t['function'] = 'unknown';
      }
      $log .= "#$i {$t['file']}({$t['line']}): ";
      if (isset($t['object']) && is_object($t['object']))
      {
        $log .= get_class($t['object']) . '->';
      }
      $log .= "{$t['function']}()\n";
    }
    if (isset($_SERVER['REQUEST_URI']))
    {
      $log .= '[QUERY] ' . $_SERVER['REQUEST_URI'];
    }
    $this->access_log($log);
    if ($this->response)
    {
      $this->response->status(500);
      $this->response->end('程序异常');
    }

    unset($this->response);
  }

  /**
   * 扫描WebConf的配置文件
   *
   * @throws \Exception
   */
  private function _scanWebConf()
  {
    if (!is_dir($WebConfDir = dirname(__DIR__) . DIRECTORY_SEPARATOR . 'conf.d'))
    {
      throw new \Exception(__FUNCTION__ . ' : ' . '不存在 ' . $WebConfDir);
    }
    $handler = opendir($WebConfDir);

    while (($file = readdir($handler)) !== false)
    {
      if (!in_array($file, ['.', '..']))
      {
        $content = require_once $WebConfDir . DIRECTORY_SEPARATOR . $file;

        $this->WebConfig[ md5($content['server_name'] . ':' . $content['listen']) ] = $content;
      }

    }
    closedir($handler);
    unset($content, $handler);
  }

  private function _require_file()
  {

    require_once(implode(DIRECTORY_SEPARATOR, [dirname(__DIR__), 'swoole', 'Memory', 'BcngxBuffer.php']));

    $buffer = BcngxBuffer::getInstance();

    $flag = $buffer->set('ROOT_PATH', dirname(__DIR__));
    if (!$flag)
    {
      throw new \Exception('Buffer set Root Path Error');
    }
    $RootPath = $buffer->get('ROOT_PATH');

    $this->HttpServerConfig = require_once(implode(DIRECTORY_SEPARATOR, [$RootPath, 'swoole', 'HttpServer', 'Conf', 'HttpServerConf.php']));
    $this->BcngxConfig      = require_once(implode(DIRECTORY_SEPARATOR, [$RootPath, 'conf', 'bcngx.conf.php']));
    $this->ServerConfig     = array_merge($this->HttpServerConfig, $this->BcngxConfig);

    spl_autoload_register([$this, '_autoLoad']);
  }

  /**
   * 自动加载
   */
  private function _autoLoad($className)
  {
    $prefix       = __NAMESPACE__ . '\\';
    $PrefixLength = strlen($prefix);

    $file = '';
    if (0 === strpos($className, $prefix))
    {
      $file = explode('\\', substr($className, $PrefixLength));
      $file = implode(DIRECTORY_SEPARATOR, $file) . '.php';
    }

    $buffer   = BcngxBuffer::getInstance();
    $RootPath = $buffer->get('ROOT_PATH');
    $path     = $RootPath . DIRECTORY_SEPARATOR . $file;

    if (file_exists($path))
    {
      require_once $path;
    }
  }

  public function run()
  {
    register_shutdown_function(array($this, 'handleFatal'));
    try
    {
      $this->_require_file();
      $this->_scanWebConf();

      $port      = 80;
      $listen_ip = '0.0.0.0';

      isset($this->ServerConfig['port']) && $port = $this->ServerConfig['port'];
      isset($this->ServerConfig['listen_ip']) && $listen_ip = $this->ServerConfig['listen_ip'];

      self::$HttpServer = new HttpServer($listen_ip, $port);
      self::$HttpServer->on('request', array($this, 'onRequest'));
      self::$HttpServer->on('Connect', array($this, 'onConnect'));
      self::$HttpServer->on('Close', array($this, 'onClose'));
      self::$HttpServer->on('Start', array($this, 'onStart'));
      self::$HttpServer->on('WorkerStart', array($this, 'onWorkerStart'));
      self::$HttpServer->on('WorkerStop', array($this, 'onWorkerStop'));
      self::$HttpServer->on('WorkerError', array($this, 'onWorkerError'));
      self::$HttpServer->on('Shutdown', array($this, 'onShutdown'));

      self::$HttpServer->start();

    } catch (\Exception $e)
    {
      $file = fopen(dirname(__DIR__) . DIRECTORY_SEPARATOR . 'log' . 'bcngx.log', 'a+');
      fwrite($file, "[EXCEPTION] (" . date('Y-m-d H:i:s') . "): " . $e->getMessage() . PHP_EOL);
      fclose($file);
    }
  }

  /**
   * 当request时调用
   *
   * @param unknown $request
   * @param unknown $response
   */
  public function onRequest($request, $response)
  {
    $this->response = $response;

    try
    {

    } catch (\Exception $e)
    {
      $response->end($e->getMessage());
    }
  }

  /**
   * 对内存表的链接与请求数的增减操作
   *
   * @param        $key
   * @param string $type
   */
  function chkSwooleTable($key, $type = 'connect')
  {
    $arr = [];

    if (!empty(self::$HttpServer))
    {
      $arr = self::$HttpServer->sw_table->get($key);
    }

    if ($type == 'connect')
    {
      $arr[ $key ]++;
    } else if ($type == 'close')
    {
      $arr[ $key ]--;
    }

    $this->access_log($key . ' Number=' . $arr[ $key ]);

    if (!empty(self::$HttpServer))
    {
      $lock = new BcngxLock(SWOOLE_RWLOCK);
      $lock->lock();
      self::$HttpServer->sw_table->set($key, $arr);
      $lock->unlock();
    }
  }

  /**
   * 连接的时候
   */
  public function onConnect()
  {
    $this->chkSwooleTable(HttpServer::$ConnectNameKey);
  }

  /**
   * 关闭的时候
   */
  public function onClose()
  {
    $this->chkSwooleTable(HttpServer::$ConnectNameKey, 'close');
  }

  public function onStart(\Swoole\Server $serv)
  {
    file_put_contents(self::$pidFile, $serv->master_pid);
    swoole_set_process_name('bcngx:matser');
  }

  public function onWorkerStart(\Swoole\Server $serv, int $worker_id)
  {
    date_default_timezone_set('PRC');

    if ($serv->taskworker)
    {
      swoole_set_process_name("bcngx[{$worker_id}] : tasker");
    } else
    {
      swoole_set_process_name("bcngx[{$worker_id}]: worker");
    }
  }

  public function onWorkerStop()
  {
    // do it
  }

  public function onWorkerError(\Swoole\Server $serv, int $worker_id, int $worker_pid, int $exit_code)
  {
    $this->access_log('worker_id = ' . $worker_id . '异常错误，pid=' . $worker_pid . '; exit_code=' . $exit_code);
  }

  public function onShutdown()
  {
    @unlink(self::$pidFile);
    echo '服务器关闭' . PHP_EOL;
  }

}

date_default_timezone_set('PRC');

(new \bcngx\bcngx())->run();