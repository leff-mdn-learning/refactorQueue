<?php


namespace AYakovlev\cli\Sender;


use AYakovlev\cli\Rabbitmq;

class WorkerSender extends Rabbitmq
{
    public function sendMessageToQueue(int $invoiceNum): void
    {
        parent::sendMessageToQueue($invoiceNum);
    }
}
