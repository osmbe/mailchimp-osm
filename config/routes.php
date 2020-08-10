<?php

declare(strict_types=1);

use Mezzio\Application;
use Mezzio\MiddlewareFactory;
use Psr\Container\ContainerInterface;

/*
 * Setup routes with a single request method:
 *
 * $app->get('/', App\Handler\HomePageHandler::class, 'home');
 * $app->post('/album', App\Handler\AlbumCreateHandler::class, 'album.create');
 * $app->put('/album/:id', App\Handler\AlbumUpdateHandler::class, 'album.put');
 * $app->patch('/album/:id', App\Handler\AlbumUpdateHandler::class, 'album.patch');
 * $app->delete('/album/:id', App\Handler\AlbumDeleteHandler::class, 'album.delete');
 *
 * Or with multiple request methods:
 *
 * $app->route('/contact', App\Handler\ContactHandler::class, ['GET', 'POST', ...], 'contact');
 *
 * Or handling all request methods:
 *
 * $app->route('/contact', App\Handler\ContactHandler::class)->setName('contact');
 *
 * or:
 *
 * $app->route(
 *     '/contact',
 *     App\Handler\ContactHandler::class,
 *     Mezzio\Router\Route::HTTP_METHOD_ANY,
 *     'contact'
 * );
 */
return function (Application $app, MiddlewareFactory $factory, ContainerInterface $container): void {
    $app->get('/', App\Handler\HomePageHandler::class, 'home');
    $app->get('/subscribe/{list}', App\Handler\SubscribeHandler::class, 'subscribe');
    $app->get('/view/{list}', App\Handler\ViewHandler::class, 'view');

    $app->get('/api/hdyc/{username}', App\Handler\API\HDYCHandler::class, 'api.hdyc');
    $app->get('/api/osm-user/{username}', App\Handler\API\OSMUserHandler::class, 'api.osm.user');
    $app->get('/api/ping', App\Handler\API\PingHandler::class, 'api.ping');
    $app->post('/api/subscribe/{list}', App\Handler\API\SubscribeHandler::class, 'api.subscribe');
};
