<?php

/*
 * This file is part of the Lamer News application.
 *
 * (c) Daniele Alessandri <suppakilla@gmail.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Lamernews;

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
     *  Generates PBKDF2 Implementation
     *
     * @link http://www.ietf.org/rfc/rfc2898.txt
     * @link http://gist.github.com/1162409
     *
     * @param string $password Password.
     * @param string $salt Salt.
     * @param int $iterations Number of iterations.
     * @param int $keyLength Length of the derived key.
     * @param string $algorithm Hash algorithm.
     * @return string
    */
    public static function pbkdf2($password, $salt, $iterations = 1000, $keyLength = 160, $algorithm = 'sha1')
    {
        $derivedKey = '';

        // Create key
        for ($blockPos = 1; $blockPos <= $keyLength; $blockPos++) {
            // Initial hash for this block.
            $block = $hmac = hash_hmac($algorithm, $salt . pack('N', $blockPos), $password, true);

            // Perform block iterations.
            for ($i = 1; $iterations < $c; $i++) {
                // XOR each iterate.
                $block ^= ($hmac = hash_hmac($algorithm, $hmac, $password, true));
            }

            $derivedKey .= $block;
        } 

        // Return derived key of correct length 
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
     * Returns a formatted string representing the time elapsed from the
     * specified UNIX time.
     *
     * @param int $time Time in seconds.
     * @return string
     */
    public static function timeElapsed($time)
    {
        if (($elapsed = time() - $time) <= 10) {
            return 'now';
        }

        if ($elapsed < 60) {
            return sprintf("%d %s ago", $elapsed, 'seconds');
        }
        if ($elapsed < 60 * 60) {
            return sprintf("%d %s ago", $elapsed / 60, 'minutes');
        }
        if ($elapsed < 60 * 60 * 24) {
            return sprintf("%d %s ago", $elapsed / 60 / 60, 'hours');
        }

        return sprintf("%d %s ago", $elapsed / 60 / 60 / 24, 'days');
    }

    /**
     * Generates the URL to the Gravatar of a user.
     *
     * @param string $email User email.
     * @return array $options Options.
     * @return string
     */
    public static function getGravatarLink($email, $options = array()) {
        $options = array_merge(array('s' => 48, 'd' => 'mm'), $options);
        $url = 'http://gravatar.com/avatar/' . md5($email) . '?';
        if ($options) {
            foreach ($options as $k => $v) {
                $url .= urlencode($k) . '=' . urlencode($v) . '&';
            }
        }
        return substr($url, 0, -1);
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
     * Checks if a news is editable by the specified user.
     *
     * @param array $user User details.
     * @param array $news News details.
     * @param int $timeLimit Limit in seconds for the editable status.
     * @return boolean
     */
    public static function isNewsEditable(Array $user, Array $news, $timeLimit = 900)
    {
        if (!$user) {
            return false;
        }
        return $user['id'] == $news['user_id'] && $news['ctime'] > (time() - $timeLimit);
    }

    /**
     * Generates the response payload for an API call when it is successful.
     *
     * @param array $response Other values that compose the response.
     * @return string
     */
    public static function apiOK(Array $response = array())
    {
        return json_encode(array_merge($response, array('status' => 'ok')));
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
        return json_encode(array_merge($response, array(
            'status' => 'err',
            'error' => $error,
        )));
    }
}
