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
        //手动消费消息
        $obj->consume(function ($data, AMQPChannel $channel, $messageDeliveryTag) {
            try {
                //业务代码
                dd($data);
                echo 'Received message: ', $data, "\n";
                throw new \Exception('测试异常');
            }catch (\Exception $exception){
                //拒绝消息确认,重新加入消息队列
                $channel->basic_nack($messageDeliveryTag, false, true);
            }
        }, 1);
        //实时消费消息
        $obj->consumePermanent(function ($data){
            //$data就是插入消息队列的内容
            dd($data);
            //业务代码
        });
```
