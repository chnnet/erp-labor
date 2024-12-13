<!DOCTYPE HTML PUBLIC "-//W3C//DTD HTML 4.01 Transitional//EN" "http://www.w3.org/TR/html4/loose.dtd">
<html><head><title>ERP-Labor</title>
<script language="JavaScript" type="text/javascript">
function init()
{
	document.login.password.focus();
}
</script>
</head>
<body onLoad="init()">

<h1> Benutzeranmeldung </h1>
<br><br><br>
<form name="login" action="/journal_auswahl.php" method=post>
	<table>
		<tr>
		<td>Login name:</td>
		<td><input type="text" name="loginname" size="30" maxlength="50" value="chn" /></td>
		</tr>
<!--		<tr>
		<td>Passwort</td>
		<td><input type=password name="password" size="30" maxlength="30"/></td>
		</tr>
-->
<tr>
		<td><b>Buchungsdatum</b></td>
		<td>
		<select name="tag_Datum">
		<?php
			$tag = date("d");
			$monat = date("m");
			$jahr = date("Y");

			$i = 1;
			while ($i < 32)
			{
				if ($i == $tag)
				{
					echo "<option value = \"" . $i . "\" selected>" . $i . "</option>";
				}
				else {
					echo "<option value = \"" . $i . "\" >" . $i . "</option>";
				}
				$i++;
			}
		?>
		</select>
		<select name="monat_Datum">
		<?php
			$i = 1;
			while ($i < 13)
			{
				if ($i == $monat)
				{
					echo "<option value = \"" . $i . "\" selected>" . $i . "</option>";
				}
				else {
					echo "<option value = \"" . $i . "\" >" . $i . "</option>";
				}
				$i++;
			}
		?>
		</select>
		<?php
			echo "<input type=text name=\"jahr_Datum\" size=\"5\" maxlength=\"4\" value=\"" . $jahr . "\" />";
		?>
		</td>

	</table>

		<input type="hidden" name="dbtyp" value="mysql" />
		<input type="hidden" name="host"  value="db22.variomedia.de" />
		<input type="hidden" name="dbname"  value="db48930" />

<input type=submit value="Demo" />

</form>
</body>
</html>