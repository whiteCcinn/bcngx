<?php
namespace bcngx\swoole\HttpServer;

use bcngx\swoole\Memory\BcngxSerialize;

if (!class_exists('swoole_server'))
{
  echo 'ERROR: please install php extension for swoole', PHP_EOL;
  exit(1);
}

/**
 * Class HttpServer
 */
class HttpServer extends \Swoole\Http\Server
{
  /**
   * @var \Swoole\Table
   */
  public $sw_table = null;
  /**
   * 内存表的行数大小
   *
   * @var int
   */
  private $SwooleTableSize = 1024;

  /**
   * @var \Swoole\Table
   */
  public $workerInfoTable = null;
  /**
   * @var \Swoole\Table
   */
  public $ConfigSWTable = null;

  static $ConnectNameKey = 'ConnectNumber';
  static $RequestingKey = 'Requesting';
  static $sessionKey = 'session';


  const HTTP_CONFIG_TABLE_KEY = 'ConfigTable';
  /**
   * @var \Swoole\Table
   */
  public $sessionTable = null;

  function __construct($host = '0.0.0.0', $port = 80)
  {
    $this->sw_table = new \Swoole\Table($this->SwooleTableSize);

    /**
     * 内存表
     */
    $this->sw_table->column(self::$ConnectNameKey, \Swoole\Table::TYPE_INT, 8);//链接量
    $this->sw_table->column(self::$RequestingKey, \Swoole\Table::TYPE_INT, 8);//正在处理的请求
    $this->sw_table->create();

    $this->sw_table->set(self::$ConnectNameKey, [self::$ConnectNameKey => 0]);
    $this->sw_table->set(self::$RequestingKey, [self::$RequestingKey => 0]);

    $this->ConfigSWTable = new \Swoole\Table(1);
    $this->ConfigSWTable->column(self::HTTP_CONFIG_TABLE_KEY, \Swoole\Table::TYPE_STRING, 1024 * 5);
    $this->ConfigSWTable->create();

    $this->createWorkerInfoTable();

    parent::__construct($host, $port);

  }

  static $memoryKey = 'memory';

  function createWorkerInfoTable()
  {

    $this->workerInfoTable = new \Swoole\Table($this->SwooleTableSize);
    $this->workerInfoTable->column(self::$memoryKey, \Swoole\Table::TYPE_INT, 8);
    $this->workerInfoTable->create();
    $this->workerInfoTable->set(self::$memoryKey, [self::$memoryKey => 0]);

    $this->sessionTable = new \Swoole\Table(65536);
    $this->sessionTable->column(self::$sessionKey, \Swoole\Table::TYPE_STRING, 1024 * 4);
    $this->sessionTable->column('time', \Swoole\Table::TYPE_INT, 11);
    $this->sessionTable->create();

  }

  /**
   * 获取服务器配置
   *
   * @return mixed
   */
  function getHttpConf()
  {
    $arr = $this->ConfigSWTable->get(self::HTTP_CONFIG_TABLE_KEY);

    return json_decode($arr[ self::HTTP_CONFIG_TABLE_KEY ], true);
  }

  function setHttpConf(array $Arr)
  {
    if ($Arr && is_array($Arr))
    {
      $serialize = new BcngxSerialize();
      $data      = $serialize->pack($Arr);
      $this->ConfigSWTable->set(self::HTTP_CONFIG_TABLE_KEY, [self::HTTP_CONFIG_TABLE_KEY => $data]);

      return true;
    }

    return false;
  }
}
