<?php

if (!defined('__IN_SYMPHONY__')) die('<h2>Symphony Error</h2><p>You cannot directly access this file</p>');

require_once(TOOLKIT . '/fields/field.upload.php');

class FieldMultiUpload extends FieldUpload
{
    protected static $svgMimeTypes = array(
        'image/svg+xml',
        'image/svg',
    );

    public function __construct()
    {
        parent::__construct();
        $this->_name = __('Multi File Upload');
    }

/*-------------------------------------------------------------------------
    Setup:
-------------------------------------------------------------------------*/

    public function createTable()
    {
        return Symphony::Database()->query("
            CREATE TABLE IF NOT EXISTS `tbl_entries_data_" . $this->get('id') . "` (
              `id` int(11) unsigned NOT NULL auto_increment,
              `entry_id` int(11) unsigned NOT NULL,
              `file` varchar(255) default NULL,
              `size` int(11) unsigned NULL,
              `mimetype` varchar(100) default NULL,
              `meta` TEXT default NULL,
              PRIMARY KEY  (`id`),
              KEY `file` (`file`),
              KEY `mimetype` (`mimetype`)
            ) ENGINE=MyISAM DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci;
        ");
    }

/*-------------------------------------------------------------------------
    Utilities:
-------------------------------------------------------------------------*/

    public function entryDataCleanup($entry_id, $data = null)
    {
        if (is_array($data)) {
            foreach($data as $file) {
                $file = $this->getFilePath($file);
                if (is_file($file)) {
                    General::deleteFile($file);
                }
            }
        }
        return Field::entryDataCleanup($entry_id);
    }


    /**
     * Given an array of POST data, transform it for the Database
     */
    private function buildAssociativeFiles($nested_array)
    {
        $final_result = array();
        foreach($nested_array as $result) {
            if($result == false) {
                continue;
            }

            foreach($result as $column => $value) {
                if(array_key_exists($column, $final_result) === false) {
                    $final_result[$column] = array();
                }

                $final_result[$column][] = $value;
            }
        }

        return $final_result;
    }

    /**
     * Given an associatve array of array's from the Database, transform it so that
     * each nested array represents a file, rather than a piece of a file.
     * eg.
     * from => array('file' => array('fff.png', 'ffff.png'), 'size' => array('2721', '3000'));
     * to => array(array('file' => 'fff.png', 'size' => '2721'), array('file' => 'ffff.png', 'size' => '3000'));
     */
    private function buildFileItems($nested_array)
    {
        $final_result = array();
        if (!isset($nested_array)) {
            return $final_result;
        }

        foreach($nested_array as $column => $result) {
            if($result == false) {
                continue;
            }

            if(!is_array($result)) {
                $result = array($result);
            }

            foreach($result as $i => $value) {
                if(array_key_exists($column, $final_result) === false) {
                    $final_result[$i][$column] = array();
                }

                $final_result[$i][$column] = $value;
            }
        }

        return $final_result;
    }

    /**
     * Adds support for svg
     */    
    protected static function removePx($value)
    {
        return str_replace('px', '', $value);
    }
    
    protected static function isSvg($type)
    {
        return General::in_iarray($type, self::$svgMimeTypes);
    }

    public static function getMetaInfo($file, $type)
    {
        $metas = parent::getMetaInfo($file, $type);
        if (self::isSvg($type)) {
            $svg = @simplexml_load_file($file);
            if (is_object($svg)) {
                $svg->registerXPathNamespace('svg', 'http://www.w3.org/2000/svg');

                $svgAttr = $svg->xpath('@width');
                if (is_object($svgAttr)) {
                    $metas['width'] = floatval(self::removePx($svgAttr[0]->__toString()));
                }

                $svgAttr = $svg->xpath('@height');
                if (is_object($svgAttr)) {
                    $metas['height'] = floatval(self::removePx($svgAttr[0]->__toString()));
                }

                if (!isset($metas['width']) || !isset($metas['height'])) {
                    $viewBoxes = array('@viewBox', '@viewbox');
                    foreach ($viewBoxes as $vb) {
                        $svgAttr = $svg->xpath($vb);
                        if (is_array($svgAttr) && !empty($svgAttr)) {
                            $matches = array();
                            $matches_count = preg_match('/^([-]?[\d\.]+)[\s]+([-]?[\d\.]+)[\s]+([\d\.]+)[\s]+([\d\.]+)[\s]?$/i', $svgAttr[0]->__toString(), $matches);
                            if ($matches_count == 1 && count($matches) == 5) {
                                $metas['width'] = floatval($matches[3]) - floatval($matches[1]);
                                $metas['height'] = floatval($matches[4]) - floatval($matches[2]);
                                break;
                            }
                        }
                    }
                }
            }
        }
        return $metas;
    }

/*-------------------------------------------------------------------------
    Publish:
-------------------------------------------------------------------------*/

    /**
     * Majority of this function is taken from the core Upload field.
     * Note to self. Make core Upload field easier to extend
     */
    public function checkPostFieldData($data, &$message, $entry_id = null)
    {
        $status = Field::__OK__;

        if(is_array($data)) {
            foreach($data as $file_data) {
                $status = parent::checkPostFieldData($file_data, $message, $entry_id);

                // Something went wrong, abort now ($message will have the error)
                if($status !== Field::__OK__) {
                    break;
                }
            }
        }
        else {
            $status = parent::checkPostFieldData($data, $message, $entry_id);
        }

        return $status;
    }

    public function processRawFieldData($data, &$status, &$message = null, $simulate = false, $entry_id = null)
    {
        $status = Field::__OK__;
        $full_result = array();

        // Get all the existing files for this entry.
        if(!is_null($entry_id)) {
            $existing_files = Symphony::Database()->fetchCol('file', sprintf(
                "SELECT `file`, `mimetype`, `size`, `meta` FROM `tbl_entries_data_%d` WHERE `entry_id` = %d ORDER BY `id`",
                $this->get('id'),
                $entry_id
            ));

            // force array, even if all the files that were attached are
            // now deleted. RE: #21
            if (is_array($data) === false) {
                $data = array();
            }

            // loop over all existing files and see if it's also in the
            // the `$data` that was sent in this request.
            foreach($existing_files as $i => $file) {
                // if it doesn't exist in data, kill it.
                if (in_array($file, $data) === false) {
                    // remove from database
                    Symphony::Database()->query(sprintf(
                        "DELETE FROM `tbl_entries_data_%d` WHERE `entry_id` = %d AND `file` = '%s'",
                        $this->get('id'),
                        $entry_id,
                        $file
                    ));

                    // remove from file system
                    General::deleteFile($this->getFilePath($file));
                }
            }
        }

        // now try and upload whatever we were sent.
        if(is_array($data)) {
            foreach($data as $i => $file_data) {
                $result = $this->processRawFieldDataIndividual($file_data, $status, $message, $simulate, $entry_id, $i);

                // Something when wrong, abort processing the rest, ($message) will have the rest
                if($status !== Field::__OK__) {
                    break;
                }
                // Merge the result of that file upload with the rest, ready for it to be set
                // on the Entry object
                else {
                    $full_result[] = $result;
                }
            }
        }

        // Now we need to iterate over the array of results and create an array of keys for each.
        $final_result = $this->buildAssociativeFiles($full_result);

        return $final_result;
    }

    public function processRawFieldDataIndividual($data, &$status, &$message=null, $simulate = false, $entry_id = null, $position)
    {
        $status = self::__OK__;

        // No file given, save empty data:
        if ($data === null) {
            return array(
                'file' =>       null,
                'mimetype' =>   null,
                'size' =>       null,
                'meta' =>       null
            );
        }

        // Its not an array, so just retain the current data and return:
        if (is_array($data) === false) {
            $file = $this->getFilePath(basename($data));

            $result = array(
                'file' =>       basename($data),
                'mimetype' =>   null,
                'size' =>       null,
                'meta' =>       null
            );

            // Grab the existing entry data to preserve the MIME type and size information
            if (isset($entry_id) && $position !== -1) {
                $row = Symphony::Database()->fetchRow(0, sprintf(
                    "SELECT `file`, `mimetype`, `size`, `meta` FROM `tbl_entries_data_%d` WHERE `entry_id` = %d AND `file` = '%s' LIMIT 1",
                    $this->get('id'),
                    $entry_id,
                    $result['file']
                ));

                if (empty($row) === false) {
                    $result = $row;
                }
            }

            // Found the file, add any missing meta information:
            if (file_exists($file) && is_readable($file)) {
                if (empty($result['mimetype'])) {
                    $result['mimetype'] = General::getMimeType($file);
                }

                if (empty($result['size'])) {
                    $result['size'] = filesize($file);
                }

                if (empty($result['meta'])) {
                    $result['meta'] = serialize(static::getMetaInfo($file, $result['mimetype']));
                }
            }

            // The file was not found, or is unreadable:
            else {
                $message = __('The file uploaded is no longer available. Please check that it exists, and is readable.');
                $status = self::__INVALID_FIELDS__;
            }

            return $result;
        }

        if ($simulate && is_null($entry_id)) return $data;

        // Do not continue on upload error:
        if ($data['error'] == UPLOAD_ERR_NO_FILE || $data['error'] != UPLOAD_ERR_OK) {
            return false;
        }

        // Where to upload the new file?
        $abs_path = DOCROOT . '/' . trim($this->get('destination'), '/');
        $rel_path = str_replace('/workspace', '', $this->get('destination'));

        // Sanitize the filename
        $data['name'] = Lang::createFilename($data['name']);

        // If a file already exists, then rename the file being uploaded by
        // adding `_1` to the filename. If `_1` already exists, the logic
        // will keep adding 1 until a filename is available (#672)
        if (file_exists($abs_path . '/' . $data['name'])) {
            $extension = General::getExtension($data['name']);
            $new_file = substr($abs_path . '/' . $data['name'], 0, -1 - strlen($extension));
            $renamed_file = $new_file;
            $count = 1;

            do {
                $renamed_file = $new_file . '_' . $count . '.' . $extension;
                $count++;
            } while (file_exists($renamed_file));

            // Extract the name filename from `$renamed_file`.
            $data['name'] = str_replace($abs_path . '/', '', $renamed_file);
        }

        $file = $this->getFilePath($data['name']);

        // Attempt to upload the file:
        $uploaded = General::uploadFile(
            $abs_path, $data['name'], $data['tmp_name'],
            Symphony::Configuration()->get('write_mode', 'file')
        );

        if ($uploaded === false) {
            $message = __(
                'There was an error while trying to upload the file %1$s to the target directory %2$s.',
                array(
                    '<code>' . $data['name'] . '</code>',
                    '<code>workspace/' . ltrim($rel_path, '/') . '</code>'
                )
            );
            $status = self::__ERROR_CUSTOM__;

            return false;
        }

        // Get the mimetype, don't trust the browser. RE: #1609
        $data['type'] = General::getMimeType($file);

        return array(
            'file' =>       basename($file),
            'size' =>       $data['size'],
            'mimetype' =>   $data['type'],
            'meta' =>       serialize(static::getMetaInfo($file, $data['type']))
        );
    }

/*-------------------------------------------------------------------------
    Sorting:
-------------------------------------------------------------------------*/

    /**
     * This implementation can be removed when dropping Symphony 2.6.x compatibility.
     * The parent implementation can return multiple records when used with this field.
     * The core should be patched in 2.7.x
     * @param  [type] &$joins
     * @param  [type] &$where
     * @param  [type] &$sort
     * @param  string $order
     */
    public function buildSortingSQL(&$joins, &$where, &$sort, $order = 'ASC')
    {
        if (in_array(strtolower($order), array('random', 'rand'))) {
            $sort = 'ORDER BY RAND()';
        } else {
            $sort = sprintf(
                'ORDER BY (
                    SELECT DISTINCT %s
                    FROM tbl_entries_data_%d AS `ed`
                    WHERE entry_id = e.id
                    LIMIT 0, 1
                ) %s',
                '`ed`.file',
                $this->get('id'),
                $order
            );
        }
    }

/*-------------------------------------------------------------------------
    Output:
-------------------------------------------------------------------------*/

    public function displayPublishPanel(XMLElement &$wrapper, $data = null, $flagWithError = null, $fieldnamePrefix = null, $fieldnamePostfix = null, $entry_id = null)
    {
        // Styles
        if(Symphony::Engine() instanceof Administration) {
            Administration::instance()->Page->addStylesheetToHead(URL . '/extensions/multiuploadfield/assets/multiupload.publish.css', 'screen', 104, false);
            Administration::instance()->Page->addScriptToHead(URL . '/extensions/multiuploadfield/assets/multiupload.publish.js', 100, false);
        }

        // Check, does the destination exist...
        if (is_dir(DOCROOT . $this->get('destination') . '/') === false) {
            $flagWithError = __('The destination directory, %s, does not exist.', array(
                '<code>' . $this->get('destination') . '</code>'
            ));
        }

        // And is it writable?
        else if ($flagWithError && is_writable(DOCROOT . $this->get('destination') . '/') === false) {
            $flagWithError = __('Destination folder is not writable.')
                . ' '
                . __('Please check permissions on %s.', array(
                    '<code>' . $this->get('destination') . '</code>'
                ));
        }

        // Markup
        $wrapper->setAttribute('data-fieldname', 'fields' . $fieldnamePrefix . '[' . $this->get('element_name'). ']' .$fieldnamePostfix . '[]');
        $label = Widget::Label($this->get('label'));
        if($this->get('required') != 'yes') {
            $label->appendChild(new XMLElement('i', __('Optional')));
        }

        // Add error information into the field
        if ($flagWithError != null) {
            $wrapper->appendChild(Widget::Error($label, $flagWithError));
        } else {
            $wrapper->appendChild($label);
        }

        $duplicator = new XMLElement('div', null, array(
            'class' => 'frame multiupload-files'
        ));
        $files = new XMLElement('ol', null, array(
            'data-add' => __('Add file'),
            'data-remove' => __('Remove file')
        ));

        // Always ensure we are working with multiple files (even if there is one)
        if(isset($data) && is_array($data)) {
            $first = current($data);
            if(!is_array($first)) {
                $data = array($data);
            }
            else {
                $data = $this->buildFileItems($data);
            }
        }

        // Do we have an array of files, or do we have an empty array?
        if (is_array($data) && !empty($data) && $first !== false) {
            foreach($data as $file_item) {
                if(empty($file_item['file'])) continue;

                $filename = basename($file_item['file']);
                $file = $this->getFilePath($filename);
                $li = new XMLElement('li');

                if(file_exists($file) === false || !is_readable($file)) {
                    $li->setAttribute('class', 'error');
                    $header = new XMLElement('header', __('The file, %s, is no longer available. Please check that it exists, and is readable.', array('<code>' . basename($file) . '</code>')));
                }
                else {
                    $header = new XMLElement('header', Widget::Anchor(preg_replace("![^a-z0-9]+!i", "$0&#8203;", $filename), URL . $this->get('destination') . '/' . $filename));
                }

                $li->appendChild($header);
                $li->appendChild(
                    Widget::Input('fields'.$fieldnamePrefix.'['.$this->get('element_name').']'.$fieldnamePostfix . '[]', $filename, 'hidden')
                );

                $files->appendChild($li);
            }
        }

        // Add upload file
        $li = new XMLElement('li', null, array('class' => 'template queued multiupload-fallback'));
        $li->appendChild(
            Widget::Input('fields'.$fieldnamePrefix.'['.$this->get('element_name').']'.$fieldnamePostfix . '[-1]', null, 'file')
        );

        $files->appendChild($li);
        $duplicator->appendChild($files);

        $wrapper->appendChild($duplicator);
    }

    public function appendFormattedElement(XMLElement &$wrapper, $data, $encode = false, $mode = null, $entry_id = null)
    {
        $data = $this->buildFileItems($data);

        $field = new XMLElement($this->get('element_name'));

        foreach($data as $file_item) {
            // It is possible an array of NULL data will be passed in. Check for this.
            if(!is_array($file_item) || !isset($file_item['file']) || is_null($file_item['file'])){
                return;
            }

            $file = $this->getFilePath(basename($file_item['file']));
            $item = new XMLElement('file');
            $item->setAttributeArray(array(
                'size' =>   (
                                file_exists($file)
                                && is_readable($file)
                                    ? General::formatFilesize(filesize($file))
                                    : 'unknown'
                            ),
                'path' =>   General::sanitize(
                                str_replace(WORKSPACE, NULL, dirname($file))
                            ),
                'type' =>   $file_item['mimetype'],
                'extension' =>  General::getExtension($file)
            ));

            $item->appendChild(new XMLElement('filename', General::sanitize(basename($file))));

            $m = unserialize($file_item['meta']);

            if(is_array($m) && !empty($m)){
                $item->appendChild(new XMLElement('meta', NULL, $m));
            }

            $field->appendChild($item);
        }

        $wrapper->appendChild($field);

        return $wrapper;
    }

    public function prepareTableValue($data, XMLElement $link = null, $entry_id = null)
    {
        $data = $this->buildFileItems($data);
        $files = array();

        foreach($data as $file_item) {
            $result = parent::prepareTableValue($file_item, $link, $entry_id);

            if(is_string($result)) {
                $files[] = $result;
            }
            else {
                $files[] = $result->generate();
            }
        }

        return implode(', ', $files);
    }

    public function prepareReadableValue($data, $entry_id = null, $truncate = false, $defaultValue = null)
    {
        $data = $this->buildFileItems($data);
        $files = array();

        foreach ($data as $file_item) {
            $result = parent::prepareReadableValue($file_item, $entry_id, $truncate, $defaultValue);

            if (is_string($result)) {
                $files[] = $result;
            }
            else {
                $files[] = $result->generate();
            }
        }

        return implode(', ', $files);
    }

    public function getExampleFormMarkup()
    {
        $label = Widget::Label($this->get('label'));
        $label->appendChild(Widget::Input('fields['.$this->get('element_name').'][]'));

        return $label;
    }

/*-------------------------------------------------------------------------
    Import:
-------------------------------------------------------------------------*/

    public function getImportModes()
    {
        return array(
            'getPostdata' =>    ImportableField::ARRAY_VALUE,
            'getValue' =>       ImportableField::STRING_VALUE
        );
    }

    public function prepareImportValue($data, $mode, $entry_id = null)
    {
        $message = $status = null;
        $modes = (object)$this->getImportModes();

        if(!is_array($data)) $data = array($data);

        if($mode === $modes->getValue) {
            return $data;
        }
        else if($mode === $modes->getPostdata) {
            $result = array();
            $status = Field::__OK__;
            $message = '';
            $count = 0;

            foreach($data as $file) {
                $result[] = $this->processRawFieldDataIndividual($file, $status, $message, false, $entry_id, $count);
                $count++;
            }

            return $result;
        }

        return null;
    }

/*-------------------------------------------------------------------------
    Export:
-------------------------------------------------------------------------*/

    /**
     * Return a list of supported export modes for use with `prepareExportValue`.
     *
     * @return array
     */
    public function getExportModes()
    {
        return array(
            'getFilename' =>    ExportableField::VALUE,
            'getObject' =>      ExportableField::OBJECT,
            'getPostdata' =>    ExportableField::POSTDATA
        );
    }

    /**
     * Give the field some data and ask it to return a value using one of many
     * possible modes.
     *
     * @param mixed $data
     * @param integer $mode
     * @param integer $entry_id
     * @return array|string|null
     */
    public function prepareExportValue($data, $mode, $entry_id = null)
    {
        $modes = (object)$this->getExportModes();

        $file = $this->getFilePath($data['file']);

        // No file, or the file that the entry is meant to have no
        // longer exists.
        if (!isset($data['file']) || !is_file($file)) {
            return null;
        }

        if ($mode === $modes->getFilename) {
            return $file;
        }

        if ($mode === $modes->getObject) {
            $object = (object)$data;

            if (isset($object->meta)) {
                $object->meta = unserialize($object->meta);
            }

            return $object;
        }

        if ($mode === $modes->getPostdata) {
            return $data['file'];
        }
    }
}
