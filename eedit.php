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
 * This file is used to edit exercise content. Called from exercises.php.
 *
 * @package    mod_mootyper
 * @copyright  2011 Jaka Luthar (jaka.luthar@gmail.com)
 * @copyright  2016 onwards AL Rachels (drachels@drachels.com)
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

use \mod_mootyper\event\exercise_edited;

// Changed to this newer format 03/01/2019.
require(__DIR__ . '/../../config.php');
require_once(__DIR__ . '/lib.php');
require_once(__DIR__ . '/locallib.php');

global $DB, $USER;

$id = optional_param('id', 0, PARAM_INT); // Course_module ID.
$exerciseid = optional_param('ex', 0, PARAM_INT);
if ($id) {
    $course     = $DB->get_record('course', array('id' => $id), '*', MUST_EXIST);
} else {
    print_error(get_string('mootypererror', 'mootyper'));
}
if ($exerciseid == 0) {
    print_error('No exercise to edit!');
}

// Get the id of the lesson/category and then use it to get the lesson/category name.
$rcrd = $DB->get_record('mootyper_exercises', array('id' => $exerciseid), 'lesson', MUST_EXIST);
$lsncat = $DB->get_record('mootyper_lessons', array('id' => $rcrd->lesson), 'lessonname', MUST_EXIST);

$context = context_course::instance($id);
require_login($course, true);

// Check to see if Confirm button is clicked and returning 'Confirm' to trigger update record.
$param1 = optional_param('button', '', PARAM_TEXT);

if (isset($param1) && get_string('fconfirm', 'mootyper') == $param1 ) {
    $newtext = optional_param('texttotype', '', PARAM_RAW);
    $rcrd = $DB->get_record('mootyper_exercises', array('id' => $exerciseid), '*', MUST_EXIST);
    $updr = new stdClass();
    $updr->id = $rcrd->id;
    $updr->texttotype = str_replace("\r\n", '\n', $newtext);
    $updr->exercisename = $rcrd->exercisename;
    $updr->lesson = $rcrd->lesson;
    $updr->snumber = $rcrd->snumber;
    $DB->update_record('mootyper_exercises', $updr);

    // Trigger module exercise_edited event.
    $params = array(
        'objectid' => $course->id,
        'context' => $context,
        'other' => array(
            'lesson' => $updr->lesson,
            'exercise' => $updr->exercisename
        )
    );
    $event = exercise_edited::create($params);
    $event->trigger();

    $webdir = $CFG->wwwroot . '/mod/mootyper/exercises.php?id='.$id.'&lesson='.$rcrd->lesson;
    echo '<script type="text/javascript">window.location="'.$webdir.'";</script>';

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

$PAGE->set_url('/mod/mootyper/eedit.php', array('id' => $course->id, 'ex' => $exerciseid));
$PAGE->set_title(get_string('etitle', 'mootyper'));
$PAGE->set_heading(get_string('eheading', 'mootyper'));
$PAGE->set_cacheable(false);
echo $OUTPUT->header();
$exercisetoedit = $DB->get_record('mootyper_exercises', array('id' => $exerciseid), 'texttotype', MUST_EXIST); ?>

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
        if((exercise_text[i] != '\n' && exercise_text[i] != '\r\n'
                                     && exercise_text[i] != '\n\r'
                                     && exercise_text[i] != '\r')
                                     && !isLetter(exercise_text[i])
                                     && !isNumber(exercise_text[i])
                                     && allowed_chars.indexOf(exercise_text[i]) == -1) {
            shown_text += '<span style="color: red;">'+exercise_text[i]+'</span>';
            ok = false;
            /*var text = (i-3)+'-'+exercise_text[i-3]+"\n";
            text += (i-2)+'-'+exercise_text[i-2]+"\n";
            text += (i-1)+'-'+exercise_text[i-1]+"\n";
            text += i+'-'+exercise_text[i]+"\n";
            text += (i+1)+'-'+exercise_text[i+1]+"\n";
            text += (i+2)+'-'+exercise_text[i+2];
            alert(text);*/
        }
        else
            shown_text += exercise_text[i];
    }
    if(!ok) {
        document.getElementById('text_holder_span').innerHTML = shown_text;
        return false;
    }
    else 
        return true;
}

</script>
<?php
$color3 = $moocfg->keyboardbgc;
echo '<div align="center" style="font-size:1em;
     font-weight:bold;background: '.$color3.';
     border:2px solid black;
     -webkit-border-radius:16px;
     -moz-border-radius:16px;border-radius:16px;">';
echo get_string('flesson', 'mod_mootyper').'/'.get_string('lsnname', 'mod_mootyper').' = '.$lsncat->lessonname.'<br>';

echo '<form method="POST">';

// Get our alignment strings and add a selector for text alignment.
$aligns = array(get_string('defaulttextalign_left', 'mod_mootyper'),
              get_string('defaulttextalign_center', 'mod_mootyper'),
              get_string('defaulttextalign_right', 'mod_mootyper'));
echo '<span id="editalign" class="">'.get_string('defaulttextalign', 'mootyper').': ';
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

echo '</select></span>';
// Create a link back to where we came from in case we want to cancel.
$url = $CFG->wwwroot . '/mod/mootyper/exercises.php?id='.$id.'&lesson='.$rcrd->lesson;

echo '<span id="text_holder_span" class=""></span><br>'.get_string('fexercise', 'mootyper').':<br>'.
    '<textarea name="texttotype" id="texttotype" rows="3" cols="60" style="text-align:'.$align.'">'.
    str_replace('\n', "&#10;", $exercisetoedit->texttotype).
    '</textarea><br>'.'<br><input class="btn btn-primary" name="button" onClick="return clClick()" type="submit" value="'
    .get_string('fconfirm', 'mootyper').'"> <a href="'.$url.'" class="btn btn-secondary" role="button">'
    .get_string('cancel', 'mootyper').'</a>'.'</form>';
echo '<br></div>';

echo $OUTPUT->footer();
