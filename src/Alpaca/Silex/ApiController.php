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
                return Helpers::apiError('Username and password are two required fields.');
            }

            @list($auth, $apisecret) = $app['db']->verifyUserCredentials($username, $password);

            if (!isset($auth)) {
                return Helpers::apiError('No match for the specified username / password pair.');
            }

            return Helpers::apiOK(array('auth' => $auth, 'apisecret' => $apisecret));
        });

        $controllers->post('/logout', function(Application $app, Request $request) {
            if (!Helpers::isRequestValid($app['user'], $request->get('apisecret'), $error)) {
                return $error;
            }

            $app['db']->updateAuthToken($app['user']['id']);

            return Helpers::apiOK();
        });

        $controllers->post('/create_account', function(Application $app, Request $request) {
            $username = $request->get('username');
            $password = $request->get('password');

            if (!strlen(trim($username)) || !strlen(trim($password))) {
                return Helpers::apiError('Username and password are two required fields.');
            }

            if ($app['db']->rateLimited(3600 * 15, array('create_user', $request->getClientIp()))) {
                return Helpers::apiError('Please wait some time before creating a new user.');
            }

            if (strlen($password) < ($minPwdLen = $app['db']->getOption('password_min_length'))) {
                return Helpers::apiError("Password is too short. Min length: $minPwdLen");
            }

            $authToken = $app['db']->createUser($username, $password);
            if (!$authToken) {
                return Helpers::apiError('Username is busy. Please select a different one.');
            }

            return Helpers::apiOK(array('auth' => $authToken));
        });

        $controllers->post('/submit', function(Application $app, Request $request) {
            if (!Helpers::isRequestValid($app['user'], $request->get('apisecret'), $error)) {
                return $error;
            }

            $newsID = $request->get('news_id');
            $title = $request->get('title');
            $url = $request->get('url');
            $text = $request->get('text');

            // We can have an empty url or an empty first comment, but not both.
            if (empty($newsID) || empty($title) || (!strlen(trim($url)) && !strlen(trim($text)))) {
                return Helpers::apiError('Please specify a news title and address or text.');
            }

            // Make sure the news has an accepted URI scheme (only http or https for now).
            if (!empty($url)) {
                $scheme = parse_url($url, PHP_URL_SCHEME);
                if ($scheme !== 'http' && $scheme !== 'https') {
                    return Helpers::apiError('We only accept http:// and https:// news.');
                }
            }

            if ($newsID == -1) {
                if (($eta = $app['db']->getNewPostEta($app['user'])) > 0) {
                    return Helpers::apiError("You have submitted a story too recently, please wait $eta seconds.");
                }
                $newsID = $app['db']->insertNews($title, $url, $text, $app['user']['id']);
            }
            else {
                $newsID = $app['db']->editNews($app['user'], $newsID, $title, $url, $text);
                if (!$newsID) {
                    return Helpers::apiError('Invalid parameters, news too old to be modified or URL recently posted.');
                }
            }

            return Helpers::apiOK(array('news_id' => $newsID));
        });

        $controllers->post('/delnews', function(Application $app, Request $request) {
            if (!Helpers::isRequestValid($app['user'], $request->get('apisecret'), $error)) {
                return $error;
            }

            $newsID = $request->get('news_id');

            if (empty($newsID)) {
                return Helpers::apiError('Please specify a news title.');
            }
            if (!$app['db']->deleteNews($app['user'], $newsID)) {
                return Helpers::apiError('News too old or wrong ID/owner.');
            }

            return Helpers::apiOK(array('news_id' => -1));
        });

        $controllers->post('/votenews', function(Application $app, Request $request) {
            if (!Helpers::isRequestValid($app['user'], $request->get('apisecret'), $error)) {
                return $error;
            }

            $newsID = $request->get('news_id');
            $voteType = $request->get('vote_type');

            if (empty($newsID) || ($voteType !== 'up' && $voteType !== 'down')) {
                return Helpers::apiError('Missing news ID or invalid vote type.');
            }

            if ($app['db']->voteNews($newsID, $app['user'], $voteType, $error) === false) {
                return Helpers::apiError($error);
            }

            return Helpers::apiOK();
        });

        $controllers->post('/postcomment', function(Application $app, Request $request) {
            if (!Helpers::isRequestValid($app['user'], $request->get('apisecret'), $error)) {
                return $error;
            }

            $newsID = $request->get('news_id');
            $commentID = $request->get('comment_id');
            $parentID = $request->get('parent_id');
            $comment = $request->get('comment');

            if (empty($newsID) || empty($commentID) || empty($parentID) || !isset($comment)) {
                return Helpers::apiError('Missing news_id, comment_id, parent_id, or comment parameter.');
            }

            $info = $app['db']->handleComment($app['user'], $newsID, $commentID, $parentID, $comment);

            if (!$info) {
                return Helpers::apiError('Invalid news, comment, or edit time expired.');
            }

            return Helpers::apiOK(array(
                'op' => $info['op'],
                'comment_id' => $info['comment_id'],
                'parent_id' => $parentID,
                'news_id' => $newsID,
            ));
        });

        $controllers->post('/votecomment', function(Application $app, Request $request) {
            if (!Helpers::isRequestValid($app['user'], $request->get('apisecret'), $error)) {
                return $error;
            }

            $compositeID = $request->get('comment_id');
            $voteType = $request->get('vote_type');

            if (!preg_match('/^\d+-\d+$/', $compositeID) || ($voteType !== 'up' && $voteType !== 'down')) {
                return Helpers::apiError('Missing or invalid comment ID or invalid vote type.');
            }

            list($newsID, $commentID) = explode('-', $compositeID);
            if (!$app['db']->voteComment($app['user'], $newsID, $commentID, $voteType)) {
                return Helpers::apiError('Invalid parameters or duplicated vote.');
            }

            return Helpers::apiOK(array(
                'comment_id' => $compositeID,
            ));
        });

        $controllers->post('/updateprofile', function(Application $app, Request $request) {
            if (!Helpers::isRequestValid($app['user'], $request->get('apisecret'), $error)) {
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
                if ($pwdLen < ($minPwdLen = $app['db']->getOption('password_min_length'))) {
                    return Helpers::apiError("Password is too short. Min length: $minPwdLen");
                }
                $attributes['password'] = Helpers::pbkdf2($password, $app['user']['salt']);
            }

            $app['db']->updateUserProfile($app['user'], $attributes);

            return Helpers::apiOK();
        });

        return $controllers;
    }
}
