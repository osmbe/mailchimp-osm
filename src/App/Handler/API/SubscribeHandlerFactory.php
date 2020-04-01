<?php

declare(strict_types=1);

namespace App\Handler\API;

use MailchimpAPI\Mailchimp;
use Psr\Container\ContainerInterface;
use Psr\Http\Server\RequestHandlerInterface;

class SubscribeHandlerFactory
{
    public function __invoke(ContainerInterface $container): RequestHandlerInterface
    {
        $config = $container->get('config');

        $mailchimp = new Mailchimp($config['apiKey']);

        return new SubscribeHandler($mailchimp);
    }
}
