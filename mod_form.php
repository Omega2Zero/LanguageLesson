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
 * Form to define a new instance of lesson or edit an instance.
 * It is used from /course/modedit.php.
 *
 * @package    mod
 * @subpackage lesson
 * @copyright  1999 onwards Martin Dougiamas  {@link http://moodle.com}
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or late
 **/

defined('MOODLE_INTERNAL') || die();

require_once($CFG->dirroot.'/course/moodleform_mod.php');
require_once($CFG->dirroot.'/mod/languagelesson/locallib.php');

class mod_languagelesson_mod_form extends moodleform_mod {

    protected $course = null;

    public function mod_languagelesson_mod_form($current, $section, $cm, $course) {
        $this->course = $course;
        parent::moodleform_mod($current, $section, $cm, $course);
    }

    function definition() {
        global $CFG, $COURSE, $DB;

        $mform    = $this->_form;

        // -------------------------------------------------------------------------------
        $mform->addElement('header', 'general', get_string('general', 'form'));

        $mform->addElement('text', 'name', get_string('name'), array('size'=>'64'));
        if (!empty($CFG->formatstringstriptags)) {
            $mform->setType('name', PARAM_TEXT);
        } else {
            $mform->setType('name', PARAM_CLEANHTML);
        }
        $mform->addRule('name', null, 'required', null, 'client');

        // Create a text box that can be enabled/disabled for lesson time limit.
        $timedgrp = array();
        $timedgrp[] = &$mform->createElement('text', 'maxtime');
        $timedgrp[] = &$mform->createElement('checkbox', 'timed', '', get_string('enable'));
        $mform->addGroup($timedgrp, 'timedgrp', get_string('maxtime', 'languagelesson'), array(' '), false);
        $mform->disabledIf('timedgrp', 'timed');

        // Add numeric rule to text field.
        $timedgrprules = array();
        $timedgrprules['maxtime'][] = array(null, 'numeric', null, 'client');
        $mform->addGroupRule('timedgrp', $timedgrprules);

        // Rest of group setup.
        $mform->setDefault('timed', 0);
        $mform->setDefault('maxtime', 20);
        $mform->setType('maxtime', PARAM_INT);

        $numbers = array();
        for ($i=20; $i>1; $i--) {
            $numbers[$i] = $i;
        }

        $mform->addElement('date_time_selector', 'available', get_string('available', 'languagelesson'), array('optional'=>true));
        $mform->setDefault('available', 0);

        $mform->addElement('date_time_selector', 'deadline', get_string('deadline', 'languagelesson'), array('optional'=>true));
        $mform->setDefault('deadline', 0);

        $mform->addElement('selectyesno', 'usepassword', get_string('usepassword', 'languagelesson'));
        $mform->addHelpButton('usepassword', 'usepassword', 'languagelesson');
        $mform->setDefault('usepassword', 0);
        $mform->setAdvanced('usepassword');

        $mform->addElement('passwordunmask', 'password', get_string('password', 'languagelesson'));
        $mform->setDefault('password', '');
        $mform->setType('password', PARAM_RAW);
        $mform->setAdvanced('password');
        $mform->disabledIf('password', 'usepassword', 'eq', 0);

        // -------------------------------------------------------------------------------
        $mform->addElement('header', 'gradeoptions', get_string('gradeoptions', 'languagelesson'));

        $options = array();
        $options[LL_TYPE_PRACTICE] = get_string('practicetype', 'languagelesson');
        $options[LL_TYPE_ASSIGNMENT] = get_string('assignmenttype', 'languagelesson');
        $options[LL_TYPE_TEST] = get_string('testtype', 'languagelesson');
        $mform->addElement('select', 'type', get_string('type', 'languagelesson'), $options);
        $mform->addHelpButton('type', 'type', 'languagelesson');
        $mform->setDefault('type', LL_TYPE_ASSIGNMENT);
        // Assign custom javascript for updating form values based on lesson type selection.
        $changeEvent = "var theform = document.getElementById('mform1');
                        var typeField = theform.elements['type']

                        autograde = theform.elements['autograde'];
                        defaultpoints = theform.elements['defaultpoints'];
                        penalty = theform.elements['penalty'];
                        penaltytype = theform.elements['penaltytype'];
                        penaltyvalue = theform.elements['penaltyvalue'];
                        ongoingscore = theform.elements['showongoingscore'];
                        maxattempts = theform.elements['maxattempts'];
                        showoldanswer = theform.elements['showoldanswer'];
                        defaultfeedback = theform.elements['defaultfeedback'];
                        contextcolors = theform.elements['contextcolors'];

                        if (typeField.value == ".LL_TYPE_PRACTICE.") {
                            autograde.disabled = true;
                            defaultpoints.disabled = true;
                            penalty.disabled = true;
                            penaltytype.disabled = true;
                            penaltyvalue.disabled = true;
                            ongoingscore.disabled = true;
                            maxattempts.value = 0;
                            showoldanswer.value = 1;
                            defaultfeedback.value = 1;
                            contextcolors.value = 1;
                        } else {
                            autograde.disabled = false;
                            defaultpoints.disabled = false;
                            penalty.disabled = false;
                            if (penalty.value == '1') { penaltytype.disabled = false; }
                            if (!penaltytype.disabled &&
                                penaltytype.value == '".LL_PENALTY_SET."') { penaltyvalue.disabled = false; }
                            ongoingscore.disabled = false;

                            // if it's a test, change other things as necessary
                            if (typeField.value == ".LL_TYPE_TEST.") {
                                maxattempts.value = 1;
                                showoldanswer.value = 0;
                                defaultfeedback.value = 0;
                                contextcolors.value = 0;
                            } else {
                                maxattempts.value = 0;
                                showoldanswer.value = 1;
                                contextcolors.value = 1;
                            }
                        }";
        $mform->updateElementAttr('type', 'onchange="'.$changeEvent.'"');

        $mform->addElement('selectyesno', 'autograde', get_string('automaticgrading', 'languagelesson'));
        $mform->addHelpButton('autograde', 'automaticgrading', 'languagelesson');
        $mform->setDefault('autograde', 0);

        $mform->addElement('text', 'defaultpoints', get_string('defaultpoints', 'languagelesson'));
        $mform->setDefault('defaultpoints', 1);
        $mform->setType('defaultpoints', PARAM_NUMBER);
        $mform->addHelpButton('defaultpoints', 'defaultpoints', 'languagelesson');

        // -------------------------------------------------------------------------------
        $mform->addElement('header', 'flowcontrol', get_string('flowcontrol', 'languagelesson'));

        $mform->addElement('selectyesno', 'showoldanswer', get_string('showoldanswer', 'languagelesson'));
        $mform->addHelpButton('showoldanswer', 'showoldanswer', 'languagelesson');
        $mform->setDefault('showoldanswer', 1);

        $numbers = array();
        $numbers[0] = 'Unlimited';
        for ($i=10; $i>0; $i--) {
            $numbers[$i] = $i;
        }
        $mform->addElement('select', 'maxattempts', get_string('maximumnumberofattempts', 'languagelesson'), $numbers);
        $mform->addHelpButton('maxattempts', 'maximumnumberofattempts', 'languagelesson');
        $mform->setDefault('maxattempts', 0);

        $mform->addElement('selectyesno', 'feedback', get_string('displaydefaultfeedback', 'languagelesson'));
        $mform->addHelpButton('feedback', 'displaydefaultfeedback', 'languagelesson');
        $mform->setDefault('feedback', 1);

        $mform->addElement('text', 'defaultcorrect', get_string('defaultcorrectfeedback', 'languagelesson'));
        $mform->addHelpButton('defaultcorrect', 'defaultcorrect', 'languagelesson');
        $mform->setDefault('defaultcorrect', get_string('defaultcorrectfeedbacktext', 'languagelesson'));
        $mform->disabledIf('defaultcorrect', 'feedback', 'selectedIndex', '1');

        $mform->addElement('text', 'defaultwrong', get_string('defaultwrongfeedback', 'languagelesson'));
        $mform->addHelpButton('defaultwrong', 'defaultwrong', 'languagelesson');
        $mform->setDefault('defaultwrong', get_string('defaultwrongfeedbacktext', 'languagelesson'));
        $mform->disabledIf('defaultwrong', 'feedback', 'selectedIndex', '1');

        $mform->addElement('selectyesno', 'progressbar', get_string('progressbar', 'languagelesson'));
        $mform->addHelpButton('progressbar', 'progressbar', 'languagelesson');
        $mform->setDefault('progressbar', 0);


        // Get the modules.
        if ($mods = get_course_mods($COURSE->id)) {
            $modinstances = array();
            foreach ($mods as $mod) {

                // Get the module name and then store it in a new array.
                if ($module = get_coursemodule_from_instance($mod->modname, $mod->instance, $COURSE->id)) {
                    if (isset($this->_cm->id) and $this->_cm->id != $mod->id) {
                        $modinstances[$mod->id] = $mod->modname.' - '.$module->name;
                    }
                }
            }
            asort($modinstances); // Sort by module name.
            $modinstances=array(0=>get_string('none'))+$modinstances;

            $mform->addElement('select', 'activitylink', get_string('activitylink', 'languagelesson'), $modinstances);
            $mform->addHelpButton('activitylink', 'activitylink', 'languagelesson');
            $mform->setDefault('activitylink', 0);
            $mform->setAdvanced('activitylink');
        }

        // -------------------------------------------------------------------------------
        $mform->addElement('header', '', get_string('lessonformating', 'languagelesson'));

        $mform->addElement('selectyesno', 'displayleft', get_string('displayleftmenu', 'languagelesson'));
        $mform->addHelpButton('displayleft', 'displayleft', 'languagelesson');
        $mform->setDefault('displayleft', 1);

        $mform->addElement('selectyesno', 'contextcolors', get_string('displayleftmenuicons', 'languagelesson'));
        $mform->addHelpButton('contextcolors', 'contextcolors', 'languagelesson');
        $mform->setDefault('contextcolors', 1);

        $mform->addElement('selectyesno', 'progressbar', get_string('progressbar', 'languagelesson'));
        $mform->addHelpButton('progressbar', 'progressbar', 'languagelesson');
        $mform->setDefault('progressbar', 0);


        // -------------------------------------------------------------------------------
        $mform->addElement('header', 'mediafileheader', get_string('mediafile', 'languagelesson'));

        $filepickeroptions = array();
        $filepickeroptions['filetypes'] = '*';
        $filepickeroptions['maxbytes'] = $this->course->maxbytes;
        $mform->addElement('filepicker', 'mediafilepicker', get_string('mediafile', 'languagelesson'), null, $filepickeroptions);
        $mform->addHelpButton('mediafilepicker', 'mediafile', 'languagelesson');

        // -------------------------------------------------------------------------------
        $mform->addElement('header', 'dependencyon', get_string('dependencyon', 'languagelesson'));

        $options = array(0=>get_string('none'));
        if ($lessons = get_all_instances_in_course('languagelesson', $COURSE)) {
            foreach ($lessons as $lesson) {
                if ($lesson->id != $this->_instance) {
                    $options[$lesson->id] = format_string($lesson->name, true);
                }

            }
        }
        $mform->addElement('select', 'dependency', get_string('dependencyon', 'languagelesson'), $options);
        $mform->addHelpButton('dependency', 'dependencyon', 'languagelesson');
        $mform->setDefault('dependency', 0);

        $mform->addElement('text', 'timespent', get_string('timespentminutes', 'languagelesson'));
        $mform->setDefault('timespent', 0);
        $mform->setType('timespent', PARAM_INT);

        $mform->addElement('checkbox', 'completed', get_string('completed', 'languagelesson'));
        $mform->setDefault('completed', 0);

        $mform->addElement('text', 'gradebetterthan', get_string('gradebetterthan', 'languagelesson'));
        $mform->setDefault('gradebetterthan', 0);
        $mform->setType('gradebetterthan', PARAM_INT);

        // -------------------------------------------------------------------------------
        $this->standard_coursemodule_elements();
        // -------------------------------------------------------------------------------
        // Buttons.
        $this->add_action_buttons();
    }

    /**
     * Enforce defaults here
     *
     * @param array $default_values Form defaults
     * @return void
     **/
    function data_preprocessing(&$default_values) {
        global $DB;
        global $module;
        if (isset($default_values['conditions'])) {
            $conditions = unserialize($default_values['conditions']);
            $default_values['timespent'] = $conditions->timespent;
            $default_values['completed'] = $conditions->completed;
            $default_values['gradebetterthan'] = $conditions->gradebetterthan;
        }
        // After this passwords are clear text, MDL-11090.
        if (isset($default_values['password']) and ($module->version<2008112600)) {
            unset($default_values['password']);
        }

        if ($this->current->instance) {
            // Editing existing instance - copy existing files into draft area.
            $draftitemid = file_get_submitted_draft_itemid('mediafilepicker');
            file_prepare_draft_area($draftitemid, $this->context->id, 'mod_languagelesson', 'mediafile', 0,
                                    array('subdirs'=>0, 'maxbytes' => $this->course->maxbytes, 'maxfiles' => 1));
            $default_values['mediafilepicker'] = $draftitemid;
        }

        // Set up the completion checkboxes which aren't part of standard data.
        // We also make the default value (if you turn on the checkbox) for those
        // numbers to be 1, this will not apply unless checkbox is ticked.
        $default_values['completionsubmit']=
            !empty($default_values['completionsubmit']) ? 1 : 0;
        if(empty($default_values['completionsubmit'])) {
            $default_values['completionsubmit']=0;
        }
    }

    /**
     * Enforce validation rules here
     *
     * @param object $data Post data to validate
     * @return array
     **/
    function validation($data, $files) {
        $errors = parent::validation($data, $files);

        if (empty($data['maxtime']) and !empty($data['timed'])) {
            $errors['timedgrp'] = get_string('err_numeric', 'form');
        }
        if (!empty($data['usepassword']) && empty($data['password'])) {
            $errors['password'] = get_string('emptypassword', 'languagelesson');
        }

        return $errors;
    }

    /**
     * Add any custom completion rules to the form.
     *
     * @return array Contains the names of the added form elements
     */
    public function add_completion_rules() {
        $mform =& $this->_form;

        $mform->addElement('checkbox', 'completionsubmit', '', get_string('completionsubmit', 'languagelesson'));
        return array('completionsubmit');
    }

    /**
     * Determines if completion is enabled for this module.
     *
     * @param array $data
     * @return bool
     */
    public function completion_rule_enabled($data) {
        return !empty($data['completionsubmit']);
    }
    
    function get_data() {
        $data = parent::get_data();
        if (!$data) {
            return $data;
        }
        if (!empty($data->completionunlocked)) {
            // Turn off completion settings if the checkboxes aren't ticked
            $autocompletion = !empty($data->completion) && $data->completion==COMPLETION_TRACKING_AUTOMATIC;
            if (empty($data->completionpostsenabled) || !$autocompletion) {
               $data->completionposts = 0;
            }
        }
        return $data;
    }


}

