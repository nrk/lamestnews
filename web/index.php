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

use Lamernews\Helpers;
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
    }

    $app['user'] = $app->share(function() use($user) { return $user; });
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
    return $app['twig']->render('login.html.twig', array(
        'title' => 'Login',
    ));
});

$app->get('/logout', function(Lamer $app, Request $request) {
    $apisecret = $request->get('apisecret');

    if (isset($app['user']) && Helpers::verifyApiSecret($app['user'], $apisecret)) {
        $app['db']->updateAuthToken($app['user']['id']);
    }

     $app->redirect('/');
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


$app->get('/api/login', function(Lamer $app, Request $request) {
    $username = $request->get('username');
    $password = $request->get('password');

    @list($auth, $apisecret) = $app['db']->verifyUserCredentials($username, $password);

    if (!isset($auth)) {
        return json_encode(array(
            'status' => 'err',
            'error' => 'No match for the specified username / password pair.',
        ));
    }

    return json_encode(array(
        'status' => 'ok',
        'auth' => $auth,
        'apisecret' => $apisecret,
    ));
});

$app->post('/api/logout', function(Lamer $app, Request $request) {
    $apisecret = $request->get('apisecret');

    if (!isset($app['user']) || !Helpers::verifyApiSecret($app['user'], $apisecret)) {
        return json_encode(array(
            'status' => 'err',
            'error' => 'Wrong auth credentials or API secret.',
        ));
    }

    $app['db']->updateAuthToken($app['user']['id']);

    return json_encode(array('status' => 'ok'));
});

$app->post('/api/create_account', function(Lamer $app, Request $request) {
    $username = $request->get('username');
    $password = $request->get('password');

    if (!isset($username, $password)) {
        return json_encode(array(
            'status' => 'err',
            'error' => 'Username and password are two required fields.',
        ));
    }

    if (strlen($password) < ($minPwdLen = $app['db']->getOption('password_min_length'))) {
        return json_encode(array(
            'status' => 'err',
            'error' => "Password is too short. Min length: $minPwdLen",
        ));
    }

    $authToken = $app['db']->createUser($username, $password);
    if (!$authToken) {
        return json_encode(array(
            'status' => 'err',
            'error' => 'Username is busy. Please select a different one.',
        ));
    }

    return json_encode(array(
        'status' => 'ok',
        'auth' => $authToken,
    ));
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
