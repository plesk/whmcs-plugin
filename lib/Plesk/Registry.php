<?php
// Copyright 1999-2016. Parallels IP Holdings GmbH.

class Plesk_Registry
{
	private static $_instance = null;
	
    private $_instances = array();
    
    public static function getInstance()
    {
    	if (is_null(self::$_instance)) {
    	   self::$_instance = new self();
    	}
        return self::$_instance;
    }
    
    function __get($name)
    {
        if (isset($this->_instances[$name])) {
            return $this->_instances[$name];
        } else {
            throw new Exception('There is no object "' . $name . '" in the registry.');
        }
    }
    
    function __set($name, $value)
    {
        $this->_instances[$name] = $value;
    }
    
    function __isset($name)
    {
        return isset($this->_instances[$name]) ? true : false;
    }
}
