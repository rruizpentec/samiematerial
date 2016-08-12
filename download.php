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
 * Returns the requested file for downloading.
 *
 * @package    SAMIE
 * @copyright  2015 Planificacion de Entornos Tecnologicos SL
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once(__DIR__ .'../../../config.php');

defined('MOODLE_INTERNAL') || die();

require_login();

global $USER, $DB;

require_once($CFG->libdir.'/blocklib.php');
require_once($CFG->dirroot . '/blocks/samiematerial/lib.php');

$afgid = required_param('afg_id', PARAM_INT);
$fileid = required_param('fileid', PARAM_INT);
$courseid = required_param('courseid', PARAM_INT);

try {
    $file = null;
    if (isset($fileid) && isset($afgid)) {
        $coursecontext = context_course::instance($courseid);
        if (has_capability('block/samiematerial:downloadfile', $coursecontext, $USER, true)) {
            $file = $DB->get_record('block_samiematerial_up', array('id' => $fileid));
            if ($file) {
                if (!$DB->record_exists('block_samiematerial_down', array('fileid' => $fileid, 'userid' => $USER->id))) {
                    try {
                        $transaction = $DB->start_delegated_transaction();
                        $record = new stdClass();
                        $record->fileid = $fileid;
                        $record->userid = $USER->id;
                        $record->downloaded_date = date('Y-m-d H:i:s');

                        $DB->insert_record('block_samiematerial_down', $record, false);
                        $samieconfig = get_config('package_samie');
                        $baseurl = $samieconfig->baseurl;
                        if (substr($baseurl, -1, 1) != '/') {
                            $baseurl .= '/';
                        }
                        $cansigndelivery = has_capability('block/samiematerial:signmaterialdelivery', $coursecontext,
                                $USER, true);
                        if ($cansigndelivery && !is_siteadmin()) {
                            $result = file_get_contents($baseurl ."inc/coursefilesrequests.php?action=material_delivery_sign".
                                    "&alu_id={$USER->id}&course_id={$file->afg_id}&file_id=$fileid");
                        } else {
                            $result = 'OK';
                        }
                        if (strcmp ($result, 'OK') == 0) {
                            $transaction->allow_commit();
                        } else {
                            $file = null;
                        }
                    } catch (Exception $e) {
                        $transaction->rollback($e);
                    }
                }
            }
        }
    }

    if ($file) {
        block_samiematerial_return_file($file);
    } else {
        block_samiematerial_print_message(get_string('wrong_resource', 'block_samiematerial'));
    }
} catch (Exception $ex) {
    block_samiematerial_print_message(get_string('wrong_resource', 'block_samiematerial'));
}