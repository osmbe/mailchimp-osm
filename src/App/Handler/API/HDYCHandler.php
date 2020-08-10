<?php

declare(strict_types=1);

namespace App\Handler\API;

use GuzzleHttp\Client;
use Laminas\Diactoros\Response\JsonResponse;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;

class HDYCHandler implements RequestHandlerInterface
{
    public function handle(ServerRequestInterface $request): ResponseInterface
    {
        $username = $request->getAttribute('username');

        $client = new Client();

        $res = $client->request(
            'GET',
            sprintf('https://hdyc.neis-one.org/search/%s', $username)
        );

        $json = json_decode((string) $res->getBody());

        return new JsonResponse(
            $json,
            $res->getStatusCode()
        );
    }
}
