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
     * Updates the authentication token for the specified users with a new one,
     * effectively invalidating the current sessions for that user.
     *
     * @param string $userID ID of a registered user.
     * @return string
     */
    public function updateAuthToken($userID);

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
     * @param array $user Current user.
     * @return array
     */
    public function getTopNews(Array $user = null);

    /**
     * Gets the list of the latest news in chronological order.
     *
     * @param array $user Current user.
     * @return array
     */
    public function getLatestNews(Array $user = null);

    /**
     * Retrieves one or more news items using their IDs.
     *
     * @param array $user Details of the current user.
     * @param string|array $newsIDs One or multiple news IDs.
     * @param boolean $updateRank Specify if the rank of news should be updated.
     * @return mixed
     */
    public function getNewsByID(Array $user, $newsIDs, $updateRank = false);

    /**
     * Adds a new news item.
     *
     * @param string $title Title of the news.
     * @param string $url URL of the news.
     * @param string $text Text of the news.
     * @param string $userID User that sumbitted the news.
     * @return string
     */
    public function insertNews($title, $url, $text, $userID);

    /**
     * Edit an already existing news item.
     *
     * @param string $newsID ID of the news item.
     * @param string $title Title of the news.
     * @param string $url URL of the news.
     * @param string $text Text of the news.
     * @param string $userID User that sumbitted the news.
     * @return string
     */
    public function editNews($newsID, $title, $url, $text, $userID);

    /**
     * Upvotes or downvotes the specified news item.
     *
     * The function ensures that:
     *   1) The vote is not duplicated.
     *   2) The karma is decreased for the voting user, accordingly to the vote type.
     *   3) The karma is transferred to the author of the post, if different.
     *   4) The news score is updated.
     *
     * It returns the news rank if the vote was inserted, or false upon failure.
     *
     * @param string $newsID ID of the news being voted.
     * @param string $userID ID of the voting user.
     * @param string $type 'up' for upvoting a news item.
     *                     'down' for downvoting a news item.
     * @return mixed
     */
    public function voteNews($newsID, $userID, $type);
}
