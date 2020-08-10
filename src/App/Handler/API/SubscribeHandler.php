<?php

declare(strict_types=1);

namespace App\Handler\API;

use Exception;
use MailchimpAPI\Mailchimp;
use MailchimpAPI\Responses\MailchimpResponse;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;
use Laminas\Diactoros\Response\JsonResponse;

class SubscribeHandler implements RequestHandlerInterface
{
    /** @var Mailchimp */
    private $mailchimp;

    public function __construct(Mailchimp $mailchimp)
    {
        $this->mailchimp = $mailchimp;
    }

    public function handle(ServerRequestInterface $request): ResponseInterface
    {
        $key = $request->getAttribute('list');

        $file = 'data/cache/lists.json';
        if (!file_exists($file) || !is_readable($file)) {
            throw new Exception(sprintf('Unable to read file "%s" !', $file));
        }

        $fileContent = file_get_contents($file);
        if ($fileContent === false) {
            throw new Exception(sprintf('Invalid JSON in file "%s" !', $file));
        }

        $lists = json_decode($fileContent);
        $list = current(array_filter($lists, function ($value) use ($key) {
            return $value->identifier === $key;
        }));

        if ($list === false) {
            throw new Exception(sprintf('The list "%s" does not exist !', $key));
        }

        $params = $request->getParsedBody();

        if (!isset($params['email'])) {
            throw new Exception('You need to define parameter "email" !');
        }

        $data = [
            'email_address'    => $params['email'],
            'status'           => $list->doubleOptIn ? 'pending' : 'subscribed',
            'merge_fields'     => [],
            'interests'        => [],
            'ip_signup'        => $_SERVER['REMOTE_ADDR'] ?? null,
            'timestamp_signup' => date('Y-m-d H:i:s'),
        ];

        foreach ($list->mergeFields as $mergeField) {
            if (isset($params[$mergeField->tag]) && !is_null($params[$mergeField->tag])) {
                $data['merge_fields'][$mergeField->tag] = $params[$mergeField->tag];
            }
        }

        foreach ($list->interestCategories as $category) {
            foreach ($category->interests as $interest) {
                $data['interests'][$interest->id] = isset($params['interests']) &&
                    in_array($interest->id, $params['interests'], true);
            }
        }

        $response = $this
            ->mailchimp
            ->lists($list->id)
            ->members()
            ->post($data);
        $json = json_decode($response->getBody());

        if ($response->wasFailure() === true) {
            /*
            if ($json->title === 'Member Exists') {
                unset(
                    $data['email_address'],
                    $data['status'],
                    $data['ip_signup'],
                    $data['timestamp_signup']
                );

                $response = $this
                    ->mailchimp
                    ->lists($list->id)
                    ->members($params['email'])
                    ->put($data);

                return self::processResponse($response);
            }
            */

            return self::processResponse($response);
        } else {
            return self::processResponse($response);
        }
    }

    public static function processResponse(MailchimpResponse $response): JsonResponse
    {
        $json = json_decode($response->getBody());

        if ($response->wasSuccess() === true) {
            return new JsonResponse($json);
        } else {
            return new JsonResponse($json, $json->status);
        }
    }
}
