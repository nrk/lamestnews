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

use Alpaca\Helpers as H;
use Silex\Application;
use Symfony\Component\HttpFoundation\Request;
use Silex\ControllerProviderInterface;
use Silex\ControllerCollection;

/**
 * Defines methods and routes exposing the public API of the application.
 *
 * @author Daniele Alessandri <suppakilla@gmail.com>
 */
class ApiController implements ControllerProviderInterface
{
    /**
     * {@inheritdoc}
     */
    public function connect(Application $app)
    {
        $controllers = new ControllerCollection();

        $controllers->get('/login', function(Application $app, Request $request) {
            $username = $request->get('username');
            $password = $request->get('password');

            if (!strlen(trim($username)) || !strlen(trim($password))) {
                return H::apiError('Username and password are two required fields.');
            }

            @list($auth, $apisecret) = $app['alpaca']->verifyUserCredentials($username, $password);

            if (!isset($auth)) {
                return H::apiError('No match for the specified username / password pair.');
            }

            return H::apiOK(array('auth' => $auth, 'apisecret' => $apisecret));
        });

        $controllers->post('/logout', function(Application $app, Request $request) {
            if (!H::isRequestValid($app['user'], $request->get('apisecret'), $error)) {
                return $error;
            }

            $app['alpaca']->updateAuthToken($app['user']['id']);

            return H::apiOK();
        });

        $controllers->post('/create_account', function(Application $app, Request $request) {
            $alpaca = $app['alpaca'];
            $username = $request->get('username');
            $password = $request->get('password');

            if (!strlen(trim($username)) || !strlen(trim($password))) {
                return H::apiError('Username and password are two required fields.');
            }

            if ($alpaca->rateLimited(3600 * 15, array('create_user', $request->getClientIp()))) {
                return H::apiError('Please wait some time before creating a new user.');
            }

            if (strlen($password) < ($minPwdLen = $alpaca->getOption('password_min_length'))) {
                return H::apiError("Password is too short. Min length: $minPwdLen");
            }

            $authToken = $alpaca->createUser($username, $password);
            if (!$authToken) {
                return H::apiError('Username is busy. Please select a different one.');
            }

            return H::apiOK(array('auth' => $authToken));
        });

        $controllers->post('/submit', function(Application $app, Request $request) {
            if (!H::isRequestValid($app['user'], $request->get('apisecret'), $error)) {
                return $error;
            }

            $alpaca = $app['alpaca'];
            $newsID = $request->get('news_id');
            $title = $request->get('title');
            $url = $request->get('url');
            $text = $request->get('text');

            // We can have an empty url or an empty first comment, but not both.
            if (empty($newsID) || empty($title) || (!strlen(trim($url)) && !strlen(trim($text)))) {
                return H::apiError('Please specify a news title and address or text.');
            }

            // Make sure the news has an accepted URI scheme (only http or https for now).
            if (!empty($url)) {
                $scheme = parse_url($url, PHP_URL_SCHEME);
                if ($scheme !== 'http' && $scheme !== 'https') {
                    return H::apiError('We only accept http:// and https:// news.');
                }
            }

            if ($newsID == -1) {
                if (($eta = $alpaca->getNewPostEta($app['user'])) > 0) {
                    return H::apiError("You have submitted a story too recently, please wait $eta seconds.");
                }
                $newsID = $alpaca->insertNews($title, $url, $text, $app['user']['id']);
            }
            else {
                $newsID = $alpaca->editNews($app['user'], $newsID, $title, $url, $text);
                if (!$newsID) {
                    return H::apiError('Invalid parameters, news too old to be modified or URL recently posted.');
                }
            }

            return H::apiOK(array('news_id' => $newsID));
        });

        $controllers->post('/delnews', function(Application $app, Request $request) {
            if (!H::isRequestValid($app['user'], $request->get('apisecret'), $error)) {
                return $error;
            }

            $newsID = $request->get('news_id');

            if (empty($newsID)) {
                return H::apiError('Please specify a news title.');
            }
            if (!$app['alpaca']->deleteNews($app['user'], $newsID)) {
                return H::apiError('News too old or wrong ID/owner.');
            }

            return H::apiOK(array('news_id' => -1));
        });

        $controllers->post('/votenews', function(Application $app, Request $request) {
            if (!H::isRequestValid($app['user'], $request->get('apisecret'), $error)) {
                return $error;
            }

            $newsID = $request->get('news_id');
            $voteType = $request->get('vote_type');

            if (empty($newsID) || ($voteType !== 'up' && $voteType !== 'down')) {
                return H::apiError('Missing news ID or invalid vote type.');
            }

            if ($app['alpaca']->voteNews($newsID, $app['user'], $voteType, $error) === false) {
                return H::apiError($error);
            }

            return H::apiOK();
        });

        $controllers->post('/postcomment', function(Application $app, Request $request) {
            if (!H::isRequestValid($app['user'], $request->get('apisecret'), $error)) {
                return $error;
            }

            $newsID = $request->get('news_id');
            $commentID = $request->get('comment_id');
            $parentID = $request->get('parent_id');
            $comment = $request->get('comment');

            if (empty($newsID) || empty($commentID) || empty($parentID) || !isset($comment)) {
                return H::apiError('Missing news_id, comment_id, parent_id, or comment parameter.');
            }

            $info = $app['alpaca']->handleComment($app['user'], $newsID, $commentID, $parentID, $comment);

            if (!$info) {
                return H::apiError('Invalid news, comment, or edit time expired.');
            }

            return H::apiOK(array(
                'op' => $info['op'],
                'comment_id' => $info['comment_id'],
                'parent_id' => $parentID,
                'news_id' => $newsID,
            ));
        });

        $controllers->post('/votecomment', function(Application $app, Request $request) {
            if (!H::isRequestValid($app['user'], $request->get('apisecret'), $error)) {
                return $error;
            }

            $compositeID = $request->get('comment_id');
            $voteType = $request->get('vote_type');

            if (!preg_match('/^\d+-\d+$/', $compositeID) || ($voteType !== 'up' && $voteType !== 'down')) {
                return H::apiError('Missing or invalid comment ID or invalid vote type.');
            }

            list($newsID, $commentID) = explode('-', $compositeID);
            if (!$app['alpaca']->voteComment($app['user'], $newsID, $commentID, $voteType)) {
                return H::apiError('Invalid parameters or duplicated vote.');
            }

            return H::apiOK(array(
                'comment_id' => $compositeID,
            ));
        });

        $controllers->post('/updateprofile', function(Application $app, Request $request) {
            if (!H::isRequestValid($app['user'], $request->get('apisecret'), $error)) {
                return $error;
            }

            $about = $request->get('about');
            $email = $request->get('email');
            $password = $request->get('password');

            $attributes = array(
                'about' => $about,
                'email' => $email,
            );

            if (($pwdLen = strlen($password)) > 0) {
                if ($pwdLen < ($minPwdLen = $app['alpaca']->getOption('password_min_length'))) {
                    return H::apiError("Password is too short. Min length: $minPwdLen");
                }
                $attributes['password'] = H::pbkdf2($password, $app['user']['salt']);
            }

            $app['alpaca']->updateUserProfile($app['user'], $attributes);

            return H::apiOK();
        });

        $controllers->get('/getnews/{sort}/{start}/{count}', function(Application $app, $sort, $start, $count) {
            $alpaca = $app['alpaca'];

            if ($sort !== 'latest' && $sort !== 'top') {
                return H::apiError('Invalid sort parameter');
            }
            if ($count > $alpaca->getOption('api_max_news_count')) {
                return H::apiError('Count is too big');
            }
            if ($start < 0) {
                $start = 0;
            }

            $newslist = $alpaca->{"get{$sort}News"}($app['user'], $start, $count);
            foreach ($newslist['news'] as &$news) {
                unset($news['rank'], $news['score'], $news['user_id']);
            }

            return H::apiOK(array(
                'news' => $newslist['news'],
                'count' => $newslist['count'],
            ));
        });

        $controllers->get('/getcomments/{newsID}', function(Application $app, $newsID) {
            $alpaca = $app['alpaca'];
            $user = $app['user'];

            @list($news) = $alpaca->getNewsByID($user, $newsID);
            if (!$news) {
                return H::apiError('Wrong news ID.');
            }

            $topcomments = array();
            $thread = $alpaca->getNewsComments($user, $news);

            foreach ($thread as $parentID => &$replies) {
                if ($parentID == -1) {
                    $topcomments = &$replies;
                }

                foreach ($replies as &$reply) {
                    $user = $alpaca->getUserByID($reply['user_id']) ?: H::getDeletedUser();

                    $reply['username'] = $user['username'];

                    if (isset($thread[$reply['id']])) {
                        $reply['replies'] = &$thread[$reply['id']];
                    }
                    else {
                        $reply['replies'] = array();
                    }

                    if (!H::commentVoted($user, $reply)) {
                        unset($reply['voted']);
                    }

                    if (isset($reply['up'])) {
                        $reply['up'] = count($reply['up']);
                    }
                    if (isset($reply['down'])) {
                        $reply['down'] = count($reply['down']);
                    }

                    unset(
                        $reply['user'], $reply['id'], $reply['thread_id'],
                        $reply['score'], $reply['parent_id'], $reply['user_id']
                    );
                }
            }

            return H::apiOK(array(
                'comments' => $topcomments,
            ));
        });

        return $controllers;
    }
}
