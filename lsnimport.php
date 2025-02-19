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
 * This file is used to import new lessons and keyboard layouts.
 *
 * Can be called from the MooTyper admin block anytime it is visible.
 * The file scans the lesson and layout folders and checks the files
 * found there against the ones already in the database.
 * Duplicates are skipped while new ones are added with the results
 * listed for the user to see. Continue at the end will take the
 * user to exercises.php for possible editing.
 *
 * @package    mod_mootyper
 * @copyright  2016 AL Rachels (drachels@drachels.com)
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later.
 */

use \mod_mootyper\event\lesson_imported;
use \mod_mootyper\event\layout_imported;

require(__DIR__ . '/../../config.php');
require_once(__DIR__ . '/lib.php');
require_once(__DIR__ . '/locallib.php');

/**
 * Define the lesson import function.
 * @param string $dafile
 * @param int $authoridarg
 * @param int $visiblearg
 * @param int $editablearg
 * @param int $coursearg
 */
function read_lessons_file($dafile, $authoridarg, $visiblearg, $editablearg, $coursearg=null) {
    global $DB, $CFG, $USER;
    $thefile = $CFG->dirroot."/mod/mootyper/lessons/".$dafile;
    // Echo the file.
    $record = new stdClass();
    $periodpos = strrpos($dafile, '.');
    $lessonname = substr($dafile, 0, $periodpos);

    // Echo lessonname.
    $record->lessonname = $lessonname;
    $record->authorid = $authoridarg;
    $record->visible = $visiblearg;
    $record->editable = $editablearg;
    if (!is_null($coursearg)) {
        $record->courseid = $coursearg;
    }
    $lessonid = $DB->insert_record('mootyper_lessons', $record, true);
    $fh = fopen($thefile, 'r');
    $thedata = fread($fh, filesize($thefile));
    fclose($fh);
    $haha = "";
    for ($i = 0; $i < strlen($thedata); $i++) {
        $haha .= $thedata[$i];
    }
    $haha = trim($haha);
    // Break lesson into separate exercises.
    $splitted = explode ('/**/' , $haha);
    for ($j = 0; $j < count($splitted); $j++) {
        $exercise = trim($splitted[$j]);
		// @codingStandardsIgnoreLine
        $allowed = array('ё', 'ë', '¸','á', 'é', 'í', 'ï', 'ó', 'ú', '\\', '~', '!', '@', '#', '$', '%', '^', '&', '(', ')', '*', '_', '+', ':', ';', '"', '{', '}', '>', '<', '?', '\'', '-', '/', '=', '.', ',', ' ', '|', '¡', '`', 'ç', 'ñ', 'º', '¿', 'ª', '·', '\n', '\r', '\r\n', '\n\r', ']', '[', '¬', '´', '`', '§', '°', '€', '¦', '¢', '£', '?', '¹', '²', '³', '¨', '?', 'ù', 'µ', 'û','÷', '×', 'ł', 'Ł', 'ß', '¤', '«', '»');
        $nm = "".($j + 1);
        $texttotype = "";
        for ($k = 0; $k < strlen($exercise); $k++) {
            // TODO
            // * If it is not a letter
            // * and if it is not a number
            // * compare against $allowed array.
            // * If not included die
            // * or something.
            $ch = $exercise[$k];
            if ($ch == "\n") {
                $texttotype .= '\n';
            } else {
                $texttotype .= $ch;
            }
        }
        $erecord = new stdClass();
        $erecord->texttotype = $texttotype;
        $erecord->exercisename = $nm;
        $erecord->lesson = $lessonid;
        $erecord->snumber = $j + 1;
        $DB->insert_record('mootyper_exercises', $erecord, false);
    }
}

/**
 * Define the keyboard import function.
 * @param string $dafile
 */
function add_keyboard_layout($dafile) {
    global $DB, $CFG, $USER;
    $thefile = $CFG->dirroot."/mod/mootyper/layouts/".$dafile;
    $wwwfile = $CFG->wwwroot."/mod/mootyper/layouts/".$dafile;
    $record = new stdClass();
    $periodpos = strrpos($dafile, '.');
    $layoutname = substr($dafile, 0, $periodpos);
    $record->filepath = $thefile;
    $record->name = $layoutname;
    $record->jspath = substr($wwwfile, 0, strripos($wwwfile, '.')).'.js';
    $DB->insert_record('mootyper_layouts', $record, true);
}

$id = optional_param('id', 0, PARAM_INT); // Course ID.
$lsn = optional_param('lsn', 0, PARAM_INT); // Lesson ID to download.

if ($id) {
    $course     = $DB->get_record('course', array('id' => $id), '*', MUST_EXIST);
} else {
    error('You must specify a course_module ID or an instance ID');
}
require_login($course, true);
$context = context_course::instance($id);

// Print the page header.
$PAGE->set_url('/mod/mootyper/exercises.php', array('id' => $course->id));
$PAGE->set_title(get_string('etitle', 'mootyper'));
$PAGE->set_heading(get_string('eheading', 'mootyper'));

// Other things you may want to set - remove if not needed.
$PAGE->set_cacheable(false);

// Output starts here.
echo $OUTPUT->header();
echo '<b>'.get_string('lsnimport', 'mootyper').'</b><br><br>';
echo '<b>'.get_string('sflesson', 'mootyper').'</b><br>';
echo '<table class="table table-hover" style="width:100%">';
echo '<tbody>';
// Set pointer to lessons folder, then get all lesson names in there.
$pth = $CFG->dirroot."/mod/mootyper/lessons";
$res = scandir($pth);

for ($i = 0; $i < count($res); $i++) {
    if (is_file($pth."/".$res[$i])) {
        // Get a filename from the lessons folder.
        $fl = $res[$i]; // Argument list dafile, authorid_arg, visible_arg, editable_arg, course_arg.

        // Strip away the .txt portion of the filename.
        $periodpos = strrpos($fl, '.');
        $lsn = substr($fl, 0, $periodpos);
        // Create sql to see if lesson name is already an installed lesson.
        $sql = "SELECT lessonname
            FROM {mootyper_lessons}
            WHERE lessonname = '".$lsn."'";

        if ($importlesson = $DB->get_record_sql($sql)) {
            // If it's true the name is already in the database, do nothing.
            echo "<tr class='table-light'><td>$lsn".get_string('lsnimportnotadd', 'mootyper').'</td></tr>';
        } else {
            // If it's not found in the db, then add the new lesson to the database.
            echo "<tr class='table-success'><td><b>$lsn".get_string('lsnimportadd', 'mootyper').'</b></td></tr>';
            read_lessons_file($fl, $USER->id, 0, 2);
            // Since we added a new lesson, make a log entry about it.
            $data = new StdClass();
            $data->mootyper = $id;
            $context = context_course::instance($id);
            // Trigger lesson_imported event.
            $params = array(
                'objectid' => $data->mootyper,
                'context' => $context,
                'other' => $lsn
            );
            $event = lesson_imported::create($params);
            $event->trigger();
        }
    }
}
echo '</tbody>';
echo '</table>';
echo '<br><b>'.get_string('layout', 'mootyper').'</b><br>';
echo '<table class="table table-hover" style="width:100%">';
echo '<tbody>';
// Set pointer to keyboard layouts folder, then get all names in there.
$pth2 = $CFG->dirroot."/mod/mootyper/layouts";
$res2 = scandir($pth2);
for ($j = 0; $j < count($res2); $j++) {
    if (is_file($pth2."/".$res2[$j]) && ( substr($res2[$j], (strripos($res2[$j], '.') + 1) ) == 'php')) {
        // Get a filename from the lessons folder.
        $fl2 = $res2[$j];
        // Strip away the .txt portion of the filename.
        $periodpos = strrpos($fl2, '.');
        $kbl = substr($fl2, 0, $periodpos);
        // Create sql to see if lesson name is already an installed lesson.
        $sql = "SELECT name
            FROM {mootyper_layouts}
            WHERE name = '".$kbl."'";

        if ($importkbl = $DB->get_record_sql($sql)) {
            // If it's true the name is already in the database, do nothing.
            echo "<tr class='table-light'><td>$kbl".get_string('kblimportnotadd', 'mootyper').'</td></tr>';
        } else {
            // If it's not found in the db, then add the new layout to the database.
            echo "<tr class='table-success'><td><b>$kbl".get_string('kblimportadd', 'mootyper').'</b></td></tr>';
            add_keyboard_layout($fl2);
            // Since we added a new layout, make a log entry about it.
            $data = new StdClass();
            $data->mootyper = $id;
            $context = context_course::instance($id);
            // Trigger layout_imported event.
            $params = array(
                'objectid' => $data->mootyper,
                'context' => $context,
                'other' => $kbl
            );
            $event = \mod_mootyper\event\layout_imported::create($params);
            $event->trigger();
        }
    }
}
echo '</tbody>';
echo '</table>';

$jlnk2 = $CFG->wwwroot . '/mod/mootyper/exercises.php?id='.$id;
// 11/19/19 Change from a, Continue, link to a, Continue, button.
echo '<br><a href="'.$jlnk2.'" class="btn btn-info" role="button">'.get_string('fcontinue', 'mootyper').'</a><br><br>';
echo $OUTPUT->footer();
return;
