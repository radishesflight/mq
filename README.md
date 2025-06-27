# mq
RabbitMQ
```php
        //配置文件
        $host = env('mq.host', '');
        $port = env('mq.port', 5672);
        $user = env('mq.user', 'robert');
        $password = env('mq.mqpassword', '123456');
        $exchange = env('mq.exchange', 'hyperf');
        $queue = env('mq.queue', 'message.routing');
        //实例化消息队列服务
       $obj= MqService::init([
            'host' => $host,
            'port' => $port,
            'user' => $user,
            'password' => $password,
            'exchange' => $exchange,
            'queue' => $queue,
        ]);
       //插入消息json字符串
        $obj->sendMessage('{"name":"hello"}');
        //实时消费消息
        $obj->consume(function ($data){
            //$data就是插入消息队列的内容
            dd($data);
            //业务代码
        });
        //手动消费消息
        $obj->consumePermanent(function ($data){
            //$data就是插入消息队列的内容
            dd($data);
            //业务代码
        });
```
