<?php
// This file is part of Moodle - http://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.
//
// Moodle is distributed in the hope that it will be useful,
// but WITHOUT ANY WARRANTY; without even the implied warranty of
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
// GNU General Public License for more details.
//
// You should have received a copy of the GNU General Public License
// along with Moodle.  If not, see <http://www.gnu.org/licenses/>.

/**
 * Provides support for the conversion of moodle1 backup to the moodle2 format
 *
 * based on original package Lesson
 * @package    mod
 * @subpackage lesson
 * @copyright  2011 Rossiani Wijaya <rwijaya@moodle.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 *
 * @package mod
 * @subpackage languagelesson
 * @copyright 2012 Carly Born <cborn@carleton.edu>
 * @license http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

require_once($CFG->dirroot . '/backup/converter/moodle1/lib.php');

/**
 * Lesson conversion handler
 */
class moodle1_mod_languagelesson_handler extends moodle1_mod_handler {
    // @var array of answers, when there are more that 4 answers, we need to fix <jumpto>.
    protected $answers;

    protected $answer;
    // @var stdClass a page object of the current page
    protected $page;
    // @var array of page objects to store entire pages, to help generate nextpageid and prevpageid in data
    protected $pages;
    // @var int a page id (previous)
    protected $prevpageid = 0;

    protected $attempts = array();

    protected $branches = array();

    protected $manattempts = array();

    protected $manattempt = null;

    protected $feedbacks = array();
    /** @var moodle1_file_manager */
    protected $fileman = null;

    /** @var int cmid */
    protected $moduleid = null;

    protected $contextid = null;

    /**
     * Declare the paths in moodle.xml we are able to convert
     *
     * The method returns list of {@link convert_path} instances.
     * For each path returned, the corresponding conversion method must be
     * defined.
     *
     * Note that the path /MOODLE_BACKUP/COURSE/MODULES/MOD/LESSON does not
     * actually exist in the file. The last element with the module name was
     * appended by the moodle1_converter class.
     *
     * @return array of {@link convert_path} instances
     */
    public function get_paths() {
        return array(
            new convert_path(
                'languagelesson', '/MOODLE_BACKUP/COURSE/MODULES/MOD/LANGUAGELESSON',
                array('renamefields' => array('usegrademax' => 'usemaxgrade') , )
            ),

            new convert_path(
                'languagelesson_page', '/MOODLE_BACKUP/COURSE/MODULES/MOD/LANGUAGELESSON/PAGES/PAGE',
                array(
                        'newfields' => array(
                            'contentsformat' => FORMAT_MOODLE,
                            'nextpageid' => 0, // Set to default to the next sequencial page in process_languagelesson_page().
                            'prevpageid' => 0,
                            'maxscore' =>0
                        ),
                )
            ),
            new convert_path(
                'languagelesson_pages', '/MOODLE_BACKUP/COURSE/MODULES/MOD/LANGUAGELESSON/PAGES'
            ),
            new convert_path(
                'languagelesson_answer', '/MOODLE_BACKUP/COURSE/MODULES/MOD/LANGUAGELESSON/PAGES/PAGE/ANSWERS/ANSWER',
                array(
                    'newfields' => array(
                        'answer_grade' => 0,
                        'answerformat' => 0,
                        'responseformat' => 0,
                    ),
                    'renamefields' => array(
                        'answertext' => 'answer_text',
                    ),
                )
            ),

            new convert_path('languagelesson_attempt', '/MOODLE_BACKUP/COURSE/MODULES/MOD/LANGUAGELESSON/PAGES/PAGE/ANSWERS/ANSWER/ATTEMPTS/ATTEMPT'),

            new convert_path('languagelesson_manattempt', '/MOODLE_BACKUP/COURSE/MODULES/MOD/LANGUAGELESSON/PAGES/PAGE/MANATTEMPTS/MANATTEMPT'),

            new convert_path('languagelesson_feedback', '/MOODLE_BACKUP/COURSE/MODULES/MOD/LANGUAGELESSON/PAGES/PAGE/MANATTEMPTS/MANATTEMPT/FEEDBACKS/FEEDBACK'),

            new convert_path('languagelesson_grades', '/MOODLE_BACKUP/COURSE/MODULES/MOD/LANGUAGELESSON/GRADES'),

            new convert_path('languagelesson_grade', '/MOODLE_BACKUP/COURSE/MODULES/MOD/LANGUAGELESSON/GRADES/GRADE'),

            new convert_path('languagelesson_timers', '/MOODLE_BACKUP/COURSE/MODULES/MOD/LANGUAGELESSON/TIMERS'),

            new convert_path('languagelesson_timer', '/MOODLE_BACKUP/COURSE/MODULES/MOD/LANGUAGELESSON/TIMERS/TIMER'),

            new convert_path('languagelesson_branch', '/MOODLE_BACKUP/COURSE/MODULES/MOD/LANGUAGELESSON/PAGES/PAGE/BRANCHES/BRANCH')
        );
    }

    /**
     * This is executed every time we have one /MOODLE_BACKUP/COURSE/MODULES/MOD/LESSON
     * data available
     */
    public function process_languagelesson($data) {
        global $contextid;

        // get the course module id and context id
        $instanceid     = $data['id'];
        $cminfo         = $this->get_cminfo($instanceid);
        $this->moduleid = $cminfo['id'];
        $contextid      = $this->converter->get_contextid(CONTEXT_MODULE, $this->moduleid);

        // get a fresh new file manager for this instance
        $this->fileman = $this->converter->get_file_manager($contextid, 'mod_languagelesson');

        // migrate referenced local media files
        if (!empty($data['mediafile']) and strpos($data['mediafile'], '://') === false) {
            $this->fileman->filearea = 'mediafile';
            $this->fileman->itemid   = 0;
            try {
                $this->fileman->migrate_file('course_files/'.$data['mediafile']);
            } catch (moodle1_convert_exception $e) {
                // the file probably does not exist
                $this->log('error migrating lesson mediafile', backup::LOG_WARNING, 'course_files/'.$data['mediafile']);
            }
        }

        // start writing lesson.xml
        $this->open_xml_writer("activities/languagelesson_{$this->moduleid}/languagelesson.xml");
        $this->xmlwriter->begin_tag('activity', array('id' => $instanceid, 'moduleid' => $this->moduleid,
            'modulename' => 'languagelesson', 'contextid' => $contextid));
        $this->xmlwriter->begin_tag('languagelesson', array('id' => $instanceid));

        // Convert element to compatible name.
        $data['lesson_grade'] = $data['grade'];
        unset($data['grade']);
        foreach ($data as $field => $value) {
            if ($field <> 'id') {
                $this->xmlwriter->full_tag($field, $value);
            }
        }
        return $data;
    }

    public function on_languagelesson_pages_start() {
        global $contextid;

        $this->xmlwriter->begin_tag('pages');
    }

    /**
     * This is executed every time we have one /MOODLE_BACKUP/COURSE/MODULES/mod/languagelesson/PAGES/PAGE
     * data available
     */
    public function process_languagelesson_page($data) {
        global $CFG, $contextid;

        require_once($CFG->dirroot.'/mod/languagelesson/locallib.php');

        // replay the upgrade step 2009120801
        if ($CFG->texteditors !== 'textarea') {
            $data['contents'] = text_to_html($data['contents'], false, false, true);
            $data['contentsformat'] = FORMAT_HTML;
        }

        // Convert course files embedded into the contents of the page.
        $this->fileman->component = 'mod_languagelesson';
        $this->fileman->itemid = $data['pageid'];
        $this->fileman->filearea = 'page_contents';
        $data['contents'] = moodle1_converter::migrate_referenced_files($data['contents'], $this->fileman);

        // store page in pages
        $this->page = new stdClass();
        $this->page->id = $data['pageid'];
        if ($data['qtype'] == LL_BRANCHTABLE) {
            $this->page->branchid = $data['pageid'];
        }
        unset($data['pageid']);
        $this->page->data = $data;
    }

    /**
     * This is executed every time we have one /MOODLE_BACKUP/COURSE/MODULES/mod/languagelesson/PAGES/PAGE/ANSWERS/ANSWER
     * data available
     */

    public function process_languagelesson_answer($data) {
        // replay the upgrade step 2010072003
        // convert element to compatible name
        if ($data['id']) {
            $flags = intval($data['flags']);
            if ($flags & 1) {
                $data['answer_text']  = text_to_html($data['answer_text'], false, false, true);
                $data['answerformat'] = FORMAT_HTML;
            }
            if ($flags & 2) {
                $data['response']       = text_to_html($data['response'], false, false, true);
                $data['responseformat'] = FORMAT_HTML;
            }

            // buffer for conversion of <jumpto> in line with
            // upgrade step 2010121400 from mod/lesson/db/upgrade.php
            $this->answer = new stdClass();
            $this->answer->data = $data;
            $this->answer->attempts = $this->attempts;
            $this->attempts = array();
            $this->answers[] = $this->answer;
            $this->answer = null;
        }
    }

    public function process_languagelesson_attempt($data) {
            $this->attempts[] = $data;
    }

    public function process_languagelesson_branch($data) {
        $this->branches[] = $data;
    }


    public function process_languagelesson_manattempt($data) {
        $this->manattempt = new stdClass();
        $this->manattempt->data = $data;
        $this->manattempt->feedbacks = $this->feedbacks;
        $this->feedbacks = array();
        $this->manattempts[] = $this->manattempt;

        $this->manattempt = null;
    }

    public function process_languagelesson_feedback($data) {
        $this->feedbacks[] = $data;
    }

    public function on_languagelesson_page_end() {
        $this->page->answers = $this->answers;
        $this->page->manattempts = $this->manattempts;
        $this->page->branches = $this->branches;
        $this->pages[] = $this->page;

        $firstbatch = count($this->pages) > 2;
        $nextbatch = count($this->pages) > 1 && $this->prevpageid != 0;

        if ( $firstbatch || $nextbatch ) { // We can write out 1 page atleast.
            if ($this->prevpageid == 0) {
                // start writing with n-2 page (relative to this on_languagelesson_page_end() call)
                $pg1 = $this->pages[1];
                $pg0 = $this->pages[0];
                $this->write_single_page_xml($pg0, 0, $pg1->id);
                $this->prevpageid = $pg0->id;
                array_shift($this->pages); // Bye bye page0.
            }

            $pg1 = $this->pages[0];
            // write pg1 referencing prevpageid and pg2
            $pg2 = $this->pages[1];

            $this->write_single_page_xml($pg1, $this->prevpageid, $pg2->id);
            $this->prevpageid = $pg1->id;
            array_shift($this->pages); // Throw written n-1th page.

        }

        $this->answers = array(); // Clear answers for the page ending. do not unset, object property will be missing.
        $this->page = null;
        $this->branches = array();
        $this->manattempts = array();
    }


    public function on_languagelesson_pages_end() {
        if ($this->pages) {
            if (isset($this->pages[1])) { // Write the case of only 2 pages.
                $this->write_single_page_xml($this->pages[0], $this->prevpageid, $this->pages[1]->id);
                $this->prevpageid = $this->pages[0]->id;
                array_shift($this->pages);
            }

            // write the remaining (first/last) single page
            $this->write_single_page_xml($this->pages[0], $this->prevpageid, 0);
        }
        $this->xmlwriter->end_tag('pages');
        // reset
        unset($this->pages);
        $this->prevpageid = 0;

    }

    public function on_languagelesson_grades_start() {
        $this->xmlwriter->begin_tag('grades');
    }

    public function process_languagelesson_grade($data) {
        // convert element name to a compatible one
        $data['grade_info'] = $data['grade_value'];
        unset($data['grade_value']);
        $this->write_xml('grade', $data);
    }


    public function on_languagelesson_grades_end() {
        $this->xmlwriter->end_tag('grades');
    }

    public function on_languagelesson_timers_start() {
        $this->xmlwriter->begin_tag('timers');
    }
    public function process_languagelesson_timer($data) {
        $this->write_xml('timer', $data);
    }

    public function on_languagelesson_timers_end() {
            $this->xmlwriter->end_tag('timers');
    }

    /**
     * This is executed when we reach the closing </MOD> tag of our 'lesson' path
     */
    public function on_languagelesson_end() {
        // finish writing lesson.xml
        $this->xmlwriter->end_tag('languagelesson');
        $this->xmlwriter->end_tag('activity');
        $this->close_xml_writer();

        // write inforef.xml
        $this->open_xml_writer("activities/languagelesson_{$this->moduleid}/inforef.xml");
        $this->xmlwriter->begin_tag('inforef');
        $this->xmlwriter->begin_tag('fileref');
        foreach ($this->fileman->get_fileids() as $fileid) {
            $this->write_xml('file', array('id' => $fileid));
        }
        $this->xmlwriter->end_tag('fileref');
        $this->xmlwriter->end_tag('inforef');
        $this->close_xml_writer();
    }

    /**
     *  writes out the given page into the open xml handle
     * @param type $page
     * @param type $prevpageid
     * @param type $nextpageid
     */
    protected function write_single_page_xml($page, $prevpageid=0, $nextpageid=0) {
        // mince nextpageid and prevpageid
        $page->data['nextpageid'] = $nextpageid;
        $page->data['prevpageid'] = $prevpageid;

        if ($page->data['qtype'] == 10) {
                $page->data['qtype'] = 9;
        }
        // write out each page data
        $this->xmlwriter->begin_tag('page', array('id' => $page->id));
        $answers = $page->answers;
        $numanswers = count($answers);

        if ($page->data['qtype'] == 5) {

            // do something to transform old cloze type answers to new one
            for ($i=0; $i< $numanswers; $i++) {
                array_push($answers, $answers[$i]);
                unset($answers[$i]);
            }
            $answers = array_values($answers);

            // real answer section transform
            for ($i=0; $i<$numanswers; $i++) {
                if ($answers[$i]->data['answer_text'] == null) {
                    unset($answers[$i]);
                } else {
                    $text = $answers[$i]->data['answer_text'];
                    $pattern = "|[0-9]*\|(.*)|";
                    preg_match($pattern, $text,  $matches);
                    $answers[$i]->data['answer_text'] = $matches[1];
                    $answers[$i]->data['jumpto'] = 0;
                }
            }

            /*
            // create empty records to sit in correct and wrong response records
            $dummyrecord = new stdClass;
            $dummyrecord->lessonid = $page->lessonid;
            $dummyrecord->pageid = $page->id;
            $dummyrecord->jumpto = 0;
            $dummyrecord->grade = 0;
            $dummyrecord->score = 0;
            $dummyrecord->flags = 0;
            $dummyrecord->timecreated = time();
            $dummyrecord->timemodified = time();
            $dummyrecord->answerformat = 0;
            $dummyrecord->responseformat = 0;

            array_unshift($answers, $dummyrecord);
            array_unshift($answers, $dummyrecord);
            */
            $this->change_cloze_type_content($page, $answers);
        }

        foreach ($page->data as $field => $value) {
            $this->xmlwriter->full_tag($field, $value);
        }

        // effectively on_languagelesson_answers_end(), where we write out answers for current page.

        $this->xmlwriter->begin_tag('answers');

        if ($numanswers) { // If there are any answers (possible there are none!).
            if ($numanswers > 3 && $page->data['qtype'] == 6) { // Fix only jumpto only for matching question types.
                if ($answers[0]->data['jumpto'] !== '0' || $answers[1]->data['jumpto'] !== '0') {
                    if ($answers[2]->data['jumpto'] !== '0') {
                        $answers[0]->data['jumpto'] = $answers[2]->data['jumpto'];
                        $answers[2]->data['jumpto'] = '0';
                    }
                    if ($answers[3]->data['jumpto'] !== '0') {
                        $answers[1]->data['jumpto'] = $answers[3]->data['jumpto'];
                        $answers[3]->data['jumpto'] = '0';
                    }
                }
            }

            foreach ($answers as $answer) {
                $this->xmlwriter->begin_tag('answer', array('id' => $answer->data['id']));
                foreach ($answer->data as $field => $value) {
                    $this->xmlwriter->full_tag($field, $value);
                }
                if ($answer->attempts) {
                    $this->write_attempts($answer->attempts);
                }
                $this->xmlwriter->end_tag('answer');
            }
        }

        $this->xmlwriter->end_tag('answers');
        if ($page->branches) {
                $this->write_branches($page->branches);
        }

        if ($page->manattempts) {
                $this->write_manattempt($page->manattempts);
        }
        // answers is now closed for current page. Ending the page.
        $this->xmlwriter->end_tag('page');
    }

    protected function change_cloze_type_content($page, $answers) {
        $content = $page->data['contents'];
        $pattern = '/<\s*a\s[^>]*name="([0-9]{1,2})"><\/a>/';
        $offset = 0;
        preg_match($pattern, $content, $matches, PREG_OFFSET_CAPTURE, $offset);

        while ($matches) {
            // operation here.
            // this is the tag of the anchor says the number of answer field it relates to.
            // eg "<a id = a2>" matches the answer field number 2.
            $answernum = $matches[1][0];

            // print_object($properties);
            if (isset($answers[$answernum - 1])) {
                if ($answers[$answernum - 1]->data['flags']) {
                    // if answer is chosen to use multiple choice (drop down menu), then mark it in class attribute.
                    $attr = 'class="multiplechoice" id="a'.$matches[1][0].'"';
                } else {
                    $attr = 'class="shortanswer" id="a'.$matches[1][0].'"';
                }
            } else {
                $attr = 'id="a'.$matches[1][0].'"';
            }

            // record the content of the oldanchor.
            $oldanchor = $matches[0][0];
            if (isset($answers[$answernum - 1])) {
                $answercontent = $answers[$answernum - 1]->data['answer_text'];
                $anchor = '<a '.$attr.' title="'.$answercontent.'"></a>';
            } else {
                $anchor = '<a '.$attr.'></a>';
            }

            $content = str_replace($oldanchor, $anchor, $content);

            $match = reset($matches);
            // next offset should be the pos right after current match.
            // match[1] is the pos for the  beginning of the current match, mathc[0] is the current matched string.
            $offset = $match[1]+ strlen($match[0]);
            preg_match($pattern, $content, $matches, PREG_OFFSET_CAPTURE, $offset);
        }

        $page->data['contents'] = $content;

    }

    protected function write_attempts($attempts) {
        $this->xmlwriter->begin_tag('attempts');
        foreach ($attempts as $data) {
                $this->write_xml('attempt', $data);
        }
        $this->xmlwriter->end_tag('attempts');
    }

    protected function write_branches($branches) {
        $this->xmlwriter->begin_tag('seenbranches');
        foreach ($branches as $data) {
            $this->write_xml('seenbranch', $data);
        }
            $this->xmlwriter->end_tag('seenbranches');
    }

    protected function write_manattempt($manattempts) {
        $this->xmlwriter->begin_tag('manattempts');
        foreach ($manattempts as $manattempt) {
            $this->xmlwriter->begin_tag('manattempt');
            foreach ($manattempt->data as $field->attempts) {
                $this->xmlwriter->full_tag($manattempt->data);

            }
            if ($manattempt->feedbacks) {
                $this->write_feedbacks($manattempt->feedbacks);
            }
                $this->xmlwriter->end_tag('manattempt');
        }
            $this->xmlwriter->end_tag('manattempts');
    }

    protected function write_feedbacks($feedbacks) {
        $this->xmlwriter->begin_tag('feedbacks');
        foreach ($feedbacks as $data) {
            $this->write_xml('feedback', $data);
        }
        $this->xmlwriter->end_tag('feedbacks');
    }

}