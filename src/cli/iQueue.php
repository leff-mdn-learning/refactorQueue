<?php


namespace AYakovlev\cli;


interface iQueue
{
    public static function getConnection();
    public function sendMessageToQueue(int $invoiceNum): void;
    public function getMessageFromQueue();
}