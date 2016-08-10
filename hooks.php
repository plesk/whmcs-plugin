<?php
// Copyright 1999-2016. Parallels IP Holdings GmbH.
/**
 * Plesk Moodule Hooks
 */
use Illuminate\Database\Capsule\Manager as Capsule;


add_hook('ShoppingCartValidateCheckout', 1, function ($vars)
{
    require_once 'lib/Plesk/Translate.php';
    require_once 'lib/Plesk/Config.php';
    require_once 'lib/Plesk/Utils.php';
    $translator = new Plesk_Translate();
    $accountLimit = (int)Plesk_Config::get()->account_limit;
    if (0 >= $accountLimit) {
        return [];
    }

    $accountCount = ('new' == $vars['custtype']) ? 0 : Plesk_Utils::getAccountsCount($vars['userid']);
    $pleskAccountsInCart = 0;
    foreach($_SESSION['cart']['products'] as $product) {
        $currentProduct = Capsule::table('tblproducts')->where('id', $product['pid'])->first();
        if ('plesk' == $currentProduct->servertype) {
            $pleskAccountsInCart++;
        }
    }
    if (!$pleskAccountsInCart) {
        return [];
    }
    $summaryAccounts = $accountCount + $pleskAccountsInCart;

    $errors = [];
    if (0 < $accountLimit && $summaryAccounts > $accountLimit) {
        $errors[] = $translator->translate(
            'ERROR_RESTRICTIONS_ACCOUNT_COUNT',
            [
                'ACCOUNT_LIMIT' => $accountLimit
            ]
        );
    }

    return $errors;
});
