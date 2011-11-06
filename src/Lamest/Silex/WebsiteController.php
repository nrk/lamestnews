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

use Lamest\Helpers as H;
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
            $newslist = $app['lamest']->getTopNews($app['user']);

            return $app['twig']->render('newslist.html.twig', array(
                'title' => 'Top news',
                'head_title' => 'Top news',
                'newslist' => $newslist['news'],
            ));
        });

        $controllers->get('/rss', function(Application $app, Request $request) {
            $newslist = $app['lamest']->getLatestNews($app['user']);

            $rss = $app['twig']->render('newslist.rss.twig', array(
                'site_name' => 'Lamer News',
                'description' => 'Latest news',
                'newslist' => $newslist['news'],
            ));

            return new Response($rss, 200, array(
                'Content-Type' => 'text/xml',
            ));
        });

        $controllers->get('/latest/{start}', function(Application $app, $start) {
            $engine = $app['lamest'];
            $perpage = $engine->getOption('latest_news_per_page');
            $newslist = $engine->getLatestNews($app['user'], $start, $perpage);

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

            $engine = $app['lamest'];
            $perpage = $engine->getOption('latest_news_per_page');
            $newslist = $engine->getSavedNews($app['user'], $start);

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
            $user = $app['lamest']->getUserByUsername($username);

            if (!$user) {
                return $app->abort(404, 'Non existing user');
            }

            $perpage = $app['lamest']->getOption('user_comments_per_page');
            $comments = $app['lamest']->getUserComments($user, $start ?: 0, $perpage);

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
        })->value('start', 0);

        $controllers->get('/login', function(Application $app) {
            return $app['twig']->render('login.html.twig', array(
                'title' => 'Login',
            ));
        });

        $controllers->get('/logout', function(Application $app, Request $request) {
            $apisecret = $request->get('apisecret');

            if (isset($app['user']) && H::verifyApiSecret($app['user'], $apisecret)) {
                $app['lamest']->updateAuthToken($app['user']['id']);
            }

            return $app->redirect('/');
        });

        $controllers->get('/submit', function(Application $app, Request $request) {
            if (!$app['user']) {
                return $app->redirect('/login');
            }

            return $app['twig']->render('submit_news.html.twig', array(
                'title' => 'Submit a new story',
                'bm_url' => $request->get('u'),
                'bm_title' => $request->get('t'),
            ));
        });

        $controllers->get('/news/{newsID}', function(Application $app, $newsID) {
            $engine = $app['lamest'];
            @list($news) = $engine->getNewsByID($app['user'], $newsID);

            if (!$news) {
                return $app->abort(404, 'This news does not exist.');
            }

            return $app['twig']->render('news.html.twig', array(
                'title' => $news['title'],
                'news' => $news,
                'user' => $engine->getUserByID($news['user_id']),
                'comments' => $engine->getNewsComments($app['user'], $news),
            ));
        });

        $controllers->get('/comment/{newsID}/{commentID}', function(Application $app, $newsID, $commentID) {
            $engine = $app['lamest'];

            if (!$news = $engine->getNewsByID($app['user'], $newsID)) {
                return $app->abort(404, 'This news does not exist.');
            }

            if (!$comment = $engine->getComment($newsID, $commentID)) {
                return $app->abort(404, 'This comment does not exist.');
            }

            if (!$user = $engine->getUserByID($comment['user_id'])) {
                $user = H::getDeletedUser();
            }

            list($news) = $news;

            return $app['twig']->render('permalink_to_comment.html.twig', array(
                'title' => $news['title'],
                'news' => $news,
                'comment' => array_merge($comment, array(
                    'id' => $commentID,
                    'user' => $user,
                    'voted' => H::commentVoted($app['user'], $comment),
                )),
                'comments' => $engine->getNewsComments($app['user'], $news),
            ));
        });

        $controllers->get('/reply/{newsID}/{commentID}', function(Application $app, $newsID, $commentID) {
            $engine = $app['lamest'];

            if (!$app['user']) {
                return $app->redirect('/login');
            }

            if (!$news = $engine->getNewsByID($app['user'], $newsID)) {
                return $app->abort(404, 'This news does not exist.');
            }

            if (!$comment = $engine->getComment($newsID, $commentID)) {
                return $app->abort(404, 'This comment does not exist.');
            }

            if (!$user = $engine->getUserByID($comment['user_id'])) {
                $user = H::getDeletedUser();
            }

            list($news) = $news;

            return $app['twig']->render('reply_to_comment.html.twig', array(
                'title' => 'Reply to comment',
                'news' => $news,
                'comment' => array_merge($comment, array(
                    'id' => $commentID,
                    'user' => $user,
                    'voted' => H::commentVoted($app['user'], $comment),
                )),
            ));
        });

        $controllers->get('/replies', function(Application $app) {
            $engine = $app['lamest'];

            if (!$app['user']) {
                return $app->redirect('/login');
            }

            $comments = $engine->getReplies($app['user'], $engine->getOption('subthreads_in_replies_page') - 1, true);
            return $app['twig']->render('user_replies.html.twig', array(
                'title' => 'Your threads',
                'comments' => $comments,
            ));
        });

        $controllers->get('/editcomment/{newsID}/{commentID}', function(Application $app, $newsID, $commentID) {
            $engine = $app['lamest'];

            if (!$app['user']) {
                return $app->redirect('/login');
            }

            if (!$news = $engine->getNewsByID($app['user'], $newsID)) {
                return $app->abort(404, 'This news does not exist.');
            }

            if (!$comment = $engine->getComment($newsID, $commentID)) {
                return $app->abort(404, 'This comment does not exist.');
            }

            $user = $engine->getUserByID($comment['user_id']);
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
                    'voted' => H::commentVoted($app['user'], $comment),
                )),
            ));
        });

        $controllers->get('/editnews/{newsID}', function(Application $app, $newsID) {
            $engine = $app['lamest'];

            if (!$app['user']) {
                return $app->redirect('/login');
            }

            if (!$news = $engine->getNewsByID($app['user'], $newsID)) {
                return $app->abort(404, 'This news does not exist.');
            }

            list($news) = $news;

            $user = $engine->getUserByID($news['user_id']);
            if (!$user || $app['user']['id'] != $user['id']) {
                return $app->abort(500, 'Permission denied.');
            }

            $text = '';
            if (!H::getNewsDomain($news)) {
                $text = H::getNewsText($news);
                $news['url'] = '';
            }

            return $app['twig']->render('edit_news.html.twig', array(
                'title' => 'Edit news',
                'news' => $news,
                'text' => $text,
            ));
        });

        $controllers->get('/user/{username}', function(Application $app, $username) {
            $engine = $app['lamest'];
            $user = $engine->getUserByUsername($username);

            if (!$user) {
                return $app->abort(404, 'Non existing user');
            }

            return $app['twig']->render('userprofile.html.twig', array(
                'title' => $username,
                'user' => $user,
                'user_counters' => $engine->getUserCounters($user),
            ));
        });

        return $controllers;
    }
}
