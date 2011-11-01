<?php

/*
 * This file is part of the Alpaca application.
 *
 * (c) Daniele Alessandri <suppakilla@gmail.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Alpaca;

use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Shared helpers for the application.
 *
 * @author Daniele Alessandri <suppakilla@gmail.com>
 */
class Helpers
{
    /**
     * Generates a random ID.
     *
     * @return string
     */
    public static function generateRandom()
    {
        if (!@is_readable('/dev/urandom')) {
            throw new \Exception("Cannot generate a random ID (Unreadable /dev/urandom)");
        }

        $resource = fopen('/dev/urandom', 'r');
        $urandom = fread($resource, 20);
        fclose($resource);

        return bin2hex($urandom);
    }

    /**
     * Generates a key from password and salt using the PBKDF2 algorithm.
     *
     * @link http://www.ietf.org/rfc/rfc2898.txt
     * @link http://gist.github.com/1162409
     *
     * @param string $password Password.
     * @param string $salt Salt.
     * @param int $keyLength Length of the derived key.
     * @param int $iterations Number of iterations.
     * @param string $algorithm Hash algorithm.
     * @return string
    */
    public static function pbkdf2($password, $salt, $keyLength = 160, $iterations = 1000, $algorithm = 'sha1')
    {
        $derivedKey = '';

        for ($blockPos = 1; $blockPos < $keyLength; $blockPos++) {
            $block = $hmac = hash_hmac($algorithm, $salt . pack('N', $blockPos), $password, true);

            for ($i = 1; $i < $iterations; $i++) {
                $block ^= ($hmac = hash_hmac($algorithm, $hmac, $password, true));
            }

            $derivedKey .= $block;
        }

        return bin2hex(substr($derivedKey, 0, $keyLength));
    }

    /**
     * Verifies the validity of a request by comparing the passed api secret
     * key with the one stored for the specified user.
     *
     * @param array $user Logged-in user details.
     * @param string $apisecret Token.
     */
    public static function verifyApiSecret(Array $user, $apisecret)
    {
        if (!isset($user) || !isset($user['apisecret'])) {
            return false;
        }
        return $user['apisecret'] === $apisecret;
    }

    /**
     * Returns the host part from the URL of a news item, if present.
     *
     * @param array $news News item details.
     * @return string
     */
    public static function getNewsDomain(Array $news)
    {
        if (strpos($news['url'], 'text://') === 0) {
            return;
        }
        return parse_url($news['url'], PHP_URL_HOST);
    }

    /**
     * Returns the text excerpt from a text:// URL of a news item.
     *
     * @param array $news News item details.
     * @return string
     */
    public static function getNewsText(Array $news)
    {
        if (strpos($news['url'], 'text://') !== 0) {
            return;
        }
        return substr($news['url'], strlen('text://'));
    }

    /**
     * Verifies if the request for the user is valid.
     *
     * @param array $user User details.
     * @param string $apisecret API secret token.
     * @param string $response Error message on invalid requests.
     * @return boolean
     */
    public static function isRequestValid(Array $user, $apisecret, &$response)
    {
        if (!$user) {
            $response = Helpers::apiError('Not authenticated.');
            return false;
        }
        if (!Helpers::verifyApiSecret($user, $apisecret)) {
            $response = Helpers::apiError('Wrong form secret.');
            return false;
        }

        return true;
    }

    /**
     * Returns the full URL for the specified path depending on the request.
     *
     * @param string $path Absolute path.
     * @return string
     */
    public static function getSiteURL(Request $request, $path = '/')
    {
        return $request->getUriForPath($path);
    }

    /**
     * Generates the response payload for an API call when it is successful.
     *
     * @param array $response Other values that compose the response.
     * @return string
     */
    public static function apiOK(Array $response = array())
    {
        $json = json_encode(array_merge($response, array('status' => 'ok')));

        return new Response($json, 200, array(
            'Content-Type' => 'application/json',
        ));
    }

    /**
     * Generates the response payload for an API call when it fails.
     *
     * @param string $error Error message.
     * @param array $response Other values that compose the response.
     * @return string
     */
    public static function apiError($error, Array $response = array())
    {
        $json = json_encode(array_merge($response, array(
            'status' => 'err',
            'error' => $error,
        )));

        return new Response($json, 200, array(
            'Content-Type' => 'application/json',
        ));
    }

    /**
     * Checks if a comment has been voted by the specified user and returns
     * which kind of vote has been given, or FALSE.
     *
     * @param array $user User details.
     * @param array $comment Comment details.
     * @param string $vote Type of vote (either up or down)
     * @return mixed
     */
    public static function commentVoted(Array $user, Array $comment)
    {
        if (!$user) {
            return false;
        }

        $votes = isset($comment['up']) ? $comment['up'] : array();
        if (in_array($user['id'], $votes)) {
            return 'up';
        }

        $votes = isset($comment['down']) ? $comment['down'] : array();
        if (in_array($user['id'], $votes)) {
            return 'down';
        }

        return false;
    }

    /**
     * Returns the details for a generic deleted user.
     *
     * @return array
     */
    public static function getDeletedUser() {
        return array('username' => 'deleted_user', 'email' => '', 'id' => -1);
    }
}
