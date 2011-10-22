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
        $this->_options = array_merge($options, $this->getDefaults());
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
