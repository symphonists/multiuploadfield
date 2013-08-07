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
				//parent::entryDataCleanup($entry_id, $file);
			}

			return Field::entryDataCleanup($entry_id);
		}
		
		
		/**
		 * Given an array of POST data, transform it for the Database
		 */
		private function buildAssociativeFiles($nested_array) {
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
		private function buildFileItems($nested_array) {
			$final_result = array();
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

	/*-------------------------------------------------------------------------
		Publish:
	-------------------------------------------------------------------------*/

		/**
		 * Majority of this function is taken from the core Upload field.
		 * Note to self. Make core Upload field easier to extend
		 */
		public function checkPostFieldData($data, &$message, $entry_id = null) {
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

			return $status;
		}

		public function processRawFieldData($data, &$status, &$message=null, $simulate = false, $entry_id = null) {
			$status = Field::__OK__;
			$full_result = array();

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
		
		public function processRawFieldDataIndividual($data, &$status, &$message=null, $simulate = false, $entry_id = null, $position) {
			$status = self::__OK__;

			// No file given, save empty data:
			if ($data === null) {
				return array(
					'file' =>		null,
					'mimetype' =>	null,
					'size' =>		null,
					'meta' =>		null
				);
			}

			// Its not an array, so just retain the current data and return:
			if (is_array($data) === false) {
				$file = $this->getFilePath(basename($data));

				$result = array(
					'file' =>		$data,
					'mimetype' =>	null,
					'size' =>		null,
					'meta' =>		null
				);

				// Grab the existing entry data to preserve the MIME type and size information
				if (isset($entry_id) && $position !== -1) {
					$row = Symphony::Database()->fetchRow(0, sprintf(
						"SELECT `file`, `mimetype`, `size`, `meta` FROM `tbl_entries_data_%d` WHERE `entry_id` = %d ORDER BY `id` LIMIT %d, 1",
						$this->get('id'),
						$entry_id,
						$position
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
						$result['meta'] = serialize(self::getMetaInfo($file, $result['mimetype']));
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

			// Check to see if the entry already has a file associated with it:
			if (is_null($entry_id) === false && $position !== -1) {
				$row = Symphony::Database()->fetchRow(0, sprintf(
					"SELECT * FROM `tbl_entries_data_%s` WHERE `entry_id` = %d ORDER BY `id` LIMIT %d, 1",
					$this->get('id'),
					$entry_id,
					$position
				));

				$existing_file = isset($row['file'])
					? $this->getFilePath($row['file'])
					: null;

				// File was removed:
				if (
					$data['error'] == UPLOAD_ERR_NO_FILE
					&& !is_null($existing_file)
					&& is_file($existing_file)
				) {
					General::deleteFile($existing_file);
				}
			}

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

			// File has been replaced:
			if (
				isset($existing_file)
				&& $existing_file !== $file
				&& is_file($existing_file)
			) {
				General::deleteFile($existing_file);
			}

			// Get the mimetype, don't trust the browser. RE: #1609
			$data['type'] = General::getMimeType($file);

			return array(
				'file' =>		basename($file),
				'size' =>		$data['size'],
				'mimetype' =>	$data['type'],
				'meta' =>		serialize(self::getMetaInfo($file, $data['type']))
			);
		}

	/*-------------------------------------------------------------------------
		Output:
	-------------------------------------------------------------------------*/

		public function displayPublishPanel(XMLElement &$wrapper, $data = null, $flagWithError = null, $fieldnamePrefix = null, $fieldnamePostfix = null, $entry_id = null) {

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
			$label = Widget::Label($this->get('label'));
			if($this->get('required') != 'yes') $label->appendChild(new XMLElement('i', __('Optional')));
			$wrapper->appendChild($label);

			$duplicator = new XMLElement('div', null, array(
				'class' => 'frame multiupload-duplicator')
			);
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

					$filename = $this->get('destination') . '/' . basename($file_item['file']);
					$file = $this->getFilePath($file_item['file']);

					if (file_exists($file) === false || !is_readable($file)) {
						$flagWithError = __('The file, %s, is no longer available. Please check that it exists, and is readable.', array('<code>' . basename($file) . '</code>'));
					}
					
					$li = new XMLElement('li');

					$li->appendChild(
						new XMLElement('header', 
							new XMLElement('span', 
								Widget::Anchor(preg_replace("![^a-z0-9]+!i", "$0&#8203;", basename($file_item['file'])), URL . $filename)
							)
						)
					);
					$li->appendChild(
						Widget::Input('fields'.$fieldnamePrefix.'['.$this->get('element_name').']'.$fieldnamePostfix . '[]', $filename, 'hidden')
					);

					$files->appendChild($li);
				}
			}
			
			// Add upload file
			$li = new XMLElement('li', '<header><span>' . __('Waiting for file') . ' …</span></header>', array('class' => 'template local'));
			$li->appendChild(
				Widget::Input('fields'.$fieldnamePrefix.'['.$this->get('element_name').']'.$fieldnamePostfix . '[-1]', null, 'file')
			);

			$files->appendChild($li);
			$duplicator->appendChild($files);

			if($flagWithError != NULL) $wrapper->appendChild(Widget::Error($duplicator, $flagWithError));
			else $wrapper->appendChild($duplicator);
		}

		public function appendFormattedElement(XMLElement &$wrapper, $data, $encode = false, $mode = null, $entry_id = null) {
			$data = $this->buildFileItems($data);
			
			$field = new XMLElement($this->get('element_name'));
			
			foreach($data as $file_item) {
				// It is possible an array of NULL data will be passed in. Check for this.
				if(!is_array($file_item) || !isset($file_item['file']) || is_null($file_item['file'])){
					return;
				}

				$file = $this->getFilePath($file_item['file']);
				$item = new XMLElement('file');
				$item->setAttributeArray(array(
					'size' =>	(
									file_exists($file)
									&& is_readable($file)
										? General::formatFilesize(filesize($file))
										: 'unknown'
								),
				 	'path' =>	General::sanitize(
									str_replace(WORKSPACE, NULL, dirname($file))
				 				),
					'type' =>	$file_item['mimetype']
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

		public function prepareTableValue($data, XMLElement $link=NULL, $entry_id = null) {
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

			if(!is_array($data)) $data = array($data);

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
