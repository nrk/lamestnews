<?php

/*
 * This file is part of the Lamest application.
 *
 * (c) Daniele Alessandri <suppakilla@gmail.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Lamest\Twig;

use \Twig_Environment;
use \Twig_Extension;
use \Twig_Filter_Method;
use \Twig_Filter_Function;
use \Twig_Function_Function;

/**
 * Twig extension that provides common filters and functions used in
 * the templates that compose an Lamest-based website.
 *
 * @author Daniele Alessandri <suppakilla@gmail.com>
 */
class LamestExtension extends Twig_Extension
{
    const COMMENT_LINKS = '/((https?:\/\/|www\.)([-\w\.]+)+(:\d+)?(\/([\w\/_\.\-\%]*(\?\S+)?)?)?)/';

    /**
     * {@inheritdoc}
     */
    public function getName()
    {
        return 'lamest';
    }

    /**
     * {@inheritdoc}
     */
    public function getFilters()
    {
        return array(
            'to_int' => new Twig_Filter_Function('intval'),

            'commentize' => new Twig_Filter_Function(__CLASS__.'::renderCommentText', array(
                'needs_environment' => true,
                'is_safe' => array('html'),
            )),
        );
    }

    /**
     * {@inheritdoc}
     */
    public function getFunctions()
    {
        return array(
            'now' => new Twig_Function_Function('time'),

            'time_elapsed' => new Twig_Function_Function(__CLASS__.'::timeElapsed'),
            'gravatar' => new Twig_Function_Function(__CLASS__.'::getGravatarLink'),
            'news_editable' => new Twig_Function_Function(__CLASS__.'::isNewsEditable'),
            'comment_score' => new Twig_Function_Function(__CLASS__.'::commentScore'),
            'sort_comments'=> new Twig_Function_Function(__CLASS__.'::sortComments'),

            'full_url' => new Twig_Function_Function('Lamest\Helpers::getSiteURL'),
            'news_domain' => new Twig_Function_Function('Lamest\Helpers::getNewsDomain'),
            'news_text' => new Twig_Function_Function('Lamest\Helpers::getNewsText'),
        );
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
    public static function getGravatarLink($email, $options = array())
    {
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
     * Computes a score for the specified comment.
     *
     * @param array $comment Comment details.
     * @return int
     */
     public static function commentScore(Array $comment)
     {
         $upvotes = isset($comment['up']) ? count($comment['up']) : 0;
         $downvotes = isset($comment['down']) ? count($comment['down']) : 0;

         return $upvotes - $downvotes;
     }

    /**
    * Sort the passed list of comments.
    *
    * @param array $comments List of comments.
    * @return array
    */
    public static function sortComments(Array $comments)
    {
        uasort($comments, function($a, $b) {
            $ascore = LamestExtension::commentScore($a);
            $bscore = LamestExtension::commentScore($b);
            if ($ascore == $bscore) {
                return $a['ctime'] != $b['ctime'] ? ($b['ctime'] < $a['ctime'] ? -1 : 1) : 0;
            }
            return $bscore < $ascore ? -1 : 1;
        });

        return $comments;
    }

    /**
     * Callback used by preg_replace_callback to transform URL occurrences
     * in a string into HTML links.
     *
     * The returned string MUST NOT be escaped.
     *
     * @param array $matches Matches from preg_replace_callback.
     * @return string
     */
    protected static function linkifierCallback(Array $matches)
    {
        $url = $matches[0];
        $dot = '';
        if ($url[strlen($url) - 1] === '.') {
            $url = substr($url, 0, -1);
            $dot = '.';
        }
        return "<a href=\"$url\">$url</a>$dot";
    }

    /**
     * Escapes text for output with some additional processing.
     *
     * @param Twig_Environment $env Twig environment.
     * @param string $text Text to parse and escape.
     * @return string
     */
    public static function renderCommentText($env, $text)
    {
        // Escape HTML first using Twig's standard escape filter.
        $escaper = $env->getFilter('escape')->compile();
        $text = $escaper($env, $text);

        // Transform URLs in text into HTML links.
        $text = preg_replace_callback(self::COMMENT_LINKS, 'self::linkifierCallback', $text);

        return $text;
    }
}
