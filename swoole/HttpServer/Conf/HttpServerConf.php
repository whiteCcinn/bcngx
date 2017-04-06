<?php
/**
 * 返回HttpServer 配置信息
 */
return [
    'worker_process'  => 2,                 // 工作进程数，/conf目录下的bcngx.conf优先级高于该配置
    'task_worker_num' => 2,                 // Task 任务进程数
    'daemonize'       => 1,                 // 是否以守护进程的形式启动
    'log_file'        => 'log/process.log'  // 进程日志
];