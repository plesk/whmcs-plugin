<?php
// Copyright 1999-2016. Parallels IP Holdings GmbH.

class Plesk_Manager_V1640 extends Plesk_Manager_V1635
{
    /**
     * @param $params
     * @return array (<domainName> => array ('diskusage' => value, 'disklimit' => value, 'bwusage' => value, 'bwlimit' => value))
     * @throws Exception
     */
    protected function _getWebspacesUsage($params)
    {
        $usage = array();
        $webspaces = Plesk_Registry::getInstance()->api->webspace_usage_get_by_name(array('domains' => $params['domains']));
        foreach($webspaces->xpath('//webspace/get/result') as $result) {
            try {
                $this->_checkErrors($result);
                $domainName = (string)$result->data->gen_info->name;
                $usage[$domainName]['diskusage'] = (float)$result->data->gen_info->real_size;
                $resourceUsage = reset($result->data->xpath('resource-usage'));
                foreach($resourceUsage->resource as $resource) {
                    $name = (string)$resource->name;
                    if ('max_traffic' == $name) {
                        $usage[$domainName]['bwusage'] = (float)$resource->value;
                        break;
                    }
                }
                $usage[$domainName] = array_merge($usage[$domainName], $this->_getLimits($result->data->limits));

                //Data saved in megabytes, not in a bytes
                foreach($usage[$domainName] as $param => $value) {
                    $usage[$domainName][$param] = $usage[$domainName][$param] / (1024 * 1024);
                }
            } catch (Exception $e) {
                if (Plesk_Api::ERROR_OBJECT_NOT_FOUND != $e->getCode()) {
                    throw $e;
                }
            }
        }
        return $usage;
    }
}
