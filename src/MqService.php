<?php

namespace RadishesFlight\Mq;

use Exception;
use PhpAmqpLib\Connection\AMQPStreamConnection;
use PhpAmqpLib\Exchange\AMQPExchangeType;
use PhpAmqpLib\Message\AMQPMessage;

class MqService
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
    public function consumePermanent($callback)
    {
        $enhancedCallback = function ($msg) use ($callback) {
            try {
                // 处理消息
                $callback($msg->body);
                // 确认消息
                $msg->ack();
            } catch (Exception $e) {
                // 可选：拒绝消息并重新入队
                $msg->nack(false, true); // 第二个参数为 true 表示消息重新入队
            }
        };
        // 设置基本消费
        $this->channel->basic_consume($this->queue, '', false, false, false, false, $enhancedCallback);

        // 循环等待消息
        while ($this->channel->is_consuming()) {
            $this->channel->wait();
        }
    }

    /**
     * 批量消费消息--循环单条处理
     * @param $callback //回调函数
     * @param $limit //获取消息数量
     * @return void
     */
    public function consume($callback, $limit = 100)
    {
        // 拉取消息
        for ($i = 0; $i < $limit; $i++) {
            // 禁用自动确认
            $message = $this->channel->basic_get($this->queue, false);
            if ($message !== null) {
                $callback($message->body, $this->channel, $message->getDeliveryTag());
                // 手动确认消息
                $this->channel->basic_ack($message->getDeliveryTag());
            } else {
                break; // 如果没有消息了，跳出循环
            }
        }
    }

    /**
     * 批量消费消息--统一返回数据包,主要避免单条的insert,减少开销
     * @param $callback //回调函数
     * @param $isJson //消息是否是json格式数据,为true则解析成数组
     * @param $noAck //是否自动确认消息
     * @param $limit //获取消息数量
     * @return void
     */
    public function consumeReturnData($callback, $isJson = false, $noAck = false, $limit = 100)
    {
        // 拉取消息
        $data = [];
        for ($i = 0; $i < $limit; $i++) {
            // 禁用自动确认
            $message = $this->channel->basic_get($this->queue, $noAck);
            if ($message !== null) {
                if ($noAck) {
                    $data[] = $isJson ? json_decode($message->body, true) : $message->body;
                } else {
                    $data[$message->getDeliveryTag()] = $isJson ? json_decode($message->body, true) : $message->body;
                }
            } else {
                break; // 如果没有消息了，跳出循环
            }
        }
        $callback($data, $this->channel);
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
}
