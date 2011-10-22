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
 * @author Daniele Alessandri <suppakilla@gmail.com>
 */
interface DatabaseInterface {
    /**
     * @return array
     */
    public function getTopNews();

    /**
     * @param array $user
     * @param string|array $newsIDs
     * @param boolean $updateRank
     * @return mixed
     */
    public function getNewsByID(Array $user, $newsIDs, $updateRank = false);
}
