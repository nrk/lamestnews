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
                return json_encode(array(
                    'status' => 'err',
                    'error' => 'No match for the specified username / password pair.',
                ));
            }

            return json_encode(array(
                'status' => 'ok',
                'auth' => $auth,
                'apisecret' => $apisecret,
            ));
        });

        $controllers->post('/logout', function(Lamer $app, Request $request) {
            $apisecret = $request->get('apisecret');

            if (!isset($app['user']) || !Helpers::verifyApiSecret($app['user'], $apisecret)) {
                return json_encode(array(
                    'status' => 'err',
                    'error' => 'Wrong auth credentials or API secret.',
                ));
            }

            $app['db']->updateAuthToken($app['user']['id']);

            return json_encode(array('status' => 'ok'));
        });

        $controllers->post('/create_account', function(Lamer $app, Request $request) {
            $username = $request->get('username');
            $password = $request->get('password');

            if (!isset($username, $password)) {
                return json_encode(array(
                    'status' => 'err',
                    'error' => 'Username and password are two required fields.',
                ));
            }

            if (strlen($password) < ($minPwdLen = $app['db']->getOption('password_min_length'))) {
                return json_encode(array(
                    'status' => 'err',
                    'error' => "Password is too short. Min length: $minPwdLen",
                ));
            }

            $authToken = $app['db']->createUser($username, $password);
            if (!$authToken) {
                return json_encode(array(
                    'status' => 'err',
                    'error' => 'Username is busy. Please select a different one.',
                ));
            }

            return json_encode(array(
                'status' => 'ok',
                'auth' => $authToken,
            ));
        });

        $controllers->post('/submit', function(Lamer $app, Request $request) {
            if (!$app['user']) {
                return json_encode(array(
                    'status' => 'err',
                    'error' => 'Not authenticated.',
                ));
            }

            $apisecret = $request->get('apisecret');
            if (!Helpers::verifyApiSecret($app['user'], $apisecret)) {
                return json_encode(array(
                    'status' => 'err',
                    'error' => 'Wrong form secret.',
                ));
            }

            $newsID = $request->get('news_id');
            $title = $request->get('title');
            $url = $request->get('url');
            $text = $request->get('text');

            // We can have an empty url or an empty first comment, but not both.
            if (!strlen($newsID) || !strlen($title) || (!strlen($url) && !strlen($text))) {
                return json_encode(array(
                    'status' => 'err',
                    'error' => 'Please specify a news title and address or text.',
                ));
            }

            // Make sure the news has an accepted URI scheme (only http or https for now).
            if (strlen($url)) {
                $scheme = parse_url($url, PHP_URL_SCHEME);
                if ($scheme !== 'http' && $scheme !== 'https') {
                    return json_encode(array(
                        'status' => 'err',
                        'error' => 'We only accept http:// and https:// news.',
                    ));
                }
            }

            if ($newsID == -1) {
                $newsID = $app['db']->insertNews($title, $url, $text, $app['user']['id']);
            }
            else {
                $newsID = $app['db']->editNews($newsID, $title, $url, $text, $app['user']['id']);
                if (!$newsID) {
                    return json_encode(array(
                        'status' => 'err',
                        'error' => 'Invalid parameters, news too old to be modified or URL recently posted',
                    ));
                }
            }

            return json_encode(array(
                'status' => 'ok',
                'news_id' => $newsID,
            ));
        });

        $controllers->post('/votenews', function(Lamer $app, Request $request) {
            if (!$app['user']) {
                return json_encode(array(
                    'status' => 'err',
                    'error' => 'Not authenticated.',
                ));
            }

            $apisecret = $request->get('apisecret');
            if (!Helpers::verifyApiSecret($app['user'], $apisecret)) {
                return json_encode(array(
                    'status' => 'err',
                    'error' => 'Wrong form secret.',
                ));
            }

            $newsID = $request->get('news_id');
            $voteType = $request->get('vote_type');

            if (!strlen($newsID) || ($voteType !== 'up' && $voteType !== 'down')) {
                return json_encode(array(
                    'status' => 'err',
                    'error' => 'Missing news ID or invalid vote type.',
                ));
            }

            if ($app['db']->voteNews($newsID, $app['user'], $voteType) === false) {
                return json_encode(array(
                    'status' => 'err',
                    'error' => 'Invalid parameters or duplicated vote.',
                ));
            }

            return json_encode(array('status' => 'ok'));
        });

        $controllers->post('/postcomment', function(Lamer $app) {
            // ...
        });

        $controllers->post('/updateprofile', function(Lamer $app, Request $request) {
            if (!$app['user']) {
                return json_encode(array(
                    'status' => 'err',
                    'error' => 'Not authenticated.',
                ));
            }

            $about = $request->get('about');
            $email = $request->get('email');

            if (!strlen($about) || !strlen($email)) {
                return json_encode(array(
                    'status' => 'err',
                    'error' => 'Missing parameters.',
                ));
            }

            $attributes = array(
                'about' => $about,
                'email' => $email,
            );

            $app['db']->updateUserProfile($app['user'], $attributes);

            return json_encode(array('status' => 'ok'));
        });


        return $controllers;
    }
}
