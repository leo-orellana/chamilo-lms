<?php
/* For licensing terms, see /license.txt */

/**
 * Class Plugin
 * Base class for plugins
 *
 *
 * This class has to be extended by every plugin. It defines basic methods
 * to install/uninstall and get information about a plugin
 *
 * @copyright (c) 2012 University of Geneva
 * @license GNU General Public License - http://www.gnu.org/copyleft/gpl.html
 * @author Laurent Opprecht <laurent@opprecht.info>
 * @author Julio Montoya <gugli100@gmail.com> added course settings support + lang variable fixes
 * @author Yannick Warnier <ywarnier@beeznest.org> added documentation
 */
class Plugin
{
    protected $version = '';
    protected $author = '';
    protected $fields = array();
    private $settings = null;
    // Translation strings.
    private $strings = null;
    public $is_course_plugin = false;

    /**
     * When creating a new course, these settings are added to the course, in
     * the course_info/infocours.php
     * To show the plugin course icons you need to add these icons:
     * main/img/icons/22/plugin_name.png
     * main/img/icons/64/plugin_name.png
     * main/img/icons/64/plugin_name_na.png
     * @example
     * $course_settings = array(
          array('name' => 'big_blue_button_welcome_message',  'type' => 'text'),
          array('name' => 'big_blue_button_record_and_store', 'type' => 'checkbox')
       );
     */
    public $course_settings = array();
    /**
     * This indicates whether changing the setting should execute the callback
     * function.
     */
    public $course_settings_callback = false;

    /**
     * Default constructor for the plugin class. By default, it only sets
     * a few attributes of the object
     * @param  string  Version of this plugin
     * @param  string  Author of this plugin
     * @param  array   Array of global settings to be proposed to configure the plugin
     */
    protected function __construct($version, $author, $settings = array())
    {
        $this->version = $version;
        $this->author = $author;
        $this->fields = $settings;

        global $language_files;
        $language_files[] = 'plugin_' . $this->get_name();
    }

    /**
     * Gets an array of information about this plugin (name, version, ...)
     * @return  array Array of information elements about this plugin
     */
    public function get_info()
    {
        $result = array();
        $result['title']            = $this->get_title();
        $result['comment']          = $this->get_comment();
        $result['version']          = $this->get_version();
        $result['author']           = $this->get_author();
        $result['plugin_class']     = get_class($this);
        $result['is_course_plugin'] = $this->is_course_plugin;

        if ($form = $this->get_settings_form()) {
            $result['settings_form'] = $form;
            foreach ($this->fields as $name => $type) {
                $value = $this->get($name);
                $result[$name] = $value;
            }
        }

        return $result;
    }

    /**
     * Returns the "system" name of the plugin in lowercase letters
     * @return string
     */
    public function get_name()
    {
        $result = get_class($this);
        $result = str_replace('Plugin', '', $result);
        $result = strtolower($result);

        return $result;
    }

    /**
     * Returns the title of the plugin
     * @return string
     */
    public function get_title()
    {
        return $this->get_lang('plugin_title');
    }

    /**
     * Returns the description of the plugin
     * @return string
     */
    public function get_comment()
    {
        return $this->get_lang('plugin_comment');
    }

    /**
     * Returns the version of the plugin
     * @return string
     */
    public function get_version()
    {
        return $this->version;
    }

    /**
     * Returns the author of the plugin
     * @return string
     */
    public function get_author()
    {
        return $this->author;
    }

    /**
     * Returns the contents of the CSS defined by the plugin
     * @return array
     */
    public function get_css()
    {
        $name = $this->get_name();
        $path = api_get_path(SYS_PLUGIN_PATH)."$name/resources/$name.css";
        if (!is_readable($path)) {
            return '';
        }
        $css = array();
        $css[] = file_get_contents($path);
        $result = implode($css);

        return $result;
    }

    /**
     * Returns an HTML form (generated by FormValidator) of the plugin settings
     * @return string FormValidator-generated form
     */
    public function get_settings_form()
    {
        $result = new FormValidator($this->get_name());

        $defaults = array();
        foreach ($this->fields as $name => $type) {
            $value = $this->get($name);

            $defaults[$name] = $value;
            $type = isset($type) ? $type : 'text';

            $help = null;
            if ($this->get_lang_plugin_exists($name.'_help')) {
                $help = $this->get_lang($name.'_help');
            }

            switch ($type) {
                case 'html':
                    $result->addElement('html', $this->get_lang($name));
                    break;
                case 'wysiwyg':
                    $result->add_html_editor($name, $this->get_lang($name));
                    break;
                case 'text':
                    $result->addElement($type, $name, array($this->get_lang($name), $help));
                    break;
                case 'boolean':
                    $group = array();
                    $group[] = $result->createElement('radio', $name, '', get_lang('Yes'), 'true');
                    $group[] = $result->createElement('radio', $name, '', get_lang('No'), 'false');
                    $result->addGroup($group, null, array($this->get_lang($name), $help));
                    break;
            }
        }
        $result->setDefaults($defaults);
        $result->addElement('style_submit_button', 'submit_button', $this->get_lang('Save'));

        return $result;
    }

    /**
     * Returns the value of a given plugin global setting
     * @param string Name of the plugin
     *
     * @return string Value of the plugin
     */
    public function get($name)
    {
        $settings = $this->get_settings();
        foreach ($settings as $setting) {
            if ($setting['variable'] == ($this->get_name() . '_' . $name)) {
                return $setting['selected_value'];
            }
        }
        return false;
    }

    /**
     * Returns an array with the global settings for this plugin
     * @return array Plugin settings as an array
     */
    public function get_settings()
    {
        if (is_null($this->settings)) {
            $settings = api_get_settings_params(
                array(
                    "subkey = ? AND category = ? AND type = ? " => array($this->get_name(), 'Plugins', 'setting')
                )
            );
            $this->settings = $settings;
        }

        return $this->settings;
    }

    /**
     * Tells whether language variables are defined for this plugin or not
     * @param string System name of the plugin
     *
     * @return boolean True if the plugin has language variables defined, false otherwise
     */
    public function get_lang_plugin_exists($name)
    {
        return isset($this->strings[$name]);
    }

    /**
     * Hook for the get_lang() function to check for plugin-defined language terms
     * @param string Name of the language variable we are looking for
     *
     * @return string The translated language term of the plugin
     */
    public function get_lang($name)
    {
        // Check whether the language strings for the plugin have already been
        // loaded. If so, no need to load them again.
        if (is_null($this->strings)) {
            global $language_interface;
            $root = api_get_path(SYS_PLUGIN_PATH);
            $plugin_name = $this->get_name();

            //1. Loading english if exists
            $english_path = $root.$plugin_name."/lang/english.php";
            if (is_readable($english_path)) {
                include $english_path;
                $this->strings = $strings;
            }

            $path = $root.$plugin_name."/lang/$language_interface.php";
            //2. Loading the system language
            if (is_readable($path)) {
                include $path;
                if (!empty($strings)) {
                    foreach ($strings as $key => $string) {
                        $this->strings[$key] = $string;
                    }
                }
            }
        }

        if (isset($this->strings[$name])) {
            return $this->strings[$name];
        }

        return get_lang($name);
    }

    /**
     * Caller for the install_course_fields() function
     * @param int The course's integer ID
     * @param boolean Whether to add a tool link on the course homepage
     *
     * @return void
     */
    public function course_install($courseId, $addToolLink = true)
    {
        $this->install_course_fields($courseId, $addToolLink);
    }

    /**
     * Add course settings and, if not asked otherwise, add a tool link on the course homepage
     * @param int Course integer ID
     * @param boolean Whether to add a tool link or not (some tools might just offer a configuration section and act on the backend)
     * @return boolean  False on error, null otherwise
     */
    public function install_course_fields($course_id, $add_tool_link = true)
    {
        $plugin_name = $this->get_name();
        $t_course = Database::get_course_table(TABLE_COURSE_SETTING);
        $course_id = intval($course_id);

        if (empty($course_id)) {
            return false;
        }
        // Ads course settings.
        if (!empty($this->course_settings)) {
            foreach ($this->course_settings as $setting) {
                $variable = Database::escape_string($setting['name']);
                $value ='';
                if (isset($setting['init_value'])) {
                    $value = Database::escape_string($setting['init_value']);
                }
                $type = 'textfield';
                if (isset($setting['type'])) {
                    $type = Database::escape_string($setting['type']);
                }
                if (isset($setting['group'])) {
                    $group = Database::escape_string($setting['group']);
                    $sql = "SELECT value FROM $t_course WHERE c_id = $course_id AND variable = '$group' AND subkey = '$variable' ";
                    $result = Database::query($sql);
                    if (!Database::num_rows($result)) {
                        $sql_course = "INSERT INTO $t_course (c_id, variable, subkey, value, category, type) VALUES ($course_id, '$group', '$variable', '$value', 'plugins', '$type')";
                        $r = Database::query($sql_course);
                    }
                } else {
                    $sql = "SELECT value FROM $t_course WHERE c_id = $course_id AND variable = '$variable' ";
                    $result = Database::query($sql);
                    if (!Database::num_rows($result)) {
                        $sql_course = "INSERT INTO $t_course (c_id, variable, value, category, subkey, type) VALUES ($course_id, '$variable','$value', 'plugins', '$plugin_name', '$type')";
                        $r = Database::query($sql_course);
                    }
                }
            }
        }

        // Stop here if we don't want a tool link on the course homepage
        if (!$add_tool_link) {
            return true;
        }

        //Add an icon in the table tool list
        $t_tool = Database::get_course_table(TABLE_TOOL_LIST);
        $sql = "SELECT name FROM $t_tool WHERE c_id = $course_id AND name = '$plugin_name' ";
        $result = Database::query($sql);
        if (!Database::num_rows($result)) {
            $tool_link = "$plugin_name/start.php";
            $visibility = string2binary(api_get_setting('course_create_active_tools', $plugin_name));
            $sql_course = "INSERT INTO $t_tool
            VALUES ($course_id, NULL, '$plugin_name', '$tool_link', '$plugin_name.png',' ".$visibility."','0', 'squaregrey.gif','NO','_self','plugin','0')";
            Database::query($sql_course);
        }
    }

    /**
     * Delete the fields added to the course settings page and the link to the
     * tool on the course's homepage
     * @param int The integer course ID
     * @return void
     */
    public function uninstall_course_fields($course_id)
    {
        $course_id = intval($course_id);
        if (empty($course_id)) {
            return false;
        }
        $plugin_name = $this->get_name();

        $t_course = Database::get_course_table(TABLE_COURSE_SETTING);
        $t_tool = Database::get_course_table(TABLE_TOOL_LIST);

        if (!empty($this->course_settings)) {
            foreach ($this->course_settings as $setting) {
                $variable = Database::escape_string($setting['name']);
                if (!empty($setting['group'])) {
                    $variable = Database::escape_string($setting['group']);
                }
                $sql_course = "DELETE FROM $t_course WHERE c_id = $course_id AND variable = '$variable'";
                Database::query($sql_course);
            }
        }

        $sql_course = "DELETE FROM $t_tool WHERE c_id = $course_id AND name = '$plugin_name'";
        Database::query($sql_course);
    }

    /**
     * Install the course fields and tool link of this plugin in all courses
     * @param boolean Whether we want to add a plugin link on the course homepage
     *
     * @return void
     */
    public function install_course_fields_in_all_courses($add_tool_link = true)
    {
        // Update existing courses to add conference settings
        $t_courses = Database::get_main_table(TABLE_MAIN_COURSE);
        $sql = "SELECT id, code FROM $t_courses ORDER BY id";
        $res = Database::query($sql);
        while ($row = Database::fetch_assoc($res)) {
            $this->install_course_fields($row['id'], $add_tool_link);
        }
    }

    /**
     * Uninstall the plugin settings fields from all courses
     * @return void
     */
    public function uninstall_course_fields_in_all_courses()
    {
        // Update existing courses to add conference settings
        $t_courses = Database::get_main_table(TABLE_MAIN_COURSE);
        $sql = "SELECT id, code FROM $t_courses ORDER BY id";
        $res = Database::query($sql);
        while ($row = Database::fetch_assoc($res)) {
            $this->uninstall_course_fields($row['id']);
        }
    }

    /**
     * @return array
     */
    public function getCourseSettings()
    {
        $settings = array();
        if (is_array($this->course_settings)) {
            foreach ($this->course_settings as $item) {
                if (isset($item['group'])) {
                    if (!in_array($item['group'], $settings)) {
                        $settings[] = $item['group'];
                    }
                } else {
                    $settings[] = $item['name'];
                }
            }
        }

        return $settings;
    }

    /**
     * Method to be extended when changing the setting in the course
     * configuration should trigger the use of a callback method
     * @param array Values sent back from the course configuration script
     * @return void
     */
    public function course_settings_updated($values = array())
    {

    }
}
