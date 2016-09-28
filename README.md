## 基于swoole的crontab服务

### 1.设计初衷
我们经常会遇到一些需要延迟或者定时处理的后台任务，很自然想到的是使用linux提供的crontab服务定时执行，但是有一些不太方便的地方：  
- 1.如果服务器比较多，需要在不同服务器上设置任务，不易管理  
- 2.没有很好的版本控制，历史记录不方便查询  
- 3.crontab只支持到分钟

### 2.特点
- 1. 提供简单的api和管理界面，方便任务管理
- 2. 支持多服务器管理，服务器配置简单灵活
- 3. 支持秒级定时任务
- 4. 支持任务的热部署，不用重启服务
- 5. 支持服务自动发现

### 3.缺点不足
- 1. 目前不支持多语句命令执行，e.g. cd /data/test; /usr/bin/php info.php
- 2. 不支持进程锁，如果需要，可在程序中自行实现
- 3. 命令必须为绝对路径，否则会exec会报 Error: No such file or directory错误

### 4.环境需求
- 1. php >= 5.4
- 2. swoole >= 1.8.7
- 5. mysql 任意稳定版本

### 5.安装
```sh
Usage: crond [options]
-s            start | stop | reload
-l            the log path
-v, --debug   open debug mode
-h, --help    show help info

php crond -s [start|stop|reload]
```
1. 默认配置文件crontab.ini.default, 第一次启动会复制模板到/etc/crontab.ini
2. http默认端口8000, Server默认端口8100
3. 管理界面访问: localhost:8000/admin

### 感谢
感谢[@Suresh Alse](https://github.com/alseambusher/crontab-ui) 提供的ui


