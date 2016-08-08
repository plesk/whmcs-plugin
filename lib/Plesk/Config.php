<?php
// Copyright 1999-2016. Parallels IP Holdings GmbH.

class Plesk_Config
{
    private static $_settings;

    private static function _init()
    {
        if (!is_null(static::$_settings)) {
            return;
        }
        static::$_settings = json_decode(json_encode(
            array_merge(static::getDefaults(), static::_getConfigFileSettings())
        ));
    }

    /**
     * @static
     * @return stdClass
     */
    public static function get()
    {
        self::_init();
        return static::$_settings;
    }


    /** Returns unmodified config's default values
     * @return array
     */
    public static function getDefaults()
    {
        return [
            'account_limit' => 0
        ];
    }

    /**
     * Override settings with panel.ini values
     *
     * @return array
     */
    private static function _getConfigFileSettings()
    {
        $filename = dirname(dirname(dirname(__FILE__))) . "/config.ini";
        if (!file_exists($filename)) {
            return [];
        }

        $result = parse_ini_file($filename, true);
        return !$result ? [] : $result;
    }
}
