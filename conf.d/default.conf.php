<?php
return [
    'listen'         => '80',                             // 监听的端口
    'server_name'    => 'localhost',                      // 访问域名,多个域名用“,”分开
    'index'          => 'index.php index.html index.htm', // 目录默认访问文件名,多个用空格隔开
    'rewrite'        => false,                            // 是否打开重写
    'rewrite_route'  => [],                               // 重写规则
    'is_cache'       => false,                            // 是否开启静态文件缓存
    /**
     * 浏览器缓存
     * type 缓存的文件类型
     * time 缓存的时长（s）
     */
    'cache'          => ['type' => ['js', 'css', 'png', 'ico', 'jpg', 'gip', 'wff2'], 'time' => 3],
    'access_denied'  => '',                                  // 拒绝访问的文件类型
    'session_expire' => 360000,                              // session会话有效期
];