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
 * @package    datafield_file
 * @copyright  2005 Martin Dougiamas
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class data_field_files extends data_field_base {
    var $type = 'files';

    public function supports_preview(): bool {
        return true;
    }

    public function get_data_content_preview(int $recordid): stdClass {
        return (object)[
            'id' => 0,
            'fieldid' => $this->field->id,
            'recordid' => $recordid,
            'content' => 'samplefile.csv',
            'content1' => 'samplefile.csv',
            'content2' => null,
            'content3' => null,
            'content4' => null,
        ];
    }

    function display_add_field($recordid = 0, $formdata = null) {
        global $CFG, $DB, $OUTPUT, $PAGE;

        // Necessary for the constants used in args.
        require_once($CFG->dirroot . '/repository/lib.php');

        $itemid = null;

        // editing an existing database entry
        if ($formdata) {
            $fieldname = 'field_' . $this->field->id . '_files';
            $itemid = clean_param($formdata->$fieldname, PARAM_INT);
        } else if ($recordid) {
            if (!$content = $DB->get_record('data_content', array('fieldid' => $this->field->id, 'recordid' => $recordid))) {
                // Quickly make one now!
                $content = new stdClass();
                $content->fieldid  = $this->field->id;
                $content->recordid = $recordid;
                $id = $DB->insert_record('data_content', $content);
                $content = $DB->get_record('data_content', array('id' => $id));
            }
            file_prepare_draft_area($itemid, $this->context->id, 'mod_data', 'content', $content->id);

        } else {
            $itemid = file_get_unused_draft_itemid();
        }

        // database entry label
        $html = '<div title="' . s($this->field->description) . '">';
        $html .= '<fieldset><legend><span class="accesshide">'.s($this->field->name);

        if ($this->field->required) {
            $html .= '&nbsp;' . get_string('requiredelement', 'form') . '</span></legend>';
            $image = $OUTPUT->pix_icon('req', get_string('requiredelement', 'form'));
            $html .= html_writer::div($image, 'inline-req');
        } else {
            $html .= '</span></legend>';
        }

        // itemid element
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

    function display_search_field($value = '') {
        return '<label class="accesshide" for="f_' . $this->field->id . '">' . s($this->field->name) . '</label>' .
               '<input type="text" size="16" id="f_'.$this->field->id.'" name="f_'.$this->field->id.'" ' .
                    'value="'.s($value).'" class="form-control"/>';
    }

    function generate_sql($tablealias, $value) {
        global $DB;

        static $i=0;
        $i++;
        $name = "df_file_$i";
        return array(" ({$tablealias}.fieldid = {$this->field->id} AND ".$DB->sql_like("{$tablealias}.content", ":$name", false).") ", array($name=>"%$value%"));
    }

    public function parse_search_field($defaults = null) {
        $param = 'f_'.$this->field->id;
        if (empty($defaults[$param])) {
            $defaults = array($param => '');
        }
        return optional_param($param, $defaults[$param], PARAM_NOTAGS);
    }

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

    function display_browse_field_item($file, $url, $name) {
        global $OUTPUT;

        $icon = $OUTPUT->pix_icon(
            file_file_icon($file),
            get_mimetype_description($file),
            'moodle',
            ['width' => 32, 'height' => 32]
        );

        return $icon . '&nbsp;' . \html_writer::link($url, s($file->get_filename()), ['class' => 'data-field-link']);
    }

    // content: "a##b" where a is the file name, b is the display name
    function update_content($recordid, $value, $name='') {
        global $CFG, $DB, $USER;
        $fs = get_file_storage();

        // Should always be available since it is set by display_add_field before initializing the draft area.
        $content = $DB->get_record('data_content', array('fieldid' => $this->field->id, 'recordid' => $recordid));
        if (!$content) {
            $content = (object)array('fieldid' => $this->field->id, 'recordid' => $recordid);
            $content->id = $DB->insert_record('data_content', $content);
        }

        file_save_draft_area_files($value, $this->context->id, 'mod_data', 'content', $content->id);

        $usercontext = context_user::instance($USER->id);
        $files = $fs->get_area_files($this->context->id, 'mod_data', 'content', $content->id, 'itemid, filepath, filename', false);

        // No upper limit on files.
        if (count($files) == 0) {
            $field = $DB->get_record('data_fields', array('id' => $this->field->id));
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
     * @return bool true which means that file export is being supported by this field type
     */
    public function file_export_supported(): bool {
        return false;
    }

    /**
     * Specifies that this field type supports the import of files.
     *
     * @return bool true which means that file import is being supported by this field type
     */
    public function file_import_supported(): bool {
        return false;
    }

    function file_ok($path) {
        return true;
    }

    /**
     * Custom notempty function
     *
     * @param string $value
     * @param string $name
     * @return bool
     */
    function notemptyfield($value, $name) {
        global $USER;

        $names = explode('_', $name);

        if ($names[2] == 'files') {
            $usercontext = context_user::instance($USER->id);
            $fs = get_file_storage();
            $files = $fs->get_area_files($usercontext->id, 'user', 'draft', $value);
            return count($files) >= 2;
        }
        return false;
    }

    /**
     * Return the plugin configs for external functions.
     *
     * @return array the list of config parameters
     */
    public function get_config_for_external() {
        // Return all the config parameters.
        $configs = [];
        for ($i = 1; $i <= 10; $i++) {
            $configs["param$i"] = $this->field->{"param$i"};
        }
        return $configs;
    }

    public function get_field_params(): array {
        global $DB, $CFG;

        $data = parent::get_field_params();

        $course = $DB->get_record('course', ['id' => $this->data->course]);
        $filesizes = get_max_upload_sizes($CFG->maxbytes, $course->maxbytes, 0, $this->field->param3);

        foreach ($filesizes as $value => $name) {
            if (!((isset($this->field->param3) && $value == $this->field->param3))) {
                $data['filesizes'][] = ['name' => $name, 'value' => $value, 'selected' => 0];
            } else {
                $data['filesizes'][] = ['name' => $name, 'value' => $value, 'selected' => 1];
            }
        }

        return $data;
    }
}
