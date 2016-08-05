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
 * Provides the interface for overall authoring of lessons
 *
 * @package    mod
 * @subpackage lesson
 * @copyright  1999 onwards Martin Dougiamas  {@link http://moodle.com}
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 **/

require_once('../../config.php');
require_once($CFG->dirroot.'/mod/languagelesson/locallib.php');

$id = required_param('id', PARAM_INT);

$cm = get_coursemodule_from_id('languagelesson', $id, 0, false, MUST_EXIST);;
$course = $DB->get_record('course', array('id' => $cm->course), '*', MUST_EXIST);
$lesson = new languagelesson($DB->get_record('languagelesson', array('id' => $cm->instance), '*', MUST_EXIST));

require_login($course, false, $cm);

$context = context_module::instance($cm->id);
require_capability('mod/languagelesson:manage', $context);

$mode    = optional_param('mode', get_user_preferences('languagelesson_view', 'collapsed'), PARAM_ALPHA);
$PAGE->set_url('/mod/languagelesson/edit.php', array('id'=>$cm->id, 'mode'=>$mode));

if ($mode != get_user_preferences('languagelesson_view', 'collapsed') && $mode !== 'single') {
    set_user_preference('languagelesson_view', $mode);
}

$lessonoutput = $PAGE->get_renderer('mod_languagelesson');
$PAGE->navbar->add(get_string('edit'));
echo $lessonoutput->header($lesson, $cm, $mode);
// Insert javascript here to get checked boxes and put them in a hidden field?
?>
<script type="text/javascript">
function getURL(pageid) {
	var checkbox = document.getElementById('page['+pageid+']');
	var linkid = document.getElementById('moveallid');
	if (checkbox.checked) {
		url = linkid + pageid + ',';
		linkid.href = url;
	} else {
		var url = linkid.href;
		var n = url.replace(pageid + ',', "");
		linkid.href = n;
	}
}
function getPage(pageid) {
		var newpageid = pageid + ',';
		php_url += php_url + newpageid;
		//document.getElementById("pages").value += newtext;
		
}
// check all or none checkboxes
function checkAll() {
    var checkboxes = document.getElementsByTagName("input");
    for (var i=0; i< checkboxes.length; i++) {
	if (checkboxes[i].type == 'checkbox') {
	    checkboxes[i].checked = true;
	}
    }
    return false;
}

function checkNone() {
    var checkboxes = document.getElementByTagName("input");
    for (var i=0; i < checkboxes.length; i++) {
	if (checkboxes[i].type == 'checkbox') {
	    checkboxes[i].checked = false;
	}
    }
    return false;
}
</script>
    <input type="hidden" id="pages" name="pages" value="">
<?php

if (!$lesson->has_pages()) {
    // There are no pages; give teacher some options.
    require_capability('mod/languagelesson:edit', $context);
    echo $lessonoutput->add_first_page_links($lesson);
} else {
    switch ($mode) {
        case 'collapsed':
            echo $lessonoutput->display_edit_collapsed($lesson, $lesson->firstpageid);
            echo $lessonoutput->display_move_link($lesson, $lesson->firstpageid);
            break;
        case 'single':
            $pageid =  required_param('pageid', PARAM_INT);
            $PAGE->url->param('pageid', $pageid);
            $singlepage = $lesson->load_page($pageid);
            echo $lessonoutput->display_edit_full($lesson, $singlepage->id, $singlepage->prevpageid, true);
            break;
        case 'full':
            echo $lessonoutput->display_edit_full($lesson, $lesson->firstpageid, 0);
            break;
    }
}

echo $lessonoutput->footer();
