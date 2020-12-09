<?php


namespace AYakovlev\cli;


use Exception;
use PhpAmqpLib\Connection\AMQPStreamConnection;
use PhpAmqpLib\Message\AMQPMessage;

class Rabbitmq implements iQueue
{
    private static array $instance;
    private static AMQPStreamConnection $connection;

    /**
     * @return AMQPStreamConnection
     */
    public static function getConnection(): AMQPStreamConnection
    {
        self::$instance = (require __DIR__ . '/../../config/settings.php')['rabbitmq'];
        self::$connection = new AMQPStreamConnection(
            self::$instance['host'],
            self::$instance['port'],
            self::$instance['user'],
            self::$instance['password']
        );

        return self::$connection;
    }

    /**
     * @param int $invoiceNum - номер накладной
     * @throws Exception
     */
    public function sendMessageToQueue(int $invoiceNum): void
    {
        $channel = self::getConnection()->channel();
        $channel->queue_declare(
            'invoice_queue',
            false,
            true,       // Очередь сохраняется
            false,
            false
        );

        $

        $msg = new AMQPMessage(
            $invoiceNum,
            ['delivery_mode' => 2]  // сообщение постоянное
        );

        $channel->basic_publish(
            $msg,
            '',
            'invoice_queue'
        );

       Log::getLog()->info("Message No. {$invoiceNum} send to Queue");
        $channel->close();
        Rabbitmq::getConnection()->close();
    }

    /**
     * @param AMQPMessage $msg
     */
    public function process(AMQPMessage $msg): void
    {
        $message = 'Received message No. ' . $msg->body;
        Log::getLog()->info($message);
        echo "-----\n";
        echo $message . "\n";
        $gf = new GeneratePdfController();
        $se = new SendEmailController();
        $gf->generatePdf();
        $se->sendEmail();
        echo "Message No. {$msg->body} processed.\n";
        $msg->delivery_info['channel']->basic_ack($msg->delivery_info['delivery_tag']);

    }

    public function getMessageFromQueue()
    {
        Log::getLog()->info('Begin listen routine');

        $channel = self::getConnection()->channel();

        $channel->queue_declare(
            'invoice_queue',
            false,
            true, // постоянная очередь
            false,
            false
        );

        $channel->basic_qos(
            null,
            1,
            null
        );

        $channel->basic_consume(
            'invoice_queue',
            '',
            false,
            false,
            false,
            false,
            array($this, 'process')
        );

        Log::getLog()->info('Consuming from queue');

        while (count($channel->callbacks)) {
            Log::getLog()->info('Waiting for incoming messages');
            $channel->wait();
        }

        $channel->close();
        self::$connection->close();
    }
}