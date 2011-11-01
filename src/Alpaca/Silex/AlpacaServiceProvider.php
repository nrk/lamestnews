<?php

/*
 * This file is part of the Alpaca application.
 *
 * (c) Daniele Alessandri <suppakilla@gmail.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Alpaca\Silex;

use Alpaca\RedisDatabase;
use Silex\Application;
use Silex\ServiceProviderInterface;
use Symfony\Component\HttpFoundation\Request;

/**
 * Service provider for using Alpaca with Silex.
 *
 * @author Daniele Alessandri <suppakilla@gmail.com>
 */
class AlpacaServiceProvider implements ServiceProviderInterface
{
    /**
     * {@inheritdoc}
     */
    public function register(Application $app)
    {
        if (!isset($app['alpaca.options'])) {
            $app['alpaca.options'] = array();
        }

        $app['alpaca'] = $app->share(function(Application $app) {
            return new RedisDatabase($app['predis'], $app['alpaca.options']);
        });

        $app['user'] = $app->share(function(Application $app) {
            return $app['alpaca']->getUser();
        });

        $app->before(function(Request $request) use ($app) {
            $alpaca = $app['alpaca'];
            $authToken = $request->cookies->get('auth');

            if ($user = $alpaca->authenticateUser($authToken)) {
                $karmaIncrement = $alpaca->getOption('karma_increment_amount');
                $karmaInterval = $alpaca->getOption('karma_increment_interval');
                $alpaca->incrementUserKarma($user, $karmaIncrement, $karmaInterval);
            }
        });
    }
}