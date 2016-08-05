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
 * Essay
 *
 * @package    mod
 * @subpackage lesson
 * @copyright  2009 Sam Hemelryk
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 **/

defined('MOODLE_INTERNAL') || die();

class languagelesson_page_type_description extends languagelesson_page {

    protected $type = languagelesson_page::TYPE_QUESTION;
    protected $typeidstring = 'description';
    protected $typeid = LL_DESCRIPTION;
    protected $string = null;

    public function get_typeid() {
        return $this->typeid;
    }
    public function get_typestring() {
        if ($this->string===null) {
            $this->string = get_string($this->typeidstring, 'languagelesson');
        }
        return $this->string;
    }
    public function get_idstring() {
        return $this->typeidstring;
    }
    public function display($renderer, $attempt) {
        global $PAGE, $CFG, $USER;

        $mform = new languagelesson_display_answer_form_description($CFG->wwwroot.'/mod/languagelesson/continue.php',
                                                        array('contents'=>$this->get_contents(), 'lessonid'=>$this->lesson->id));

        $data = new stdClass;
        $data->id = $PAGE->cm->id;
        $data->pageid = $this->properties->id;
        return $mform->display();
    }
    public function create_answers($properties) {
        global $DB;
        // Now add the answers.
        $newanswer = new stdClass;
        $newanswer->lessonid = $this->lesson->id;
        $newanswer->pageid = $this->properties->id;
        $newanswer->timecreated = $this->properties->timecreated;

        if (isset($properties->jumpto[0])) {
            $newanswer->jumpto = $properties->jumpto[0];
        }
        if (isset($properties->score[0])) {
            $newanswer->score = $properties->score[0];
        }
        $newanswer->id = $DB->insert_record("languagelesson_answers", $newanswer);
        $answers = array($newanswer->id => new languagelesson_page_answer($newanswer));
        $this->answers = $answers;
        return $answers;
    }
    public function check_answer() {
        global $PAGE, $CFG;
        $result = parent::check_answer();
        $result->isessayquestion = true;
        $result->typeid = $this->typeid;

        $mform = new languagelesson_display_answer_form_essay($CFG->wwwroot.'/mod/languagelesson/continue.php',
                                                              array('contents'=>$this->get_contents()));
        $data = $mform->get_data();
        require_sesskey();

        if (!$data) {
            redirect(new moodle_url('/mod/languagelesson/view.php', array('id'=>$PAGE->cm->id, 'pageid'=>$this->properties->id)));
        }

        if ($data->maxattemptsreached == true) {
            redirect(new moodle_url('/mod/languagelesson/view.php',
                                    array('id'=>$PAGE->cm->id, 'pageid'=>$this->properties->nextpageid)));
        }

        if (is_array($data->answer)) {
            $studentanswer = $data->answer['text'];
            $studentanswerformat = $data->answer['format'];
        } else {
            $studentanswer = $data->answer;
            $studentanswerformat = FORMAT_MOODLE;
        }

        if (trim($studentanswer) === '') {
            $result->noanswer = true;
            return $result;
        }

        $answers = $this->get_answers();
        foreach ($answers as $answer) {
            $result->answerid = $answer->id;
            $result->newpageid = $answer->jumpto;
        }

        $userresponse = new stdClass;
        $userresponse->sent=0;
        $userresponse->graded = 0;
        $userresponse->score = 0;
        $userresponse->answer = $studentanswer;
        $userresponse->answerformat = $studentanswerformat;
        $userresponse->response = "";
        $result->userresponse = serialize($userresponse);
        $result->studentanswerformat = $studentanswerformat;
        $result->studentanswer = s($studentanswer);
        $result->score = 0;
        return $result;
    }
    public function update($properties, $context = null, $maxbytes = null) {
        global $DB, $PAGE;
        $answers  = $this->get_answers();
        $properties->id = $this->properties->id;
        $properties->lessonid = $this->lesson->id;
        $properties = file_postupdate_standard_editor($properties, 'contents',
                            array('noclean'=>true, 'maxfiles'=>EDITOR_UNLIMITED_FILES, 'maxbytes'=>$PAGE->course->maxbytes),
                            context_module::instance($PAGE->cm->id),
                            'mod_languagelesson', 'page_contents', $properties->id);
        $DB->update_record("languagelesson_pages", $properties);

        if (!array_key_exists(0, $this->answers)) {
            $this->answers[0] = new stdClass;
            $this->answers[0]->lessonid = $this->lesson->id;
            $this->answers[0]->pageid = $this->id;
            $this->answers[0]->timecreated = $this->timecreated;
        }
        if (isset($properties->jumpto[0])) {
            $this->answers[0]->jumpto = $properties->jumpto[0];
        }
        if (isset($properties->score[0])) {
            $this->answers[0]->score = $properties->score[0];
        }
        if (!isset($this->answers[0]->id)) {
            $this->answers[0]->id =  $DB->insert_record("languagelesson_answers", $this->answers[0]);
        } else {
            $DB->update_record("languagelesson_answers", $this->answers[0]->properties());
        }
        // Update maxscore field of page record.
        $maxscore = languagelesson_calc_page_maxscore($this->id);
        $DB->set_field("languagelesson_pages", 'maxscore', $maxscore, array('id'=>$this->id));

        return true;
    }
    public function stats(array &$pagestats, $tries) {
        if (count($tries) > $this->lesson->maxattempts) {
            // If there are more tries than the max that is allowed, grab the last "legal" attempt.
            $temp = $tries[$this->lesson->maxattempts - 1];
        } else {
            // Else, user attempted the question less than the max, so grab the last one.
            $temp = end($tries);
        }
        $essayinfo = unserialize($temp->useranswer);
        if ($essayinfo->graded) {
            if (isset($pagestats[$temp->pageid])) {
                $essaystats = $pagestats[$temp->pageid];
                $essaystats->totalscore += $essayinfo->score;
                $essaystats->total++;
                $pagestats[$temp->pageid] = $essaystats;
            } else {
                $essaystats = new stdClass();
                $essaystats->totalscore = $essayinfo->score;
                $essaystats->total = 1;
                $pagestats[$temp->pageid] = $essaystats;
            }
        }
        return true;
    }
    public function report_answers($answerpage, $answerdata, $useranswer, $pagestats, &$i, &$n) {
        $answers = $this->get_answers();
        $formattextdefoptions = new stdClass;
        $formattextdefoptions->para = false;  // I'll use it widely in this page.
        $formattextdefoptions->context = $answerpage->context;

        foreach ($answers as $answer) {
            if ($useranswer != null) {
                $essayinfo = unserialize($useranswer->useranswer);
                if ($essayinfo->response == null) {
                    $answerdata->response = get_string("nocommentyet", "languagelesson");
                } else {
                    $answerdata->response = s($essayinfo->response);
                }
                if (isset($pagestats[$this->properties->id])) {
                    $percent = $pagestats[$this->properties->id]->totalscore / $pagestats[$this->properties->id]->total * 100;
                    $percent = round($percent, 2);
                    $percent = get_string("averagescore", "languagelesson").": ". $percent ."%";
                } else {
                    // Dont think this should ever be reached....
                    $percent = get_string("nooneansweredthisquestion", "languagelesson");
                }
                if ($essayinfo->graded) {
                    if ($this->lesson->custom) {
                        $answerdata->score = get_string("pointsearned", "languagelesson").": ".$essayinfo->score;
                    } else if ($essayinfo->score) {
                        $answerdata->score = get_string("receivedcredit", "languagelesson");
                    } else {
                        $answerdata->score = get_string("didnotreceivecredit", "languagelesson");
                    }
                } else {
                    $answerdata->score = get_string("havenotgradedyet", "languagelesson");
                }
            } else {
                $essayinfo = new stdClass();
                $essayinfo->answer = get_string("didnotanswerquestion", "languagelesson");
            }

            if (isset($pagestats[$this->properties->id])) {
                $avescore = $pagestats[$this->properties->id]->totalscore / $pagestats[$this->properties->id]->total;
                $avescore = round($avescore, 2);
                $avescore = get_string("averagescore", "languagelesson").": ". $avescore;
            } else {
                // Dont think this should ever be reached....
                $avescore = get_string("nooneansweredthisquestion", "languagelesson");
            }
            $answerdata->answers[] = array(format_text($essayinfo->answer, $essayinfo->answerformat, $formattextdefoptions), $avescore);
            $answerpage->answerdata = $answerdata;
        }
        return $answerpage;
    }
    public function is_unanswered($nretakes) {
        global $DB, $USER;
        if (!$DB->count_records("languagelesson_attempts", array('pageid'=>$this->properties->id, 'userid'=>$USER->id, 'retry'=>$nretakes))) {
            return true;
        }
        return false;
    }
    public function requires_manual_grading() {
        return true;
    }
    public function get_earnedscore($answers, $attempt) {
        $essayinfo = unserialize($attempt->useranswer);
        return $essayinfo->score;
    }
}

class languagelesson_add_page_form_description extends languagelesson_add_page_form_base {

    public $qtype = 'description';
    public $qtypestring = 'description';

    public function custom_definition() {

        $this->add_jumpto(0);
    }
}

class languagelesson_display_answer_form_description extends moodleform {

    public function definition() {
        global $USER, $OUTPUT, $DB, $PAGE, $CFG;
        $pageid = optional_param('pageid', null, PARAM_INT);
        $cmid = $PAGE->cm->id;
        $lessonid = $PAGE->cm->instance;

        $mform = $this->_form;

        $contents = $this->_customdata['contents'];

        $mform->addElement('header', 'pageheader');

        $mform->addElement('html', $OUTPUT->container($contents, 'contents'));

        $mform->addElement('hidden', 'id');
        $mform->setType('id', PARAM_INT);

        $mform->addElement('hidden', 'pageid');
        $mform->setType('pageid', PARAM_INT);

        // Just include a next button to bypass all submit functions.
        if (!($nextpage = $DB->get_field('languagelesson_pages', 'nextpageid', array('id'=>$pageid)))) {
            $nextpage = $DB->get_field('languagelesson_pages', 'nextpageid', array('lessonid' => $lessonid, 'ordering' => 0));
        }
        $url = '\''.$CFG->wwwroot.'/mod/languagelesson/view.php?id='.$cmid.'&pageid='.$nextpage.'\'';

        $mform->addElement('html', '<script type="text/javascript">');
        $mform->addElement('html', 'function gotonext(url)');
        $mform->addElement('html', '{ window.location.assign(url)}');
        $mform->addElement('html', '</script>');
        $mform->addElement('html', '<center><button type="button" onclick="gotonext('.$url.')">Continue</button></center>');

    }
}
