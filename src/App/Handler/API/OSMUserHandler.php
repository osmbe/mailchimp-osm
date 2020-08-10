<?php

declare(strict_types=1);

namespace App\Handler\API;

use GuzzleHttp\Client;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;
use Laminas\Diactoros\Response\EmptyResponse;

class OSMUserHandler implements RequestHandlerInterface
{
    public function handle(ServerRequestInterface $request): ResponseInterface
    {
        $username = $request->getAttribute('username');

        $client = new Client();

        $res = $client->request(
            'GET',
            sprintf('https://www.openstreetmap.org/user/%s', $username)
        );

        return new EmptyResponse($res->getStatusCode());
    }
}
