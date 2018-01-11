<?php
// Copyright 1999-2016. Parallels IP Holdings GmbH.
use WHMCS\Database\Capsule;

class Plesk_Utils
{
    /** Gets Plesk's accounts count for user by id
     * @param int $userId
     * @return int
     */
    public static function getAccountsCount($userId)
    {
        $hostingAccounts = Capsule::table('tblhosting')
            ->join('tblservers', 'tblservers.id', '=', 'tblhosting.server')
            ->where('tblhosting.userid', $userId)
            ->where('tblservers.type', 'plesk')
            ->whereIn('tblhosting.domainstatus', array('Active', 'Suspended', 'Pending'))
            ->count();

        $hostingAddonAccounts = Capsule::table('tblhostingaddons')
            ->join('tblservers', 'tblhostingaddons.server', '=', 'tblservers.id')
            ->where('tblhostingaddons.userid', $userId)
            ->where('tblservers.type', 'plesk')
            ->whereIn('status', ['Active', 'Suspended', 'Pending'])
            ->count();

        return $hostingAccounts + $hostingAddonAccounts;
    }

}
