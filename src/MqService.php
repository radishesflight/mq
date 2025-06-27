<?php

namespace RadishesFlight\Mq;

use Exception;
use PhpAmqpLib\Connection\AMQPStreamConnection;
use PhpAmqpLib\Exchange\AMQPExchangeType;
use PhpAmqpLib\Message\AMQPMessage;

class MqService implements MqHandlingInterFace
{
    public $connection;
    public $channel;
    public $exchange;
    public $queue;
    public static $obj;

    public function __construct($host, $port, $user, $password, $exchange, $queue)
    {
        $this->exchange = $exchange;
        $this->queue = $queue;
        // 创建连接和信道
        $this->connection = new AMQPStreamConnection($host, $port, $user, $password);
        $this->channel = $this->connection->channel();

        // 声明交换器和队列
        $this->channel->exchange_declare($this->exchange, AMQPExchangeType::TOPIC, false, true, false);
        $this->channel->queue_declare($this->queue, false, true, false, false);
        $this->channel->queue_bind($this->queue, $this->exchange, $this->queue);
    }

    public function __destruct()
    {
        $this->close();
    }

    public static function init($config)
    {
        if (!self::$obj instanceof self) {

            self::$obj = new self($config['host'], $config['port'], $config['user'], $config['password'], $config['exchange'], $config['queue']);
        }
        return self::$obj;
    }

    /**
     * 发送消息
     * @param $messageBody
     * @return MqService
     */
    public function sendMessage($messageBody, $routing_key = '')
    {
        $routing_key = $routing_key ?: $this->queue;
        $message = new AMQPMessage($messageBody, ['delivery_mode' => AMQPMessage::DELIVERY_MODE_PERSISTENT]);
        $this->channel->basic_publish($message, $this->exchange, $routing_key);
    }

    /**
     * 消费消息常驻
     * @return void
     */
    public function consumePermanent()
    {
        $callback = function ($msg) {
            try {
                echo 'Received message: ', $msg->body, "\n";
                // 处理消息
                $this->handle($msg->body);
                // 确认消息
                $msg->ack();
            } catch (Exception $e) {
                // 可选：拒绝消息并重新入队
                $msg->nack(false, true); // 第二个参数为 true 表示消息重新入队
            }
        };
        // 设置基本消费
        $this->channel->basic_consume($this->queue, '', false, false, false, false, $callback);

        // 循环等待消息
        while ($this->channel->is_consuming()) {
            $this->channel->wait();
        }
    }

    public function consume($limit = 100)
    {
        // 拉取消息
        for ($i = 0; $i < $limit; $i++) {
            // 禁用自动确认
            $message = $this->channel->basic_get($this->queue, false);
            if ($message !== null) {
                try {
                    $this->handle($message->body);
                    // 手动确认消息
                    $this->channel->basic_ack($message->getDeliveryTag());
                } catch (Exception $e) {
                    // 处理错误，例如日志记录
                    // 这里你可以选择不确认消息，这样消息会被重新排队
                    // 或者你可以使用basic_nack来拒绝消息
                    $this->channel->basic_nack($message->getDeliveryTag(), false, true);
                }
            } else {
                break; // 如果没有消息了，跳出循环
            }
        }
    }

    /**
     * 关闭连接
     * @return void
     * @throws \Exception
     */
    public function close()
    {
        $this->channel->close();
        $this->connection->close();
    }

    public function handle($message)
    {
        // TODO: Implement handle() method.
    }
}
