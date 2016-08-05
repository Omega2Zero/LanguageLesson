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

require('../../config.php');
require('lib.php');
require('locallib.php');

global $DB;

$cmid = required_param('cmid', PARAM_INT);
$mode = optional_param('mode', 0, PARAM_INT);
$savegradesflag = optional_param('savegradesflag', 0, PARAM_INT);

list($cm, $course, $lesson) = languagelesson_get_basics($cmid);

// SAVING MANUALLY-ASSIGNED GRADES.

if ($savegradesflag) {
    $stugrades = $_POST['students_grades'];
    $stugrades = explode('|', $stugrades);
    // Kill the final entry in the array, as it's empty.
    array_pop($stugrades);
    $stugraders = array();
    foreach ($stugrades as $stugrade) {
        $chunks = explode(',', $stugrade);
        $stugraders[(int)$chunks[0]] = (int)$chunks[1];
    }

    foreach ($stugraders as $id => $gradeval) {
        $grade = new stdClass();
        $grade->lessonid = $lesson->id;
        $grade->userid = $id;
        $grade->grade = $gradeval;
        $grade->completed = time();

        // If this lesson already has a grade saved for this user, update it.
        if ($oldgrade = $DB->get_record("languagelesson_grades", array("lessonid"=>$lesson->id,
                                   "userid"=>$id))) {
            $grade->id = $oldgrade->id;
            if (!$update = $DB->update_record("languagelesson_grades", $grade)) {
                error("Grader: Manual grade not updated");
            }
        } else {
            // Otherwise, just insert it.
            if (!$newgradeid = $DB->insert_record("languagelesson_grades", $grade)) {
                error("Grader: Manual grade not inserted");
            }
        }
        // Finally, update the records in the gradebook.
                $gradeitemid = $DB->get_field('grade_items', 'id', array('iteminstance'=>$lesson->id));

        if (!$gradeinstance = $DB->get_record('grade_grades', array('itemid'=>$gradeitemid, 'userid'=>$id))) {
            // Create grade record in grade_grades for this student on this lesson.
            $gradeinstance = new stdClass;
            $gradeinstance->itemid = $gradeitemid;
            $gradeinstance->userid = $id;
            $gradeinstance->rawgrademax = $DB->get_field('grade_items', 'grademax', array('id'=>$gradeitemid));
            $gradeinstance->rawgrademin = $DB->get_field('grade_items', 'grademin', array('id'=>$gradeitemid));
            $gradeinstance->finalgrade = $grade->grade;
            $DB->insert_record('grade_grades', $gradeinstance);
        } else {
            $gradeinstance->finalgrade = $grade->grade;
            $DB->update_record('grade_grades', $gradeinstance);
        }
    }
} else {
    // SENDING NOTIFICATION EMAILS.
    $stuids = $_POST['students_toemail'];
    if ($stuids) {
        $stuids = explode(",", $stuids);
        foreach ($stuids as $stuid) {
            $stu = $DB->get_record('user', array('id'=>$stuid));

            $a->coursename = $course->shortname;
            $a->modulename = get_string('modulenameplural', 'languagelesson');
            $a->llname = $lesson->name;

            $subject = get_string('emailsubject', 'languagelesson', $a);

            $thisteach = $DB->get_record('user', array('id'=>$USER->id));
            $info = new stdClass();
            $info->teacher = fullname($thisteach);
            $info->llname = $lesson->name;
            $info->url = "$CFG->wwwroot/mod/languagelesson/view.php?id=$cm->id";

            $usehtml = optional_param('useHTML', false, PARAM_BOOL);

            $plaintext = "$course->shortname -> $a->modulename -> $lesson->name\n";
            $plaintext .= "-------------------------------------------------------\n";
            $plaintext .= get_string('emailmessage', 'languagelesson', $info);
            $plaintext .= "-------------------------------------------------------\n";

            $html = '';
            if ($usehtml) {
                $html = "<p><font face=\"sans-serif\">".
                "<a href=\"$CFG->wwwroot/course/view.php?id=$course->id\">$course->shortname</a> -> ".
                "<a href=\"$CFG->wwwroot/mod/languagelesson/index.php?id=$course->id\">$a->modulename</a> -> ".
                "<a href=\"$CFG->wwwroot/mod/languagelesson/view.php?id=$cm->id\">$lesson->name</a></font></p>";
                $html .= "<hr /><font face=\"sans-serif\">";
                $html .= "<p>".get_string('emailmessagehtml', 'languagelesson', $info)."</p>";
                $html .= "</font><hr />";
            }

            if (!email_to_user($stu, $thisteach, $subject, $plaintext, $html)) {
                print_error('Emailing students failed.');
            }
        }
    }
}

// And redirect to the grader page.
$path = "$CFG->wwwroot/mod/languagelesson/grader.php?id=$cmid";
if ($savegradesflag) {
    $path .= "&amp;savedgrades=1";
} else {
    $path .= "&amp;sentemails=1";
}
redirect($path);
