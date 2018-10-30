<?php

declare(strict_types=1);

namespace App\Handler;

use Exception;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;
use Zend\Diactoros\Response\HtmlResponse;
use Zend\Expressive\Router\RouterInterface;
use Zend\Expressive\Template\TemplateRendererInterface;

class ViewHandler implements RequestHandlerInterface
{
    /** @var string */
    private $containerName;

    /** @var RouterInterface */
    private $router;

    /** @var TemplateRendererInterface */
    private $template;

    public function __construct(
        RouterInterface $router,
        TemplateRendererInterface $template,
        string $containerName
    ) {
        $this->router = $router;
        $this->template = $template;
        $this->containerName = $containerName;
    }

    public function handle(ServerRequestInterface $request) : ResponseInterface
    {
        $query = $request->getQueryParams();

        $iframe = isset($query['iframe']);

        $key = $request->getAttribute('list');

        try {
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
            if ($list->displayList !== true) {
                throw new Exception(sprintf('You can\'t display members from list "%s".', $key));
            }

            $membersFile = sprintf('data/cache/members-%s.json', $list->identifier);
            if (!file_exists($membersFile) || !is_readable($membersFile)) {
                throw new Exception(sprintf('Unable to read file "%s" !', $membersFile));
            }

            $membersFileContent = file_get_contents($membersFile);
            if ($membersFileContent === false) {
                throw new Exception(sprintf('Invalid JSON in file "%s" !', $membersFile));
            }

            $members = json_decode($membersFileContent);

            $data = [
                'iframe'  => $iframe,
                'update' => filemtime($membersFile),
                'list'    => $list,
                'members' => $members,
            ];

            return new HtmlResponse($this->template->render('app::view', $data));
        } catch (Exception $e) {
            $data = [
                'iframe'  => $iframe,
                'message' => $e->getMessage(),
            ];

            return new HtmlResponse($this->template->render('error::app-error', $data));
        }
    }
}
