<?php
namespace bcngx\swoole\Memory;

/**
 * 内存操作类
 * Class BcngxBuffer
 */
class BcngxBuffer
{
  public static $BufferPool = [];

  private static $instance = null;

  /**
   * 设置全局对象，支持二进制对象
   *
   * @param     $key
   * @param     $value
   * @param int $size
   *
   * @return bool
   */
  public function set($key, $value, int $size = 128)
  {
    if (isset(self::$BufferPool[ $key ]))
    {
      return false;
    }

    $buffer = new \Swoole\Buffer($size);

    $length = $buffer->append($value);

    self::$BufferPool[ $key ] = $buffer;

    return true;
  }

  /**
   * 获取数据
   *
   * @param $key
   *
   * @return null
   */
  public function get($key)
  {
    if (!isset(self::$BufferPool[ $key ]))
    {
      return null;
    }

    // 读取所有数据
    $offset = 0;
    $data   = self::$BufferPool[ $key ]->substr($offset);

    return $data;
  }

  /**
   * 清空内存操作对象
   *
   * @param $key
   *
   * @return bool
   */
  public function clear($key, $destroy = false)
  {
    if (!isset(self::$BufferPool[ $key ]))
    {
      return false;
    }

    self::$BufferPool[ $key ]->clear();

    if ($destroy)
    {
      if (!$this->destroy($key))
      {
        return false;
      }
    }

    return true;
  }

  /**
   * 销毁内存操作对象
   *
   * @param $key
   *
   * @return bool
   */
  public function destroy($key)
  {
    if (!isset(self::$BufferPool[ $key ]))
    {
      return false;
    }

    unset(self::$BufferPool[ $key ]);

    return true;
  }


  /**
   * 单例设计模式
   * @return BcngxBuffer|null
   */
  public static function getInstance()
  {
    if (self::$instance == null)
    {
      self::$instance = new BcngxBuffer();
    }

    return self::$instance;
  }
}