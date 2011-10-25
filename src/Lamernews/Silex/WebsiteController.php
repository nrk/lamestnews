<?php

namespace Lamernews\Silex;

use Silex\Application as Lamer;
use Symfony\Component\HttpFoundation\Request;
use Silex\ControllerProviderInterface;
use Silex\ControllerCollection;
use Lamernews\Helpers;

/**
 * Defines the methods and routes for the website.
 *
 * @author Daniele Alessandri <suppakilla@gmail.com>
 */
class WebsiteController implements ControllerProviderInterface
{
    /**
     * {@inheritdoc}
     */
    public function connect(Lamer $app)
    {
        $controllers = new ControllerCollection();

        $controllers->get('/', function(Lamer $app) {
            return $app['twig']->render('newslist.html.twig', array(
                'title' => 'Top news',
                'newslist' => $app['db']->getTopNews($app['user']),
            ));
        });

        $controllers->get('/latest', function(Lamer $app) {
            return $app['twig']->render('newslist.html.twig', array(
                'title' => 'Latest news',
                'newslist' => $app['db']->getLatestNews($app['user']),
            ));
        });

        $controllers->get('/login', function(Lamer $app) {
            return $app['twig']->render('login.html.twig', array(
                'title' => 'Login',
            ));
        });

        $controllers->get('/logout', function(Lamer $app, Request $request) {
            $apisecret = $request->get('apisecret');

            if (isset($app['user']) && Helpers::verifyApiSecret($app['user'], $apisecret)) {
                $app['db']->updateAuthToken($app['user']['id']);
            }

             return $app->redirect('/');
        });

        $controllers->get('/submit', function(Lamer $app) {
            return $app['twig']->render('submit_news.html.twig', array(
                'title' => 'Submit a new story',
            ));
        });

        $controllers->get('/news/{newsID}', function(Lamer $app, $newsID) {
            $user = $app['user'] ?: array();
            list($news) = $app['db']->getNewsByID($user, array($newsID));

            if (!$news) {
                return $app->abort(404, 'This news does not exist.');
            }

            return $app['twig']->render('news.html.twig', array(
                'title' => $news['title'],
                'news' => $news,
                'user' => $app['db']->getUserByID($news['user_id']),
                'comments' => $app['db']->getNewsComments($user, $news),
            ));
        });

        $controllers->get('/reply/{newsID}/{commentID}', function(Lamer $app, $newsID, $commentID) {
            // ...
        });

        $controllers->get('/editcomment/{newsID}/{commentID}', function(Lamer $app, $newsID, $commentID) {
            // ...
        });

        $controllers->get('/editnews/{newsID}', function(Lamer $app, $newsID) {
            // ...
        });

        $controllers->get('/user/{username}', function(Lamer $app, $username) {
            $user = $app['db']->getUserByUsername($username);

            if (!$user) {
                return $app->abort(404, 'Non existing user');
            }

            return $app['twig']->render('userprofile.html.twig', array(
                'title' => $username,
                'user' => $user,
                'user_counters' => $app['db']->getUserCounters($user),
            ));
        });

        return $controllers;
    }
}
