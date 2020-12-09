<?php


namespace AYakovlev\cli;


use AYakovlev\cli\Handler\GeneratePdf;
use AYakovlev\cli\Handler\SendEmail;
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
     * @param string $data - json-data for invoice
     * @param string $template - template for invoice
     * @throws Exception
     */
    public function sendMessageToQueue(string $data, string $template): void
    {
        $channel = self::getConnection()->channel();
        $channel->queue_declare(
            'invoice_queue',
            false,
            true,       // Очередь сохраняется
            false,
            false
        );

        $filedata = file_get_contents(__DIR__ . "/../../" . $data);
        $msgdata = array_merge(compact('filedata', $filedata), compact('template', $template));

        $msg = new AMQPMessage(
            json_encode($msgdata),
            ['delivery_mode' => 2]  // сообщение постоянное
        );

        $channel->basic_publish(
            $msg,
            '',
            'invoice_queue'
        );

        Log::getLog()->info("Message No. {$data} send to Queue");
        $channel->close();
        Rabbitmq::getConnection()->close();
    }

    /**
     * @param AMQPMessage $msg
     */
    public function process(AMQPMessage $msg): void
    {
        $message = 'Received message';
        Log::getLog()->info($message);
        echo "-----\n";
        echo $message . "\n";

        GeneratePdf::generatePdf($msg->body);
        SendEmail::sendEmail();
        $assocArrayFromFileData = (json_decode($msg->body, true))['filedata'];
        $invoiceNo = (json_decode($assocArrayFromFileData, true))['invoiceNo'];
        echo "Message No. " . $invoiceNo . " processed.\n";
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