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

use Alpaca\Silex\AlpacaServiceProvider;
use Alpaca\Silex\WebsiteController;
use Alpaca\Silex\ApiController;
use Alpaca\Twig\AlpacaExtension as AlpacaTwigExtension;
use Predis\Silex\PredisServiceProvider as PredisProvider;
use Silex\Provider\TwigServiceProvider as TwigProvider;

$app = new Silex\Application();

$app['debug'] = true;

$app['autoloader']->registerNamespaces(array(
    'Alpaca' => __DIR__.'/../src',
    'Predis' => __VENDOR__.'/predis/lib',
    'Predis\Silex' => __VENDOR__.'/predis-serviceprovider/lib',
));

$app->register(new PredisProvider(), array(
    'predis.parameters' => 'tcp://127.0.0.1:6379',
    'predis.options' => array('profile' => 'dev'),
));

$app->register(new TwigProvider(), array(
    'twig.class_path' => __VENDOR__.'/twig/lib',
    'twig.path' => __DIR__.'/../template',
));

$app->register(new AlpacaServiceProvider(), array(
    'alpaca.options' => array(
        'site_name' => 'Lamer News',
    ),
));

// ************************************************************************** //

$app['twig']->addExtension(new AlpacaTwigExtension());

$app->mount('/', new WebsiteController());
$app->mount('/api', new ApiController());

// ************************************************************************** //

$app->run();
