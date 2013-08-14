<?php
// Copyright 1999-2016. Parallels IP Holdings GmbH.

require_once 'lib/Plesk/Loader.php';

function plesk_MetaData() {
    return array(
        'DisplayName' => 'Plesk V8+',
        'APIVersion' => '1.1',
    );
}

/**
 * @return array
 */
function plesk_ConfigOptions($params)
{
    require_once 'lib/Plesk/Translate.php';
    $translator = new Plesk_Translate();

    $configarray = array(
        "servicePlanName" => array(
            "FriendlyName" => $translator->translate("CONFIG_SERVICE_PLAN_NAME"),
            "Type" => "text",
            "Size" => "25"
        ),
        "resellerPlanName" => array(
            "FriendlyName" => $translator->translate("CONFIG_RESELLER_PLAN_NAME"),
            "Type" => "text",
            "Size" => "25"
        ),
        "ipAdresses" => array (
            "FriendlyName" => $translator->translate("CONFIG_WHICH_IP_ADDRESSES"),
            "Type" => "dropdown",
            "Options" => "IPv4 shared; IPv6 none,IPv4 dedicated; IPv6 none,IPv4 none; IPv6 shared,IPv4 none; IPv6 dedicated,IPv4 shared; IPv6 shared,IPv4 shared; IPv6 dedicated,IPv4 dedicated; IPv6 shared,IPv4 dedicated; IPv6 dedicated",
            "Default" => "IPv4 shared; IPv6 none",
            "Description" => "",
        ),
        "powerUser" => array(
            "FriendlyName" => $translator->translate("CONFIG_POWER_USER_MODE"),
            "Type" => "yesno",
            "Description" => $translator->translate("CONFIG_POWER_USER_MODE_DESCRIPTION"),
        ),

    );

    return $configarray;
}

/**
 * @param $params
 * @return string
 */
function plesk_AdminLink($params)
{
    $address = ($params['serverhostname']) ? $params['serverhostname'] : $params['serverip'];
    $port = ($params["serveraccesshash"]) ? $params["serveraccesshash"] : '8443';
    $secure = ($params["serversecure"]) ? 'https' : 'http';
    if (empty($address)) {
        return '';
    }

    $form = sprintf(
        '<form action="%s://%s:%s/login_up.php3" method="post" target="_blank">' .
        '<input type="hidden" name="login_name" value="%s" />' .
        '<input type="hidden" name="passwd" value="%s" />' .
        '<input type="submit" value="%s">' .
        '</form>',
        $secure,
        WHMCS\Input\Sanitize::encode($address),
        WHMCS\Input\Sanitize::encode($port),
        WHMCS\Input\Sanitize::encode($params["serverusername"]),
        WHMCS\Input\Sanitize::encode($params["serverpassword"]),
        'Login to panel'
    );

    return $form;
}

/**
 * @param $params
 * @return string
 */
function plesk_ClientArea($params) {
    try {
        Plesk_Loader::init($params);
        return Plesk_Registry::getInstance()->manager->getClientAreaForm($params);

    } catch (Exception $e) {
        return Plesk_Registry::getInstance()->translator->translate('ERROR_COMMON_MESSAGE', array('CODE' => $e->getCode(), 'MESSAGE' => $e->getMessage()));
    }
}

/**
 * Create panel reseller or customer with webspace. If customer exists function add webspace to him.
 * @param $params
 * @return string
 */
function plesk_CreateAccount($params) {

    try {

        Plesk_Loader::init($params);
        $translator = Plesk_Registry::getInstance()->translator;

        if ("" == $params['clientsdetails']['firstname'] && "" == $params['clientsdetails']['lastname']) {
            return $translator->translate('ERROR_ACCOUNT_VALIDATION_EMPTY_FIRST_OR_LASTNAME');
        } elseif ("" == $params["username"]) {
            return $translator->translate('ERROR_ACCOUNT_VALIDATION_EMPTY_USERNAME');
        }

        Plesk_Registry::getInstance()->manager->createTableForAccountStorage();
        $sqlresult = select_query(
            'mod_pleskaccounts',
            'panelexternalid',
            array(
                "userid" => $params['clientsdetails']['userid'],
                "usertype" => $params['type'],
            )
        );
        $panelExternalId = '';
        while ($data = mysql_fetch_row($sqlresult)) {
            $panelExternalId = reset($data);
        }

        $accountId = null;
        try{
            $accountInfo = Plesk_Registry::getInstance()->manager->getAccountInfo($params, $panelExternalId);
            if (isset($accountInfo['id'])) {
                $accountId = $accountInfo['id'];
            }
        } catch (Exception $e) {
            if (Plesk_Api::ERROR_OBJECT_NOT_FOUND != $e->getCode()) {
                throw $e;
            }
        }

        if (!is_null($accountId) && Plesk_Object_Customer::TYPE_RESELLER == $params['type']) {
            return $translator->translate('ERROR_RESELLER_ACCOUNT_IS_ALREADY_EXISTS', array('EMAIL' => $params['clientsdetails']['email']));
        }

        $params = array_merge($params, Plesk_Registry::getInstance()->manager->getIps($params));
        if (is_null($accountId)) {
            try {
                $accountId = Plesk_Registry::getInstance()->manager->addAccount($params);
            } catch (Exception $e) {
                if (Plesk_Api::ERROR_OPERATION_FAILED == $e->getCode()) {
                    return $translator->translate('ERROR_ACCOUNT_CREATE_COMMON_MESSAGE');
                }
                throw $e;
            }
        }
        Plesk_Registry::getInstance()->manager->addIpToIpPool($accountId, $params);

        if ('' == $panelExternalId && '' != ($possibleExternalId = Plesk_Registry::getInstance()->manager->getCustomerExternalId($params['clientsdetails']['userid']))) {
            insert_query('mod_pleskaccounts',
                array(
                    'userid' => $params['clientsdetails']['userid'],
                    'usertype' => $params['type'],
                    'panelexternalid' => $possibleExternalId
                )
            );
        }

        if (!is_null($accountId) && Plesk_Object_Customer::TYPE_RESELLER == $params['type']) {
            return 'success';
        }

        $params['ownerId'] = $accountId;
        Plesk_Registry::getInstance()->manager->addWebspace($params);

        if (!empty($params['configoptions'])) {
            Plesk_Registry::getInstance()->manager->processAddons($params);
        }

        return 'success';
    } catch (Exception $e) {
        return Plesk_Registry::getInstance()->translator->translate('ERROR_COMMON_MESSAGE', array('CODE' => $e->getCode(), 'MESSAGE' => $e->getMessage()));
    }
}

/**
 * Suspend reseller account or customer's subscription (webspace)
 * @param $params
 * @return string
 */
function plesk_SuspendAccount($params) {

    try {
        Plesk_Loader::init($params);
        $params['status'] = ('root' != $params['serverusername'] && 'admin' != $params['serverusername']) ? Plesk_Object_Customer::STATUS_SUSPENDED_BY_RESELLER : Plesk_Object_Customer::STATUS_SUSPENDED_BY_ADMIN ;

        switch ($params['type']) {
            case Plesk_Object_Customer::TYPE_CLIENT:
                Plesk_Registry::getInstance()->manager->setWebspaceStatus($params);
                break;
            case Plesk_Object_Customer::TYPE_RESELLER:
                Plesk_Registry::getInstance()->manager->setResellerStatus($params);
                break;
        }
        return 'success';

    } catch (Exception $e) {
        return Plesk_Registry::getInstance()->translator->translate('ERROR_COMMON_MESSAGE', array('CODE' => $e->getCode(), 'MESSAGE' => $e->getMessage()));
    }

}

/**
 * Unsuspend reseller account or customer's subscription (webspace)
 * @param $params
 * @return string
 */
function plesk_UnsuspendAccount($params) {

    try {
        Plesk_Loader::init($params);
        switch ($params['type']) {
            case Plesk_Object_Customer::TYPE_CLIENT:
                $params["status"] = Plesk_Object_Webspace::STATUS_ACTIVE;
                Plesk_Registry::getInstance()->manager->setWebspaceStatus($params);
                break;
            case Plesk_Object_Customer::TYPE_RESELLER:
                $params["status"] = Plesk_Object_Customer::STATUS_ACTIVE;
                Plesk_Registry::getInstance()->manager->setResellerStatus($params);
                break;
        }
        return 'success';

    } catch (Exception $e) {
        return Plesk_Registry::getInstance()->translator->translate('ERROR_COMMON_MESSAGE', array('CODE' => $e->getCode(), 'MESSAGE' => $e->getMessage()));
    }

}

/**
 * Delete webspace or reseller from Panel
 * @param $params
 * @return string
 */
function plesk_TerminateAccount($params) {

    try {
        Plesk_Loader::init($params);
        switch ($params['type']) {
            case Plesk_Object_Customer::TYPE_CLIENT:
                Plesk_Registry::getInstance()->manager->deleteWebspace($params);
                break;
            case Plesk_Object_Customer::TYPE_RESELLER:
                Plesk_Registry::getInstance()->manager->deleteReseller($params);
                break;
        }
        return 'success';

    } catch (Exception $e) {
        return Plesk_Registry::getInstance()->translator->translate('ERROR_COMMON_MESSAGE', array('CODE' => $e->getCode(), 'MESSAGE' => $e->getMessage()));
    }
}

/**
 * @param $params
 * @return string
 */
function plesk_ChangePassword($params) {

    try {
        Plesk_Loader::init($params);
        Plesk_Registry::getInstance()->manager->setAccountPassword($params);
        if (Plesk_Object_Customer::TYPE_RESELLER == $params['type']) {
            return 'success';
        }

        Plesk_Registry::getInstance()->manager->setWebspacePassword($params);
        return 'success';
    } catch (Exception $e) {
        return Plesk_Registry::getInstance()->translator->translate('ERROR_COMMON_MESSAGE', array('CODE' => $e->getCode(), 'MESSAGE' => $e->getMessage()));
    }
}

function plesk_AdminServicesTabFields($params) {



    try {
        Plesk_Loader::init($params);
        $translator = Plesk_Registry::getInstance()->translator;
        $accountInfo = Plesk_Registry::getInstance()->manager->getAccountInfo($params);
        if (!isset($accountInfo['login'])) {
            return array();
        }

        if ($accountInfo['login'] == $params["username"]) {
            return array('' => $translator->translate('FIELD_CHANGE_PASSWORD_MAIN_PACKAGE_DESCR'));
        }

        $sqlresult = select_query("tblhosting","domain",
            array(
                "username" => $accountInfo['login'],
                "userid" => $params['clientsdetails']['userid'],
            )
        );
        $domain = '';
        while ($data = mysql_fetch_row($sqlresult)) {
            $domain = reset($data);
        }
        return array('' => $translator->translate('FIELD_CHANGE_PASSWORD_ADDITIONAL_PACKAGE_DESCR', array('PACKAGE' => $domain)));

    } catch (Exception $e) {
        return Plesk_Registry::getInstance()->translator->translate('ERROR_COMMON_MESSAGE', array('CODE' => $e->getCode(), 'MESSAGE' => $e->getMessage()));
    }
}

/**
 * @param $params
 * @return string
 */
function plesk_ChangePackage($params) {
    try {
        Plesk_Loader::init($params);
        $params = array_merge($params, Plesk_Registry::getInstance()->manager->getIps($params));

        Plesk_Registry::getInstance()->manager->switchSubscription($params);
        if (Plesk_Object_Customer::TYPE_RESELLER == $params['type']) {
            return 'success';
        }
        Plesk_Registry::getInstance()->manager->processAddons($params);
        Plesk_Registry::getInstance()->manager->changeSubscriptionIp($params);

        return 'success';

    } catch (Exception $e) {
        return Plesk_Registry::getInstance()->translator->translate('ERROR_COMMON_MESSAGE', array('CODE' => $e->getCode(), 'MESSAGE' => $e->getMessage()));
    }
}

/**
 * @param $params
 * @return string
 */
function plesk_UsageUpdate($params) {

    $sqlresult = select_query("tblhosting","domain",array("server"=>$params["serverid"]));
    $domains = array();
    while ($data = mysql_fetch_row($sqlresult)) {
       $domains[] = reset($data);
    }
    $params["domains"] = $domains;
    try {
        Plesk_Loader::init($params);
        $domainsUsage = Plesk_Registry::getInstance()->manager->getWebspacesUsage($params);
    } catch (Exception $e) {
        return Plesk_Registry::getInstance()->translator->translate('ERROR_COMMON_MESSAGE', array('CODE' => $e->getCode(), 'MESSAGE' => $e->getMessage()));
    }

    foreach ( $domainsUsage as $domainName => $usage ) {
        update_query(
            "tblhosting",
            array(
                "diskusage" => $usage['diskusage'],
                "disklimit" => $usage['disklimit'],
                "bwusage" => $usage['bwusage'],
                "bwlimit" => $usage['bwlimit'],
                "lastupdate" => "now()",
            ),
            array(
                "server" => $params['serverid'],
                "domain" => $domainName
            )
        );
    }

    return 'success';
}

function plesk_TestConnection($params) {
    try {
        Plesk_Loader::init($params);
        $translator = Plesk_Registry::getInstance()->translator;
        return array(
            'success' => true,
        );
    } catch (Exception $e) {
        return array(
            'error' => Plesk_Registry::getInstance()->translator->translate('ERROR_COMMON_MESSAGE', array('CODE' => $e->getCode(), 'MESSAGE' => $e->getMessage())),
        );
    }
}
