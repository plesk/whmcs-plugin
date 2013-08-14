<?php
// Copyright 1999-2016. Parallels IP Holdings GmbH.

class Plesk_Manager_V1635 extends Plesk_Manager_V1632
{
    protected function _createSession($params)
    {
        $ownerInfo = $this->_getAccountInfo($params);
        if (!isset($ownerInfo['login'])) {
            return null;
        }
        $result = Plesk_Registry::getInstance()->api->session_create(
            array(
                'login' => $ownerInfo['login'],
                'userIp' => base64_encode($_SERVER['REMOTE_ADDR']),
            )
        );

        return $result->server->create_session->result->id;
    }

    protected function _getClientAreaForm($params)
    {
        $address = ($params['serverhostname']) ? $params['serverhostname'] : $params['serverip'];
        $port = ($params["serveraccesshash"]) ? $params["serveraccesshash"] : '8443';
        $secure = ($params["serversecure"]) ? 'https' : 'http';
        if (empty($address)) {
            return '';
        }

        $sessionId = $this->_createSession($params);
        if (is_null($sessionId)) {
            return '';
        }

        $form = sprintf(
            '<form action="%s://%s:%s/enterprise/rsession_init.php" method="post" target="_blank">' .
            '<input type="hidden" name="PLESKSESSID" value="%s" />' .
            '<input type="hidden" name="PHPSESSID" value="%s" />' .
            '<input type="submit" value="%s" />' .
            '</form>',
            $secure,
            WHMCS\Input\Sanitize::encode($address),
            WHMCS\Input\Sanitize::encode($port),
            WHMCS\Input\Sanitize::encode($sessionId),
            WHMCS\Input\Sanitize::encode($sessionId),
            Plesk_Registry::getInstance()->translator->translate('BUTTON_CONTROL_PANEL')
        );

        return $form;
    }
}
