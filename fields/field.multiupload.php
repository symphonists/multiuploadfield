<?php

	if (!defined('__IN_SYMPHONY__')) die('<h2>Symphony Error</h2><p>You cannot directly access this file</p>');

	require_once(TOOLKIT . '/fields/field.upload.php');

	class FieldMultiUpload extends FieldUpload {

		public function __construct(){
			parent::__construct();

			$this->_name = __('Multi File Upload');
		}

	/*-------------------------------------------------------------------------
		Setup:
	-------------------------------------------------------------------------*/

		public function createTable(){
			return Symphony::Database()->query("
				CREATE TABLE IF NOT EXISTS `tbl_entries_data_" . $this->get('id') . "` (
				  `id` int(11) unsigned NOT NULL auto_increment,
				  `entry_id` int(11) unsigned NOT NULL,
				  `file` varchar(255) default NULL,
				  `size` int(11) unsigned NULL,
				  `mimetype` varchar(100) default NULL,
				  `meta` TEXT default NULL,
				  PRIMARY KEY  (`id`),
				  KEY `entry_id` (`entry_id`),
				  KEY `file` (`file`),
				  KEY `mimetype` (`mimetype`)
				) ENGINE=MyISAM DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci;
			");
		}

	/*-------------------------------------------------------------------------
		Utilities:
	-------------------------------------------------------------------------*/

		public function entryDataCleanup($entry_id, $data=NULL){
			foreach($data as $file) {
				parent::entryDataCleanup($entry_id, $file);
			}

			return Field::entryDataCleanup($entry_id);
		}

	/*-------------------------------------------------------------------------
		Publish:
	-------------------------------------------------------------------------*/

		/**
		 * Majority of this function is taken from the core Upload field.
		 * Note to self. Make core Upload field easier to extend
		 */
		public function checkPostFieldData($data, &$message, $entry_id = null) {
			return parent::checkPostFieldData($data, $message, $entry_id);
		}

		public function processRawFieldData($data, &$status, &$message=null, $simulate = false, $entry_id = null) {
			return parent::processRawFieldData($data, &$status, &$message, $simulate, $entry_id);
		}

	/*-------------------------------------------------------------------------
		Output:
	-------------------------------------------------------------------------*/

		public function displayPublishPanel(XMLElement &$wrapper, $data = null, $flagWithError = null, $fieldnamePrefix = null, $fieldnamePostfix = null, $entry_id = null) {

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

			$label = Widget::Label($this->get('label'));
			$label->setAttribute('class', 'file');
			if($this->get('required') != 'yes') $label->appendChild(new XMLElement('i', __('Optional')));

			$span = new XMLElement('span', NULL, array('class' => 'frame'));
			$files = new XMLElement('ul');

			if (is_array($data) && !empty($data)) {
				foreach($data as $file_item) {
					$filename = $this->get('destination') . '/' . basename($data['file']);
					$file = $this->getFilePath($data['file']);

					if (file_exists($file) === false || !is_readable($file)) {
						$flagWithError = __('The file uploaded is no longer available. Please check that it exists, and is readable.');
					}

					$li = new XMLElement('li', Widget::Anchor(preg_replace("![^a-z0-9]+!i", "$0&#8203;", $filename), URL . $filename));
					$li->appendChild(
						Widget::Input('fields'.$fieldnamePrefix.'['.$this->get('element_name').']'.$fieldnamePostfix . '[]', $filename, 'hidden')
					);

					$files->appendChild($li);
				}
			}
			else {
				$li = new XMLElement('li');
				$li->appendChild(
					Widget::Input('fields'.$fieldnamePrefix.'['.$this->get('element_name').']'.$fieldnamePostfix . '[]', null, 'file')
				);

				$files->appendChild($li);
			}

			$span->appendChild($files);
			$label->appendChild($span);

			if($flagWithError != NULL) $wrapper->appendChild(Widget::Error($label, $flagWithError));
			else $wrapper->appendChild($label);
		}

		public function appendFormattedElement(XMLElement &$wrapper, $data, $encode = false, $mode = null, $entry_id = null) {
			return parent::appendFormattedElement($wrapper, $data, $encode, $mode, $entry_id);
		}

		public function prepareTableValue($data, XMLElement $link=NULL, $entry_id = null) {
			return parent::prepareTableValue($data, $link, $entry_id);
		}

	/*-------------------------------------------------------------------------
		Import:
	-------------------------------------------------------------------------*/

		public function getImportModes() {
			return array(
				'getValue' =>		ImportableField::STRING_VALUE,
				'getPostdata' =>	ImportableField::ARRAY_VALUE
			);
		}

		public function prepareImportValue($data, $mode, $entry_id = null) {
			$message = $status = null;
			$modes = (object)$this->getImportModes();

			if($mode === $modes->getValue) {
				return $data;
			}
			else if($mode === $modes->getPostdata) {
				return $this->processRawFieldData($data, $status, $message, true, $entry_id);
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
		public function getExportModes() {
			return array(
				'getFilename' =>	ExportableField::VALUE,
				'getObject' =>		ExportableField::OBJECT,
				'getPostdata' =>	ExportableField::POSTDATA
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
		public function prepareExportValue($data, $mode, $entry_id = null) {
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
