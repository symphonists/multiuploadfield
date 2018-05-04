<?php

class extension_multiuploadfield extends Extension {
    public function install()
    {
        return Symphony::Database()->query("
            CREATE TABLE `tbl_fields_multiupload` (
              `id` int(11) unsigned NOT NULL AUTO_INCREMENT,
              `field_id` int(11) unsigned NOT NULL,
              `destination` varchar(255) COLLATE utf8_unicode_ci NOT NULL,
              `validator` varchar(255) COLLATE utf8_unicode_ci DEFAULT NULL,
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
