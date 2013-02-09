<?php

/*
 * This file is part of the Lamest application.
 *
 * (c) Daniele Alessandri <suppakilla@gmail.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Lamest\Silex;

require __DIR__.'/../vendor/autoload.php';

use Silex\Application;
use Silex\Provider\TwigServiceProvider;
use Lamest\Twig\LamestExtension;
use Predis\Silex\PredisServiceProvider;

$app = new Application();

$app['debug'] = true;

$app->register(new PredisServiceProvider(), array(
    'predis.parameters' => 'tcp://127.0.0.1:6379',
));

$app->register(new TwigServiceProvider(), array(
    'twig.path' => __DIR__.'/../template',
));

$app['twig'] = $app->share($app->extend('twig', function($twig, $app) {
    $twig->addExtension(new LamestExtension());

    return $twig;
}));

$app->register(new LamestServiceProvider(), array(
    'lamest.options' => array(
        'site_name' => 'Lamest News',
    ),
));

// ************************************************************************** //

$app->mount('/', new WebsiteController());
$app->mount('/api', new ApiController());

// ************************************************************************** //

$app->run();
