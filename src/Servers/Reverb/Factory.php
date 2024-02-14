<?php

namespace Laravel\Reverb\Servers\Reverb;

use InvalidArgumentException;
use Laravel\Reverb\Contracts\ApplicationProvider;
use Laravel\Reverb\Protocols\Pusher\Contracts\ChannelConnectionManager;
use Laravel\Reverb\Protocols\Pusher\Contracts\ChannelManager;
use Laravel\Reverb\Protocols\Pusher\Http\Controllers\ChannelController;
use Laravel\Reverb\Protocols\Pusher\Http\Controllers\ChannelsController;
use Laravel\Reverb\Protocols\Pusher\Http\Controllers\ChannelUsersController;
use Laravel\Reverb\Protocols\Pusher\Http\Controllers\ConnectionsController;
use Laravel\Reverb\Protocols\Pusher\Http\Controllers\EventsBatchController;
use Laravel\Reverb\Protocols\Pusher\Http\Controllers\EventsController;
use Laravel\Reverb\Protocols\Pusher\Http\Controllers\PusherController;
use Laravel\Reverb\Protocols\Pusher\Http\Controllers\UsersTerminateController;
use Laravel\Reverb\Protocols\Pusher\Managers\ArrayChannelConnectionManager;
use Laravel\Reverb\Protocols\Pusher\Managers\ArrayChannelManager;
use Laravel\Reverb\Protocols\Pusher\Server as PusherServer;
use Laravel\Reverb\Servers\Reverb\Http\Route;
use Laravel\Reverb\Servers\Reverb\Http\Router;
use Laravel\Reverb\Servers\Reverb\Http\Server as HttpServer;
use React\EventLoop\Loop;
use React\EventLoop\LoopInterface;
use React\Socket\SocketServer;
use Symfony\Component\Routing\Matcher\UrlMatcher;
use Symfony\Component\Routing\RequestContext;
use Symfony\Component\Routing\RouteCollection;

class Factory
{
    /**
     * Create a new WebSocket server instance.
     */
    public static function make(string $host = '0.0.0.0', string $port = '8080', string $protocol = 'pusher', ?LoopInterface $loop = null): HttpServer
    {
        $loop = $loop ?: Loop::get();

        $router = match ($protocol) {
            'pusher' => static::makePusherServer(),
            default => throw new InvalidArgumentException("Unsupported protocol [{$protocol}]."),
        };

        return new HttpServer(
            new SocketServer("{$host}:{$port}", [], $loop),
            $router,
            $loop
        );
    }

    /**
     * Create a new WebSocket server for the Pusher protocol.
     */
    public static function makePusherServer(): Router
    {
        app()->singleton(
            ChannelManager::class,
            fn () => new ArrayChannelManager
        );

        app()->bind(
            ChannelConnectionManager::class,
            fn () => new ArrayChannelConnectionManager
        );

        return new Router(new UrlMatcher(static::pusherRoutes(), new RequestContext));
    }

    /**
     * Generate the routes required to handle Pusher requests.
     */
    protected static function pusherRoutes(): RouteCollection
    {
        $routes = new RouteCollection;

        $routes->add('sockets', Route::get('/app/{appKey}', new PusherController(app(PusherServer::class), app(ApplicationProvider::class))));
        $routes->add('events', Route::post('/apps/{appId}/events', new EventsController));
        $routes->add('events_batch', Route::post('/apps/{appId}/batch_events', new EventsBatchController));
        $routes->add('connections', Route::get('/apps/{appId}/connections', new ConnectionsController));
        $routes->add('channels', Route::get('/apps/{appId}/channels', new ChannelsController));
        $routes->add('channel', Route::get('/apps/{appId}/channels/{channel}', new ChannelController));
        $routes->add('channel_users', Route::get('/apps/{appId}/channels/{channel}/users', new ChannelUsersController));
        $routes->add('users_terminate', Route::post('/apps/{appId}/users/{userId}/terminate_connections', new UsersTerminateController));

        return $routes;
    }
}
