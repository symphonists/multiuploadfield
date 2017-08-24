<?php

    define('DOCROOT', rtrim(realpath(__DIR__ . '/../../../'), '/'));
    define('DOMAIN', rtrim(rtrim($_SERVER['HTTP_HOST'], '/') . str_replace('/extensions/multiuploadfield/lib', null, dirname($_SERVER['PHP_SELF'])), '/'));

    // Is there vendor autoloader?
    require_once DOCROOT . '/vendor/autoload.php';
    require_once DOCROOT . '/symphony/lib/boot/bundle.php';

    $instance = Administration::instance();
    $entry_id = null;
    $position = null;

    // Get the field ID that's uploading
    if(!isset($_REQUEST['field-id'])) {
        header("HTTP/1.0 400 Bad Request", true, 400);
        exit;
    }
    else {
        $field_id = (int)$_REQUEST['field-id'];
        $entry_id = (int)$_REQUEST['entry-id'];
        $position = (int)$_REQUEST['position'];

        if($entry_id <= 0) {
            $entry_id = null;
        }
    }

    // Upload the file
    $field = FieldManager::fetch($field_id);
    if(!($field instanceof FieldMultiUpload)) {
        header("HTTP/1.0 400 Bad Request", true, 400);
        exit;
    }
    else {
        $message = '';
        $data = $_FILES['file'];
        // Do upload
        $result = $field->processRawFieldDataIndividual($data, $status, $message, false, $entry_id, $position);

        // output back to browser..
        if(is_array($result)) {
            header("HTTP/1.0 201 Created", true, 201);
            header("Content-Type: application/json");

            echo json_encode(array(
                'url' => str_replace(WORKSPACE, URL . '/workspace', $field->getFilePath($result['file'])),
                'size' => $result['size'],
                'mimetype' => $result['mimetype'],
                'meta' => unserialize($result['meta'])
            ));
        }
        else {
            header("HTTP/1.0 400 Bad Request", true, 400);
            header("Content-Type: application/json");

            echo json_encode(array(
                'error' => $message
            ));
        }
    }
