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

use Predis\Client;
use Predis\Pipeline\PipelineContext;

/**
 * Main abstractions to access the data of Lamer News stored in Redis.
 *
 * @author Daniele Alessandri <suppakilla@gmail.com>
 */
class RedisDatabase implements DatabaseInterface
{
    private $_redis;
    private $_options;

    /**
     * Initializes the database class.
     *
     * @param Client $redis Redis client used to access the database
     * @param array $options Array of options
     */
    public function __construct(Client $redis, Array $options = array())
    {
        $this->_redis = $redis;
        $this->_options = array_merge($this->getDefaults(), $options);
    }

    /**
     * Gets the default options to process the data stored in the database.
     *
     * @return array
     */
    protected function getDefaults()
    {
        return array(
            'password_min_length' => 8,

            // comments
            'comment_max_length' => 4096,
            'comment_edit_time' => 3600 * 2,
            'comment_reply_shift' => 30,

            // user
            'karma_increment_interval' => 3600 * 3,
            'karma_increment_amount' => 1,

            // news and ranking
            'news_age_padding' => 60 * 10,
            'top_news_per_page' => 30,
            'latest_news_per_page' => 100,
            'news_edit_time' => 60 * 15,
            'news_score_log_booster' => 5,
            'rank_aging_factor' => 1,
            'prevent_repost_time' => 3600 * 48,
        );
    }

    /**
     * {@inheritdoc}
     */
    public function createUser($username, $password)
    {
        $redis = $this->getRedis();

        if ($redis->exists("username.to.id:".strtolower($username))) {
            return;
        }

        $userID = $redis->incr('users.count');
        $authToken = Helpers::generateRandom();
        $salt = Helpers::generateRandom();

        $userDetails = array(
            'id' => $userID,
            'username' => $username,
            'salt' => $salt,
            'password' => Helpers::pbkdf2($password, $user['salt']),
            'ctime' => time(),
            'karma' => 10,
            'about' => '',
            'email' => '',
            'auth' => $authToken,
            'apisecret' => Helpers::generateRandom(),
            'flags' => '',
            'karma_incr_time' => time(),
        );

        $redis->hmset("user:$userID", $userDetails);
        $redis->set("username.to.id:".strtolower($username), $userID);
        $redis->set("auth:$authToken", $userID);

        return $authToken;
    }

    /**
     * {@inheritdoc}
     */
    public function getUserByID($userID)
    {
        return $this->getRedis()->("user:$userID");
    }

    /**
     * {@inheritdoc}
     */
    public function getUserByUsername($username)
    {
        $userID = $this->getRedis()->get('username.to.id:'.strtolower($username));
        if (!$userID) {
            return;
        }

        return $this->getUserByID($userID);
    }

    /**
     * {@inheritdoc}
     */
    public function verifyUserCredentials($username, $password)
    {
        $user = $this->getUserByUsername($username);
        if (!$user) {
            return;
        }

        $hashedPassword = Helpers::pbkdf2($password, $user['salt']);
        if ($user['password'] !== $hashedPassword) {
            return;
        }

        return array(
            $user['auth'],
            $user['apisecret'],
        );
    }

    /**
     * {@inheritdoc}
     */
    public function authenticateUser($authToken)
    {
        if (!$authToken) {
            return;
        }

        $userID = $this->getRedis()->get("auth:$authToken");
        if (!$userID) {
            return;
        }

        $user = $this->getRedis()->hgetall("user:$userID");
        if (!$user) {
            return;
        }

        return $user;
    }

    /**
     * {@inheritdoc}
     */
    public function getTopNews()
    {
        $newsIDs = $this->getRedis()
                        ->zrevrange('news.top', 0, $this->getOption(top_news_per_page) - 1);

        if (!$newsIDs) {
            return array();
        }

        $result = $this->getNewsByID($newsIDs, true);

        // Sort by rank before returning, since we adjusted ranks during iteration.
        usort($result, function($a, $b) {
            return (float) $b['rank'] - (float) $a['rank'];
        });

        return $result;
    }

    /**
     * {@inheritdoc}
     */
    public function getNewsByID(Array $user, $newsIDs, $updateRank = false)
    {
        if (!$newsIDs) {
            return array();
        }
        if (!is_array($newsIDs)) {
            $newsIDs = array((string) $newsIDs);
        }

        $news = $this->getRedis()->pipeline(function($pipe) {
            foreach ($newsIDs as $newsID) {
                $pipe->hgetall("news:$newsID");
            }
        });

        if (!$news) {
            return array();
        }

        $result = array();

        // Get all the news
        $this->getRedis()->pipeline(function($pipe) use (&$result) {
            foreach ($news as $n) {
                // Adjust rank if too different from the real-time value.
                $hash = array();
                foreach (array_chunk($n, 2) as $k => $v) {
                    $hash[$k] = $v;
                }

                if ($updateRank) {
                    $this->updateNewsRank($pipe, $hash);
                }

                $result[] = $hash;
            }
        });

        // Get the associated users information.
        $usernames = $this->getRedis()->pipeline(function($pipe) use($result) {
            foreach ($result as $n) {
                $pipe->hget("user:{$n['user_id']}", 'username');
            }
        });

        foreach ($result as $i => &$n) {
            $n['username'] = $usernames[$i];
        }

        // Load user's vote information if we are in the context of a
        // registered user.
        if (isset($user)) {
            $votes = $this->getRedis()->pipeline(function($pipe) use ($user) {
                foreach ($result as $n) {
                    $pipe->zscore("news.up:{$n['id']}", $user['id']);
                    $pipe->zscore("news.down:{$n['id']}", $user['id']);
                }
            });
            foreach ($result as $i => &$n) {
                if ($votes[$i * 2]) {
                    $n["voted"] = 'up';
                }
                else if ($votes[$i * 2 + 1]) {
                    $n["voted"] = 'down';
                }
            }
        }

        return $result;
    }

    /**
     * Updates the rank of a news item.
     *
     * @param PipelineContext $pipe Pipeline used to batch the update operations.
     * @param array $news Single news item.
     */
    protected function updateNewsRank(PipelineContext $pipe, Array &$news)
    {
        $realRank = $this->computeNewsRank($news);
        if (abs($realRank - (float) $news['rank']) > 0.001) {
            $pipe->hmset("news:{$news['id']}", 'rank', $realRank);
            $news['rank'] = (string) $realRank;
        }
    }

    /**
     * Computes the rank of a news item.
     *
     * @param array $news Single news item.
     * @return float
     */
    protected function computeNewsRank(Array $news)
    {
        $age = time() - (int) $news['ctime'] + $this->getOption('news_age_padding');
        return (((float) $news['score']) * 1000) / ($age * $this->getOption('rank_aging_factor'));
    }

    /**
     * Gets an option by its name or returns all the options.
     *
     * @param string $option Name of the option.
     * @return mixed
     */
    public function getOption($option = null)
    {
        if (!$option) {
            return $this->_options;
        }
        if (isset($this->_options[$option])) {
            return $this->_options[$option];
        }
    }

    /**
     * Gets the underlying Redis client used to interact with Redis.
     *
     * @return Client
     */
    public function getRedis()
    {
        return $this->_redis;
    }
}
