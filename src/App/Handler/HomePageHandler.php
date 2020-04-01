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

class HomePageHandler implements RequestHandlerInterface
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

    public function handle(ServerRequestInterface $request): ResponseInterface
    {
        $query = $request->getQueryParams();

        $iframe = isset($query['iframe']);

        try {
            $file = 'data/cache/lists.json';
            if (!file_exists($file)) {
                throw new Exception(sprintf('File "%s" does not exist !', $file));
            }
            if (!is_readable($file)) {
                throw new Exception(sprintf('File "%s" is not readable !', $file));
            }

            $fileContent = file_get_contents($file);
            if ($fileContent === false) {
                throw new Exception(sprintf('Invalid JSON in file "%s" !', $file));
            }
            $lists = json_decode($fileContent);

            $data = [
                'iframe' => $iframe,
                'update' => filemtime($file),
                'lists'  => $lists,
            ];

            return new HtmlResponse($this->template->render('app::home-page', $data));
        } catch (Exception $e) {
            $data = [
                'iframe'  => $iframe,
                'message' => $e->getMessage(),
            ];

            return new HtmlResponse($this->template->render('error::app-error', $data));
        }
    }
}
