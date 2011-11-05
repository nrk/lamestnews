<?php

/*
 * This file is part of the Lamest application.
 *
 * (c) Daniele Alessandri <suppakilla@gmail.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

define('__VENDOR__', __DIR__.'/../vendor');

require __VENDOR__.'/silex/silex.phar';

use Lamest\Silex\LamestServiceProvider;
use Lamest\Silex\WebsiteController;
use Lamest\Silex\ApiController;
use Lamest\Twig\LamestExtension as LamestTwigExtension;
use Predis\Silex\PredisServiceProvider as PredisProvider;
use Silex\Provider\TwigServiceProvider as TwigProvider;

$app = new Silex\Application();

$app['debug'] = true;

$app['autoloader']->registerNamespaces(array(
    'Lamest' => __DIR__.'/../src',
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

$app->register(new LamestServiceProvider(), array(
    'lamest.options' => array(
        'site_name' => 'Lamer News',
    ),
));

// ************************************************************************** //

$app['twig']->addExtension(new LamestTwigExtension());

$app->mount('/', new WebsiteController());
$app->mount('/api', new ApiController());

// ************************************************************************** //

$app->run();
