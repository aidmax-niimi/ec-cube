<?php

namespace Eccube\ServiceProvider;

use Eccube\Monolog\Helper\EccubeMonologHelper;
use Silex\Application;
use Silex\ServiceProviderInterface;

/**
 * Class EccubeMonologServiceProvider
 *
 * @package Eccube\ServiceProvider
 */
class EccubeMonologServiceProvider implements ServiceProviderInterface
{
    public function register(Application $app)
    {
        $app->register(new \Silex\Provider\MonologServiceProvider());


        // ヘルパー作成
        $app['eccube.monolog.helper'] = $app->share(function ($app) {
            return new EccubeMonologHelper($app);
        });

        // ログクラス作成ファクトリー
        $app['eccube.monolog.factory'] = $app->protect(function (array $channelValues) use ($app) {

            $log = new $app['monolog.logger.class']($channelValues['name']);

            // EccubeMonologHelper内でHandlerを設定している
            $log->pushHandler($app['eccube.monolog.helper']->getHandler($channelValues));

            return $log;
        });

        // チャネルに応じてログを作成し、フロント、管理、プラグイン用のログ出力クラスを作成
        $channels = $app['config']['log']['channel'];
        // monologの設定は除外
        unset($channels['monolog']);
        foreach ($channels as $channel => $channelValues) {
            $app['monolog.logger.'.$channel] = $app->share(function ($app) use ($channelValues) {
                return $app['eccube.monolog.factory']($channelValues);
            });
        }

        // MonologServiceProviderで定義されているmonolog.handlerの置換
        $channelValues = $app['config']['log']['channel']['monolog'];
        $app['monolog.name'] = $channelValues['name'];
        $app['monolog.handler'] = $app->share(function ($app) use ($channelValues) {
            return $app['eccube.monolog.helper']->getHandler($channelValues);
        });

        $app['listener.requestdump'] = $app->share(function ($app) {
            return new \Eccube\EventListener\RequestDumpListener($app);
        });
    }

    public function boot(Application $app)
    {
        $app['dispatcher']->addSubscriber($app['listener.requestdump']);
    }
}
