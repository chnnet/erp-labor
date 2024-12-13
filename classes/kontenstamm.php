<?php

class kontenstamm
{
	private $id;
	private $klasse;
	public $kontonr;
	public $bezeichnung;
	
	function GetKontoListe($con, $ktorahmen_id $typ)
	{
		$sql = "SELECT kontonr, bezeichnung WHERE ktorahmen_id=" . $ktorahmen_id;
		
		// Schleife über Ergbins erstellen der Objekte Key_Value
		
		return $kontenliste;
	}
}
	
?>