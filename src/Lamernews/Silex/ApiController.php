<?php

namespace Lamernews\Silex;

use Silex\Application as Lamer;
use Symfony\Component\HttpFoundation\Request;
use Silex\ControllerProviderInterface;
use Silex\ControllerCollection;
use Lamernews\Helpers;

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
    public function connect(Lamer $app)
    {
        $controllers = new ControllerCollection();

        $controllers->get('/login', function(Lamer $app, Request $request) {
            $username = $request->get('username');
            $password = $request->get('password');

            @list($auth, $apisecret) = $app['db']->verifyUserCredentials($username, $password);

            if (!isset($auth)) {
                return Helpers::apiError('No match for the specified username / password pair.');
            }

            return Helpers::apiOK(array('auth' => $auth, 'apisecret' => $apisecret));
        });

        $controllers->post('/logout', function(Lamer $app, Request $request) {
            if (!Helpers::isRequestValid($app['user'], $request->get('apisecret'), $error)) {
                return $error;
            }

            $app['db']->updateAuthToken($app['user']['id']);

            return Helpers::apiOK();
        });

        $controllers->post('/create_account', function(Lamer $app, Request $request) {
            $username = $request->get('username');
            $password = $request->get('password');

            if (!isset($username, $password)) {
                return Helpers::apiError('Username and password are two required fields.');
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

        $controllers->post('/submit', function(Lamer $app, Request $request) {
            if (!Helpers::isRequestValid($app['user'], $request->get('apisecret'), $error)) {
                return $error;
            }

            $newsID = $request->get('news_id');
            $title = $request->get('title');
            $url = $request->get('url');
            $text = $request->get('text');

            // We can have an empty url or an empty first comment, but not both.
            if (!strlen($newsID) || !strlen($title) || (!strlen($url) && !strlen($text))) {
                return Helpers::apiError('Please specify a news title and address or text.');
            }

            // Make sure the news has an accepted URI scheme (only http or https for now).
            if (strlen($url)) {
                $scheme = parse_url($url, PHP_URL_SCHEME);
                if ($scheme !== 'http' && $scheme !== 'https') {
                    return Helpers::apiError('We only accept http:// and https:// news.');
                }
            }

            if ($newsID == -1) {
                $newsID = $app['db']->insertNews($title, $url, $text, $app['user']['id']);
            }
            else {
                $newsID = $app['db']->editNews($newsID, $title, $url, $text, $app['user']['id']);
                if (!$newsID) {
                    return Helpers::apiError('Invalid parameters, news too old to be modified or URL recently posted.');
                }
            }

            return Helpers::apiOK(array('news_id' => $newsID));
        });

        $controllers->post('/votenews', function(Lamer $app, Request $request) {
            if (!Helpers::isRequestValid($app['user'], $request->get('apisecret'), $error)) {
                return $error;
            }

            $newsID = $request->get('news_id');
            $voteType = $request->get('vote_type');

            if (!strlen($newsID) || ($voteType !== 'up' && $voteType !== 'down')) {
                return Helpers::apiError('Missing news ID or invalid vote type.');
            }

            if ($app['db']->voteNews($newsID, $app['user'], $voteType) === false) {
                return Helpers::apiError('Invalid parameters or duplicated vote.');
            }

            return Helpers::apiOK();
        });

        $controllers->post('/postcomment', function(Lamer $app, Request $request) {
            if (!Helpers::isRequestValid($app['user'], $request->get('apisecret'), $error)) {
                return $error;
            }

            $newsID = $request->get('news_id');
            $commentID = $request->get('comment_id');
            $parentID = $request->get('parent_id');
            $comment = $request->get('comment');

            if (!strlen($newsID) || !strlen($commentID) || !strlen($parentID)) {
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

        $controllers->post('/updateprofile', function(Lamer $app, Request $request) {
            if (!Helpers::isRequestValid($app['user'], $request->get('apisecret'), $error)) {
                return $error;
            }

            $about = $request->get('about');
            $email = $request->get('email');

            if (!strlen($about) || !strlen($email)) {
                return Helpers::apiError('Missing parameters.');
            }

            $attributes = array(
                'about' => $about,
                'email' => $email,
            );

            $app['db']->updateUserProfile($app['user'], $attributes);

            return Helpers::apiOK();
        });


        return $controllers;
    }
}
