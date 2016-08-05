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
 * True/false
 *
 * @package    mod
 * @subpackage lesson
 * @copyright  2009 Sam Hemelryk
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 **/

defined('MOODLE_INTERNAL') || die();

class languagelesson_page_type_truefalse extends languagelesson_page {

    protected $type = languagelesson_page::TYPE_QUESTION;
    protected $typeidstring = 'truefalse';
    protected $typeid = LL_TRUEFALSE;
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
        global $USER, $CFG, $PAGE;
        $answers = $this->get_answers();
        shuffle($answers);

        $params = array('answers'=>$answers, 'lessonid'=>$this->lesson->id, 'contents'=>$this->get_contents(), 'attempt'=>$attempt);
        $mform = new languagelesson_display_answer_form_truefalse($CFG->wwwroot.'/mod/languagelesson/continue.php', $params);
        $data = new stdClass;
        $data->id = $PAGE->cm->id;
        $data->pageid = $this->properties->id;
        $mform->set_data($data);
        return $mform->display();
    }
    public function check_answer() {
        global $DB, $CFG, $PAGE;
        $formattextdefoptions = new stdClass();
        $formattextdefoptions->noclean = true;
        $formattextdefoptions->para = false;

        $answers = $this->get_answers();
        shuffle($answers); // What is the point of this?
        $params = array('answers'=>$answers, 'lessonid'=>$this->lesson->id, 'contents'=>$this->get_contents());
        $mform = new languagelesson_display_answer_form_truefalse($CFG->wwwroot.'/mod/languagelesson/continue.php', $params);
        $data = $mform->get_data();
        require_sesskey();

        if ($data->maxattemptsreached == true) {
            redirect(new moodle_url('/mod/languagelesson/view.php',
                                    array('id'=>$PAGE->cm->id, 'pageid'=>$this->properties->nextpageid)));
        }

        $result = parent::check_answer();

        $answerid = $data->answerid;
        if ($answerid === false) {
            $result->noanswer = true;
            return $result;
        }
        $result->answerid = $answerid;
        $answer = $DB->get_record("languagelesson_answers", array("id" => $result->answerid), '*', MUST_EXIST);
        if ($this->lesson->jumpto_is_correct($this->properties->id, $answer->jumpto)) {
            $result->correctanswer = true;
            $result->score = $answer->score;
        } else {
            $result->score = 0;
        }
        if ($this->lesson->custom) {
            if ($answer->score > 0) {
                $result->correctanswer = true;
                $result->score = $answer->score;
            } else {
                $result->correctanswer = false;
                $result->score = 0;
            }
        }
        $result->newpageid = $answer->jumpto;
        $result->response  = format_text($answer->response, $answer->responseformat, $formattextdefoptions);
        $result->studentanswer = $result->userresponse = $answer->answer;
        $result->typeid = $this->typeid;

        return $result;
    }

    public function display_answers(html_table $table) {
        $answers = $this->get_answers();
        $options = new stdClass();
        $options->noclean = true;
        $options->para = false;
        $i = 1;
        foreach ($answers as $answer) {
            $cells = array();
            if ($this->lesson->custom && $answer->score > 0) {
                // If the score is > 0, then it is correct.
                $cells[] = '<span class="labelcorrect">'.get_string("answer", "languagelesson")." $i</span>: \n";
            } else if ($this->lesson->custom) {
                $cells[] = '<span class="label">'.get_string("answer", "languagelesson")." $i</span>: \n";
            } else if ($this->lesson->jumpto_is_correct($this->properties->id, $answer->jumpto)) {
                // Underline correct answers.
                $cells[] = '<span class="correct">'.get_string("answer", "languagelesson")." $i</span>: \n";
            } else {
                $cells[] = '<span class="labelcorrect">'.get_string("answer", "languagelesson")." $i</span>: \n";
            }
            $cells[] = format_text($answer->answer, $answer->answerformat, $options);
            $table->data[] = new html_table_row($cells);

            $cells = array();
            $cells[] = "<span class=\"label\">".get_string("response", "languagelesson")." $i</span>";
            $cells[] = format_text($answer->response, $answer->responseformat, $options);
            $table->data[] = new html_table_row($cells);

            $cells = array();
            $cells[] = "<span class=\"label\">".get_string("score", "languagelesson").'</span>';
            $cells[] = $answer->score;
            $table->data[] = new html_table_row($cells);

            $cells = array();
            $cells[] = "<span class=\"label\">".get_string("jump", "languagelesson").'</span>';
            $cells[] = $this->get_jump_name($answer->jumpto);
            $table->data[] = new html_table_row($cells);

            if ($i === 1) {
                $table->data[count($table->data)-1]->cells[0]->style = 'width:20%;';
            }

            $i++;
        }
        return $table;
    }

    /**
     * Updates the page and its answers
     *
     * @global moodle_database $DB
     * @global moodle_page $PAGE
     * @param stdClass $properties
     * @return bool
     */
    public function update($properties, $context = null, $maxbytes = null) {
        global $DB, $PAGE;
        $answers  = $this->get_answers();
        $properties->id = $this->properties->id;
        $properties->lessonid = $this->lesson->id;
        $properties = file_postupdate_standard_editor($properties, 'contents',
                            array('noclean'=>true, 'maxfiles'=>EDITOR_UNLIMITED_FILES, 'maxbytes'=>$PAGE->course->maxbytes),
                            context_module::instance($PAGE->cm->id), 'mod_languagelesson',
                            'page_contents', $properties->id);
        $DB->update_record("languagelesson_pages", $properties);

        // Need to reset offset for correct and wrong responses.
        $this->lesson->maxanswers = 2;
        for ($i = 0; $i < $this->lesson->maxanswers; $i++) {
            if (!array_key_exists($i, $this->answers)) {
                $this->answers[$i] = new stdClass;
                $this->answers[$i]->lessonid = $this->lesson->id;
                $this->answers[$i]->pageid = $this->id;
                $this->answers[$i]->timecreated = $this->timecreated;
            }

            if (!empty($properties->answer_editor[$i]) && is_array($properties->answer_editor[$i])) {
                $this->answers[$i]->answer = $properties->answer_editor[$i]['text'];
                $this->answers[$i]->answerformat = $properties->answer_editor[$i]['format'];
            }

            if (!empty($properties->response_editor[$i]) && is_array($properties->response_editor[$i])) {
                $this->answers[$i]->response = $properties->response_editor[$i]['text'];
                $this->answers[$i]->responseformat = $properties->response_editor[$i]['format'];
            }

            // We don't need to check for isset here because properties called it's own isset method.
            if ($this->answers[$i]->answer != '') {
                if (isset($properties->jumpto[$i])) {
                    $this->answers[$i]->jumpto = $properties->jumpto[$i];
                }
                if ($this->lesson->custom && isset($properties->score[$i])) {
                    $this->answers[$i]->score = $properties->score[$i];
                }
                if (!isset($this->answers[$i]->id)) {
                    $this->answers[$i]->id =  $DB->insert_record("languagelesson_answers", $this->answers[$i]);
                } else {
                    $DB->update_record("languagelesson_answers", $this->answers[$i]->properties());
                }
            } else if (isset($this->answers[$i]->id)) {
                $DB->delete_records('languagelesson_answers', array('id'=>$this->answers[$i]->id));
                unset($this->answers[$i]);
            }
        }
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
        if ($this->properties->qoption) {
            $userresponse = explode(",", $temp->useranswer);
            foreach ($userresponse as $response) {
                if (isset($pagestats[$temp->pageid][$response])) {
                    $pagestats[$temp->pageid][$response]++;
                } else {
                    $pagestats[$temp->pageid][$response] = 1;
                }
            }
        } else {
            if (isset($pagestats[$temp->pageid][$temp->answerid])) {
                $pagestats[$temp->pageid][$temp->answerid]++;
            } else {
                $pagestats[$temp->pageid][$temp->answerid] = 1;
            }
        }
        if (isset($pagestats[$temp->pageid]["total"])) {
            $pagestats[$temp->pageid]["total"]++;
        } else {
            $pagestats[$temp->pageid]["total"] = 1;
        }
        return true;
    }

    public function report_answers($answerpage, $answerdata, $useranswer, $pagestats, &$i, &$n) {
        $answers = $this->get_answers();
        $formattextdefoptions = new stdClass(); // I'll use it widely in this page.
        $formattextdefoptions->para = false;
        $formattextdefoptions->noclean = true;
        foreach ($answers as $answer) {
            if ($this->properties->qoption) {
                if ($useranswer == null) {
                    $userresponse = array();
                } else {
                    $userresponse = explode(",", $useranswer->useranswer);
                }
                if (in_array($answer->id, $userresponse)) {
                    // Make checked.
                    $data = "<input  readonly=\"readonly\" disabled=\"disabled\" name=\"answer[$i]\"
                                            checked=\"checked\" type=\"checkbox\" value=\"1\" />";
                    if (!isset($answerdata->response)) {
                        if ($answer->response == null) {
                            if ($useranswer->correct) {
                                $answerdata->response = get_string("thatsthecorrectanswer", "languagelesson");
                            } else {
                                $answerdata->response = get_string("thatsthewronganswer", "languagelesson");
                            }
                        } else {
                            $answerdata->response = format_text($answer->response, $answer->responseformat, $formattextdefoptions);
                        }
                    }
                    if (!isset($answerdata->score)) {
                        if ($this->lesson->custom) {
                            $answerdata->score = get_string("pointsearned", "languagelesson").": ".$answer->score;
                        } else if ($useranswer->correct) {
                            $answerdata->score = get_string("receivedcredit", "languagelesson");
                        } else {
                            $answerdata->score = get_string("didnotreceivecredit", "languagelesson");
                        }
                    }
                } else {
                    // Unchecked.
                    $data = "<input type=\"checkbox\" readonly=\"readonly\" name=\"answer[$i]\" value=\"0\" disabled=\"disabled\" />";
                }
                if (($answer->score > 0 && $this->lesson->custom) ||
                            ($this->lesson->jumpto_is_correct($this->properties->id, $answer->jumpto) && !$this->lesson->custom)) {
                    $data .= "<div class=highlight>".format_text($answer->answer, $answer->answerformat, $formattextdefoptions)."</div>";
                } else {
                    $data .= format_text($answer->answer, $answer->answerformat, $formattextdefoptions);
                }
            } else {
                if ($useranswer != null and $answer->id == $useranswer->answerid) {
                    // Make checked.
                    $data = "<input  readonly=\"readonly\" disabled=\"disabled\" name=\"answer[$i]\" checked=\"checked\"
                                                                                                type=\"checkbox\" value=\"1\" />";
                    if ($answer->response == null) {
                        if ($useranswer->correct) {
                            $answerdata->response = get_string("thatsthecorrectanswer", "languagelesson");
                        } else {
                            $answerdata->response = get_string("thatsthewronganswer", "languagelesson");
                        }
                    } else {
                        $answerdata->response = format_text($answer->response, $answer->responseformat, $formattextdefoptions);
                    }
                    if ($this->lesson->custom) {
                        $answerdata->score = get_string("pointsearned", "languagelesson").": ".$answer->score;
                    } else if ($useranswer->correct) {
                        $answerdata->score = get_string("receivedcredit", "languagelesson");
                    } else {
                        $answerdata->score = get_string("didnotreceivecredit", "languagelesson");
                    }
                } else {
                    // Unchecked.
                    $data = "<input type=\"checkbox\" readonly=\"readonly\" name=\"answer[$i]\" value=\"0\" disabled=\"disabled\" />";
                }
                if (($answer->score > 0 && $this->lesson->custom) ||
                            ($this->lesson->jumpto_is_correct($this->properties->id, $answer->jumpto) && !$this->lesson->custom)) {
                    $data .= "<div class=\"highlight\">".format_text($answer->answer, $answer->answerformat, $formattextdefoptions)."</div>";
                } else {
                    $data .= format_text($answer->answer, $answer->answerformat, $formattextdefoptions);
                }
            }
            if (isset($pagestats[$this->properties->id][$answer->id])) {
                $percent = $pagestats[$this->properties->id][$answer->id] / $pagestats[$this->properties->id]["total"] * 100;
                $percent = round($percent, 2);
                $percent .= "% ".get_string("checkedthisone", "languagelesson");
            } else {
                $percent = get_string("noonecheckedthis", "languagelesson");
            }

            $answerdata->answers[] = array($data, $percent);
            $answerpage->answerdata = $answerdata;
        }
        return $answerpage;
    }
}

class languagelesson_add_page_form_truefalse extends languagelesson_add_page_form_base {

    public $qtype = 'truefalse';
    public $qtypestring = 'truefalse';

    public function custom_definition() {
        $this->_form->addElement('header', 'answertitle0', get_string('correctresponse', 'languagelesson'));
        $this->add_answer(0, null, true);
        $this->add_response(0);
        $this->add_jumpto(0, get_string('correctanswerjump', 'languagelesson'), LL_NEXTPAGE);
        $this->add_score(0, get_string('correctanswerscore', 'languagelesson'), 1);

        $this->_form->addElement('header', 'answertitle1', get_string('wrongresponse', 'languagelesson'));
        $this->add_answer(1, null, true);
        $this->add_response(1);
        $this->add_jumpto(1, get_string('wronganswerjump', 'languagelesson'), LL_THISPAGE);
        $this->add_score(1, get_string('wronganswerscore', 'languagelesson'), 0);
    }
}

class languagelesson_display_answer_form_truefalse extends moodleform {

    public function definition() {
        global $USER, $OUTPUT, $DB;
        $mform = $this->_form;
        $answers = $this->_customdata['answers'];
        $lessonid = $this->_customdata['lessonid'];
        $contents = $this->_customdata['contents'];
        if (array_key_exists('attempt', $this->_customdata)) {
            if ($this->_customdata['attempt'] !== false) {
                $attempt = $this->_customdata['attempt'];
                $hasattempt = true;
            } else {
                $attempt = new stdClass();
                $attempt->answerid = null;
                $hasattempt = false;
            }
        } else {
                $attempt = new stdClass();
                $attempt->answerid = null;
                $hasattempt = false;
        }

        $mform->addElement('header', 'pageheader');

        $mform->addElement('html', $OUTPUT->container($contents, 'contents'));

        $hasfeedback = false;
        $disabled = '';
        $maxattemptsreached = 0;
        $maxattempts = $DB->get_field('languagelesson', 'maxattempts', array('id'=>$lessonid));
        if ($hasattempt == true) {
            if ($maxattempts != 0) {
                if ($attempt->retry >= $maxattempts) {
                    $disabled = array('disabled' => 'disabled');
                    $maxattemptsreached = 1;
                }
            }
        }

        if (isset($USER->modattempts[$lessonid]) && !empty($USER->modattempts[$lessonid])) {
            if ($hasattempt == true) {
                $feedbackrecords = $DB->get_records('languagelesson_feedback', array('attemptid' => $attempt->id));
            }
            if (isset($feedbackrecords) && !empty($feedbackrecords)) {
                $hasfeedback = true;
                foreach ($feedbackrecords as $feedback) {
                    if ($feedback->text != null) {
                        $feedbackteacher = new stdClass();
                        $feedbackteacher = $DB->get_record('user', array('id'=>$feedback->teacherid));
                        $teacherpic = $OUTPUT->user_picture($feedbackteacher);
                        $fullname = fullname($feedbackteacher);

                        $textfeedback = nl2br($feedback->text);
                        $textfeedback = stripslashes($textfeedback);

                        $feedbackdate = userdate($feedback->timeseen);
                    }
                }
            }
        }

        $options = new stdClass();
        $options->para = false;
        $options->noclean = true;

        $mform->addElement('hidden', 'id');
        $mform->setType('id', PARAM_INT);

        $mform->addElement('hidden', 'pageid');
        $mform->setType('pageid', PARAM_INT);

        $mform->addElement('hidden', 'maxattemptsreached');
        $mform->setType('maxattemptsreached', PARAM_INT);
        $mform->setDefault('maxattemptsreached', $maxattemptsreached);

        if ($hasfeedback) {
            $mform->addElement('html', '<center><table border=1><tr><td>'. $teacherpic . $fullname . '</td></tr>
                               <tr><td>'.$feedbackdate.'</td></tr><tr><td><hr>'.$textfeedback.'</td></tr></table></center>');
        }

        $i = 0;
        foreach ($answers as $answer) {
            $mform->addElement('html', '<div class="answeroption">');

            $mform->addElement('radio', 'answerid', null,
                               format_text($answer->answer, $answer->answerformat, $options), $answer->id, $disabled);
            $mform->setType('answerid', PARAM_INT);
            if ($hasattempt == true) {
                if ($answer->id == $USER->modattempts[$lessonid]->answerid) {
                    $mform->setDefault('answerid', $attempt->answerid);
                }
            }
            $mform->addElement('html', '</div>');
            $i++;
        }

        if (!empty($disabled)) {
            $this->add_action_buttons(null, get_string("nextpage", "languagelesson"));
        } else {
            $this->add_action_buttons(null, get_string("submit", "languagelesson"));
        }

    }

}
