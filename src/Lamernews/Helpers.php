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

}
