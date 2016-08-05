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
 * Short answer
 *
 * @package    mod
 * @subpackage lesson
 * @copyright  2009 Sam Hemelryk
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 **/

defined('MOODLE_INTERNAL') || die();

class languagelesson_page_type_shortanswer extends languagelesson_page {

    protected $type = languagelesson_page::TYPE_QUESTION;
    protected $typeidstring = 'shortanswer';
    protected $typeid = LL_SHORTANSWER;
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
        $mform = new languagelesson_display_answer_form_shortanswer($CFG->wwwroot.'/mod/languagelesson/continue.php',
                                                        array('contents'=>$this->get_contents(), 'lessonid'=>$this->lesson->id));
        $data = new stdClass;
        $data->id = $PAGE->cm->id;
        $data->pageid = $this->properties->id;
        if (isset($USER->modattempts[$this->lesson->id])) {
            if ($attempt != null) {
                $data->answer = s($attempt->useranswer);
            }
        }
        $mform->set_data($data);
        return $mform->display();
    }
    public function check_answer() {
        global $CFG, $PAGE;
        $result = parent::check_answer();
        $result->typeid = $this->typeid;
        $result->score = 0;

        $mform = new languagelesson_display_answer_form_shortanswer($CFG->wwwroot.'/mod/languagelesson/continue.php',
                                                                    array('contents'=>$this->get_contents()));
        $data = $mform->get_data();
        require_sesskey();

        if ($data->maxattemptsreached == true) {
            redirect(new moodle_url('/mod/languagelesson/view.php', array('id'=>$PAGE->cm->id, 'pageid'=>$this->properties->nextpageid)));
        }

        $studentanswer = trim($data->answer);
        if ($studentanswer === '') {
            $result->noanswer = true;
            return $result;
        }

        $i=0;
        $answers = $this->get_answers();
        foreach ($answers as $answer) {
            $i++;
            $expectedanswer  = $answer->answer; // For easier handling of $answer->answer.
            $ismatch         = false;
            $markit          = false;
            $useregexp       = ($this->qoption);

            if ($useregexp) { // We are using 'normal analysis', which ignores case.
                $ignorecase = '';
                if (substr($expectedanswer, -2) == '/i') {
                    $expectedanswer = substr($expectedanswer, 0, -2);
                    $ignorecase = 'i';
                }
            } else {
                $expectedanswer = str_replace('*', '#####', $expectedanswer);
                $expectedanswer = preg_quote($expectedanswer, '/');
                $expectedanswer = str_replace('#####', '.*', $expectedanswer);
            }
            // See if user typed in any of the correct answers.
            if ((!$this->lesson->custom && $this->lesson->jumpto_is_correct($this->properties->id, $answer->jumpto)) or
                                                                                ($this->lesson->custom && $answer->score > 0) ) {
                if (!$useregexp) { // We are using 'normal analysis', which ignores case.
                    if (preg_match('/^'.$expectedanswer.'$/i', $studentanswer)) {
                        $ismatch = true;
                    }
                } else {
                    if (preg_match('/^'.$expectedanswer.'$/'.$ignorecase, $studentanswer)) {
                        $ismatch = true;
                    }
                }
                if ($ismatch == true) {
                    $result->correctanswer = true;
                }
            } else {
                if (!$useregexp) { // We are using 'normal analysis'.
                    // See if user typed in any of the wrong answers; don't worry about case.
                    if (preg_match('/^'.$expectedanswer.'$/i', $studentanswer)) {
                        $ismatch = true;
                    }
                } else { // We are using regular expressions analysis.
                    $startcode = substr($expectedanswer, 0, 2);
                    switch ($startcode){
                        // 1- check for absence of required string in $studentanswer (coded by initial '--').
                        case "--":
                            $expectedanswer = substr($expectedanswer, 2);
                            if (!preg_match('/^'.$expectedanswer.'$/'.$ignorecase, $studentanswer)) {
                                $ismatch = true;
                            }
                            break;
                        // 2- check for code for marking wrong strings (coded by initial '++').
                        case "++":
                            $expectedanswer=substr($expectedanswer, 2);
                            $markit = true;
                            // Check for one or several matches.
                            if (preg_match_all('/'.$expectedanswer.'/'.$ignorecase, $studentanswer, $matches)) {
                                $ismatch   = true;
                                $nb        = count($matches[0]);
                                $original  = array();
                                $marked    = array();
                                $fontstart = '<span class="incorrect matches">';
                                $fontend   = '</span>';
                                for ($i = 0; $i < $nb; $i++) {
                                    array_push($original, $matches[0][$i]);
                                    array_push($marked, $fontstart.$matches[0][$i].$fontend);
                                }
                                $studentanswer = str_replace($original, $marked, $studentanswer);
                            }
                            break;
                        // 3- check for wrong answers belonging neither to -- nor to ++ categories.
                        default:
                            if (preg_match('/^'.$expectedanswer.'$/'.$ignorecase, $studentanswer, $matches)) {
                                $ismatch = true;
                            }
                            break;
                    }
                    $result->correctanswer = false;
                }
            }
            if ($ismatch) {
                $result->newpageid = $answer->jumpto;
                if (trim(strip_tags($answer->response))) {
                    $result->response = $answer->response;
                }
                $result->answerid = $answer->id;
                $result->score = $answer->score;
                break; // Quit answer analysis immediately after a match has been found.
            }
        }
        $result->userresponse = $studentanswer;
        // Clean student answer as it goes to output.
        $result->studentanswer = s($studentanswer);
        return $result;
    }

    public function option_description_string() {
        if ($this->properties->qoption) {
            return " - ".get_string("casesensitive", "languagelesson");
        }
        return parent::option_description_string();
    }

    public function display_answers(html_table $table) {
        $answers = $this->get_answers();
        $options = new stdClass;
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
    public function stats(array &$pagestats, $tries) {
        if (count($tries) > $this->lesson->maxattempts) {
            // If there are more tries than the max that is allowed, grab the last "legal" attempt.
            $temp = $tries[$this->lesson->maxattempts - 1];
        } else {
            // Else, user attempted the question less than the max, so grab the last one.
            $temp = end($tries);
        }
        if (isset($pagestats[$temp->pageid][$temp->useranswer])) {
            $pagestats[$temp->pageid][$temp->useranswer]++;
        } else {
            $pagestats[$temp->pageid][$temp->useranswer] = 1;
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
        $formattextdefoptions = new stdClass;
        $formattextdefoptions->para = false;  // I'll use it widely in this page.
        foreach ($answers as $answer) {
            if ($useranswer == null && $i == 0) {
                // I have the $i == 0 because it is easier to blast through it all at once.
                if (isset($pagestats[$this->properties->id])) {
                    $stats = $pagestats[$this->properties->id];
                    $total = $stats["total"];
                    unset($stats["total"]);
                    foreach ($stats as $valentered => $ntimes) {
                        $data = '<input type="text" size="50" disabled="disabled" readonly="readonly" value="'.s($valentered).'" />';
                        $percent = $ntimes / $total * 100;
                        $percent = round($percent, 2);
                        $percent .= "% ".get_string("enteredthis", "languagelesson");
                        $answerdata->answers[] = array($data, $percent);
                    }
                } else {
                    $answerdata->answers[] = array(get_string("nooneansweredthisquestion", "languagelesson"), " ");
                }
                $i++;
            } else if ($useranswer != null && ($answer->id == $useranswer->answerid || $answer == end($answers))) {
                 // Get in here when what the user entered is not one of the answers.
                $data = '<input type="text" size="50" disabled="disabled" readonly="readonly" value="'.s($useranswer->useranswer).'">';
                if (isset($pagestats[$this->properties->id][$useranswer->useranswer])) {
                    $percent = $pagestats[$this->properties->id][$useranswer->useranswer] / $pagestats[$this->properties->id]["total"] * 100;
                    $percent = round($percent, 2);
                    $percent .= "% ".get_string("enteredthis", "languagelesson");
                } else {
                    $percent = get_string("nooneenteredthis", "languagelesson");
                }
                $answerdata->answers[] = array($data, $percent);

                if ($answer->id == $useranswer->answerid) {
                    if ($answer->response == null) {
                        if ($useranswer->correct) {
                            $answerdata->response = get_string("thatsthecorrectanswer", "languagelesson");
                        } else {
                            $answerdata->response = get_string("thatsthewronganswer", "languagelesson");
                        }
                    } else {
                        $answerdata->response = $answer->response;
                    }
                    if ($this->lesson->custom) {
                        $answerdata->score = get_string("pointsearned", "languagelesson").": ".$answer->score;
                    } else if ($useranswer->correct) {
                        $answerdata->score = get_string("receivedcredit", "languagelesson");
                    } else {
                        $answerdata->score = get_string("didnotreceivecredit", "languagelesson");
                    }
                } else {
                    $answerdata->response = get_string("thatsthewronganswer", "languagelesson");
                    if ($this->lesson->custom) {
                        $answerdata->score = get_string("pointsearned", "languagelesson").": 0";
                    } else {
                        $answerdata->score = get_string("didnotreceivecredit", "languagelesson");
                    }
                }
            }
            $answerpage->answerdata = $answerdata;
        }
        return $answerpage;
    }
}


class languagelesson_add_page_form_shortanswer extends languagelesson_add_page_form_base {
    public $qtype = 'shortanswer';
    public $qtypestring = 'shortanswer';

    public function custom_definition() {
        global $DB;

        $this->_form->addElement('checkbox', 'qoption', get_string('options', 'languagelesson'),
                                 get_string('casesensitive', 'languagelesson')); // Oh my, this is a regex option!
        $this->_form->setDefault('qoption', 0);
        $this->_form->addHelpButton('qoption', 'casesensitive', 'languagelesson');

        $pageid = optional_param('pageid', 0, PARAM_INT);

        if (($answercount = $DB->count_records('languagelesson_answers', array('pageid'=> $pageid))) >= 4) {
            $maxanswers = $answercount+2;
        } else {
            $maxanswers = $this->_customdata['languagelesson']->maxanswers;
        }

        for ($i = 0; $i < $maxanswers; $i++) {
            $this->_form->addElement('header', 'answertitle'.$i, get_string('answer').' '.($i+1));
            $this->add_answer($i);
            $this->add_response($i);
            $this->add_jumpto($i, null, ($i == 0 ? LL_NEXTPAGE : LL_THISPAGE));
            $this->add_score($i, null, ($i===0)?1:0);
        }
        $this->_form->addElement('header', 'addanswerstitle', get_string('addanswerstitle', 'languagelesson'));
        $this->_form->addElement('submit', 'addanswers', get_string('addanswers', 'languagelesson'));
    }
}

class languagelesson_display_answer_form_shortanswer extends moodleform {

    public function definition() {
        global $OUTPUT, $USER, $DB, $PAGE;
        $mform = $this->_form;
        $contents = $this->_customdata['contents'];

        $hasattempt = false;
        $hasfeedback = false;
        $maxattemptsreached = 0;
        $retry = true;
        $attrs = array('size'=>'50', 'maxlength'=>'200');

        if (isset($this->_customdata['lessonid'])) {
            $lessonid = $this->_customdata['lessonid'];
            $maxattempts = $DB->get_field('languagelesson', 'maxattempts', array('id'=>$lessonid));
            if (isset($USER->modattempts[$lessonid]->useranswer)) {
                $this->_customdata['useranswer'] = htmlspecialchars_decode($USER->modattempts[$lessonid]->useranswer);
                $hasattempt = true;
                if ($maxattempts != 0) {
                    if ($USER->modattempts[$lessonid]->retry >= $maxattempts) {
                        $attrs['readonly'] = 'readonly';
                        $retry = false;
                        $maxattemptsreached = 1;
                    }
                }
                if ($feedbackrecords = $DB->get_records('languagelesson_feedback',
                                                array('pageid' => $USER->modattempts[$lessonid]->pageid, 'userid'=>$USER->id))) {
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
        }

        $mform->addElement('header', 'pageheader');

        $mform->addElement('html', $OUTPUT->container($contents, 'contents'));

        $options = new stdClass;
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

        $mform->addElement('text', 'answer', get_string('youranswer', 'languagelesson'), $attrs);
        $mform->setType('answer', PARAM_TEXT);

        if ($retry == false) {
            $this->add_action_buttons(null, get_string("nextpage", "languagelesson"));
        } else {
            $this->add_action_buttons(null, get_string("submit", "languagelesson"));
        }
    }

}
