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
 * Block functions library.
 *
 * @package    SAMIE
 * @copyright  2015 Planificacion de Entornos Tecnologicos SL
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

/**
 * Check if the logged user is a course student
 *
 * @param $courseid Course id
 *
 * @return bool A status indicating yes or not
 */
function block_samiematerial_is_student($courseid) {
    global $USER;
    $context = context_course::instance($courseid);
    return is_enrolled($context, $USER);
}

/** This function will print a message. Generally for error messages
 *
 * @param string The message displayed
 * @return void
 */
function block_samiematerial_print_message($msg = null) {
    global $PAGE, $OUTPUT;
    $systemcontext = context_system::instance();
    require_login();
    $PAGE->set_url('/blocks/samiematerial/download.php');
    $PAGE->set_pagelayout('standard');
    $PAGE->set_context($systemcontext);
    $PAGE->set_title(get_string('title', 'block_samiematerial'));

    echo $OUTPUT->header();

    $message = html_writer::start_tag('div', array('id' => 'samiematerialcontent', 'class' => 'block'));

    $message .= html_writer::start_tag('div', array('class' => 'header'));
    $message .= html_writer::start_tag('div', array('class' => 'title'));
    $message .= html_writer::tag('h2', get_string('title', 'block_samiematerial'));
    $message .= html_writer::end_tag('div');
    $message .= html_writer::end_tag('div');
    $message .= html_writer::tag('h3', $msg);
    $message .= html_writer::tag('button', get_string('close', 'block_samiematerial'),
        array('class' => 'btn btn-default', 'onclick' => 'window.close();'));
    $message .= html_writer::end_tag('div');

    echo $message;
    echo $OUTPUT->footer();
}

/** Returns the contents of the selected file to download
 *
 * @uses exit
 * @param coursefile File object that contains the filename, the real filename and the afg_id
 * @param int Our id of course
 */
function block_samiematerial_return_file($coursefile) {
    global $CFG;
    $path = $CFG->dirroot. '/blocks/samiematerial/coursefiles/course'.$coursefile->afg_id;

    if (!file_exists($path.'/'.$coursefile->realfilename)) {
        block_samiematerial_print_message(get_string('not_found', 'block_samiematerial'));
        return false;
    }

    $headertype = block_samiematerial_get_file_mime_type($coursefile->filename);
    header('Content-Description: File Transfer');
    header("Content-Type: $headertype");
    header('Content-Disposition: attachment; filename="'.$coursefile->filename.'"');
    header('Expires: 0');
    header('Cache-Control: must-revalidate');
    header('Pragma: public');
    header('Content-Length: '.filesize($path.'/'.$coursefile->realfilename));
    readfile($path.'/'.$coursefile->realfilename);
}

/**
 * Get the img html tag corresponding with the mime type depending on the filename extension
 *
 * @param $filename Name of file
 *
 * @return string Will return the html tag with the image of the mime type
 */
function block_samiematerial_get_mime_type_image_tag($filename) {
    $extensionfile = explode('.', $filename);
    $extensionfile = $extensionfile[1]; // This contains the file extension.

    $out = ''; // Return html string .
    $imagesrc = '../blocks/samiematerial/pix/';
    switch($extensionfile) {
        case 'png':
        case 'jpg':
        case 'jpeg':
        case 'bmp':
        case 'gif':
            $imagesrc .= 'images.png';
            break;
        case 'rar':
        case 'zip':
            $imagesrc .= 'rar.png';
            break;
        case 'wmv':
        case 'avi':
        case 'flv':
            $imagesrc .= 'video.png';
            break;
        case 'doc':
        case 'docx':
        case 'xls':
        case 'xlsx':
            $imagesrc .= 'word.png';
            break;
        case 'txt':
            $imagesrc .= 'text.png';
            break;
        case 'pdf':
            $imagesrc .= 'pdf.png';
            break;
        default:
            $imagesrc .= 'unknown.png';
            break;
    }
    $out = html_writer::img($imagesrc, '', array('class' => 'block_samiematerial_mimetypeimg'));
    return $out;
}

/** Returns the correct header depending the file extension (mime type)
 * @param string filename
 * @return string return the correct header
 */
function block_samiematerial_get_file_mime_type($filename) {
    $fileextension = explode('.', $filename);
    $fileextension = $fileextension[1]; // This contains the file extension.
    $headertype = '';
    switch($fileextension) {
        case 'png':
            $headertype = 'image/png';
            break;
        case 'jpg':
            $headertype = 'image/jpeg';
            break;
        case 'jpeg':
            $headertype = 'image/jpeg';
            break;
        case 'bmp':
            $headertype = 'image/bmp';
            break;
        case 'gif':
            $headertype = 'image/gif';
            break;
        case 'rar':
            $headertype = 'application/x-rar-compressed';
            break;
        case 'zip':
            $headertype = 'application/zip';
            break;
        default:
            $headertype = 'application/octet-stream';
            break;
    }
    return $headertype;
}

/**
 * Shorten a text if its length is bigger than maxlength (and add an ellipsis)
 *
 * @param text Text to shorten
 * @param maxlength Maximun length size
 */
function block_samiematerial_shorten_text_with_ellipsis($text, $maxlength) {
    $result = $text;
    if (strlen($text) > $maxlength - 3) {
        $result = substr_replace($text, '...', $maxlength, strlen($text));
    }
    return $result;
}