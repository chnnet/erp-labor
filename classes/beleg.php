<!DOCTYPE html><html><head><meta charset="utf-8"></head><body>
<?php

class beleg
{
	private $journal_id;
	public $vorgang;
	private $journalzeile;
	public $kontonr;
	public $bezeichnung;
	public $soll_haben;
	
	function Buchen($con, $typ$, $kontonr, $soll_haben, $belegnummer, $referenz, $betrag)
	{
		$sql = "INSERT INTO hauptbuch () values ()";
	}
}
	
?>