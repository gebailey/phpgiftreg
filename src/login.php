<?php
// This program is free software; you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation; either version 2 of the License, or
// (at your option) any later version.

// This program is distributed in the hope that it will be useful,
// but WITHOUT ANY WARRANTY; without even the implied warranty of
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
// GNU General Public License for more details.

// You should have received a copy of the GNU General Public License
// along with this program; if not, write to the Free Software
// Foundation, Inc., 59 Temple Place, Suite 330, Boston, MA  02111-1307  USA

include("config.php");
include("db.php");
include("funcLib.php");

if (isset($_GET["action"])) {
	if ($_GET["action"] == "logout") {
		session_start();
		session_destroy();
	}
}

if (!empty($_POST["username"])) {
	include "db.php";
	$username = $_POST["username"];
	$password = $_POST["password"];
	if (!get_magic_quotes_gpc()) {
		$username = addslashes($username);
		$password = addslashes($password);
	}

	$query = "SELECT userid, fullname, admin FROM {$OPT["table_prefix"]}users WHERE username = '$username' AND password = {$OPT["password_hasher"]}('$password') AND approved = 1";
	$rs = mysql_query($query) or die("Could not query: " . mysql_error());
	if ($row = mysql_fetch_array($rs,MYSQL_ASSOC)) {
		session_start();
		$_SESSION["userid"] = $row["userid"];
		$_SESSION["fullname"] = $row["fullname"];
		$_SESSION["admin"] = $row["admin"];
		header("Location: " . getFullPath("index.php"));
		mysql_free_result($rs);
		exit;
	}
}
echo "<?xml version=\"1.0\" encoding=\"UTF-8\" ?>\r\n";
?>
<!DOCTYPE html PUBLIC "-//W3C//DTD XHTML 1.0 Strict//EN" "DTD/xhtml1-strict.dtd">
<html xmlns="http://www.w3.org/1999/xhtml" xml:lang="en" lang="en">
<head>
<title>Gift Registry - Login</title>
<link href="styles.css" type="text/css" rel="stylesheet" />
</head>
<body onLoad="document.login.username.focus();">
<form name="login" method="post" action="login.php">	
	<div align="center">
		<img src="images/title.gif" border="0" alt="Gift Registry" title="Gift Registry" />
	</div>
	<div align="center">
		<table cellpadding="3" class="partbox">
			<?php
			if (isset($_POST["username"])) {
				echo "<caption><font color=\"red\">Bad login.</font></caption>";
			}
			?>
			<tr>
				<td colspan="2" class="partboxtitle" align="center">Login to the Gift Registry</td>
			</tr>
			<tr>
				<td>Username</td>
				<td>
					<input name="username" type="text" />
				</td>
			</tr>
			<tr>
				<td>Password</td>
				<td>
					<input name="password" type="password" />
				</td>
			</tr>
			<tr>
				<td colspan="2" align="center">
					<input type="submit" value="Login"/>
				</td>
			</tr>
		</table>
	</div>
	<p>
		<div align="center">
			<a href="signup.php">Need an account?</a>
		</div>
	</p>
	<p>
		<div align="center">
			<a href="forgot.php">Forgot your password?</a>
		</div>
	</p>
</form>
</body>
</html>
