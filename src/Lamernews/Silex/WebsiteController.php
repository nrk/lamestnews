<?php

namespace Lamernews\Silex;

use Silex\Application as Lamer;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
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

        $controllers->get('/rss', function(Lamer $app, Request $request) {
            $rss = $app['twig']->render('newslist.rss.twig', array(
                'site_name' => 'Lamer News',
                'site_url' => $request->getUriForPath('/'),
                'description' => 'Latest news',
                'newslist' => $app['db']->getLatestNews(),
            ));

            return new Response($rss, 200, array(
                'Content-Type' => 'text/xml',
            ));
        });

        $controllers->get('/latest', function(Lamer $app) {
            return $app['twig']->render('newslist.html.twig', array(
                'title' => 'Latest news',
                'newslist' => $app['db']->getLatestNews($app['user']),
            ));
        });

        $controllers->get('/saved/{start}', function(Lamer $app, $start) {
            if (!$app['user']) {
                return $app->redirect('/login');
            }

            if (($start = (int)$start) < 0) {
                $start = 0;
            }

            $saved = $app['db']->getSavedNews($app['user'], $start);

            return $app['twig']->render('news_saved.html.twig', array(
                'title' => 'Saved news',
                'newslist' => $saved['news'],
                'pagination' => array(
                    'start' => $start,
                    'count' => $saved['count'],
                    'perpage' => $app['db']->getOption('saved_news_per_page'),
                ),
            ));
        });

        $controllers->get('/usercomments/{username}/{start}', function(Lamer $app, $username, $start) {
            $user = $app['db']->getUserByUsername($username);

            if (!$user) {
                return $app->abort(404, 'Non existing user');
            }

            $perpage = $app['db']->getOption('user_comments_per_page');
            $comments = $app['db']->getUserComments($app['user'], $user, $start ?: 0, $perpage);

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
            list($news) = $app['db']->getNewsByID($app['user'], array($newsID));

            if (!$news) {
                return $app->abort(404, 'This news does not exist.');
            }

            return $app['twig']->render('news.html.twig', array(
                'title' => $news['title'],
                'news' => $news,
                'user' => $app['db']->getUserByID($news['user_id']),
                'comments' => $app['db']->getNewsComments($app['user'], $news),
            ));
        });

        $controllers->get('/comment/{newsID}/{commentID}', function(Lamer $app, $newsID, $commentID) {
            if (!($news = $app['db']->getNewsByID($app['user'], $newsID))) {
                return $app->abort(404, 'This news does not exist.');
            }

            if (!($comment = $app['db']->getComment($newsID, $commentID))) {
                return $app->abort(404, 'This comment does not exist.');
            }

            if (!($user = $app['db']->getUserByID($comment['user_id']))) {
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
                'comments' => $app['db']->getNewsComments($app['user'], $news),
            ));
        });

        $controllers->get('/reply/{newsID}/{commentID}', function(Lamer $app, $newsID, $commentID) {
            if (!$app['user']) {
                return $app->redirect('/login');
            }

            if (!($news = $app['db']->getNewsByID($app['user'], $newsID))) {
                return $app->abort(404, 'This news does not exist.');
            }

            if (!($comment = $app['db']->getComment($newsID, $commentID))) {
                return $app->abort(404, 'This comment does not exist.');
            }

            if (!($user = $app['db']->getUserByID($comment['user_id']))) {
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

        $controllers->get('/editcomment/{newsID}/{commentID}', function(Lamer $app, $newsID, $commentID) {
            if (!$app['user']) {
                return $app->redirect('/login');
            }

            if (!($news = $app['db']->getNewsByID($app['user'], $newsID))) {
                return $app->abort(404, 'This news does not exist.');
            }

            if (!($comment = $app['db']->getComment($newsID, $commentID))) {
                return $app->abort(404, 'This comment does not exist.');
            }

            $user = $app['db']->getUserByID($comment['user_id']);
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

        $controllers->get('/editnews/{newsID}', function(Lamer $app, $newsID) {
            if (!$app['user']) {
                return $app->redirect('/login');
            }

            if (!($news = $app['db']->getNewsByID($app['user'], $newsID))) {
                return $app->abort(404, 'This news does not exist.');
            }

            list($news) = $news;

            $user = $app['db']->getUserByID($news['user_id']);
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
