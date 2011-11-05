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

use Lamest\RedisDatabase;
use Silex\Application;
use Silex\ServiceProviderInterface;
use Symfony\Component\HttpFoundation\Request;

/**
 * Service provider for using Lamest with Silex.
 *
 * @author Daniele Alessandri <suppakilla@gmail.com>
 */
class LamestServiceProvider implements ServiceProviderInterface
{
    /**
     * {@inheritdoc}
     */
    public function register(Application $app)
    {
        if (!isset($app['lamest.options'])) {
            $app['lamest.options'] = array();
        }

        $app['lamest'] = $app->share(function(Application $app) {
            return new RedisDatabase($app['predis'], $app['lamest.options']);
        });

        $app['user'] = $app->share(function(Application $app) {
            return $app['lamest']->getUser();
        });

        $app->before(function(Request $request) use ($app) {
            $engine = $app['lamest'];
            $authToken = $request->cookies->get('auth');

            if ($user = $engine->authenticateUser($authToken)) {
                $karmaIncrement = $engine->getOption('karma_increment_amount');
                $karmaInterval = $engine->getOption('karma_increment_interval');
                $engine->incrementUserKarma($user, $karmaIncrement, $karmaInterval);
            }
        });
    }
}