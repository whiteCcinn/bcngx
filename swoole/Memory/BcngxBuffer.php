<?php

/**
 * 内存操作类
 * Class BcngxBuffer
 */
class BcngxBuffer extends \Swoole\Buffer
{
  public static $BufferPool = [];

  /**
   * 设置全局对象，支持二进制对象
   * @param $key
   * @param $value
   */
  public function set($key, $value ,int $size = 128)
  {
    $buffer = new \Swoole\Buffer($size);

    $length = $buffer->append($value);

    self::$BufferPool[$key] = $buffer;
  }
}