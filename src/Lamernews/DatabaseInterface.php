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
 * Interface for abstractions that access to a Lamer News data storage.
 *
 * @author Daniele Alessandri <suppakilla@gmail.com>
 */
interface DatabaseInterface
{
    /**
     * Creates a new user and returns a new autorization token.
     *
     * @param $username Username for the new user.
     * @param $password Password for the new user.
     * @return string
     */
    public function createUser($username, $password);

    /**
     * Fetches user details using the given user ID.
     *
     * @param string $userID ID of a registered user.
     * @return array
     */
    public function getUserByID($userID);

    /**
     * Fetches user details using the given username.
     *
     * @param string $username Username of a registered user.
     * @return array
     */
    public function getUserByUsername($username);

    /**
     * Verifies if the username / password pair identifies a user and
     * returns its authorization token and form secret.
     *
     * @param $username Username of a registered user.
     * @param $password Password of a registered user.
     * @return array
     */
    public function verifyUserCredentials($username, $password);

    /**
     * Returns the data for a logged in user.
     *
     * @param string $authToken Token used for user authentication.
     * @return array
     */
    public function authenticateUser($authToken);

    /**
     * Increments the user karma when a certain amout of time has passed.
     *
     * @param array $user User details.
     * @param int $increment Amount of the increment.
     * @param int $interval Interval of time in seconds.
     * @return boolean
     */
    public function incrementUserKarma(Array &$user, $increment, $interval);

    /**
     * Gets the list of the current top news items.
     *
     * @return array
     */
    public function getTopNews();

    /**
     * Retrieves one or more news items using their IDs.
     *
     * @param array $user Details of the current user.
     * @param string|array $newsIDs One or multiple news IDs.
     * @param boolean $updateRank Specify if the rank of news should be updated.
     * @return mixed
     */
    public function getNewsByID(Array $user, $newsIDs, $updateRank = false);
}
