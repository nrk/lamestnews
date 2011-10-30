<?php

/*
 * This file is part of the Alpaca application.
 *
 * (c) Daniele Alessandri <suppakilla@gmail.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

define('__VENDOR__', __DIR__.'/../vendor');

require __VENDOR__.'/silex/silex.phar';

use Alpaca\Helpers;
use Alpaca\RedisDatabase;
use Alpaca\Silex\WebsiteController;
use Alpaca\Silex\ApiController;
use Silex\Application as Application;
use Silex\Provider\TwigServiceProvider as TwigProvider;
use Symfony\Component\HttpFoundation\Request;
use Predis\Silex\PredisServiceProvider as PredisProvider;

$app = new Application();

$app['debug'] = true;

$app['autoloader']->registerNamespaces(array(
    'Predis' => __VENDOR__.'/predis/lib',
    'Predis\Silex' => __VENDOR__.'/predis-serviceprovider/lib',
    'Alpaca' => __DIR__.'/../src',
));

$app->register(new PredisProvider(), array(
    'predis.parameters' => 'tcp://127.0.0.1:6379',
    'predis.options' => array(
        'profile' => 'dev'
    ),
));

$app->register(new TwigProvider(), array(
    'twig.class_path' => __VENDOR__.'/twig/lib',
    'twig.path' => __DIR__.'/../template',
));

$twig = $app['twig'];
$twig->addFilter('to_int', new Twig_Filter_Function('intval'));
$twig->addFunction('now', new Twig_Function_Function('time'));
$twig->addFunction('time_elapsed', new Twig_Function_Function('Alpaca\Helpers::timeElapsed'));
$twig->addFunction('gravatar', new Twig_Function_Function('Alpaca\Helpers::getGravatarLink'));
$twig->addFunction('news_editable', new Twig_Function_Function('Alpaca\Helpers::isNewsEditable'));
$twig->addFunction('news_domain', new Twig_Function_Function('Alpaca\Helpers::getNewsDomain'));
$twig->addFunction('news_text', new Twig_Function_Function('Alpaca\Helpers::getNewsText'));
$twig->addFunction('comment_score', new Twig_Function_Function('Alpaca\Helpers::commentScore'));
$twig->addFunction('sort_comments', new Twig_Function_Function('Alpaca\Helpers::sortComments'));

$app['alpaca'] = $app->share(function(Application $app) {
    return new RedisDatabase($app['predis']);
});

$app['user'] = $app->share(function(Application $app) {
    return $app['alpaca']->getUser();
});

// ************************************************************************** //

define('__SITENAME__', 'Lamer News');
define('__VERSION__', Alpaca\DatabaseInterface::VERSION);
define('__COMPATIBILITY__', Alpaca\DatabaseInterface::COMPATIBILITY);

$app->before(function(Request $request) use ($app) {
    $alpaca = $app['alpaca'];
    $authToken = $request->cookies->get('auth');

    if (!$user = $alpaca->authenticateUser($authToken)) {
        return;
    }

    $karmaIncrement = $alpaca->getOption('karma_increment_amount');
    $karmaInterval = $alpaca->getOption('karma_increment_interval');
    $alpaca->incrementUserKarma($user, $karmaIncrement, $karmaInterval);
});

$app->mount('/', new WebsiteController());
$app->mount('/api', new ApiController());

// ************************************************************************** //

$app->run();
