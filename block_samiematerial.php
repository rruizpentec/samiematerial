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
 * Classes to implement the block.
 *
 * @package    SAMIE
 * @copyright  2015 Planificacion de Entornos Tecnologicos SL
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

global $CFG;

require_once($CFG->libdir . '/formslib.php');
require_once($CFG->libdir . '/blocklib.php');
require_once($CFG->dirroot . '/blocks/samiematerial/lib.php');

/**
 * Block samiematerial class definition.
 *
 * This block can be added to a course page to display of
 * list of files for a course. This block allow to download
 * files and if you are a teacher, can delete files, or upload more.
 *
 * @package    SAMIE
 * @copyright  2015 Planificacion de Entornos Tecnologicos SL
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class block_samiematerial extends block_base {
    /**
     * Core function used to initialize the block.
     *
     * @return void
     */
    public function init() {
        $this->title = get_string('title', 'block_samiematerial');
    }

    /**
     * This function will delete the file selected from directory and the 'deleted' field will set to 1,
     * for don't appear in the course files list
     *
     * @return void
     */
    public static function block_samiematerial_delete_file() {
        global $DB, $CFG, $COURSE, $USER;
        $fileid = required_param('file_id', PARAM_INT);
        $file = $DB->get_record('block_samiematerial_up', array('id' => $fileid));
        $coursecontext = context_course::instance($COURSE->id);
        if (has_capability('block/samiematerial:managefiles', $coursecontext, $USER, true)) {
            if ($file != '') {
                unlink($CFG->dirroot. '/blocks/samiematerial/coursefiles/course'.$file->afg_id.'/'.$file->realfilename);
                // Logical delete.
                $file->deleted = 1;
                $DB->update_record('block_samiematerial_up', $file);
                header('Location: view.php?id='.$COURSE->id);
            }
        }
    }

    /**
     * Returns the upload file form and processes upload actions
     *
     * @return form
     */
    private static function get_files_form ($coursecontext) {
        global $USER, $CFG, $COURSE;
        $form = null;
        if (has_capability('block/samiematerial:managefiles', $coursecontext, $USER, true)) {
            $form = new block_samiematerial_form();
            $afgid = optional_param('afg_id', null, PARAM_INT);
            $categorytype = optional_param('categorytype', null, PARAM_ALPHAEXT);
            $courseid = optional_param('courseid', null, PARAM_INT);
            $description = optional_param('descriptionfile', null, PARAM_TEXT);
            $action = optional_param('action', null, PARAM_TEXT);
            if (!isset($action)) {
                $action = '';
            }
            if ($action != 'delete') {
                if (!isset($afgid)) {
                    $afgid = '';
                }
                if (!isset($categorytype)) {
                    $categorytype = '';
                }
                if (!isset($courseid)) {
                    $courseid = '';
                }
                if (!isset($description)) {
                    $description = '';
                }
                $name = $form->get_new_filename('userfile');
                $path = $CFG->dirroot. '/blocks/samiematerial/coursefiles/course'.$afgid;

                if (!is_dir($path)) {
                    mkdir($path);
                }
                if (is_dir($path)) {
                    $uniquename = explode('.', $name);
                    // For prevent files are overwritten.
                    if (count($uniquename) > 1) {
                        $uniquename = $uniquename[0]. '.' .md5(uniqid(rand(), true)). '.' .$uniquename[1];
                    } else {
                        $uniquename = $name. '.' .md5(uniqid(rand(), true));
                    }
                    $completedir = $path.'/'.$uniquename;
                    if ($form->save_file('userfile', $completedir, true)) {
                        self::store_file ($description, $name, $afgid, $categorytype, $uniquename, $USER);
                        // Redirect to avoid submitting the same file twice.
                        header('Location: view.php?id='.$COURSE->id);
                    }
                } else {
                    $form = null;
                }
            }
        }
        return $form;
    }

    /**
     * Stores a record of an uploaded file
     *
     * @return string
     */
    private function store_file ($description, $name, $afgid, $categorytype, $uniquename, $USER) {
        global $DB;
        $record = new stdClass();
        $record->description = ($description == '' ? $name : $description);
        $record->filename = $name;
        $record->afg_id = $afgid;
        $record->afg_type = $categorytype;
        $record->deleted = 0;
        $record->realfilename = $uniquename;
        $record->userid = $USER->id;
        $record->uploaded_date = date('Y-m-d H:i:s');
        $DB->insert_record('block_samiematerial_up', $record, false);
    }

    /**
     * Returns html code with the files table
     *
     * @return string
     */
    private function get_files_table($course, $coursecontext) {
        global $USER, $DB, $CFG;
        $table = '';
        $afgidlms = '';
        $categorytype = '';
        if ($course->category == 1) {
            // Propia.
            $afgidlms = $course->id;
            $categorytype = 'C';
        } else {
            // CNCP.
            $afgidlms = $course->category;
            $categorytype = 'SC';
        }
        $sql = "SELECT *
                  FROM {block_samiematerial_up}
                 WHERE afg_id = :afgidvalue
                       AND afg_type = :categorytype
                       AND deleted = 0";
        $result = $DB->get_recordset_sql($sql, array('afgidvalue' => $afgidlms , 'categorytype' => $categorytype));
        $filecount = 0;
        $tablerows = '';
        foreach ($result as $file) {
            // Show the description field, but if it is empty, the file name.
            $filenameshown = $file->description != '' ? $file->description : $file->filename;
            $filenameshown = block_samiematerial_shorten_text_with_ellipsis($filenameshown,
                    27);
            $tablerows .= html_writer::start_tag('tr');
            $tablerows .= html_writer::start_tag('td');
            $tablerows .= block_samiematerial_get_mime_type_image_tag($file->filename);
            $tablerows .= html_writer::end_tag('td');
            $tablerows .= html_writer::start_tag('td');
            $downloadfileurl = $CFG->wwwroot.'/blocks/samiematerial/download.php?fileid='.
                    $file->id.'&afg_id='.$afgidlms.'&courseid='.$course->id;
            $tablerows .= html_writer::tag('a', $filenameshown, array(
                'href' => $downloadfileurl,
                'target' => '_blank',
                'class' => 'block_samiematerial_filelink'));
            $tablerows .= html_writer::end_tag('td');
            // File managers can see the delete file button.
            if (has_capability('block/samiematerial:managefiles', $coursecontext, $USER)) {
                $tablerows .= html_writer::start_tag('td');
                $tablerows .= html_writer::img('../blocks/samiematerial/pix/close-icon.png', 'Delete',
                        array(
                            'onclick' => "(function() {
                                require('block_samiematerial/samiematerial').confirm_deletion(".$file->id.")
                            })();",
                            'style'   => 'cursor: pointer;',
                            'id'      => 'block_samiematerial_deletefile_'.$file->id)
                );
                $tablerows .= html_writer::end_tag('td');
            }
            $filecount++;
        }
        // Init tag.
        $table = html_writer::start_tag('table', array('class' => 'table', 'id' => 'coursematerialfilestable'));
        // Table header.
        if ($filecount > 0) {
            $table .= html_writer::start_tag('tr');
            $table .= html_writer::tag('th', get_string('files', 'block_samiematerial').' ('.$filecount.')',
                    array('colspan' => '100%'));
            $table .= html_writer::end_tag('tr');
        } else {
            $table .= html_writer::tag('th', get_string('files', 'block_samiematerial'));
            $table .= html_writer::start_tag('tr');
            $table .= html_writer::tag('td', get_string('nofiles', 'block_samiematerial'),
                    array('class' => 'block_samiematerial_nodatafound'));
            $table .= html_writer::end_tag('tr');
        }
        // Table body.
        $table .= $tablerows;
        // End tag.
        $table .= html_writer::end_tag('table');
        return $table;
    }

    /**
     * Used to generate the content for the block.
     *
     * @return string
     */
    public function get_content() {
        global $COURSE, $CFG, $PAGE, $USER;

        $PAGE->requires->js_call_amd('block_samiematerial/samiematerial', 'init', array($USER->id));
        if (isset($this->content)) {
            if ($this->content !== null) {
                return $this->content;
            }
        } else {
            $this->content = new stdClass();
            $this->content->text = '';
        }

        // The user must be in a course context to be able to see the block content.
        if ($PAGE->pagelayout != 'course') {
            $courseid = optional_param('courseid', null, PARAM_INT);
            if ($courseid != null) {
                $this->content->text = html_writer::tag('a', get_string('gobacktocourse', 'block_samiematerial'),
                        array(
                            'href' => $CFG->wwwroot."/course/view.php?id=$courseid",
                            'class' => 'btn btn-default block_samiematerial_button'));
            } else {
                $this->content->text = get_string('accesstocoursemessage', 'block_samiematerial');
            }
            return null;
        }

        $action = optional_param('action', null, PARAM_ALPHA);
        if (isset($action)) {
            if ($action == 'delete') {
                // Receiving delete action.
                self::block_samiematerial_delete_file();
            }
        }

        $content = '';

        // The teachers of this course or admins, could see the upload files form.
        $coursecontext = context_course::instance($COURSE->id);

        // Get the files form to process file uploads before showing uploaded files table.
        $form = self::get_files_form($coursecontext);
        // Show the files uploaded table.
        $content .= self::get_files_table($COURSE, $coursecontext);

        if (has_capability('block/samiematerial:managefiles', $coursecontext, $USER)) {
            if (!$form) {
                $content .= get_string('unabletomakedir', 'block_samiematerial');
            } else {
                $content .= $form->display();
            }
            $this->content->footer = html_writer::tag('a', get_string('downloadlist_button', 'block_samiematerial'),
                    array(
                        'href'  => $CFG->wwwroot.'/blocks/samiematerial/list.php?courseid='.$COURSE->id.'&action=download',
                        'class' => 'btn btn-default')
            );
            $this->content->footer .= html_writer::tag('a', get_string('uploadlist_button', 'block_samiematerial'),
                    array(
                        'href'  => $CFG->wwwroot.'/blocks/samiematerial/list.php?courseid='.$COURSE->id.'&action=upload',
                        'class' => 'btn btn-default')
            );
        }
        $this->content->text = $content;
        return $this->content;
    }

    /**
     * Core function, specifies where the block can be used.
     *
     * @return array
     */
    public function applicable_formats() {
        return array(
            'all'                => true,
            'site'               => true,
            'site-index'         => true,
            'course-view'        => true,
            'course-view-social' => false,
            'mod'                => true,
            'mod-quiz'           => false);
    }

    /**
     * Allows the block to be added multiple times to a single page
     *
     * @return bool
     */
    public function instance_allow_multiple() {
        return false;
    }

    /**
     * This line tells Moodle that the block has a settings.php file.
     *
     * @return bool
     */
    public function has_config() {
        return false;
    }
}

/**
 * This class is used to build the form for uploading files
 *
 * @package    SAMIE
 * @copyright  2015 Planificacion de Entornos Tecnologicos SL
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class block_samiematerial_form extends moodleform{
    /**
     * Method to initialize the form object.
     *
     * @return void
     */
    public function definition() {
        global $COURSE;
        $afgidlms = 0;
        $categorytype = '';
        if ($COURSE->category == 1) {
            // Propia.
            $afgidlms = $COURSE->id;
            $categorytype = 'C';
        } else {
            // CNCP.
            $afgidlms = $COURSE->category;
            $categorytype = 'SC';
        }

        $mform = $this->_form;
        $mform->setAttributes(array(
            'class'  => 'block_samiematerial_filepickerbutton',
            'method' => 'POST',
            'id'     => 'coursefileform'));
        $mform->addElement('filepicker', 'userfile', '', null, array('maxbytes' => 0, 'accepted_types' => '*'));

        $mform->setType('descriptionfile', PARAM_TEXT);
        $attributes = array('size' => '20', 'maxlength' => '50', 'class' => 'block_samiematerial_descriptionfile');
        $mform->addElement('text', 'descriptionfile', get_string('descriptionfile', 'block_samiematerial'), $attributes);

        $mform->addElement('hidden', 'afg_id', $afgidlms);
        $mform->setType('afg_id', PARAM_INT);

        $mform->addElement('hidden', 'categorytype', $categorytype);
        $mform->setType('categorytype', PARAM_ALPHAEXT);

        $mform->addElement('hidden', 'action', '', array('id' => 'action'));
        $mform->setType('action', PARAM_ALPHAEXT);

        $mform->addElement('hidden', 'askquestion', get_string('askquestion', 'block_samiematerial'),
                array('id' => 'askquestion'));
        $mform->setType('askquestion', PARAM_TEXT);

        $mform->addElement('hidden', 'file_id', '', array('id' => 'file_id'));
        $mform->setType('file_id', PARAM_INT);

        $mform->addElement('hidden', 'courseid', $COURSE->id);
        $mform->setType('courseid', PARAM_INT);

        $mform->addElement('submit', 'metalink_submit', get_string('upload'));
    }

    /**
     * Generate the HTML for the form, capture it in an output buffer, then return it
     *
     * @return string
     */
    public function display() {
        // Finalize the form definition if not yet done.
        if (!$this->_definition_finalized) {
            $this->_definition_finalized = true;
            $this->definition_after_data();
        }
        ob_start();
        $this->_form->display();
        $form = ob_get_clean();
        return $form;
    }
}
