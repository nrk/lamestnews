<?php

/*
 * This file is part of the Lamer News application.
 *
 * (c) Daniele Alessandri <suppakilla@gmail.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

define('__VERSION__', '0.5.1');
define('__VENDOR__', __DIR__.'/../vendor');

require __VENDOR__.'/silex/silex.phar';

use Lamernews\Helpers;
use Lamernews\Silex\WebsiteController;
use Lamernews\Silex\ApiController;
use Silex\Application as Lamer;
use Silex\Provider\TwigServiceProvider as TwigProvider;
use Symfony\Component\HttpFoundation\Request;
use Predis\Silex\PredisServiceProvider as PredisProvider;

$app = new Lamer();

$app['debug'] = true;

$app['autoloader']->registerNamespaces(array(
    'Predis' => __VENDOR__.'/predis/lib',
    'Predis\Silex' => __VENDOR__.'/predis-serviceprovider/lib',
    'Lamernews' => __DIR__.'/../src',
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
$twig->addFunction('time_elapsed', new Twig_Function_Function('Lamernews\Helpers::timeElapsed'));
$twig->addFunction('gravatar', new Twig_Function_Function('Lamernews\Helpers::getGravatarLink'));
$twig->addFunction('news_editable', new Twig_Function_Function('Lamernews\Helpers::isNewsEditable'));
$twig->addFunction('news_domain', new Twig_Function_Function('Lamernews\Helpers::getNewsDomain'));
$twig->addFunction('news_text', new Twig_Function_Function('Lamernews\Helpers::getNewsText'));
$twig->addFunction('comment_score', new Twig_Function_Function('Lamernews\Helpers::commentScore'));
$twig->addFunction('sort_comments', new Twig_Function_Function('Lamernews\Helpers::sortComments'));

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

    $app['user'] = $app->share(function() use($app) { return $app['db']->getUser(); });
});

$app->mount('/', new WebsiteController());
$app->mount('/api', new ApiController());

// ************************************************************************** //

$app->run();
