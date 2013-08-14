<!-- Copyright 1999-2016. Parallels IP Holdings GmbH. -->
<customer>
    <add>
        <gen_info>
            <cname><?php echo $params['companyname']; ?></cname>
            <pname><?php echo $params['firstname']; ?> <?php echo $params['lastname']; ?></pname>
            <login><?php echo $username; ?></login>
            <passwd><?php echo $accountPassword; ?></passwd>
            <status><?php echo $status; ?></status>
            <phone><?php echo $phonenumber; ?></phone>
            <email><?php echo $email; ?></email>
            <address><?php echo $params['address1']; ?></address>
            <city><?php echo $city; ?></city>
            <state><?php echo $state; ?></state>
            <pcode><?php echo $postcode; ?></pcode>
            <country><?php echo $country; ?></country>
            <external-id><?php echo $externalId; ?></external-id>
        </gen_info>
    </add>
</customer>
