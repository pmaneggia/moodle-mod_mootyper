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
 * This is is used to add a new lesson/category.
 *
 * Settings for category name, visibility and who can edit the exercise, are included.
 *
 * @package    mod_mootyper
 * @copyright  2011 Jaka Luthar (jaka.luthar@gmail.com)
 * @copyright  2016 onwards AL Rachels (drachels@drachels.com)
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later.
 **/

use \mod_mootyper\event\exercise_added;

// Changed to this newer format 03/01/2019.
require(__DIR__ . '/../../config.php');
require_once(__DIR__ . '/lib.php');
require_once(__DIR__ . '/locallib.php');

global $USER, $DB;

$id = optional_param('id', 0, PARAM_INT); // Course_module ID, or
// $n = optional_param('n', 0, PARAM_INT); // Mootyper instance ID - it should be named as the first character of the module.
$lsnnamepo = optional_param('lesson', '', PARAM_TEXT);

if ($id) {
    $course = $DB->get_record('course', array('id' => $id), '*', MUST_EXIST);
} else {
    print_error(get_string('mootypererror', 'mootyper'));
}
require_login($course, true);
$lessonpo = optional_param('lesson', -1, PARAM_INT);

$context = context_course::instance($id);

// Check to see if Confirm button is clicked and returning 'Confirm' to trigger insert record.
$param1 = optional_param('button', '', PARAM_TEXT);

// DB insert.
if (isset($param1) && get_string('fconfirm', 'mootyper') == $param1 ) {

    $texttotypeepo = optional_param('texttotype', '', PARAM_RAW);

    if ($lessonpo == -1) {
        $lsnnamepo = optional_param('lessonname', '', PARAM_TEXT);
        $lsnrecord = new stdClass();
        $lsnrecord->lessonname = $lsnnamepo;
        $lsnrecord->visible = optional_param('visible', '', PARAM_TEXT);
        $lsnrecord->editable = optional_param('editable', '', PARAM_TEXT);
        $lsnrecord->authorid = $USER->id;
        $lsnrecord->courseid = $course->id;
        $lessonid = $DB->insert_record('mootyper_lessons', $lsnrecord, true);
    } else {
        $lessonid = $lessonpo;
    }
    $snum = get_new_snumber($lessonid);
    $erecord = new stdClass();
    $erecord->exercisename = "".$snum;
    $erecord->snumber = $snum;
    $erecord->lesson = $lessonid;
    $erecord->texttotype = str_replace("\r\n", '\n', $texttotypeepo);
    $DB->insert_record('mootyper_exercises', $erecord, false);
    $webdir = $CFG->wwwroot . '/mod/mootyper/exercises.php?id='.$id.'&lesson='.$lessonid;

    // If adding a new lesson and first exercise, get lesson name.
    if ($lsnnamepo) {
        $lesson = $lsnnamepo;
    } else {
        // If adding an exercise to existing lesson, get the lesson id.
        $lesson = $lessonpo;
    }

    echo '<script type="text/javascript">window.location="'.$webdir.'";</script>';
    // Trigger module exercise_added event.
    $params = array(
        'objectid' => $course->id,
        'context' => $context,
        'other' => array(
            'lesson' => $lesson,
            'exercisename' => $erecord->exercisename
        )
    );
    $event = exercise_added::create($params);
    $event->trigger();
}
// Get all the default configuration settings for MooTyper.
$moocfg = get_config('mod_mootyper');

// Check to see if configuration for MooTyper defaulteditalign is set.
if (isset($moocfg->defaulteditalign)) {
    // Current MooTyper edittalign is set so use it.
    $editalign = optional_param('editalign', $moocfg->defaulteditalign, PARAM_INT);
    $align = $editalign;
} else {
    // Current MooTyper edittalign is NOT set so set it to left.
    $editalign = optional_param('editalign', 0, PARAM_INT);
    $align = $editalign;
}
// Print the page header.

$PAGE->set_url('/mod/mootyper/eins.php', array('id' => $course->id));
$PAGE->set_title(get_string('etitle', 'mootyper'));
$PAGE->set_heading(get_string('eheading', 'mootyper'));

// Other things you may want to set - remove if not needed.
$PAGE->set_cacheable(false);

// Output starts here.
echo $OUTPUT->header();

$lessonsg = get_typerlessons();
if (has_capability('mod/mootyper:editall', context_course::instance($course->id))) {
    $lessons = $lessonsg;
} else {
    $lessons = array();
    foreach ($lessonsg as $lsng) {
        if (is_editable_by_me($USER->id, $lsng['id'])) {
            $lessons[] = $lsng;
        }
    }
}

$color3 = $moocfg->keyboardbgc;
echo '<div align="center" style="font-size:1em;
     font-weight:bold;background: '.$color3.';
     border:2px solid black;
     -webkit-border-radius:16px;
     -moz-border-radius:16px;border-radius:16px;">'.'<br>';

echo '<form method="POST">';
echo get_string('fnewexercise', 'mootyper').'&nbsp;';
echo '<select onchange="this.form.submit()" name="lesson">';
echo '<option value="-1">'.get_string('fnewlesson', 'mootyper').'</option>';
for ($ij = 0; $ij < count($lessons); $ij++) {
    if ($lessons[$ij]['id'] == $lessonpo) {
        echo '<option selected="true" value="'.$lessons[$ij]['id'].'">'.$lessons[$ij]['lessonname'].'</option>';
    } else {
        echo '<option value="'.$lessons[$ij]['id'].'">'.$lessons[$ij]['lessonname'].'</option>';
    }
}
echo '</select>';
if ($lessonpo == -1) {
    echo '<br><br>...'.get_string('lsnname', 'mootyper').': <input type="text" name="lessonname" id="lessonname">
          <span style="color:red;" id="namemsg"></span>';
    echo '<br><br>'.get_string('visibility', 'mootyper').': <select name="visible">';
    echo '<option value="2">'.get_string('vaccess2', 'mootyper').'</option>';
    echo '<option value="1">'.get_string('vaccess1', 'mootyper').'</option>';
    echo '<option value="0">'.get_string('vaccess0', 'mootyper').'</option>';
    echo '</select><br><br>'.get_string('editable', 'mootyper').': <select name="editable">';
    echo '<option value="2">'.get_string('eaccess2', 'mootyper').'</option>';
    echo '<option value="1">'.get_string('eaccess1', 'mootyper').'</option>';
    echo '<option value="0">'.get_string('eaccess0', 'mootyper').'</option>';
    echo '</select>';

}
?>

<script type="text/javascript">
function isLetter(str) {
    // var pattern = /[a-z,ก-๛,а-я,א-ת,ㄱ-ㅣ,äáàâãčćçëéèêđïîíöôóõüúùûµšžº¡ñ]/i;
    var pattern = /[!-ﻼ]/i;
    return str.length === 1 && str.match(pattern);
}
function isNumber(n) {
  return !isNaN(parseFloat(n)) && isFinite(n);
}

var ok = true;

function clClick() {
    var exercise_text = document.getElementById("texttotype").value;
    var allowed_chars = ['\\', '~', '!', '@', '#', '$', '%', '^', '&', '(', ')',
                         '*', '_', '+', ':', ';', '"', '{', '}', '>', '<', '?', '\'',
                         '-', '/', '=', '.', ',', ' ', '|', '¡', '`', 'ç', 'ñ', 'º',
                         '¿', 'ª', '·', '\n', '\r', '\r\n', '\n\r', ']', '[', '¬',
                         '´', '`', '§', '°', '€', '¦', '¢', '£', '₢', '¹', '²', '³',
                         '¨', 'Ё', '№', 'ё', 'ë', 'ù', 'µ', 'ï','÷', '×', 'ł', 'Ł', 'ß',
                         '¤', '«', '»', '₪', '־', 'װ', 'ױ', 'ײ', 'ˇ', '½'];
    var shown_text = "";
    ok = true;
    for(var i=0; i<exercise_text.length; i++) {
        if(!isLetter(exercise_text[i]) && !isNumber(exercise_text[i]) && allowed_chars.indexOf(exercise_text[i]) == -1) {
            shown_text += '<span style="color: red;">'+exercise_text[i]+'</span>';
            ok = false;
        }
        else
            shown_text += exercise_text[i];
    }
    if(!ok) {
        document.getElementById('text_holder_span').innerHTML = shown_text;
        return false;
    }
    if(document.getElementById("lessonname").value == "") {
        document.getElementById("namemsg").innerHTML = '<?php echo get_string('reqfield', 'mootyper');?>';
        return false;
    }
    else
        return true;
}
</script>

<?php
// Get our alignment strings and add a selector for text alignment.
$aligns = array(get_string('defaulttextalign_left', 'mod_mootyper'),
              get_string('defaulttextalign_center', 'mod_mootyper'),
              get_string('defaulttextalign_right', 'mod_mootyper'));
echo '<br><br><span id="editalign" class="">'.get_string('defaulttextalign', 'mootyper').': ';
echo '<select onchange="this.form.submit()" name="editalign">';
// This will loop through ALL three alignments and show current alignment setting.
foreach ($aligns as $akey => $aval) {
    // The first if is executed ONLY when, when defaulttextalign matches one of the alignments
    // and it will then show that alignment in the selector.
    if ($akey == $editalign) {
        echo '<option value="'.$akey.'" selected="true">'.$aval.'</option>';
        $align = $aval;
    } else {
        // This part of the if is reached the most and its when an alignment
        // is is not the one selected.
        echo '<option value="'.$akey.'">'.$aval.'</option>';
    }
}

echo '</select></span>'.get_string('defaulttextalign_warning', 'mootyper');

// Create a link back to where we came from in case we want to cancel.
if ($lessonpo == -1) {
    $url = $CFG->wwwroot . '/mod/mootyper/exercises.php?id='.$id;
} else {
    $url = $CFG->wwwroot . '/mod/mootyper/exercises.php?id='.$id.'&lesson='.$lessonpo;
}

echo '<br><span id="text_holder_span" class=""></span><br>'.get_string('fexercise', 'mootyper').':<br>'.
     '<textarea rows="4" cols="60" name="texttotype" id="texttotype"style="text-align:'.$align.'"></textarea><br>'.
     '<br><input class="btn btn-primary" name="button" onClick="return clClick()" type="submit" value="'
     .get_string('fconfirm', 'mootyper').'"> <a href="'.$url.'" class="btn btn-secondary" role="button">'
     .get_string('cancel', 'mootyper').'</a>'.'</form>';

echo '<br></div>';

echo $OUTPUT->footer();
