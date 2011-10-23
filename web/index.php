<?php

/*
 * This file is part of the Lamer News application.
 *
 * (c) Daniele Alessandri <suppakilla@gmail.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

define('__VENDOR__', __DIR__.'/../vendor');

require __VENDOR__.'/silex/silex.phar';

use Silex\Application as Lamer;
use Silex\Provider\TwigServiceProvider as TwigProvider;
use Predis\Silex\PredisServiceProvider as Predilex;
use Symfony\Component\HttpFoundation\Request;

$app = new Lamer();

$app['debug'] = true;

$app['autoloader']->registerNamespaces(array(
    'Predis' => __VENDOR__.'/predis/lib',
    'Predis\Silex' => __VENDOR__.'/predis-serviceprovider/lib',
    'Lamernews' => __DIR__.'/../src',
));

$app->register(new TwigProvider(), array(
    'twig.class_path' => __VENDOR__.'/twig/lib',
    'twig.path' => __DIR__.'/../template',
));

$app->register(new Predilex(), array(
    'predis.parameters' => 'tcp://127.0.0.1:6379',
    'predis.options' => array(
        'profile' => 'dev'
    ),
));

$app['db'] = $app->share(function(Lamer $app) {
    return new Lamernews\RedisDatabase($app['predis']);
});

// ************************************************************************** //

$app->before(function(Request $request) use ($app) {
    $authToken = $request->cookies->get('auth');
    $user = $app['db']->authenticateUser($authToken);

    if ($user) {
        $karmaIncrement = $app['db']->getOption('karma_increment_amount');
        $karmaInterval = $app['db']->getOption('karma_increment_interval');
        $app['db']->incrementUserKarma($user, $karmaIncrement, $karmaInterval);

        $app['user'] = $app->share(function() use($user) { return $user; });
    }
});

$app->get('/', function(Lamer $app) {
    return $app['twig']->render('index.html.twig', array(
        'title' => 'Coming soon!',
    ));
});

$app->get('/latest', function(Lamer $app) {
    // ...
});

$app->get('/login', function(Lamer $app) {
    // ...
});

$app->get('/logout', function(Lamer $app) {
    // ...
});

$app->get('/submit', function(Lamer $app) {
    // ...
});

$app->get('/news/{newsID}', function(Lamer $app, $newsID) {
    // ...
});

$app->get('/reply/{newsID}/{commentID}', function(Lamer $app, $newsID, $commentID) {
    // ...
});

$app->get('"/editcomment/{newsID}/{commentID}', function(Lamer $app, $newsID, $commentID) {
    // ...
});

$app->get('/editnews/{newsID}', function(Lamer $app, $newsID) {
    // ...
});

$app->get('/user/{username}', function(Lamer $app, $username) {
    // ...
});

// ************************************************************************** //


$app->get('/api/login', function(Lamer $app) {
    // ...
});

$app->post('/api/logout', function(Lamer $app) {
    // ...
});

$app->post('/api/create_account', function(Lamer $app) {
    // ...
});

$app->post('/api/submit', function(Lamer $app) {
    // ...
});

$app->post('/api/votenews', function(Lamer $app) {
    // ...
});

$app->post('/api/postcomment', function(Lamer $app) {
    // ...
});

$app->post('/api/updateprofile', function(Lamer $app) {
    // ...
});

// ************************************************************************** //

$app->run();
