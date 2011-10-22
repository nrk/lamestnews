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
