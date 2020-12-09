<?php


namespace AYakovlev\cli\Sender;


use AYakovlev\cli\AbstractCommand;
use AYakovlev\cli\Rabbitmq;

class Send extends AbstractCommand
{
    private WorkerSender $sender;
    public function __construct(array $params)
    {
        parent::__construct($params);
        $this->sender = new WorkerSender();
    }

    protected function checkParams()
    {
        $this->ensureParamExists('d');
        $this->ensureParamExists('t');
    }

    public function execute()
    {
        var_dump($this->getParam('d'));
        var_dump($this->getParam('t'));
        $this->sender->sendMessageToQueue($this->getParam('d'), $this->getParam('t'));
    }

}