<?php
/**
 * Data class to mark a notice as a question
 *
 * PHP version 5
 *
 * @category QuestionAndAnswer
 * @package  StatusNet
 * @author   Zach Copley <zach@status.net>
 * @license  http://www.fsf.org/licensing/licenses/agpl.html AGPLv3
 * @link     http://status.net/
 *
 * StatusNet - the distributed open-source microblogging tool
 * Copyright (C) 2011, StatusNet, Inc.
 *
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU Affero General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.     See the
 * GNU Affero General Public License for more details.
 *
 * You should have received a copy of the GNU Affero General Public License
 * along with this program. If not, see <http://www.gnu.org/licenses/>.
 */

if (!defined('STATUSNET')) {
    exit(1);
}

/**
 * For storing a question
 *
 * @category QuestionAndAnswer
 * @package  StatusNet
 * @author   Zach Copley <zach@status.net>
 * @license  http://www.fsf.org/licensing/licenses/agpl.html AGPLv3
 * @link     http://status.net/
 *
 * @see      DB_DataObject
 */

class Question extends Managed_DataObject
{
    public $__table = 'question'; // table name
    public $id;          // char(36) primary key not null -> UUID
    public $uri;
    public $profile_id;  // int -> profile.id
    public $title;       // text
    public $description; // text
    public $created;     // datetime

    /**
     * Get an instance by key
     *
     * This is a utility method to get a single instance with a given key value.
     *
     * @param string $k Key to use to lookup (usually 'user_id' for this class)
     * @param mixed  $v Value to lookup
     *
     * @return User_greeting_count object found, or null for no hits
     *
     */
    function staticGet($k, $v=null)
    {
        return Memcached_DataObject::staticGet('Question', $k, $v);
    }

    /**
     * Get an instance by compound key
     *
     * This is a utility method to get a single instance with a given set of
     * key-value pairs. Usually used for the primary key for a compound key; thus
     * the name.
     *
     * @param array $kv array of key-value mappings
     *
     * @return Bookmark object found, or null for no hits
     *
     */
    function pkeyGet($kv)
    {
        return Memcached_DataObject::pkeyGet('Question', $kv);
    }

    /**
     * The One True Thingy that must be defined and declared.
     */
    public static function schemaDef()
    {
        return array(
            'description' => 'Per-notice question data for QuestionAndAnswer plugin',
            'fields' => array(
                'id' => array('type' => 'char', 'length' => 36, 'not null' => true, 'description' => 'UUID'),
                'uri' => array('type' => 'varchar', 'length' => 255, 'not null' => true),
                'profile_id' => array('type' => 'int'),
                'title' => array('type' => 'text'),
                'description' => array('type' => 'text'),
                'created' => array('type' => 'datetime', 'not null' => true),
            ),
            'primary key' => array('id'),
            'unique keys' => array(
                'question_uri_key' => array('uri'),
            ),
        );
    }

    /**
     * Get a question based on a notice
     *
     * @param Notice $notice Notice to check for
     *
     * @return Question found question or null
     */
    function getByNotice($notice)
    {
        return self::staticGet('uri', $notice->uri);
    }

    function getNotice()
    {
        return Notice::staticGet('uri', $this->uri);
    }

    function bestUrl()
    {
        return $this->getNotice()->bestUrl();
    }

    /**
     * Get the answer from a particular user to this question, if any.
     *
     * @param Profile $profile
     *
     * @return Answer object or null
     */
    function getAnswer(Profile $profile)
    {
        $a = new Answer();
        $a->question_id = $this->id;
        $a->profile_id = $profile->id;
        $a->find();
        if ($a->fetch()) {
            return $a;
        } else {
            return null;
        }
    }

    function countAnswers()
    {
        $a = new Answer();
        
        $a->question_id = $this->id;
        return $a-count();
    }

    /**
     * Save a new question notice
     *
     * @param Profile $profile
     * @param string  $question
     * @param string  $title
     * @param string  $description
     * @param array   $option // and whatnot
     *
     * @return Notice saved notice
     */
    static function saveNew($profile, $question, $title, $description, $options = array())
    {
        $q = new Question();

        $q->id          = UUID::gen();
        $q->profile_id  = $profile->id;
        $q->title       = $title;
        $q->description = $description;

        if (array_key_exists('created', $options)) {
            $q->created = $options['created'];
        } else {
            $q->created = common_sql_now();
        }

        if (array_key_exists('uri', $options)) {
            $q->uri = $options['uri'];
        } else {
            $q->uri = common_local_url(
                'showquestion',
                array('id' => $q->id)
            );
        }

        common_log(LOG_DEBUG, "Saving question: $q->id $q->uri");
        $q->insert();

        // TRANS: Notice content creating a question.
        // TRANS: %1$s is the title of the question, %2$s is a link to the question.
        $content  = sprintf(
            _m('question: %1$s %2$s'),
            $title,
            $q->uri
        );
        
        $link = '<a href="' . htmlspecialchars($q->uri) . '">' . htmlspecialchars($title) . '</a>';
        // TRANS: Rendered version of the notice content creating a question.
        // TRANS: %s a link to the question as link description.
        $rendered = sprintf(_m('Question: %s'), $link);

        $tags    = array('question');
        $replies = array();

        $options = array_merge(
            array(
                'urls'        => array(),
                'rendered'    => $rendered,
                'tags'        => $tags,
                'replies'     => $replies,
                'object_type' => QuestionAndAnswerPlugin::QUESTION_OBJECT
            ),
            $options
        );

        if (!array_key_exists('uri', $options)) {
            $options['uri'] = $p->uri;
        }

        $saved = Notice::saveNew(
            $profile->id,
            $content,
            array_key_exists('source', $options) ?
            $options['source'] : 'web',
            $options
        );

        return $saved;
    }
}
