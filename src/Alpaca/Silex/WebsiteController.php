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

use Alpaca\Helpers;
use Silex\Application;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Silex\ControllerProviderInterface;
use Silex\ControllerCollection;

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
    public function connect(Application $app)
    {
        $controllers = new ControllerCollection();

        $controllers->get('/', function(Application $app) {
            $newslist = $app['alpaca']->getTopNews($app['user']);

            return $app['twig']->render('newslist.html.twig', array(
                'title' => 'Top news',
                'head_title' => 'Top news',
                'newslist' => $newslist['news'],
            ));
        });

        $controllers->get('/rss', function(Application $app, Request $request) {
            $rss = $app['twig']->render('newslist.rss.twig', array(
                'site_name' => 'Lamer News',
                'site_url' => $request->getUriForPath('/'),
                'description' => 'Latest news',
                'newslist' => $app['alpaca']->getLatestNews(),
            ));

            return new Response($rss, 200, array(
                'Content-Type' => 'text/xml',
            ));
        });

        $controllers->get('/latest/{start}', function(Application $app, $start) {
            $alpaca = $app['alpaca'];
            $perpage = $alpaca->getOption('latest_news_per_page');
            $newslist = $alpaca->getLatestNews($app['user'], $start, $perpage);

            return $app['twig']->render('newslist.html.twig', array(
                'title' => 'Latest news',
                'newslist' => $newslist['news'],
                'pagination' => array(
                    'start' => $start,
                    'count' => $newslist['count'],
                    'perpage' => $perpage,
                    'linkbase' => 'latest',
                ),
            ));
        })->value('start', 0);

        $controllers->get('/saved/{start}', function(Application $app, $start) {
            if (!$app['user']) {
                return $app->redirect('/login');
            }

            $alpaca = $app['alpaca'];
            $perpage = $alpaca->getOption('latest_news_per_page');
            $newslist = $alpaca->getSavedNews($app['user'], $start);

            return $app['twig']->render('newslist.html.twig', array(
                'title' => 'Saved news',
                'head_title' => 'Your saved news',
                'newslist' => $newslist['news'],
                'pagination' => array(
                    'start' => $start,
                    'count' => $newslist['count'],
                    'perpage' => $perpage,
                    'linkbase' => 'saved',
                ),
            ));
        })->value('start', 0);

        $controllers->get('/usercomments/{username}/{start}', function(Application $app, $username, $start) {
            $user = $app['alpaca']->getUserByUsername($username);

            if (!$user) {
                return $app->abort(404, 'Non existing user');
            }

            $perpage = $app['alpaca']->getOption('user_comments_per_page');
            $comments = $app['alpaca']->getUserComments($user, $start ?: 0, $perpage);

            return $app['twig']->render('user_comments.html.twig', array(
                'title' => "$username comments",
                'comments' => $comments['list'],
                'username' => $username,
                'pagination' => array(
                    'start' => $start,
                    'count' => $comments['total'],
                    'perpage' => $perpage,
                ),
            ));
        });

        $controllers->get('/login', function(Application $app) {
            return $app['twig']->render('login.html.twig', array(
                'title' => 'Login',
            ));
        });

        $controllers->get('/logout', function(Application $app, Request $request) {
            $apisecret = $request->get('apisecret');

            if (isset($app['user']) && Helpers::verifyApiSecret($app['user'], $apisecret)) {
                $app['alpaca']->updateAuthToken($app['user']['id']);
            }

            return $app->redirect('/');
        });

        $controllers->get('/submit', function(Application $app) {
            if (!$app['user']) {
                return $app->redirect('/login');
            }

            return $app['twig']->render('submit_news.html.twig', array(
                'title' => 'Submit a new story',
            ));
        });

        $controllers->get('/news/{newsID}', function(Application $app, $newsID) {
            $alpaca = $app['alpaca'];
            list($news) = $alpaca->getNewsByID($app['user'], array($newsID));

            if (!$news) {
                return $app->abort(404, 'This news does not exist.');
            }

            return $app['twig']->render('news.html.twig', array(
                'title' => $news['title'],
                'news' => $news,
                'user' => $alpaca->getUserByID($news['user_id']),
                'comments' => $alpaca->getNewsComments($app['user'], $news),
            ));
        });

        $controllers->get('/comment/{newsID}/{commentID}', function(Application $app, $newsID, $commentID) {
            $alpaca = $app['alpaca'];

            if (!($news = $alpaca->getNewsByID($app['user'], $newsID))) {
                return $app->abort(404, 'This news does not exist.');
            }

            if (!($comment = $alpaca->getComment($newsID, $commentID))) {
                return $app->abort(404, 'This comment does not exist.');
            }

            if (!($user = $alpaca->getUserByID($comment['user_id']))) {
                $user = array('username' => 'deleted_user', 'email' => '', 'id' => -1);
            }

            list($news) = $news;

            return $app['twig']->render('permalink_to_comment.html.twig', array(
                'title' => $news['title'],
                'news' => $news,
                'comment' => array_merge($comment, array(
                    'id' => $commentID,
                    'user' => $user,
                    'voted' => Helpers::commentVoted($app['user'], $comment),
                )),
                'comments' => $alpaca->getNewsComments($app['user'], $news),
            ));
        });

        $controllers->get('/reply/{newsID}/{commentID}', function(Application $app, $newsID, $commentID) {
            $alpaca = $app['alpaca'];

            if (!$app['user']) {
                return $app->redirect('/login');
            }

            if (!($news = $alpaca->getNewsByID($app['user'], $newsID))) {
                return $app->abort(404, 'This news does not exist.');
            }

            if (!($comment = $alpaca->getComment($newsID, $commentID))) {
                return $app->abort(404, 'This comment does not exist.');
            }

            if (!($user = $alpaca->getUserByID($comment['user_id']))) {
                $user = array('username' => 'deleted_user', 'email' => '', 'id' => -1);
            }

            list($news) = $news;

            return $app['twig']->render('reply_to_comment.html.twig', array(
                'title' => 'Reply to comment',
                'news' => $news,
                'comment' => array_merge($comment, array(
                    'id' => $commentID,
                    'user' => $user,
                    'voted' => Helpers::commentVoted($app['user'], $comment),
                )),
            ));
        });

        $controllers->get('/replies', function(Application $app) {
            $alpaca = $app['alpaca'];

            if (!$app['user']) {
                return $app->redirect('/login');
            }

            $comments = $alpaca->getReplies($app['user'], $alpaca->getOption('subthreads_in_replies_page') - 1, true);
            return $app['twig']->render('user_replies.html.twig', array(
                'title' => 'Your threads',
                'comments' => $comments,
            ));
        });

        $controllers->get('/editcomment/{newsID}/{commentID}', function(Application $app, $newsID, $commentID) {
            $alpaca = $app['alpaca'];

            if (!$app['user']) {
                return $app->redirect('/login');
            }

            if (!($news = $alpaca->getNewsByID($app['user'], $newsID))) {
                return $app->abort(404, 'This news does not exist.');
            }

            if (!($comment = $alpaca->getComment($newsID, $commentID))) {
                return $app->abort(404, 'This comment does not exist.');
            }

            $user = $alpaca->getUserByID($comment['user_id']);
            if (!$user || $app['user']['id'] != $user['id']) {
                return $app->abort(500, 'Permission denied.');
            }

            list($news) = $news;

            return $app['twig']->render('edit_comment.html.twig', array(
                'title' => 'Edit comment',
                'news' => $news,
                'comment' => array_merge($comment, array(
                    'id' => $commentID,
                    'user' => $user,
                    'voted' => Helpers::commentVoted($app['user'], $comment),
                )),
            ));
        });

        $controllers->get('/editnews/{newsID}', function(Application $app, $newsID) {
            $alpaca = $app['alpaca'];

            if (!$app['user']) {
                return $app->redirect('/login');
            }

            if (!($news = $alpaca->getNewsByID($app['user'], $newsID))) {
                return $app->abort(404, 'This news does not exist.');
            }

            list($news) = $news;

            $user = $alpaca->getUserByID($news['user_id']);
            if (!$user || $app['user']['id'] != $user['id']) {
                return $app->abort(500, 'Permission denied.');
            }

            $text = '';
            if (!Helpers::getNewsDomain($news)) {
                $text = Helpers::getNewsText($news);
                $news['url'] = '';
            }

            return $app['twig']->render('edit_news.html.twig', array(
                'title' => 'Edit news',
                'news' => $news,
                'text' => $text,
            ));
        });

        $controllers->get('/user/{username}', function(Application $app, $username) {
            $alpaca = $app['alpaca'];
            $user = $alpaca->getUserByUsername($username);

            if (!$user) {
                return $app->abort(404, 'Non existing user');
            }

            return $app['twig']->render('userprofile.html.twig', array(
                'title' => $username,
                'user' => $user,
                'user_counters' => $alpaca->getUserCounters($user),
            ));
        });

        return $controllers;
    }
}
