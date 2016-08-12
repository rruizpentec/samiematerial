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
 * Page that lists file downloads/uploads.
 *
 * @package    SAMIE
 * @copyright  2015 Planificacion de Entornos Tecnologicos SL
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once(__DIR__ .'../../../config.php');

defined('MOODLE_INTERNAL') || die();

require_once($CFG->libdir.'/blocklib.php');
require_once($CFG->dirroot . '/blocks/samiematerial/lib.php');

$courseid = required_param('courseid', PARAM_INT);
$action = required_param('action', PARAM_TEXT);

global $CFG, $PAGE, $OUTPUT, $DB;
$systemcontext = context_system::instance();
require_login();
$PAGE->set_url('/blocks/samiematerial/list.php?courseid='.$courseid.'&action='.$action);
$PAGE->set_pagelayout('standard');
$PAGE->set_context($systemcontext);
$PAGE->set_title(get_string('title', 'block_samiematerial'));

$course = $DB->get_record('course', array('id' => $courseid));
$PAGE->navbar->add($course->fullname, new moodle_url('/course/view.php', array('id' => $courseid)));
echo $OUTPUT->header();

$content = html_writer::start_tag('div', array('id' => 'samiematerialcontent', 'class' => 'block'));
$content .= html_writer::start_tag('div', array('class' => 'header'));
$content .= html_writer::start_tag('div', array('class' => 'title'));
$content .= html_writer::tag('h2', get_string('title', 'block_samiematerial'));
$content .= html_writer::end_tag('div');
$content .= html_writer::end_tag('div');

$coursecontext = context_course::instance($courseid);
if (has_capability('block/samiematerial:managefiles', $coursecontext, $USER)) {
    $content .= html_writer::start_tag('h3').get_string($action.'list_title', 'block_samiematerial');
    $content .= html_writer::tag('a', get_string('gobacktocourse', 'block_samiematerial'),
            array(
                'href' => $CFG->wwwroot."/course/view.php?id=$courseid",
                'class' => 'btn btn-default',
                'style' => 'float: right')
    );
    $content .= html_writer::end_tag('h3');
    $category = '';
    $course = $DB->get_record('course', array('id' => $courseid), $fields = '*', $strictness = IGNORE_MISSING);
    if ($course->category == 1) {
        $afgidlms = $course->id;
        $category = 'C';
    } else {
        $afgidlms = $course->category;
        $category = 'SC';
    }

    if ($action == 'upload') {
        $sql = "SELECT mfu.id, mfu.filename, mfu.description, u.firstname, u.lastname, mfu.uploaded_date as date, mfu.deleted
                  FROM {block_samiematerial_up} mfu
                  JOIN {user} u ON u.id = mfu.userid
                 WHERE mfu.afg_id = :afgidlms
                       AND mfu.afg_type = :category
                       ORDER BY uploaded_date DESC";
    } else if ($action == 'download') {
        $sql = "SELECT mfd.id, mfu.filename, mfu.description, u.firstname, u.lastname, mfd.downloaded_date as date, mfu.deleted
                      FROM {block_samiematerial_down} mfd
                      JOIN {block_samiematerial_up} mfu ON mfd.fileid = mfu.id
                      JOIN {user} u ON u.id = mfd.userid
                     WHERE mfu.afg_id = :afgidlms
                           AND mfu.afg_type = :category
                           ORDER BY downloaded_date DESC";
    }
    $records = $DB->get_recordset_sql($sql, array('afgidlms' => $afgidlms, 'category' => $category));

    $content .= html_writer::start_tag('table', array('class' => 'table'));
    $content .= html_writer::start_tag('tr');
    $content .= html_writer::tag('th', get_string('description', 'block_samiematerial'));
    $content .= html_writer::tag('th', get_string('firstname', 'block_samiematerial'));
    $content .= html_writer::tag('th', get_string('lastname', 'block_samiematerial'));
    $content .= html_writer::tag('th', get_string($action.'eddate', 'block_samiematerial'));
    $content .= html_writer::tag('th', get_string('deleted', 'block_samiematerial'));
    $content .= html_writer::end_tag('tr');

    foreach ($records as $record) {
        $content .= html_writer::start_tag('tr');
        $content .= html_writer::tag('td', $record->description);
        $content .= html_writer::tag('td', $record->firstname);
        $content .= html_writer::tag('td', $record->lastname);
        $content .= html_writer::tag('td', $record->date);
        $content .= html_writer::tag('td', get_string(($record->deleted ? 'yes' : 'no'), 'block_samiematerial'));
        $content .= html_writer::end_tag('tr');
    }
    $content .= html_writer::end_tag('table');

} else {
    echo html_writer::tag('h3', get_string('permission_denied', 'block_samiematerial'));
}
$content .= html_writer::end_tag('div');
echo $content;
echo $OUTPUT->footer();