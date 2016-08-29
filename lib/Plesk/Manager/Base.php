<?php
// Copyright 1999-2016. Parallels IP Holdings GmbH.
use Illuminate\Database\Capsule\Manager as Capsule;

abstract class Plesk_Manager_Base
{

    public function __call($name, $args)
    {
	    // call requested method, if it exist
	    $methodName = '_' . $name;
	    if (!method_exists($this, $methodName)) {
            throw new Exception(Plesk_Registry::getInstance()->translator->translate('ERROR_NO_TEMPLATE_TO_API_VERSION', array('METHOD' => $methodName, 'API_VERSION' => $this->getVersion())));
	    }
	    
	    // set appropriate api version
	    $reflection = new ReflectionClass(get_class($this));
	    $declaringClassName = $reflection->getMethod($methodName)->getDeclaringClass()->name;
	    $declaringClass = new $declaringClassName();
	    $version = $declaringClass->getVersion();
	    
	    $currentApiVersion = isset(Plesk_Registry::getInstance()->version) ? Plesk_Registry::getInstance()->version : null;
        Plesk_Registry::getInstance()->version = $version;
	    
	    $result = call_user_func_array(array($this, $methodName), $args);
	    
	    Plesk_Registry::getInstance()->version = $currentApiVersion;
	    
	    return $result;
	}
	
    public function getVersion()
    {
        $className = get_class($this);
        return implode('.', str_split(substr($className, strrpos($className, 'V') + 1)));
    }

    public function createTableForAccountStorage()
    {
        if (Capsule::schema()->hasTable('mod_pleskaccounts')) {
            return;
        }

        Capsule::schema()->create(
            'mod_pleskaccounts',
            function ($table) {
                $table->engine = 'MyISAM';

                /** @var \Illuminate\Database\Schema\Blueprint $table */
                $table->integer('userid');
                $table->string('usertype');
                $table->string('panelexternalid');

                $table->primary('userid');
                $table->index('usertype');
                $table->unique('panelexternalid');
            }
        );
    }

    protected function _checkErrors($result)
    {
        if (Plesk_Api::STATUS_OK == (string)$result->status) {
            return;
        }
        switch ((int)$result->errcode) {
            case Plesk_Api::ERROR_AUTHENTICATION_FAILED:
                $errorMessage = Plesk_Registry::getInstance()->translator->translate('ERROR_AUTHENTICATION_FAILED');
                break;
            case Plesk_Api::ERROR_AGENT_INITIALIZATION_FAILED:
                $errorMessage = Plesk_Registry::getInstance()->translator->translate('ERROR_AGENT_INITIALIZATION_FAILED');
                break;
            default:
                $errorMessage = (string)$result->errtext;
                break;
        }

        throw new Exception( $errorMessage, (int)$result->errcode);
    }
}
