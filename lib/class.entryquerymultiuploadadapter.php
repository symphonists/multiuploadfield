<?php

/**
 * @package toolkit
 */
/**
 * Specialized EntryQueryFieldAdapter that facilitate creation of queries filtering/sorting data from
 * an Multi Upload Field.
 * @see FieldMultiUpload
 * @since Symphony 3.0.0
 */
class EntryQueryMultiUploadAdapter extends EntryQueryUploadAdapter
{
    public function getFilterColumns()
    {
        $lc = FLang::getLangCode();

        if ($lc) {
            return ["file-$lc", "size-$lc", "mimetype-$lc"];
        }

        return parent::getFilterColumns();
    }

    public function getSortColumns()
    {
        $lc = FLang::getLangCode();

        if ($lc) {
            return ["file-$lc"];
        }

        return parent::getSortColumns();
    }
}
