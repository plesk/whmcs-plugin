<?php
// Copyright 1999-2016. Parallels IP Holdings GmbH.

class Plesk_Object_ResellerPlan
{
	public $id;
    public $name;
    
    public function __construct($id, $name)
    {
        $this->id = $id;
        $this->name = $name;
    }
}
