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
 * Handles lesson actions
 *
 * ACTIONS handled are:
 *    confirmdelete
 *    duplicate
 *    move
 *    moveall
 *    delete
 *    moveit
 *    movethemall
 * @package    mod
 * @subpackage lesson
 * @copyright 1999 onwards Martin Dougiamas  {@link http://moodle.com}
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 **/

require_once("../../config.php");
require_once($CFG->dirroot.'/mod/languagelesson/locallib.php');

$id     = required_param('id', PARAM_INT);         // Course Module ID.
$action = required_param('action', PARAM_ALPHA);   // Action.
$pageid = optional_param('pageid', 0, PARAM_INT);
$pages = optional_param_array('page', array(), PARAM_ALPHA); // For moving multiple pages.

$cm = get_coursemodule_from_id('languagelesson', $id, 0, false, MUST_EXIST);;
$course = $DB->get_record('course', array('id' => $cm->course), '*', MUST_EXIST);
$lesson = new languagelesson($DB->get_record('languagelesson', array('id' => $cm->instance), '*', MUST_EXIST));

require_login($course, false, $cm);

$url = new moodle_url('/mod/languagelesson/lesson.php', array('id'=>$id, 'action'=>$action));
$PAGE->set_url($url);

$context = context_module::instance($cm->id);
require_capability('mod/languagelesson:edit', $context);
require_sesskey();

global $DB;

$lessonoutput = $PAGE->get_renderer('mod_languagelesson');

// Process the action.
switch ($action) {
    case 'confirmdelete':
        $PAGE->navbar->add(get_string($action, 'languagelesson'));

        $thispage = $lesson->load_page($pageid);

        echo $lessonoutput->header($lesson, $cm);
        echo $OUTPUT->heading(get_string("deletingpage", "languagelesson", format_string($thispage->title)));
        // Print the jumps to this page.
        $params = array("lessonid" => $lesson->id, "pageid" => $pageid);
        if ($answers = $DB->get_records_select("languagelesson_answers", "lessonid = :lessonid AND jumpto = :pageid + 1", $params)) {
            echo $OUTPUT->heading(get_string("thefollowingpagesjumptothispage", "languagelesson"));
            echo "<p align=\"center\">\n";
            foreach ($answers as $answer) {
                if (!$title = $DB->get_field("languagelesson_pages", "title", array("id" => $answer->pageid))) {
                    print_error('cannotfindpagetitle', 'languagelesson');
                }
                echo $title."<br />\n";
            }
        }
        echo $OUTPUT->confirm(get_string("confirmdeletionofthispage", "languagelesson"),
                        "lesson.php?action=delete&id=$cm->id&pageid=$pageid", "view.php?id=$cm->id");

        break;
    case 'confirmduplicate':
        $PAGE->navbar->add(get_string($action, 'languagelesson'));

        $thispage = $lesson->load_page($pageid);

        echo $lessonoutput->header($lesson, $cm);
        echo $OUTPUT->heading(get_string("duplicatingpage", "languagelesson", format_string($thispage->title)));
        // Print the jumps to this page.
        $params = array("lessonid" => $lesson->id, "pageid" => $pageid);
        if ($answers = $DB->get_records_select("languagelesson_answers", "lessonid = :lessonid AND jumpto = :pageid + 1", $params)) {
            echo $OUTPUT->heading(get_string("thefollowingpagesjumptothispage", "languagelesson"));
            echo "<p align=\"center\">\n";
            foreach ($answers as $answer) {
                if (!$title = $DB->get_field("languagelesson_pages", "title", array("id" => $answer->pageid))) {
                    print_error('cannotfindpagetitle', 'languagelesson');
                }
                echo $title."<br />\n";
            }
        }
        echo $OUTPUT->confirm(get_string("confirmduplicationofthispage", "languagelesson"),
                              "lesson.php?action=duplicate&id=$cm->id&pageid=$pageid", "view.php?id=$cm->id");

        break;
    case 'duplicate':
        $PAGE->navbar->add(get_string($action, 'languagelesson'));

        $thispage = $DB->get_record('languagelesson_pages', array('id' => $pageid));

        echo $lessonoutput->header($lesson, $cm);
        echo $OUTPUT->heading(get_string("duplicatingpage", "languagelesson", format_string($thispage->title)));

        $order = $thispage->ordering;

        // Duplicate page with new id, creation time, page order, prevpageid, nextpageid.
        $newpage = clone $thispage;
        $newpage->prevpageid = $thispage->id;
        $newpage->nextpageid = $thispage->nextpageid;
        $newpage->ordering = $order += 1;
        $newpage->timecreated = time();

        $duplicateid = $DB->insert_record('languagelesson_pages', $newpage);
        $newpage = $DB->get_record('languagelesson_pages', array('id'=>$duplicateid));

        $thispage->nextpageid = $newpage->id;
        $DB->update_record('languagelesson_pages', $thispage);

        // Now need to fix all page ordering starting with page after insert.

        if ($thispage = $DB->get_record('languagelesson_pages', array('id'=> $newpage->nextpageid))) {
            $prev = $newpage;
            $thispage->prevpageid = $prev->id;
            while ($thispage !== null) {
                $thispage->ordering = $order += 1;
                $DB->update_record('languagelesson_pages', $thispage);
                $prev = $thispage;
                if ($prev->nextpageid != 0) {
                    $thispage = $DB->get_record('languagelesson_pages', array('id'=> $prev->nextpageid));
                } else {
                    $thispage = null;
                }
            }
        }

            // Duplicate related answers if any.
        if (!$answers = $DB->get_records('languagelesson_answers', array('pageid'=> $pageid))) {
            break;
        } else {
            foreach ($answers as $answer) {
                $newanswer = $answer;
                $newanswer->pageid = $duplicateid;
                $newanswer->timecreated = time();
                $DB->insert_record('languagelesson_answers', $newanswer);
            }
        }

            redirect("$CFG->wwwroot/mod/languagelesson/editpage.php?id=$cm->id&pageid=$duplicateid&edit=1");
            break;
    case 'move':
        $PAGE->navbar->add(get_string($action, 'languagelesson'));

        $title = $DB->get_field("languagelesson_pages", "title", array("id" => $pageid));

        echo $lessonoutput->header($lesson, $cm);
        echo $OUTPUT->heading(get_string("moving", "languagelesson", format_string($title)));

        $params = array ("lessonid" => $lesson->id, "prevpageid" => 0);
        if (!$page = $DB->get_record_select("languagelesson_pages", "lessonid = :lessonid AND prevpageid = :prevpageid", $params)) {
            print_error('cannotfindfirstpage', 'languagelesson');
        }

        echo "<center><table cellpadding=\"5\" border=\"1\">\n";
        echo "<tr><td><a href=\"lesson.php?id=$cm->id&amp;sesskey=".sesskey().
                                "&amp;action=moveit&amp;pageid=$pageid&amp;after=0\"><small>".
            get_string("movepagehere", "languagelesson")."</small></a></td></tr>\n";
        while (true) {
            if ($page->id != $pageid) {
                if (!$title = trim(format_string($page->title))) {
                    $title = "<< ".get_string("notitle", "languagelesson")."  >>";
                }
                echo "<tr><td><b>$title</b></td></tr>\n";
                echo "<tr><td><a href=\"lesson.php?id=$cm->id&amp;sesskey=".sesskey().
                                        "&amp;action=moveit&amp;pageid=$pageid&amp;after={$page->id}\"><small>".
                    get_string("movepagehere", "languagelesson")."</small></a></td></tr>\n";
            }
            if ($page->nextpageid) {
                if (!$page = $DB->get_record("languagelesson_pages", array("id" => $page->nextpageid))) {
                    print_error('cannotfindnextpage', 'languagelesson');
                }
            } else {
                // Last page reached.
                break;
            }
        }
        echo "</table>\n";

        break;
    case 'moveall':
        $listofpageids = $_GET['pageids'];
        $listofpageids = trim($listofpageids, ', ');
        $pagestomove = explode(', ', $listofpageids);

        // Make sure they are in order by field ordering.
        $order = array();
        foreach ($pagestomove as $key => $obj) {
            $order[$key] = $obj->ordering;
        }
        array_multisort($order, SORT_ASC, $pagestomove);

        $PAGE->navbar->add(get_string($action, 'languagelesson'));

        foreach ($pagestomove as $pageid) {
            $title = $DB->get_field("languagelesson_pages", "title", array("id" => $pageid));
            $titles .= $title.', ';
        }
        $titles = trim($titles, ', ');

           echo $lessonoutput->header($lesson, $cm);
        echo $OUTPUT->heading(get_string("movingmany", "languagelesson", format_string($titles)));

        $params = array ("lessonid" => $lesson->id, "prevpageid" => 0);
        if (!$page = $DB->get_record_select("languagelesson_pages", "lessonid = :lessonid AND prevpageid = :prevpageid", $params)) {
            print_error('cannotfindfirstpage', 'languagelesson');
        }

        $pages = $DB->get_records('languagelesson_pages', array('lessonid' => $lesson->id));
        // Make sure they are in order by field ordering.
        $order2 = array();
        foreach ($pages as $key => $obj) {
            $order2[$key] = $obj->ordering;
        }
        array_multisort($order2, SORT_ASC, $pages);

        echo "<center><table cellpadding=\"5\" border=\"1\">\n";
        echo "<tr><td><a href=\"lesson.php?id=$cm->id&amp;sesskey=".sesskey().
                                "&amp;action=movethemall&amp;after=0&amp;pageids=$listofpageids\"><small>".
               get_string("movepagehere", "languagelesson")."</small></a></td></tr>\n";

        // Check that the selected records are not among those selected.
        foreach ($pages as $page) {
            if (!in_array($page->id, $pagestomove)) {
                if (!$title = trim(format_string($page->title))) {
                    $title = "<< ".get_string("notitle", "languagelesson")."  >>";
                }
                echo "<tr><td><b>$title</b></td></tr>\n";
                echo "<tr><td><a href=\"lesson.php?id=$cm->id&amp;sesskey=".sesskey().
                     "&amp;action=movethemall&amp;after={$page->id}&amp;pageids=$listofpageids\"><small>".
                     get_string("movepagehere", "languagelesson")."</small></a></td></tr>\n";
            }
            if ($page->nextpageid) {
                if (!$page = $DB->get_record("languagelesson_pages", array("id" => $page->nextpageid))) {
                    print_error('cannotfindnextpage', 'languagelesson');
                }
            } else {
                // Last page reached.
                break;
            }
        }
        echo "</table>\n";

        break;

    case 'delete':
        $thispage = $lesson->load_page($pageid);
        $thispage->delete();
        redirect("$CFG->wwwroot/mod/languagelesson/edit.php?id=$cm->id");
        break;
    case 'moveit':
        $after = (int)required_param('after', PARAM_INT); // Target page.

        $pages = $lesson->load_all_pages();

        if (!array_key_exists($pageid, $pages) || ($after!=0 && !array_key_exists($after, $pages))) {
            print_error('cannotfindpages', 'languagelesson', "$CFG->wwwroot/mod/languagelesson/edit.php?id=$cm->id");
        }
        $pagetomove = clone($pages[$pageid]);
        unset($pages[$pageid]);

        $pageids = array();
        if ($after === 0) {
            $pageids['p0'] = $pageid;
        }
        foreach ($pages as $page) {
            $pageids[] = $page->id;
            if ($page->id == $after) {
                $pageids[] = $pageid;
            }
        }

        $pageidsref = $pageids;
        reset($pageidsref);
        $prev = 0;
        $next = next($pageidsref);
        foreach ($pageids as $pid) {
            if ($pid === $pageid) {
                $page = $pagetomove;
            } else {
                $page = $pages[$pid];
            }
            if ($page->prevpageid != $prev || $page->nextpageid != $next) {
                $page->move($next, $prev);
            }
            $prev = $page->id;
            $next = next($pageidsref);
            if (!$next) {
                $next = 0;
            }
        }
        languagelesson_reorder_pages($lesson->id);

        redirect("$CFG->wwwroot/mod/languagelesson/edit.php?id=$cm->id");
        break;
    case 'movethemall':
        $after = (int)($_GET['after']); // Target page.
        $tomove = $_GET['pageids'];
        $pages = explode(',', $tomove);

        $allpages = $DB->get_records('languagelesson_pages', array('lessonid' => $lesson->id));

        // Sort allpages array by ordering field.
        $order = array();
        foreach ($allpages as $key => $obj) {
            $order[$key] = $obj->ordering;
        }
        array_multisort($order, SORT_ASC, $allpages);

        // Get array of page objects for pages to move.
        $pagestomove = array();
        $pageskeys = array();
        foreach ($pages as $page => $value) {
            $pagestomove[$value] = $DB->get_record('languagelesson_pages', array('id' => $value));
            $pageskeys[$value] = $value;
        }

        if ($after != 0) {
            $after = $DB->get_record('languagelesson_pages', array('id'=> $after));
            if (!$pageaftermove = $DB->get_record('languagelesson_pages', array('id'=> $after->nextpageid))) {
                $pageaftermove = null;
            }
        } else {
            $pageaftermove = $DB->get_record('languagelesson_pages', array('prevpageid'=> 0));
        }

        $remainingpagesbefore = array();
        $remainingpagesafter = array();

        // Split $allpages into block before move & block after move.
        foreach ($allpages as $key => $obj) {
            if (!in_array($obj->id, $pageskeys)) {
                if ($obj->ordering <= $after->ordering) {
                    $remainingpagesbefore[$key] = $obj;
                } else {
                    $remainingpagesafter[$key] = $obj;
                }
            }
        }

        // Sort pages_to_move array by ordering field.
        $order2 = array();
        foreach ($pagestomove as $key => $obj) {
            $order2[$key] = $obj->ordering;
        }
        array_multisort($order2, SORT_ASC, $pagestomove);
        if (!$pagestomove) {
            print_error('cannotfindpages', 'languagelesson', "$CFG->wwwroot/mod/languagelesson/edit.php?id=$cm->id");
        }

        if (count($remainingpagesbefore) > 1 ) {
            $prev = array_shift($remainingpagesbefore);
            $current = array_shift($remainingpagesbefore);
            $prev->nextpageid = $current->id;
            $DB->update_record('languagelesson_pages', $prev);
            $i = $prev->ordering;

            while ($current) {
                $current->ordering = $i += 1;
                $current->prevpageid = $prev->id;
                $DB->update_record('languagelesson_pages', $current);
                $prev = $current;
                $current = array_shift($remainingpagesbefore);
                if (!$current) {
                    $current = array_shift($pagestomove);
                    $prev->nextpageid = $current->id;
                    $DB->update_record('languagelesson_pages', $prev);
                    break;
                } else {
                    $prev->nextpageid = $current->id;
                    $DB->update_record('languagelesson_pages', $prev);
                }
            }
        } else if (count($remainingpagesbefore) == 1) {
            $prev = array_shift($remainingpagesbefore);
            $current = array_shift($pagestomove);
            $prev->nextpageid = $current->id;
            $DB->update_record('languagelesson_pages', $prev);
            $i = $prev->ordering;
        } else {
            $prev = array_shift($pagestomove);
            $current = array_shift($pagestomove);

            $prev->prevpageid = 0;
            $prev->nextpageid = $current->id;
            $prev->ordering = 0;
            $DB->update_record('languagelesson_pages', $prev);
            $i = $prev->ordering;
        }

        while ($current != null) {
            $current->ordering = $i += 1;
            $current->prevpageid = $prev->id;
            $DB->update_record('languagelesson_pages', $current);
            $prev = $current;
            $current = array_shift($pagestomove);
            if (!$current) {
                if (!$remainingpagesafter) {
                    $prev->nextpageid = 0;
                    $DB->update_record('languagelesson_pages', $prev);
                } else {
                    $current = array_shift($remainingpagesafter);
                    $prev->nextpageid = $current->id;
                    $DB->update_record('languagelesson_pages', $prev);
                }
                break;
            }
            $prev->nextpageid = $current->id;
            $DB->update_record('languagelesson_pages', $prev);
        }

        while ($current != null) {
            $current->ordering = $i += 1;
            $current->prevpageid = $prev->id;
            $DB->update_record('languagelesson_pages', $current);
            $prev = $current;
            $current = array_shift($remainingpagesafter);
            if (!$current) {
                $prev->nextpageid = 0;
                $DB->update_record('languagelesson_pages', $prev);
                break;
            }
            $prev->nextpageid = $current->id;
            $DB->update_record('languagelesson_pages', $prev);
        }

        redirect("$CFG->wwwroot/mod/languagelesson/edit.php?id=$cm->id");
            break;

    default:
            print_error('unknowaction');
            break;
}
// echo $lessonoutput->footer();