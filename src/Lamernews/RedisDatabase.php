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
 * @author Daniele Alessandri <suppakilla@gmail.com>
 */
class RedisDatabase implements DatabaseInterface {
    private $_redis;
    private $_options;

    /**
     * @param Predis\Client $client
     * @param array
     */
    public function __construct(Client $redis, Array $options = array())
    {
        $this->_redis = $redis;
        $this->_options = array_merge($this->getDefaults(), $options);
    }

    /**
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
        if (is_string($newsIDs)) {
            $newsIDs = array($newsIDs);
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
     * @param PipelineContext $pipe
     * @param array $n
     */
    protected function updateNewsRank(PipelineContext $pipe, Array $n)
    {
        $realRank = $this->computeNewsRank($n);
        if (abs($realRank - (float)$n['rank']) > 0.001) {
            $pipe->hmset("news:{$n['id']}", 'rank', $realRank);
            $n["rank"] = (string) $realRank;
        }
    }

    /**
     * @param array $news
     */
    protected function computeNewsRank(Array $news)
    {
        $age = time() - (int)$news["ctime"] + $this->getOption('news_age_padding');
        return (((float)$news['score']) * 1000) / ($age * $this->getOption('rank_aging_factor'));
    }

    /**
     * @param string $option
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
     * @return Predis\Client
     */
    public function getRedis()
    {
        return $this->_redis;
    }
}
