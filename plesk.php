<?php
// Copyright 1999-2016. Parallels IP Holdings GmbH.

require_once 'lib/Plesk/Loader.php';

use Carbon\Carbon;
use WHMCS\Database\Capsule;
use WHMCS\Input\Sanitize;
use WHMCS\Service\Addon;
use WHMCS\Service\Service;

function plesk_MetaData() {
    return array(
        'DisplayName' => 'Plesk',
        'APIVersion' => '1.1',
    );
}

/**
 * @param array $params
 * @return array
 */
function plesk_ConfigOptions(array $params)
{
    require_once 'lib/Plesk/Translate.php';
    $translator = new Plesk_Translate();

    $resellerSimpleMode = ($params['producttype'] == 'reselleraccount');

    $configarray = array(
        "servicePlanName" => array(
            "FriendlyName" => $translator->translate("CONFIG_SERVICE_PLAN_NAME"),
            "Type" => "text",
            "Size" => "25",
            'Loader' => function(array $params) {
                $return = array();

                Plesk_Loader::init($params);
                $packages = Plesk_Registry::getInstance()->manager->getServicePlans();
                $return[''] = 'None';
                foreach ($packages as $package) {
                    $return[$package] = $package;
                }

                return $return;
            },
            'SimpleMode' => true,
        ),
        "resellerPlanName" => array(
            "FriendlyName" => $translator->translate("CONFIG_RESELLER_PLAN_NAME"),
            "Type" => "text",
            "Size" => "25",
            'Loader' => function(array $params) {
                $return = array();

                Plesk_Loader::init($params);
                $packages = Plesk_Registry::getInstance()->manager->getResellerPlans();
                $return[''] = 'None';
                foreach ($packages as $package) {
                    $return[$package] = $package;
                }

                return $return;
            },
            'SimpleMode' => $resellerSimpleMode,
        ),
        "ipAdresses" => array (
            "FriendlyName" => $translator->translate("CONFIG_WHICH_IP_ADDRESSES"),
            "Type" => "dropdown",
            "Options" => "IPv4 shared; IPv6 none,IPv4 dedicated; IPv6 none,IPv4 none; IPv6 shared,IPv4 none; IPv6 dedicated,IPv4 shared; IPv6 shared,IPv4 shared; IPv6 dedicated,IPv4 dedicated; IPv6 shared,IPv4 dedicated; IPv6 dedicated",
            "Default" => "IPv4 shared; IPv6 none",
            "Description" => "",
            'SimpleMode' => true,
        ),
        "powerUser" => array(
            "FriendlyName" => $translator->translate("CONFIG_POWER_USER_MODE"),
            "Type" => "yesno",
            "Description" => $translator->translate("CONFIG_POWER_USER_MODE_DESCRIPTION"),
        ),
        "hostingType" => array(
            "FriendlyName" => $translator->translate("CONFIG_HOSTING_TYPE"),
            "Type" => "dropdown",
            "Options" => "vrt_hst,none,std_fwd,frm_fwd",
            "Default" => "vrt_hst",
            "Description" => $translator->translate("CONFIG_HOSTING_TYPE_DESCRIPTION"),
            'SimpleMode' => true,
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
        Sanitize::encode($address),
        Sanitize::encode($port),
        Sanitize::encode($params["serverusername"]),
        Sanitize::encode($params["serverpassword"]),
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

        /** @var stdClass $account */
        $account = Capsule::table('mod_pleskaccounts')
            ->where('userid', $params['clientsdetails']['userid'])
            ->where('usertype', $params['type'])
            ->first();

        $panelExternalId = is_null($account) ? '' : $account->panelexternalid;
        $params['clientsdetails']['panelExternalId'] = $panelExternalId;

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

        if ('' == $panelExternalId && '' != ($possibleExternalId = Plesk_Registry::getInstance()->manager->getCustomerExternalId($params))) {
            /** @var stdClass $account */
            Capsule::table('mod_pleskaccounts')
                ->insert(
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

        return array(
            '' => $translator->translate('FIELD_CHANGE_PASSWORD_ADDITIONAL_PACKAGE_DESCR',
                array('PACKAGE' => $params['domain'],)
            )
        );

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

    $services = Service::where('server', '=', $params['serverid'])->whereIn('domainstatus', ['Active', 'Suspended',])->get();
    $addons = Addon::whereHas('customFieldValues.customField', function ($query) {
        $query->where('fieldname', 'Domain');
    })
        ->with('customFieldValues', 'customFieldValues.customField')
        ->where('server', '=', $params['serverid'])
        ->whereIn('status', ['Active', 'Suspended',])
        ->get();

    $domains = [];
    $resellerUsernames = [];
    $resellerAccountsUsage = [];
    $domainToModel = [];

    /** @var Service $service */
    foreach ($services as $service) {
        if ($service->product->type == 'reselleraccount') {
            $resellerUsernames['service'][] = $service->username;
            $resellerToModel[$service->username] = $service;
        } elseif ($service->domain) {
            $domains[] = $service->domain;
            $domainToModel[$service->domain] = $service;
        }
    }

    /** @var Addon $addon */
    foreach ($addons as $addon) {
        if ($addon->productAddon->type == 'reselleraccount') {
            $resellerUsernames['addon'][] = $addon->username;
            $resellerToModel[$addon->username] = $addon;
            continue;
        }
        foreach ($addon->customFieldValues as $customFieldValue) {
            if (!$customFieldValue->customField) {
                continue;
            }
            if ($customFieldValue->value) {
                $domains[] = $customFieldValue->value;
                $domainToModel[$customFieldValue->value] = $addon;
            }
            break;
        }
    }

    /** Reseller Plan Updates **/
    if (!empty($resellerUsernames) && !empty($resellerUsernames['service'])) {
        $params['usernames'] = $resellerUsernames['service'];
        try {
            Plesk_Loader::init($params);
            $resellerServiceUsage = Plesk_Registry::getInstance()->manager->getResellersUsage($params);
        } catch (Exception $e) {
            return Plesk_Registry::getInstance()->translator->translate('ERROR_COMMON_MESSAGE', array('CODE' => $e->getCode(), 'MESSAGE' => $e->getMessage()));
        }
        $resellerAccountsUsage = $resellerServiceUsage;
    }

    if (!empty($resellerUsernames) && !empty($resellerUsernames['addon'])) {
        $params['usernames'] = $resellerUsernames['addon'];
        try {
            Plesk_Loader::init($params);
            $resellerAddonUsage = Plesk_Registry::getInstance()->manager->getResellersUsage($params);
        } catch (Exception $e) {
            return Plesk_Registry::getInstance()->translator->translate('ERROR_COMMON_MESSAGE', array('CODE' => $e->getCode(), 'MESSAGE' => $e->getMessage()));
        }
        $resellerAccountsUsage = array_merge($resellerAccountsUsage, $resellerAddonUsage);
    }

    if (!empty ($resellerAccountsUsage)) {
        foreach ($resellerAccountsUsage as $username => $usage) {

            /** @var Addon|Service $domainModel */
            $domainModel = $resellerToModel[$username];

            if ($domainModel) {

                $domainModel->serviceProperties->save(
                    [
                        'diskusage' => $usage['diskusage'],
                        'disklimit' => $usage['disklimit'],
                        'bwusage' => $usage['bwusage'],
                        'bwlimit' => $usage['bwlimit'],
                        'lastupdate' => Carbon::now()->toDateTimeString(),
                    ]
                );
            }
        }
    }

    if (!empty($domains)) {
        $params["domains"] = $domains;

        try {
            Plesk_Loader::init($params);
            $domainsUsage = Plesk_Registry::getInstance()->manager->getWebspacesUsage($params);
        } catch (Exception $e) {
            return Plesk_Registry::getInstance()->translator->translate('ERROR_COMMON_MESSAGE', array('CODE' => $e->getCode(), 'MESSAGE' => $e->getMessage()));
        }

        foreach ($domainsUsage as $domainName => $usage) {

            /** @var Addon|Service $domainModel */
            $domainModel = $domainToModel[$domainName];

            if ($domainModel) {

                $domainModel->serviceProperties->save(
                    [
                        'diskusage' => $usage['diskusage'],
                        'disklimit' => $usage['disklimit'],
                        'bwusage' => $usage['bwusage'],
                        'bwlimit' => $usage['bwlimit'],
                        'lastupdate' => Carbon::now()->toDateTimeString(),
                    ]
                );
            }
        }
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

/**
 * @param array $params
 *
 * @return string|array
 */
function plesk_GenerateCertificateSigningRequest(array $params)
{
    try {
        Plesk_Loader::init($params);

        $result = Plesk_Registry::getInstance()->manager->generateCSR($params);
        return [
            'csr' => $result->certificate->generate->result->csr->__toString(),
            'key' => $result->certificate->generate->result->pvt->__toString(),
            'saveData' => true,
        ];
    } catch (\Exception $e) {
        return Plesk_Registry::getInstance()
            ->translator
            ->translate(
                'ERROR_COMMON_MESSAGE',
                array('CODE' => $e->getCode(), 'MESSAGE' => $e->getMessage())
            );
    }

}

/**
 * @param array $params
 *
 * @return string
 */
function plesk_InstallSsl(array $params)
{
    try {
        Plesk_Loader::init($params);
        Plesk_Registry::getInstance()->manager->installSsl($params);
        return 'success';
    } catch (\Exception $e) {
        return Plesk_Registry::getInstance()
            ->translator
            ->translate(
                'ERROR_COMMON_MESSAGE',
                array('CODE' => $e->getCode(), 'MESSAGE' => $e->getMessage())
            );
    }
}

/**
 * @throws Exception
 *
 * @param array $params
 *
 * @return array
 */
function plesk_GetMxRecords(array $params)
{
    try {
        Plesk_Loader::init($params);
        return Plesk_Registry::getInstance()->manager->getMxRecords($params);
    } catch (\Exception $e) {
        throw new Exception(
            'MX Retrieval Failed: ' . Plesk_Registry::getInstance()
                ->translator
                ->translate(
                    'ERROR_COMMON_MESSAGE',
                    array('CODE' => $e->getCode(), 'MESSAGE' => $e->getMessage())
                )
        );
    }
}

/**
 * @throws Exception
 *
 * @param array $params
 */
function plesk_DeleteMxRecords(array $params)
{
    try {
        Plesk_Loader::init($params);
        Plesk_Registry::getInstance()->manager->deleteMxRecords($params);
    } catch (\Exception $e) {
        throw new Exception(
            'Unable to Delete MX Record: ' . Plesk_Registry::getInstance()
                ->translator
                ->translate(
                    'ERROR_COMMON_MESSAGE',
                    array('CODE' => $e->getCode(), 'MESSAGE' => $e->getMessage())
                )
        );
    }
}

/**
 * @throws Exception
 *
 * @param array $params
 */
function plesk_AddMxRecords(array $params)
{
    try {
        Plesk_Loader::init($params);
        Plesk_Registry::getInstance()->manager->addMxRecords($params);
    } catch (\Exception $e) {
        throw new Exception(
            'MX Creation Failed: ' . Plesk_Registry::getInstance()
                ->translator
                ->translate(
                    'ERROR_COMMON_MESSAGE',
                    array('CODE' => $e->getCode(), 'MESSAGE' => $e->getMessage())
                )
        );
    }
}

function plesk_CreateFileWithinDocRoot(array $params)
{

    $ftpConnection = false;
    if (function_exists('ftp_ssl_connect')) {
        $ftpConnection = @ftp_ssl_connect($params['serverhostname']);
    }
    if (!$ftpConnection) {
        $ftpConnection = @ftp_connect($params['serverhostname']);
    }
    if (!$ftpConnection) {
        throw new Exception('Plesk: Unable to create DV Auth File: FTP Connection Failed');
    }
    $ftpLogin = @ftp_login($ftpConnection, $params['username'], $params['password']);

    if (!$ftpLogin) {
        throw new Exception('Plesk: Unable to create DV Auth File: FTP Login Failed');
    }

    $tempFile = tempnam(sys_get_temp_dir(), "plesk");
    if (!$tempFile) {
        throw new Exception('Plesk: Unable to create DV Auth File: Unable to Create Temp File');
    }
    $file = fopen($tempFile, 'w+');
    if (!fwrite($file, $params['fileContent'])) {
        throw new Exception('Plesk: Unable to create DV Auth File: Unable to Write to Temp File');
    }
    fclose($file);
    ftp_chdir($ftpConnection, 'httpdocs');

    $dir = array_key_exists('dir', $params) ? $params['dir'] : '';

    if ($dir) {
        $dirParts = explode('/', $dir);
        foreach ($dirParts as $dirPart) {
            if(!@ftp_chdir($ftpConnection, $dirPart)){
                ftp_mkdir($ftpConnection, $dirPart);
                ftp_chdir($ftpConnection, $dirPart);
            }
        }
    }
    $upload = ftp_put($ftpConnection, $params['filename'], $tempFile, FTP_ASCII);
    if (!$upload) {
        ftp_pasv($ftpConnection, true);
        $upload = ftp_put($ftpConnection, $params['filename'], $tempFile, FTP_ASCII);
    }
    ftp_close($ftpConnection);
    if (!$upload) {
        throw new Exception('Plesk: Unable to create DV Auth File: Unable to Upload File: ' . json_encode(error_get_last()));
    }
}
