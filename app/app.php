<?php
// use {{{
use Predis\Client as RedisClient;
use Predis\Profile\ServerProfile;
use Silex\Application;
use Silex\Provider\TwigServiceProvider;
use Silex\Provider\UrlGeneratorServiceProvider;
use Symfony\Component\HttpFoundation\Request;
use Whoops\Provider\Silex\WhoopsServiceProvider;
use Symfony\Component\HttpFoundation\JsonResponse;
// }}}

require __DIR__.'/../vendor/autoload.php';
$app = new Application();

// service {{{
$app->register(new WhoopsServiceProvider());
$app->register(new TwigServiceProvider());
$app->register(new UrlGeneratorServiceProvider());
$app['redis'] = $app->share(function (Application $app) {
    $profile = ServerProfile::get('2.8');
    return new RedisClient(['host' => $app['redis.host'], 'port' => $app['redis.port']], compact('profile'));
});
// }}}

require 'config.php';

// router {{{
$app->get('/', function (Application $app) {
    return $app->redirect($app['url_generator']->generate('feature_index'));
})->bind('index');

$app->get('/feature', function (Application $app) {
    $features = $app['redis']->hgetall($app['features.key']);
    foreach ($features as $name => $feature) {
        $features[$name] = json_decode($feature, true);
    }
    return $app['twig']->render('index.twig', compact('features'));
})->bind('feature_index');

$app->get('/feature/create', function (Application $app) {
    return $app['twig']->render('create.twig');
})->bind('feature_create');

$app->post('/feature', function (Application $app, Request $request) {
    $name = trim($request->request->get('name', ''));
    $whitelist = array_map(function ($item) {
        return (int)trim($item);
    }, split(',', trim($request->request->get('whitelist', ''))));
    $anonymous = in_array(trim($request->request->get('anonymous', '')), array('true', '1', 'on'));
    $percent = (int)trim($request->request->get('percent', ''));
    $feature = json_encode(compact('name', 'whitelist', 'anonymous', 'percent'));
    $app['redis']->hset($app['features.key'], $name, $feature);
    return $app->redirect($app['url_generator']->generate('feature_index'));
})->bind('feature_store');

$app->post('/feature/{name}/destroy', function ($name, Application $app, Request $request) {
    $app['redis']->hdel($app['features.key'], $name);
    return $app->redirect($app['url_generator']->generate('feature_index'));
})->bind('feature_destroy');

$app->get('/feature/{name}/edit', function ($name, Application $app, Request $request) {
    $feature = json_decode($app['redis']->hget($app['features.key'], $name), true);
    return $app['twig']->render('edit.twig', compact('name', 'feature'));
})->bind('feature_edit');

$app->post('/feature/{name}/update', function ($name, Application $app, Request $request) {
    $whitelist = array_map(function ($item) {
        return (int)trim($item);
    }, split(',', trim($request->request->get('whitelist', ''))));
    $anonymous = in_array(trim($request->request->get('anonymous', '')), array('true', '1', 'on'));
    $percent = (int)trim($request->request->get('percent', ''));
    $feature = json_encode(compact('name', 'whitelist', 'anonymous', 'percent'));
    $app['redis']->hset($app['features.key'], $name, $feature);
    return $app->redirect($app['url_generator']->generate('feature_index'));
})->bind('feature_update');

$app->get('/feature/{name}', function ($name, Application $app) {
    $feature = json_decode($app['redis']->hget($app['features.key'], $name), true);
    return $app['twig']->render('show.twig', compact('name', 'feature'));
})->bind('feature_show');
// }}}

return $app;
