<?php
// Copyright 1999-2016. Parallels IP Holdings GmbH.

class Plesk_Object_Customer
{
    const STATUS_ACTIVE = 0;
    const STATUS_SUSPENDED_BY_ADMIN = 16;
    const STATUS_SUSPENDED_BY_RESELLER = 32;

    const TYPE_CLIENT = 'hostingaccount';
    const TYPE_RESELLER = 'reselleraccount';

    const EXTERNAL_ID_PREFIX = 'whmcs_plesk_';

    public static function getCustomerExternalId($params)
    {
        if (isset($params['clientsdetails']['panelExternalId'])
            && '' != $params['clientsdetails']['panelExternalId'])
        {
            return $params['clientsdetails']['panelExternalId'];
        }
        return $params['clientsdetails']['uuid'];
    }
}
