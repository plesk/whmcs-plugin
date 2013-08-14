<?php
// Copyright 1999-2016. Parallels IP Holdings GmbH.

class Plesk_Translate
{
    private $_keys = array();

    public function __construct()
    {
        $dir = realpath(dirname(__FILE__) . "/../../lang");

        $englishFile = $dir . "/english.php";
        $currentFile = $dir . "/" . $this->_getLanguage() . ".php";

        if (file_exists($englishFile)) {
            require_once $englishFile;
            $this->_keys = $keys;
        }

        if (file_exists($currentFile)) {
            require_once $currentFile;
            $this->_keys = array_merge($this->_keys, $keys);
        }
    }

    public function translate($msg, $placeholders = array())
    {
        if (isset($this->_keys[$msg])) {
            $msg = $this->_keys[$msg];
            foreach ($placeholders as $key => $val)
                $msg = str_replace("@{$key}@", $val, $msg);
        }

        return $msg;
    }

    private function _getLanguage()
    {
        $language = "english";
        if (isset($GLOBALS[ 'CONFIG' ][ 'Language' ])) {
            $language = $GLOBALS[ 'CONFIG' ][ 'Language' ];
        }
        if (isset($_SESSION["adminid"])) {
            $language = $this->_getUserLanguage('tbladmins', 'adminid');
        } elseif ($_SESSION["uid"]) {
            $language = $this->_getUserLanguage('tblclients', 'uid');
        }

        return strtolower($language);
    }

    private function _getUserLanguage($table, $field)
    {
        $sqlresult = select_query(
            $table,
            'language',
            array(
                'id' => mysql_real_escape_string($_SESSION[$field]),
            )
        );
        while ($data = mysql_fetch_row($sqlresult)) {
            return reset($data);
        }
        return '';
    }
}
