<?php

class extension_multiuploadfield extends Extension {
    public function install()
    {
        return Symphony::Database()
            ->create('tbl_fields_multiupload')
            ->ifNotExists()
            ->charset('utf8')
            ->collate('utf8_unicode_ci')
            ->fields([
                'id' => [
                    'type' => 'int(11)',
                    'auto' => true,
                ],
                'field_id' => 'int(11)',
                'destination' => 'varchar(255)',
                'validator' => [
                    'type' => 'varchar(255)',
                    'null' => true,
                ],
            ])
            ->keys([
                'id' => 'primary',
                'field_id' => 'key',
            ])
            ->execute()
            ->success();
    }

    public function update($previousVersion = null)
    {
    }

    public function uninstall()
    {
        return Symphony::Database()
            ->drop('tbl_fields_multiupload')
            ->ifExists()
            ->execute()
            ->success();
    }
}
