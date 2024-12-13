<!DOCTYPE html><html><head><meta charset="utf-8"></head><body>
<?php

class keyvalue
{
	public $key;
	public $value;
	
	function buildArray($tabelle, $key, $value, $bedingung)
	{
		$sql = "SELECT " . $key . "," . $value . " FROM " . $tabelle;
		
		// key-value-Liste erstellen
		
		
		return $keyvaluelist;
	}
}