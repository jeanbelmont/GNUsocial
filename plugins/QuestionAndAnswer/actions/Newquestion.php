<?php
/**
 * StatusNet - the distributed open-source microblogging tool
 * Copyright (C) 2011, StatusNet, Inc.
 *
 * Add a new Question
 *
 * PHP version 5
 *
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU Affero General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU Affero General Public License for more details.
 *
 * You should have received a copy of the GNU Affero General Public License
 * along with this program.  If not, see <http://www.gnu.org/licenses/>.
 *
 * @category  QuestionAndAnswer
 * @package   StatusNet
 * @author    Zach Copley <zach@status.net>
 * @copyright 2011 StatusNet, Inc.
 * @license   http://www.fsf.org/licensing/licenses/agpl-3.0.html AGPL 3.0
 * @link      http://status.net/
 */
if (!defined('STATUSNET')) {
    // This check helps protect against security problems;
    // your code file can't be executed directly from the web.
    exit(1);
}

/**
 * Add a new Question
 *
 * @category  Plugin
 * @package   StatusNet
 * @author    Zach Copley <zach@status.net>
 * @copyright 2010 StatusNet, Inc.
 * @license   http://www.fsf.org/licensing/licenses/agpl-3.0.html AGPL 3.0
 * @link      http://status.net/
 */
class NewquestionAction extends Action
{
    protected $user        = null;
    protected $error       = null;
    protected $complete    = null;

    protected $question    = null;

    /**
     * Returns the title of the action
     *
     * @return string Action title
     */
    function title()
    {
        // TRANS: Title for Question page.
        return _m('New question');
    }

    /**
     * For initializing members of the class.
     *
     * @param array $argarray misc. arguments
     *
     * @return boolean true
     */
    function prepare($argarray)
    {
        parent::prepare($argarray);

        $this->user = common_current_user();

        if (empty($this->user)) {
            // TRANS: Client exception thrown trying to create a Question while not logged in.
            throw new ClientException(_m('You must be logged in to post a question.'),
                                      403);
        }

        if ($this->isPost()) {
            $this->checkSessionToken();
        }

        return true;
    }

    /**
     * Handler method
     *
     * @param array $argarray is ignored since it's now passed in in prepare()
     *
     * @return void
     */
    function handle($argarray=null)
    {
        parent::handle($argarray);

        if ($this->isPost()) {
            $this->newQuestion();
        } else {
            $this->showPage();
        }

        return;
    }

    /**
     * Add a new Question
     *
     * @return void
     */
    function newQuestion()
    {
        if ($this->boolean('ajax')) {
            StatusNet::setApi(true);
        }
        try {
            if (empty($this->question)) {
            // TRANS: Client exception thrown trying to create a Question without a question.
                throw new ClientException(_m('Question must have a question.'));
            }

            $saved = Question::saveNew(
                $this->user->getProfile(),
                $this->question
            );
        } catch (ClientException $ce) {
            $this->error = $ce->getMessage();
            $this->showPage();
            return;
        }

        if ($this->boolean('ajax')) {
            header('Content-Type: text/xml;charset=utf-8');
            $this->xw->startDocument('1.0', 'UTF-8');
            $this->elementStart('html');
            $this->elementStart('head');
            // TRANS: Page title after sending a notice.
            $this->element('title', null, _m('Notice posted'));
            $this->elementEnd('head');
            $this->elementStart('body');
            $this->showNotice($saved);
            $this->elementEnd('body');
            $this->elementEnd('html');
        } else {
            common_redirect($saved->bestUrl(), 303);
        }
    }

    /**
     * Output a notice
     *
     * Used to generate the notice code for Ajax results.
     *
     * @param Notice $notice Notice that was saved
     *
     * @return void
     */
    function showNotice($notice)
    {
        class_exists('NoticeList'); // @fixme hack for autoloader
        $nli = new NoticeListItem($notice, $this);
        $nli->show();
    }

    /**
     * Show the Question form
     *
     * @return void
     */
    function showContent()
    {
        if (!empty($this->error)) {
            $this->element('p', 'error', $this->error);
        }

        $form = new NewQuestionForm(
            $this,
            $this->question,
            $this->options
        );

        $form->show();

        return;
    }

    /**
     * Return true if read only.
     *
     * MAY override
     *
     * @param array $args other arguments
     *
     * @return boolean is read only action?
     */
    function isReadOnly($args)
    {
        if ($_SERVER['REQUEST_METHOD'] == 'GET' ||
            $_SERVER['REQUEST_METHOD'] == 'HEAD') {
            return true;
        } else {
            return false;
        }
    }
}

