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
 * Class files field for database activity
 *
 * Forked from datafield_file and extended to support multiple files.
 *
 * @package    datafield_files
 * @copyright  2024 Lafayette College ITS
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

require_once(__DIR__ . '/../file/field.class.php');

/**
 * Class files field for database activity
 *
 * Forked from datafield_file and extended to support multiple files.
 *
 * @package    datafield_files
 * @copyright  2024 Lafayette College ITS
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class data_field_files extends data_field_file {
    /** @var string The internal datafield type */
    var $type = 'files';

    /**
     * Output control for editing content.
     *
     * @param int $recordid the id of the data record.
     * @param object $formdata the submitted form.
     *
     * @return string
     */
    public function display_add_field($recordid = 0, $formdata = null) {
        global $CFG, $DB, $OUTPUT, $PAGE;

        // Necessary for the constants used in args.
        require_once($CFG->dirroot . '/repository/lib.php');

        $itemid = null;

        // Editing an existing database entry.
        if ($formdata) {
            $fieldname = 'field_' . $this->field->id . '_files';
            $itemid = clean_param($formdata->$fieldname, PARAM_INT);
        } else if ($recordid) {
            if (!$content = $DB->get_record('data_content', ['fieldid' => $this->field->id, 'recordid' => $recordid])) {
                // Quickly make one now!
                $content = new stdClass();
                $content->fieldid  = $this->field->id;
                $content->recordid = $recordid;
                $id = $DB->insert_record('data_content', $content);
                $content = $DB->get_record('data_content', ['id' => $id]);
            }
            file_prepare_draft_area($itemid, $this->context->id, 'mod_data', 'content', $content->id);

        } else {
            $itemid = file_get_unused_draft_itemid();
        }

        // Database entry label.
        $html = '<div title="' . s($this->field->description) . '">';
        $html .= '<fieldset><legend><span class="accesshide">'.s($this->field->name);

        if ($this->field->required) {
            $html .= '&nbsp;' . get_string('requiredelement', 'form') . '</span></legend>';
            $image = $OUTPUT->pix_icon('req', get_string('requiredelement', 'form'));
            $html .= html_writer::div($image, 'inline-req');
        } else {
            $html .= '</span></legend>';
        }

        // Itemid element.
        $html .= '<input type="hidden" name="field_'.$this->field->id.'_files" value="'.s($itemid).'" />';

        $options = new stdClass();
        $options->maxbytes = $this->field->param3;
        $options->itemid    = $itemid;
        $options->accepted_types = '*';
        $options->return_types = FILE_INTERNAL | FILE_CONTROLLED_LINK;
        $options->context = $PAGE->context;

        $fm = new form_filemanager($options);
        // Print out file manager.

        $output = $PAGE->get_renderer('core', 'files');
        $html .= '<div class="mod-data-input">';
        $html .= $output->render($fm);
        $html .= '</div>';
        $html .= '</fieldset>';
        $html .= '</div>';

        return $html;
    }

    /**
     * Get the assciated files.
     *
     * @param int $recordid
     * @param string $content string representation of names
     *
     * @return array
     */
    function get_files($recordid, $content=null) {
        global $DB;
        if (empty($content)) {
            if (!$content = $this->get_data_content($recordid)) {
                return null;
            }
        }

        $fs = get_file_storage();
        if (!$files = $fs->get_area_files($this->context->id, 'mod_data', 'content', $content->id)) {
            return null;
        }

        return $files;
    }

    /**
     * Write out the list of files in a human-readable list.
     *
     * @param int $recordid the record id
     * @param object $template the template object; unused
     *
     * @return string
     */
    function display_browse_field($recordid, $template) {
        $content = $this->get_data_content($recordid);

        if (!$content || empty($content->content)) {
            return $this->field->param4;
        }

        if ($this->preview) {
            $file = (object)[
                'filename' => $content->content,
                'mimetype' => 'text/csv',
            ];
            $name = !empty($content->content1) ? $content->content1 : $content->content;
            $items[] = $this->display_browse_field_item($file, '', $name);
        } else {
            $files = $this->get_files($recordid, $content);
            if (!$files) {
                return $this->field->param4;
            }

            $items = [];
            foreach($files as $file) {
                if($file->get_filename() == '.') {
                    continue;
                }

                $fileurl = moodle_url::make_pluginfile_url(
                    $file->get_contextid(),
                    $file->get_component(),
                    $file->get_filearea(),
                    $file->get_itemid(),
                    $file->get_filepath(),
                    $file->get_filename()
                );
                $url = $fileurl->out();

                $items[] = $this->display_browse_field_item($file, $url, s($file->get_filename()));
            }
        }

        return \html_writer::alist($items);
    }

    /**
     * Render a single file in the file list.
     *
     * @param stored_file $file a file object
     * @param moodle_url $url the exported URL to the file
     * @param string the plain language file name
     *
     * @return string
     */
    protected function display_browse_field_item($file, $url, $name) {
        global $OUTPUT;

        $icon = $OUTPUT->pix_icon(
            file_file_icon($file),
            get_mimetype_description($file),
            'moodle',
            ['width' => 32, 'height' => 32]
        );

        return $icon . '&nbsp;' . \html_writer::link($url, s($file->get_filename()), ['class' => 'data-field-link']);
    }

    /**
     * Create or update the content.
     *
     * @param int $recordid the record id
     * @param string $value the the draft area id
     * @param string $name constructed name of the field, such as "field_10_files"
     */
    public function update_content($recordid, $value, $name='') {
        global $CFG, $DB, $USER;
        $fs = get_file_storage();

        // Should always be available since it is set by display_add_field before initializing the draft area.
        $content = $DB->get_record('data_content', ['fieldid' => $this->field->id, 'recordid' => $recordid]);
        if (!$content) {
            $content = (object)['fieldid' => $this->field->id, 'recordid' => $recordid];
            $content->id = $DB->insert_record('data_content', $content);
        }

        file_save_draft_area_files($value, $this->context->id, 'mod_data', 'content', $content->id);

        $usercontext = context_user::instance($USER->id);
        $files = $fs->get_area_files($this->context->id, 'mod_data', 'content', $content->id, 'itemid, filepath, filename', false);

        // No upper limit on files.
        if (count($files) == 0) {
            $field = $DB->get_record('data_fields', ['id' => $this->field->id]);
            $content->content = null;
        } else {
            $filenames = [];
            foreach($files as $file) {
                $filenames[] = $file->get_filename();
            }
            $content->content = serialize($filenames);
        }
        $DB->update_record('data_content', $content);
    }

    /**
     * Here we export the text value of a files field which is the filenames of the exported files.
     *
     * @param stdClass $record the record which is being exported
     * @return string the value which will be stored in the exported file for this field
     */
    public function export_text_value(stdClass $record): string {
        if (!empty($record->content)) {
            $content = unserialize($record->content);
            return implode(',',$content);
        } else {
            return '';
        }
    }

    /**
     * Specifies that this field type supports the export of files.
     *
     * @return bool
     */
    public function file_export_supported(): bool {
        return false;
    }

    /**
     * Specifies that this field type supports the import of files.
     *
     * @return bool
     */
    public function file_import_supported(): bool {
        return false;
    }
}
