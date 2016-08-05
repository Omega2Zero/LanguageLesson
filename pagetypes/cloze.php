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
 * Cloze
 *
 * @package    mod
 * @subpackage languagelesson
 * @copyright  2012 Carly Born
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * 
 * @todo I suspect when commas are used in cloze answers things break badly.
 **/

defined('MOODLE_INTERNAL') || die();

class languagelesson_page_type_cloze extends languagelesson_page {

    protected $type = languagelesson_page::TYPE_QUESTION;
    protected $typeidstring = 'cloze';
    protected $typeid = LL_CLOZE;
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
        global $USER, $CFG, $PAGE, $DB;
        $mform = new languagelesson_display_answer_form_cloze($CFG->wwwroot.'/mod/languagelesson/continue.php',
                                                        array('contents'=>$this->get_contents(), 'lessonid'=>$this->lesson->id));
        $data = new stdClass;
        $data->id = $PAGE->cm->id;
        $data->pageid = $this->properties->id;

        if (isset($USER->modattempts[$this->lesson->id]) & $attempt != null) {

            $mypage = $DB->get_record('languagelesson_pages', array('id'=>$data->pageid));
            $html = $this->get_cloze_correct_incorrect_view($attempt->useranswer, $mypage->contents);
            $data->answer = $this->get_cloze_set_data_values($attempt->useranswer, $mypage->contents);
        }
        $mform->set_data($data);
        return $mform->display();
    }

    /**
     * Takes array of user answers:
     *
     * - array('answer1', 'answer2') ..
     *
     * @param index int anchor target number 
     * @return array of true/false values indicating if each is correct
     */
    public function check_cloze_answer($useranswer, $rawanswerfield) {
        $answerarray = explode(", ", $rawanswerfield);

        if (count($answerarray) > 1) {
            foreach ($answerarray as $k => $answeroption) {
                    $answeroption = trim($answeroption);

                if (substr($answeroption, 0, 1) == "=") {
                    $theanswer = ltrim($answeroption, "= ");
                    break;
                }
            }
        }

        if (!isset($theanswer)) {
            $theanswer = reset($answerarray);
        }

        $match = preg_match('/^'.$useranswer.'$/i', $theanswer);
        return (!empty($match));
    }

    /**
     * Attempting to wrap up all the close craziness so I can use the display in continue.php and response_window.php
     */
    public function get_cloze_correct_incorrect_view($useranswersstr, $questionwithoutanswers) {
        global $CFG;
        $dbanswers = $this->get_answers();
        $useranswers = explode(", ", $useranswersstr);

        /*
         * We'll use DOMDocument to add our HTML - this is way too crazy what we have to do to get
         * to UTF8 encoded data that we can reference using getElementByID but whatever.
         */
        $doc = new DOMDocument();
        $doc->loadHTML('<?xml encoding="UTF-8">' . $questionwithoutanswers);

        foreach ($doc->childNodes as $item) {
            if ($item->nodeType == XML_PI_NODE) {
                $doc->removeChild($item); // Remove hack.
            }
        }
        $doc->encoding = 'UTF-8'; // Insert proper encoding.
        $doc2 = new DOMDocument();
        $doc2->loadHTML($doc->saveHTML());

        // We care about the position a1, a2, a3, a4 and need to determine it.
        // It is valid and required if
        // - there is an a id="a1" or whatever.
        // - there is a corresponding answer for that item in $page->answers.

        // $answers correspond to whichever a1, a2, a3, a4 have an answer, and in the order they appear.

        // First we need to find the position order.
                $html = $doc->saveHTML();
                $answercount = count($dbanswers);

        for ($i = 1; $i <= $answercount; $i++) {
            if (strpos($html, 'id="a'.$i.'"') !== false) {
                $anchorpos[strpos($html, 'id="a'.$i.'"')] = 'a'.$i;
            }
        }

        ksort($anchorpos);

        // Next lets get the actual answer fields from the database to get raw answers with proper keys.
        foreach ($dbanswers as $k => $answer) {
            $answernum = ($k+1); // Corresponds to a1, a2, a3, a4 ordinally.
            $actualrawanswers['a'.$answernum] = $answer->answer;
        }

        // Now lets create key => value pairs from the actual useranswers.
        foreach ($anchorpos as $key) {
            if (strlen($actualrawanswers[$key]) > 0) {
                $preppedanswers[$key] = array_shift($useranswers);
            }
        }

        // Carly's winter break fixes.
        // Lets do the actual loop to add our values.
        if (!empty($preppedanswers)) {
            foreach ($preppedanswers as $k => $v) {
                $mya = $doc2->getElementByID($k);
                $strong = $doc2->createElement('strong');
                $img = $doc2->createElement('img');

                if ($this->check_cloze_answer($v, $actualrawanswers[$k])) {
                    $img = $doc2->createElement('img');
                    $img->setAttribute('src', "{$CFG->wwwroot}".get_string('iconsrccorrect', 'languagelesson'));
                    $img->setAttribute('width', '10');
                    $img->setAttribute('height', '10');
                    $img->setAttribute('alt', 'correct');
                    $strong->setAttribute('style','background-color:#CCEACC;');
                    $strong->nodeValue = $v . ' ';
                } else {
                    $img = $doc2->createElement('img');
                    $img->setAttribute('src', "{$CFG->wwwroot}".get_string('iconsrcwrong', 'languagelesson'));
                    $img->setAttribute('width', '10');
                    $img->setAttribute('height', '10');
                    $img->setAttribute('alt', 'incorrect');
                    $strong->setAttribute('style','background-color:#F4A6A6;');
                    $strong->nodeValue = $v . ' ';
                }
                $mya->appendChild($strong);
                $mya->appendChild($img);
            }
        }

        // Get actual html sans doctype, html, head, body tags.
        $html = $doc2->saveHTML();
        $html = preg_replace('~<(?:!DOCTYPE|/?(?:html|head|body))[^>]*>\s*~i', '', $html);

        return $html;
    }

    /**
     * Figuring out the right answers to the right questions is messy due to how we are storing answers.
     * 
     * This maps a useranswer array to the correct data structure to use for mform->set_data to populate existing items.
     */
    public function get_cloze_set_data_values($useranswersstr, $questionwithoutanswers) {
        global $CFG;
        $dbanswers = $this->get_answers();
        $useranswers = explode(", ", $useranswersstr);

        /*
         * We'll use DOMDocument to parse our HTML to figure out questions about order - this is way too crazy
         */
        $doc = new DOMDocument();
        $doc->loadHTML('<?xml encoding="UTF-8">' . $questionwithoutanswers);
        foreach ($doc->childNodes as $item) {
            if ($item->nodeType == XML_PI_NODE) {
                $doc->removeChild($item); // Remove hack.
            }
        }
        $doc->encoding = 'UTF-8'; // Insert proper encoding.
        $doc2 = new DOMDocument();
        $doc2->loadHTML($doc->saveHTML());

        // We care about the position a1, a2, a3, a4 and need to determine it.
        // It is valid and required if
        // - there is an a id="a1" or whatever.
        // - there is a corresponding answer for that item in $page->answers.

        // $answers correspond to whichever a1, a2, a3, a4 have an answer, and in the order they appear.

        // First we need to find the position order.
        $html = $doc->saveHTML();
        $answercount = count($dbanswers);

        for ($i = 1; $i <= $answercount; $i++) {
            if (strpos($html, 'id="a'.$i.'"') !== false) {
                $anchorpos[strpos($html, 'id="a'.$i.'"')] = 'a'.$i;
            }
        }

        ksort($anchorpos);

        // Next lets get the actual answer fields from the database to get raw answers with proper keys.
        foreach ($dbanswers as $k => $answer) {
            $answernum = ($k+1); // Corresponds to a1, a2, a3, a4 ordinally.
            $actualrawanswers['a'.$answernum] = $answer->answer;
        }

        // Now lets create key => value pairs from the actual useranswers.
        foreach ($anchorpos as $key) {
            if (strlen($actualrawanswers[$key]) > 0) {
                $answer = array_shift($useranswers);
                $answerarray = explode(", ", $actualrawanswers[$key]);

                if (count($answerarray) > 1) {
                    foreach ($answerarray as $index => $answeroption) {
                        $pos = strpos($answeroption, '=');
                        if ($pos !== false) {
                            $thisanswer = ltrim($answeroption, "= ");

                            if (trim($answer) == trim($thisanswer)) {
                                $answer = $index;
                                break;
                            }
                        }
                    }
                }
                $preppedanswers[$key] = $answer;
            }
        }

        return $preppedanswers;
    }

    /**
     * Carly's winter break fixes
     * @todo this should use check_cloze_answers to ensure we use the same logic but this is so tied to the
     *       for submission process that it is not straightforward to do.
     */
    public function check_answer() {
        global $CFG, $PAGE;
        $result = parent::check_answer();
        $result->typeid = $this->typeid;
        $ismatch = true;
        $score = 0;
        $mform = new languagelesson_display_answer_form_cloze($CFG->wwwroot.'/mod/languagelesson/continue.php',
                                                              array('contents'=>$this->get_contents()));
        $data = $mform->get_data();
        require_sesskey();

        $answers = $this->get_answers();

        if ($data->maxattemptsreached == 1) {
            redirect(new moodle_url('/mod/languagelesson/view.php',
                                    array('id'=>$PAGE->cm->id, 'pageid'=>$this->properties->nextpageid)));
        }

        if (isset($data->answer)) {
            $studentanswers = $data->answer;
            $result->noanswer = false;
            foreach ($studentanswers as &$answer) {
                $answer = trim($answer);
                if ($answer == '') {
                    $result->noanswer = true;
                }
            }
            if ($result->noanswer) {
                // If user have all blanks in the answer fields, then return no answer message;
                return $result;
            }
            foreach ($studentanswers as $answernum => $stuanswer) {
                $ansinfo = $answers[$answernum - 1];
                 // Should always have this relationship with actual answer in db since ans_num start with 1.
                if (isset($answers[$answernum-1])) {
                    if ($ansinfo->flags) {
                        $hasanswer = false;
                        $choices = explode(", ", $ansinfo->answer);
                        // Since its a multiple choice then answer submitted is in number format.
                        // So we need to reformated in real answer.
                        $studentanswers[$answernum] = ltrim($choices[$stuanswer], "= ");

                        foreach ($choices as $choice) {
                            // Trim the choices first.
                            $choice = trim($choice);

                            if (substr($choice, 0, 1) == "=") {
                                $expectedanswer = ltrim($choice, "= ");
                                $hasanswer = true;
                                break;
                            }
                        }

                        if (!$hasanswer) {
                            // No right choice then by default the first one is the default choice.
                            $expectedanswer = trim(reset($choices));
                        }
                    } else {
                        $expectedanswer  = trim($ansinfo->answer);
                    }
                    $attemptedanswer = $studentanswers[$answernum];
                    $markit          = false;
                    $casesensitive   = ($this->qoption);

                    $expectedanswer = str_replace('*', '#####', $expectedanswer);
                    $expectedanswer = preg_quote($expectedanswer, '/');
                    $expectedanswer = str_replace('#####', '.*', $expectedanswer);

                    // If case sensitive option not enabled.

                    if (!$casesensitive) {
                        $expectedanswer = strtolower($expectedanswer);
                        $attemptedanswer = strtolower($attemptedanswer);
                    }
                    // See if user typed in any of the correct answers.

                    // We are using 'normal analysis', which ignores case.
                    if (!preg_match('/^'.$expectedanswer.'$/i', $attemptedanswer)) {
                        $ismatch = false;
                    } else {
                        $score = $score + $ansinfo->score;
                    }
                }
            }
            $studentanswersstring = implode(", ", $studentanswers);
        }
        $formattextdefoptions = new stdClass();
        $formattextdefoptions->noclean = true;
        $formattextdefoptions->para = false;

        if ($ismatch) {
            $result->correctanswer = true;
            // $result->response      = format_text($correct->answer, $correct->answerformat, $formattextdefoptions);
            // $result->answerid      = $correct->id;
            // $result->newpageid     = $correct->jumpto;
        } else {
            $result->correctanswer = false;
            // $result->response      = format_text($wrong->answer, $wrong->answerformat, $formattextdefoptions);
            // $result->answerid      = $wrong->id;
            // $result->newpageid     = $wrong->jumpto;
        }

        if (isset($studentanswersstring)) {
            $result->userresponse = $studentanswersstring;
            // Clean student answer as it goes to output.
            $result->studentanswer = s($result->userresponse);
        }
        $result->score = $score;
        return $result;
    }

    public function properties() {
        $properties = clone($this->properties);
        if ($this->answers === null) {
            $this->get_answers();
        }
        if (count($this->answers)>0) {
            $count = 0;
            foreach ($this->answers as $answer) {
                $properties->{'answer_editor['.$count.']'} = array('text'=>$answer->answer, 'format'=>$answer->answerformat);
                $properties->{'response_editor['.$count.']'} = array('text'=>$answer->response, 'format'=>$answer->responseformat);
                $properties->{'jumpto['.$count.']'} = $answer->jumpto;
                $properties->{'score['.$count.']'} = $answer->score;
                // Add ans_option to the generic properties function.
                $properties->{'ans_option['.$count.']'} = $answer->flags;
                $count++;
            }
        }
        return $properties;
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

        $i = 0;
        $n = 0;
        foreach ($answers as $answer) {
            if ($n < 2) {
                if ($answer->answer != null) {
                    $cells = array();
                    if ($n == 0) {
                        $cells[] = "<span class=\"label\">".get_string("correctresponse", "languagelesson").'</span>';
                    } else if ($n == 1) {
                        $cells[] = "<span class=\"label\">".get_string("wrongresponse", "languagelesson").'</span>';
                    }
                    $cells[] = format_text($answer->answer, $answer->answerformat, $options);
                    $table->data[] = new html_table_row($cells);
                }

                if ($n == 0) {
                    $cells = array();
                    $cells[] = '<span class="label">'.get_string("correctanswerjump", "languagelesson")."</span>: ";
                    $cells[] = $this->get_jump_name($answer->jumpto);
                    $table->data[] = new html_table_row($cells);
                } else if ($n == 1) {
                    $cells = array();
                    $cells[] = '<span class="label">'.get_string("wronganswerjump", "languagelesson")."</span>: ";
                    $cells[] = $this->get_jump_name($answer->jumpto);
                    $table->data[] = new html_table_row($cells);
                }
                if ($n === 0) {
                    $table->data[count($table->data)-1]->cells[0]->style = 'width:20%;';
                }
                $n++;
                $i--;
            } else {
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
                $cells[] = "<span class=\"label\">".get_string("score", "languagelesson").'</span>';
                $cells[] = $answer->score;
                $table->data[] = new html_table_row($cells);
                if ($i === 1) {
                    $table->data[count($table->data)-1]->cells[0]->style = 'width:20%;';
                }
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

    /**
     * This currently does not return something that works for the places
     * where it is seemingly called (respond_window.php, report.php).
     */
    public function report_answers($answerpage, $answerdata, $useranswer, $pagestats, &$i, &$n) {
        $formattextdefoptions = new stdClass;
        $formattextdefoptions->para = false;  // I'll use it widely in this page.

        $answerpage->answerdata = $answerdata;

        return $answerpage;
    }
}


class languagelesson_add_page_form_cloze extends languagelesson_add_page_form_base {
    public $qtype = 'cloze';
    public $qtypestring = 'cloze';

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
            $this->_form->addElement('checkbox', "ans_option[$i]", get_string('options', 'languagelesson'),
                                     get_string('clozeusedropdownmenu', 'languagelesson'));
            // $this->_form->setDefault("ans_option_[$i]".$i, 0);
            // $this->add_response($i);
            // $this->add_jumpto($i, null, ($i == 0 ? LL_NEXTPAGE : LL_THISPAGE));
            $this->add_score($i, null, ($i===0)?1:0);
        }
        $this->_form->addElement('header', 'addanswerstitle', get_string('addanswerstitle', 'languagelesson'));
        $this->_form->addElement('submit', 'addanswers', get_string('addanswers', 'languagelesson'));

    }
}

class languagelesson_display_answer_form_cloze extends moodleform {

    public function definition() {
        global $OUTPUT, $USER, $DB;
        $mform = $this->_form;
        $contents = $this->_customdata['contents'];
        $pageid = optional_param('pageid', 0, PARAM_INT);

        $hasattempt = false;
        $hasfeedback = false;
        // $attrs = array('size'=>'15', 'maxlength'=>'200');
        $maxattemptsreached = 0;

        if (isset($this->_customdata['lessonid'])) {
            $lessonid = $this->_customdata['lessonid'];
            if (isset($USER->modattempts[$lessonid]->useranswer)) {
                $hasattempt = true;
                $maxattempts = $DB->get_field('languagelesson', 'maxattempts', array('id'=>$lessonid));
                if ($maxattempts != 0) {
                    if ($USER->modattempts[$lessonid]->retry >= $maxattempts) {
                        $maxattemptsreached = 1;
                    }
                }
                if ($feedbackrecords = $DB->get_records('languagelesson_feedback',
                                                        array('userid' => $USER->id, 'pageid'=>$pageid))) {
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
        $mform->addElement('html', $OUTPUT->container_start('contents'));

        if ($hasfeedback) {
            $mform->addElement('html', '<center><table border=1><tr><td>'. $teacherpic . $fullname .
                               '</td></tr><tr><td>'.$feedbackdate.'</td></tr>
                               <tr><td><hr>'.$textfeedback.'</td></tr></table></center>');
        }

        // Substitute anchor mark in real moodleform object.
        $pattern = '|<\s*a\s[^>]*(class="(.[^"]*)"\s*id="a([0-9]{1,2})"\s*title="(.[^"]*)"\s*)><\/a>|U';
        $offset = 0;

        $contentgroup = array();
        preg_match($pattern, $contents, $matches, PREG_OFFSET_CAPTURE, $offset);

        while ($matches) {
            // Operations here.
            $chunkoftext = substr($contents, $offset, $matches[0][1]-$offset);
            $type = $matches[2][0];
            $answernum = $matches[3][0];
            $answercontent = $matches[4][0];

            // Push the chuck of the text first.
            array_push($contentgroup, $mform->createElement('static', 'cloze_text', '', $chunkoftext));

            // If the anchor is a short answer one, then push an text box.
            if ($type == 'shortanswer') {
                $charcount = strlen($answercontent);
                $attrs = array('size'=> $charcount, 'maxlength'=>'200');
                array_push($contentgroup, $mform->createElement('text', "answer[$answernum]", null, $attrs));
            } else if ($type == 'multiplechoice') {
                $choices = explode(',', $answercontent);
                foreach ($choices as &$choice) {
                    $choice = ltrim($choice, "= ");
                }
                array_push($contentgroup, $mform->createElement('select', "answer[$answernum]", null, $choices));
            }
            // Next offset should be the pos right after current match.
            // Matches[0][1] is the pos for the  beginning of the current match, mathc[0] is the current matched string.
            $offset = $matches[0][1]+ strlen($matches[0][0]);
            preg_match($pattern, $contents, $matches, PREG_OFFSET_CAPTURE, $offset);
        }

        array_push($contentgroup, $mform->createElement('static', 'cloze_text', '', substr($contents, $offset)));

        $mform->addGroup($contentgroup, 'contents', ' ', array(' '), false);
        $mform->addElement('html', $OUTPUT->container_end('contents'));

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

        // $mform->addElement('text', 'answer', get_string('youranswer', 'languagelesson'), $attrs);
        // $mform->setType('answer', PARAM_TEXT);

        if ($maxattemptsreached == 1) {
            $this->add_action_buttons(null, get_string("nextpage", "languagelesson"));
        } else {
            $this->add_action_buttons(null, get_string("submit", "languagelesson"));
        }
    }
}
