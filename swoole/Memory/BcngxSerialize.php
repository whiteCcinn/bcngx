<?php

namespace bcngx\swoole\Memory;

/**
 * 序列化操作类
 * Class BcngxSerialize
 *
 * @package bcngx\swoole\Memory
 */
class BcngxSerialize
{
  public function pack($data)
  {
    if (class_exists('\Swoole\Serialize'))
    {
      $SerializeData = \Swoole\Serialize::pack($data);
    } elseif (function_exists('swoole_unpack'))
    {
      $SerializeData = swoole_pack($data);
    } else
    {
      $SerializeData = serialize($data);
    }

    return $SerializeData;
  }

  public function unpack($SerializeData)
  {
    if (class_exists('\Swoole\Serialize'))
    {
      $data = \Swoole\Serialize::unpack($SerializeData);
    } else if (function_exists('swoole_unpack'))
    {
      $data = swoole_unpack($SerializeData);
    } else
    {
      $data = unserialize($SerializeData);
    }

    return $data;
  }
}