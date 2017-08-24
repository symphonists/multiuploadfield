<?php

class extension_multiuploadfield extends Extension {
    public function install()
    {
        return Symphony::Database()->query("
            CREATE TABLE `tbl_fields_multiupload` (
              `id` INT(11) UNSIGNED NOT NULL AUTO_INCREMENT,
              `field_id` INT(11) UNSIGNED NOT NULL,
              `destination` VARCHAR(255) COLLATE utf8_unicode_ci NOT NULL,
              `validator` VARCHAR(255) COLLATE utf8_unicode_ci DEFAULT NULL,
              PRIMARY KEY (`id`),
              KEY `field_id` (`field_id`)
            ) ENGINE=MyISAM  DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci;
        ");
    }

    public function update($previousVersion = null)
    {
    }

    public function uninstall()
    {
        Symphony::Database()->query("DROP TABLE `tbl_fields_multiupload`");
    }
}
