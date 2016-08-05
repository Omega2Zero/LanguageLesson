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
 * Branch Table
 *
 * @package    mod
 * @subpackage lesson
 * @copyright  2009 Sam Hemelryk
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 **/

defined('MOODLE_INTERNAL') || die();

class languagelesson_page_type_branchtable extends languagelesson_page {

    protected $type = languagelesson_page::TYPE_STRUCTURE;
    protected $typeid = LL_BRANCHTABLE;
    protected $typeidstring = 'branchtable';
    protected $string = null;
    protected $jumpto = null;

    public function get_typeid() {
        return $this->typeid;
    }
    public function get_typestring() {
        if ($this->string===null) {
            $this->string = get_string($this->typeidstring, 'languagelesson');
        }
        return $this->string;
    }

    /**
     * Gets an array of the jumps used by the answers of this page
     *
     * @return array
     */
    public function get_jumps() {
        global $DB;
        $jumps = array();
        $params = array ("lessonid" => $this->lesson->id, "pageid" => $this->properties->id);
        if ($answers = $this->get_answers()) {
            foreach ($answers as $answer) {
                if ($answer->answer === '') {
                    // Show only jumps for real branches (==have description).
                    continue;
                }
                $jumps[] = $this->get_jump_name($answer->jumpto);
            }
        } else {
            // We get here is the lesson was created on a Moodle 1.9 site and
            // the lesson contains question pages without any answers.
            $jumps[] = $this->get_jump_name($this->properties->nextpageid);
        }
        return $jumps;
    }

    public static function get_jumptooptions($firstpage, languagelesson $lesson) {
        global $DB, $PAGE;
        $jump = array();
        $jump[0] = get_string("thispage", "languagelesson");
        $jump[LL_EOL] = get_string("endoflesson", "languagelesson");
        $jump[LL_UNSEENBRANCHPAGE] = get_string("unseenpageinbranch", "languagelesson");
        $jump[LL_RANDOMPAGE] = get_string("randompageinbranch", "languagelesson");
        $jump[LL_RANDOMBRANCH] = get_string("randombranch", "languagelesson");

        if (!$firstpage) {
            if (!$apageid = $DB->get_field("languagelesson_pages", "id", array("lessonid" => $lesson->id, "prevpageid" => 0))) {
                print_error('cannotfindfirstpage', 'languagelesson');
            }
            while (true) {
                if ($apageid) {
                    $title = $DB->get_field("languagelesson_pages", "title", array("id" => $apageid));
                    $jump[$apageid] = $title;
                    $apageid = $DB->get_field("languagelesson_pages", "nextpageid", array("id" => $apageid));
                } else {
                    // Last page reached.
                    break;
                }
            }
        }
        return $jump;
    }
    public function get_idstring() {
        return $this->typeidstring;
    }
    public function display($renderer, $attempt) {
        global $PAGE, $CFG;

        $output = '';
        $options = new stdClass;
        $options->para = false;
        $options->noclean = true;

        // We are using level 3 header because the page title is a sub-heading of lesson title (MDL-30911).
        $output .= $renderer->heading(format_string($this->properties->title), 3);
        $output .= $renderer->box($this->get_contents(), 'contents');

        $buttons = array();
        $i = 0;
        foreach ($this->get_answers() as $answer) {
            if ($answer->answer === '') {
                // Not a branch!
                continue;
            }
            $params = array();
            $params['id'] = $PAGE->cm->id;
            $params['pageid'] = $this->properties->id;
            $params['sesskey'] = sesskey();
            $params['jumpto'] = $answer->jumpto;
            $url = new moodle_url('/mod/languagelesson/continue.php', $params);
            $buttons[] = $renderer->single_button($url, strip_tags(format_text($answer->answer, FORMAT_MOODLE, $options)));
            $i++;
        }
        // Set the orientation.
        if ($this->properties->layout) {
            $buttonshtml = $renderer->box(implode("\n", $buttons), 'branchbuttoncontainer horizontal');
        } else {
            $buttonshtml = $renderer->box(implode("\n", $buttons), 'branchbuttoncontainer vertical');
        }
        $output .= $buttonshtml;

        return $output;
    }

    public function check_answer() {
        global $USER, $DB, $PAGE, $CFG;

        require_sesskey();
        $newpageid = optional_param('jumpto', null, PARAM_INT);
        // Going to insert into languagelesson_branch.
        if ($newpageid == LL_RANDOMBRANCH) {
            $branchflag = 1;
        } else {
            $branchflag = 0;
        }
        if ($grades = $DB->get_records("languagelesson_grades", array("lessonid" => $this->lesson->id,
                                                                      "userid" => $USER->id), "grade DESC")) {
            $retries = count($grades);
        } else {
            $retries = 0;
        }
        $branch = new stdClass;
        $branch->lessonid = $this->lesson->id;
        $branch->userid = $USER->id;
        $branch->pageid = $this->properties->id;
        $branch->retry = 0;
        $branch->flag = $branchflag;
        $branch->timeseen = time();

        $DB->insert_record("languagelesson_branch", $branch);

        //  This is called when jumping to random from a branch table.
        $context = context_module::instance($PAGE->cm->id);
        if ($newpageid == LL_UNSEENBRANCHPAGE) {
            if (has_capability('mod/languagelesson:manage', $context)) {
                 $newpageid = LL_NEXTPAGE;
            } else {
                // This may return 0.
                $newpageid = languagelesson_unseen_question_jump($this->lesson, $USER->id, $this->properties->id);
            }
        }
        // Convert jumpto page into a proper page id.
        if ($newpageid == 0) {
            $newpageid = $this->properties->id;
        } else if ($newpageid == LL_NEXTPAGE) {
            if (!$newpageid = $this->nextpageid) {
                // No nextpage go to end of lesson.
                $newpageid = LL_EOL;
            }
        } else if ($newpageid == LL_PREVIOUSPAGE) {
            $newpageid = $this->prevpageid;
        } else if ($newpageid == LL_RANDOMPAGE) {
            $newpageid = languagelesson_random_question_jump($this->lesson, $this->properties->id);
        } else if ($newpageid == LL_RANDOMBRANCH) {
            $newpageid = languagelesson_unseen_branch_jump($this->lesson, $USER->id);
        }
        // No need to record anything in languagelesson_attempts.
        redirect(new moodle_url('/mod/languagelesson/view.php', array('id'=>$PAGE->cm->id, 'pageid'=>$newpageid)));
    }

    public function display_answers(html_table $table) {
        $answers = $this->get_answers();
        $options = new stdClass;
        $options->noclean = true;
        $options->para = false;
        $i = 1;
        foreach ($answers as $answer) {
            if ($answer->answer === '') {
                // Not a branch!
                continue;
            }
            $cells = array();
            $cells[] = "<span class=\"label\">".get_string("branch", "languagelesson")." $i<span>: ";
            $cells[] = format_text($answer->answer, $answer->answerformat, $options);
            $table->data[] = new html_table_row($cells);

            $cells = array();
            $cells[] = "<span class=\"label\">".get_string("jump", "languagelesson")." $i<span>: ";
            $cells[] = $this->get_jump_name($answer->jumpto);
            $table->data[] = new html_table_row($cells);

            if ($i === 1) {
                $table->data[count($table->data)-1]->cells[0]->style = 'width:20%;';
            }
            $i++;
        }
        return $table;
    }
    public function get_grayout() {
        return 1;
    }
    public function report_answers($answerpage, $answerdata, $useranswer, $pagestats, &$i, &$n) {
        $answers = $this->get_answers();
        $formattextdefoptions = new stdClass;
        $formattextdefoptions->para = false;  // I'll use it widely in this page.
        foreach ($answers as $answer) {
            $data = "<input type=\"button\" name=\"$answer->id\" value=\"".s(strip_tags(format_text($answer->answer,
                                                            FORMAT_MOODLE, $formattextdefoptions)))."\" disabled=\"disabled\"> ";
            $data .= get_string('jumpsto', 'languagelesson', $this->get_jump_name($answer->jumpto));
            $answerdata->answers[] = array($data, "");
            $answerpage->answerdata = $answerdata;
        }
        return $answerpage;
    }

    public function update($properties, $context = null, $maxbytes = null) {
        if (empty($properties->display)) {
            $properties->display = '0';
        }
        if (empty($properties->layout)) {
            $properties->layout = '0';
        }
        return parent::update($properties);
    }
    public function add_page_link($previd) {
        global $PAGE, $CFG;
        $addurl = new moodle_url('/mod/languagelesson/editpage.php', array('id'=>$PAGE->cm->id,
                                                                           'pageid'=>$previd, 'qtype'=>LL_BRANCHTABLE));
        return array('addurl'=>$addurl, 'type'=>LL_BRANCHTABLE, 'name'=>get_string('addabranchtable', 'languagelesson'));
    }
    protected function get_displayinmenublock() {
        return true;
    }
    public function is_unseen($param) {
        global $USER, $DB;
        if (is_array($param)) {
            $seenpages = $param;
            $branchpages = $this->lesson->get_sub_pages_of($this->properties->id, array(LL_BRANCHTABLE, LL_ENDOFBRANCH));
            foreach ($branchpages as $branchpage) {
                if (array_key_exists($branchpage->id, $seenpages)) {  // Check if any of the pages have been viewed.
                    return false;
                }
            }
            return true;
        } else {
            $nretakes = $param;
            if (!$DB->count_records("languagelesson_attempts", array("pageid"=>$this->properties->id,
                                                                     "userid"=>$USER->id, "retry"=>$nretakes))) {
                return true;
            }
            return false;
        }
    }
}

class languagelesson_add_page_form_branchtable extends languagelesson_add_page_form_base {

    public $qtype = LL_BRANCHTABLE;
    public $qtypestring = 'branchtable';
    protected $standard = false;

    public function custom_definition() {
        global $PAGE, $DB;

        $mform = $this->_form;
        $lesson = $this->_customdata['languagelesson'];

        $firstpage = optional_param('firstpage', false, PARAM_BOOL);
        $pageid = optional_param('pageid', 0, PARAM_INT);

        $jumptooptions = languagelesson_page_type_branchtable::get_jumptooptions($firstpage, $lesson);

        $mform->setDefault('qtypeheading', get_string('addabranchtable', 'languagelesson'));

        $mform->addElement('hidden', 'firstpage');
        $mform->setType('firstpage', PARAM_BOOL);
        $mform->setDefault('firstpage', $firstpage);

        $mform->addElement('hidden', 'qtype');
        $mform->setType('qtype', PARAM_INT);

        $mform->addElement('text', 'title', get_string("pagetitle", "languagelesson"), array('size'=>70));
        $mform->setType('title', PARAM_TEXT);
        $mform->addRule('title', null, 'required', null, 'server');

        $this->editoroptions = array('noclean'=>true, 'maxfiles'=>EDITOR_UNLIMITED_FILES, 'maxbytes'=>$PAGE->course->maxbytes);
        $mform->addElement('editor', 'contents_editor', get_string("pagecontents", "languagelesson"), null, $this->editoroptions);
        $mform->setType('contents_editor', PARAM_RAW);

        $mform->addElement('checkbox', 'layout', null, get_string("arrangebuttonshorizontally", "languagelesson"));
        $mform->setDefault('layout', true);

        $mform->addElement('checkbox', 'display', null, get_string("displayinleftmenu", "languagelesson"));
        $mform->setDefault('display', true);

        if (($answercount = $DB->count_records('languagelesson_answers', array('pageid'=> $pageid))) >= 4) {
            $maxanswers = $answercount+2;
        } else {
            $maxanswers = $lesson->maxanswers;
        }

        for ($i = 0; $i < $maxanswers; $i++) {
            $mform->addElement('header', 'headeranswer'.$i, get_string('branch', 'languagelesson').' '.($i+1));
            $this->add_answer($i, get_string("description", "languagelesson"), $i == 0);
            $mform->addElement('select', 'jumpto['.$i.']', get_string("jump", "languagelesson"), $jumptooptions);
            if ($i === 0) {
                $mform->setDefault('jumpto['.$i.']', 0);
            } else {
                $mform->setDefault('jumpto['.$i.']', LL_NEXTPAGE);
            }
        }

        $this->_form->addElement('header', 'addanswerstitle', get_string('addanswerstitle', 'languagelesson'));
        $this->_form->addElement('submit', 'addanswers', get_string('addanswers', 'languagelesson'));
    }
}
