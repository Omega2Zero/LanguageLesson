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
 * Action for adding a question page.  Prints an HTML form.
 *
 * @package    mod
 * @subpackage lesson
 * @copyright  2009 Sam Hemelryk
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 **/

require_once("../../config.php");
require_once($CFG->dirroot.'/mod/languagelesson/locallib.php');
require_once('editpage_form.php');

// First get the preceeding page.
$pageid = required_param('pageid', PARAM_INT);
$id     = required_param('id', PARAM_INT);         // Course Module ID.
$qtype  = optional_param('qtype', 0, PARAM_INT);
$edit   = optional_param('edit', false, PARAM_BOOL);

$cm = get_coursemodule_from_id('languagelesson', $id, 0, false, MUST_EXIST);;
$course = $DB->get_record('course', array('id' => $cm->course), '*', MUST_EXIST);
$lesson = new languagelesson($DB->get_record('languagelesson', array('id' => $cm->instance), '*', MUST_EXIST));

require_login($course, false, $cm);

$context = context_module::instance($cm->id);
require_capability('mod/languagelesson:edit', $context);

$PAGE->set_url('/mod/languagelesson/editpage.php', array('pageid'=>$pageid, 'id'=>$id, 'qtype'=>$qtype));

if ($edit) {
    $editpage = languagelesson_page::load($pageid, $lesson);
    $qtype = $editpage->qtype;
    $edit = true;
} else {
    $edit = false;
}

$jumpto = languagelesson_page::get_jumptooptions($pageid, $lesson);
$manager = languagelesson_page_type_manager::get($lesson);
$editoroptions = array('noclean'=>true, 'maxfiles'=>EDITOR_UNLIMITED_FILES, 'maxbytes'=>$CFG->maxbytes);


// If the previous page was the Question type selection form, this form
// will have a different name (e.g. _qf__languagelesson_add_page_form_selection
// versus _qf__languagelesson_add_page_form_multichoice). This causes confusion
// in moodleform::_process_submission because the array key check doesn't
// tie up with the current form name, which in turn means the "submitted"
// check ends up evaluating as false, thus it's not possible to check whether
// the Question type selection was cancelled. For this reason, a dummy form
// is created here solely to check whether the selection was cancelled.
if ($qtype) {
    $mformdummy = $manager->get_page_form(0, array('editoroptions'=>$editoroptions, 'jumpto'=>$jumpto,
                                    'languagelesson'=>$lesson, 'edit'=>$edit, 'maxbytes'=>$PAGE->course->maxbytes));
    if ($mformdummy->is_cancelled()) {
        redirect("$CFG->wwwroot/mod/languagelesson/edit.php?id=$id");
        exit;
    }
}

$mform = $manager->get_page_form($qtype, array('editoroptions'=>$editoroptions, 'jumpto'=>$jumpto,
                                            'languagelesson'=>$lesson, 'edit'=>$edit, 'maxbytes'=>$PAGE->course->maxbytes));

if ($mform->is_cancelled()) {
    redirect("$CFG->wwwroot/mod/languagelesson/edit.php?id=$id");
    exit;
}
// If this page is a CLOZE, validate it before we change any records.
if ($mform->qtype == LL_CLOZE) {
    // Note that since the text is coming from TinyMCE, it's pre-slashed, so we have to strip the slashes to get a proper
    // DOMDocument reading of the HTML.
    languagelesson_validate_cloze_text(stripslashes($mform->contents), $mform->answer, $mform->dropdown);
}

if ($edit) {
    $data = $editpage->properties();
    $data->pageid = $editpage->id;
    $data->id = $cm->id;
    $editoroptions['context'] = $context;
    $data = file_prepare_standard_editor($data, 'contents', $editoroptions, $context,
                                            'mod_languagelesson', 'page_contents',  $editpage->id);
    $mform->set_data($data);
    $PAGE->navbar->add(get_string('edit'), new moodle_url('/mod/languagelesson/edit.php', array('id'=>$id)));
    $PAGE->navbar->add(get_string('editingquestionpage', 'languagelesson', get_string($mform->qtypestring, 'languagelesson')));
} else {
    // Give the page type being created a chance to override the creation process
    // this is used by endofbranch, cluster, and endofcluster to skip the creation form.
    // IT SHOULD ALWAYS CALL require_sesskey();.
    $mform->construction_override($pageid, $lesson);

    $data = new stdClass;
    $data->id = $cm->id;
    $data->pageid = $pageid;
    if ($qtype) {
        // TODO: the handling of form for the selection of question type is a bloody hack! (skodak).
        $data->qtype = $qtype;
    }
    $data = file_prepare_standard_editor($data, 'contents', $editoroptions, null);
    $mform->set_data($data);
    $PAGE->navbar->add(get_string('addanewpage', 'languagelesson'), $PAGE->url);
    if ($qtype !== 'unknown') {
        $PAGE->navbar->add(get_string($mform->qtypestring, 'languagelesson'));
    }
}

if ($data = $mform->get_data()) {
    require_sesskey();
    if ($edit) {
        $data->lessonid = $data->id;
        $data->id = $data->pageid;
        unset($data->pageid);
        unset($data->edit);
        $editpage->update($data, $context, $PAGE->course->maxbytes);
    } else {
        $editpage = languagelesson_page::create($data, $lesson, $context, $PAGE->course->maxbytes);
    }

    redirect(new moodle_url('/mod/languagelesson/edit.php', array('id'=>$cm->id)));
}

$lessonoutput = $PAGE->get_renderer('mod_languagelesson');
echo $lessonoutput->header($lesson, $cm);
$mform->display();
echo $lessonoutput->footer();
