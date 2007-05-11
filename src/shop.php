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

session_start();
if (!isset($_SESSION["userid"])) {
	header("Location: " . getFullPath("login.php"));
	exit;
}
else {
	$userid = $_SESSION["userid"];
}

$action = "";
if (!empty($_GET["action"])) {
	$action = $_GET["action"];
	$itemid = (int) $_GET["itemid"];
	if ($action == "reserve") {
		adjustAllocQuantity($itemid,$userid,0,+1);
	}
	else if ($action == "purchase") {
		// decrement reserved.
		adjustAllocQuantity($itemid,$userid,0,-1);
		// increment purchased.
		adjustAllocQuantity($itemid,$userid,1,+1);
	}
	else if ($action == "return") {
		// increment reserved.
		adjustAllocQuantity($itemid,$userid,0,+1);
		// decrement purchased.
		adjustAllocQuantity($itemid,$userid,1,-1);
	}
	else if ($action == "release") {
		adjustAllocQuantity($itemid,$userid,0,-1);
	}
	else if ($action == "copy") {
		/* 
		can't do this because MySQL 3.x doesn't seem to support it (at least the version i was using).
		$query = "INSERT INTO items(userid,description,price,source,url,category) SELECT $userid, description, price, source, url, category FROM items WHERE itemid = " . $_GET["itemid"];
		mysql_query($query) or die("Could not query: " . mysql_error());
		*/
		$query = "SELECT userid, description, price, source, url, category, comment FROM {$OPT["table_prefix"]}items WHERE itemid = " . (int) $_GET["itemid"];
		$rs = mysql_query($query) or die("Could not query: " . mysql_error());
		$row = mysql_fetch_array($rs,MYSQL_ASSOC) or die("No item to copy.");
		$desc = mysql_escape_string($row["description"]);
		$source = mysql_escape_string($row["source"]);
		$url = mysql_escape_string($row["url"]);
		$comment = mysql_escape_string($row["comment"]);
		$price = (float) $row["price"];
		$cat = (int) $row["category"];
		mysql_free_result($rs);
		$query = "INSERT INTO {$OPT["table_prefix"]}items(userid,description,price,source,url,comment,category,ranking,quantity) VALUES($userid,'$desc','$price','$source'," . (($url == "") ? "NULL" : "'$url'") . "," . (($comment == "") ? "NULL" : "'$comment'") . "," . (($cat == "") ? "NULL" : $cat) . ",1,1)";
		mysql_query($query) or die("Could not query: $query " . mysql_error());
		stampUser($userid);
		$message = "Added '" . stripslashes($desc) . "' to your gift list.";
	}
}

$shopfor = (int) $_GET["shopfor"];
if ($shopfor == $userid) {
	echo "Nice try! (You can't shop for yourself.)";
	exit;
}
$rs = mysql_query("SELECT * FROM {$OPT["table_prefix"]}shoppers WHERE shopper = $userid AND mayshopfor = $shopfor AND pending = 0") or die("Could not query: " . mysql_error());
if (mysql_num_rows($rs) == 0) {
	echo "Nice try! (You can't shop for someone who hasn't approved it.)";
	exit;
}
mysql_free_result($rs);

if (!isset($_GET["sort"])) {
	$sortby = "rankorder DESC, description";
	$sort = "";
}
else {
	$sort = $_GET["sort"];
	switch ($sort) {
		case "ranking":
			$sortby = "rankorder DESC, description";
			break;
		case "description":
			$sortby = "description";
			break;
		case "source":
			$sortby = "source, rankorder DESC, description";
			break;
		case "price":
			$sortby = "price, rankorder DESC, description";
			break;
		case "url":
			$sortby = "url, rankorder DESC, description";
			break;
		case "status":
			$sortby = "reservedid DESC, boughtid DESC, rankorder DESC, description";
			break;
		case "category":
			$sortby = "c.category, rankorder DESC, description";
			break;
		default:
			$sortby = "rankorder DESC, description";
	}
}
/* here's what we're going to do: we're going to pull back the shopping list along with any alloc record
	for those items with a quantity of 1.  if the item's quantity > 1 we'll query alloc when we
	get to that record.  the theory is that most items will have quantity = 1 so we'll make the least
	number of trips. */
$query = "SELECT i.itemid, description, price, source, c.category, url, " .
		"ub.fullname AS bfullname, ub.userid AS boughtid, " .
		"ur.fullname AS rfullname, ur.userid AS reservedid, " .
		"rendered, i.comment, i.quantity " .
	"FROM {$OPT["table_prefix"]}items i " .
	"LEFT OUTER JOIN {$OPT["table_prefix"]}categories c ON c.categoryid = i.category " .
	"LEFT OUTER JOIN {$OPT["table_prefix"]}ranks r ON r.ranking = i.ranking " .
	"LEFT OUTER JOIN {$OPT["table_prefix"]}allocs a ON a.itemid = i.itemid AND i.quantity = 1 " .	// only join allocs for single-quantity items.
	"LEFT OUTER JOIN {$OPT["table_prefix"]}users ub ON ub.userid = a.userid AND a.bought = 1 " .
	"LEFT OUTER JOIN {$OPT["table_prefix"]}users ur ON ur.userid = a.userid AND a.bought = 0 " .
	"WHERE i.userid = $shopfor ORDER BY $sortby";
$rs = mysql_query($query) or die("Could not query: " . mysql_error());

/* okay, I *would* retrieve the shoppee's fullname from the items recordset,
	except that I wouldn't get it if he had no items, so I *could* LEFT OUTER
	JOIN, but then it would complicate the iteration logic, so let's just
	hit the DB again. */
$query = "SELECT fullname FROM {$OPT["table_prefix"]}users WHERE userid = $shopfor";
$urs = mysql_query($query) or die("Could not query: " . mysql_error());
$ufullname = mysql_fetch_array($urs,MYSQL_ASSOC);
$ufullname = $ufullname["fullname"];

echo "<?xml version=\"1.0\" encoding=\"UTF-8\" ?>\r\n";
?>
<!DOCTYPE html PUBLIC "-//W3C//DTD XHTML 1.0 Strict//EN" "DTD/xhtml1-strict.dtd">
<html xmlns="http://www.w3.org/1999/xhtml" xml:lang="en" lang="en">
<head>
<?php
echo "<title>Gift Registry - Shopping List for $ufullname</title>";
?>
<link href="styles.css" type="text/css" rel="stylesheet" />
<script language="JavaScript" type="text/javascript">
	function showComment(itemid) {
		window.open("comment.php?itemid=" + itemid,"","width=400,height=200,left=50,top=50,menubar=no,location=no,scrollbars=yes,resizable=yes");
	}
	function printPage() {
		window.print();
	}
</script>
</head>
<body>
<?php
if (isset($message)) {
	echo "<span class=\"message\">" . $message . "</span>";
}
?>
<p class="pagetitle">Gift Registry - Shopping List for <?php echo $ufullname; ?></p>
<?php
if ($OPT["show_helptext"]) {
	?>
	<p>
		<div class="helptext">
			<ul>
				<li>If you intend to purchase a gift for this person, click the <img src="images/lock_co.gif"> icon.  If you end up actually purchasing it, come back and click the <img src="images/step_done.gif"> icon.  If you change your mind and don't want to buy it, come back and click the <img src="images/unlock_co.gif"> icon.</li>
				<li>If you return something you've purchased, come back and click the <img src="images/run_exc.gif"> icon.  It will remain reserved for you.</li>
				<li>Just because an item has a URL listed doesn't mean you have to buy it from there (unless the comment says so).</li>
				<li>You can click the column headers to sort by that attribute.</li>
				<li>If you see something you'd like for yourself, click the <img src="images/toolbar_replace.gif"> icon to copy it to your own list.</li>
			</ul>
		</div>
	</p>
	<?php
}
?>
<div align="right">
	<img src="images/lock_co.gif" alt="Reserve" title="Reserve"> = Reserve, <img src="images/unlock_co.gif" alt="Release" title="Release"> = Release, <img src="images/step_done.gif" alt="Purchase" title="Purchase"> = Purchase, <img src="images/run_exc.gif" alt="Return" title="Return"> = Return, <img src="images/toolbar_replace.gif" alt="I Want This Too" title="I Want This Too"> = I Want This Too
</div>
<p><table class="partbox" width="100%" cellpadding="3" cellspacing="0">
	<tr valign="top">
		<th class="colheader"><a href="shop.php?shopfor=<?php echo $shopfor ?>&sort=ranking">Rank</a></th>
		<th class="colheader"><a href="shop.php?shopfor=<?php echo $shopfor ?>&sort=description">Description</a></th>
		<th class="colheader"><a href="shop.php?shopfor=<?php echo $shopfor ?>&sort=category">Category</a></th>
		<th class="rcolheader"><a href="shop.php?shopfor=<?php echo $shopfor ?>&sort=price">Price</a></th>
		<th class="colheader"><a href="shop.php?shopfor=<?php echo $shopfor ?>&sort=source">Store/Location</a></th>
		<th class="colheader"><a href="shop.php?shopfor=<?php echo $shopfor ?>&sort=status">Status</a></th>
		<th class="rcolheader">&nbsp;</th>
		<th class="rcolheader">&nbsp;</th>
	</tr>
	<?php
	$i = 0;
	while ($row = mysql_fetch_array($rs,MYSQL_ASSOC)) {
		?>
		<tr class="<?php echo (!($i++ % 2)) ? "evenrow" : "oddrow" ?>" valign="top">	
			<?php
			$rendered = $row["rendered"];
			$url = $row["url"];
			$itemid = $row["itemid"];
			$rfullname = $row["rfullname"];
			$bfullname = $row["bfullname"];
			$boughtid = $row["boughtid"];
			$reservedid = $row["reservedid"];
			$category = $row["category"];
			$comment = $row["comment"];
			$quantity = $row["quantity"];
			$suffix = "";
			if (($rfullname != "" && $reservedid != $userid) || ($bfullname != "" && $boughtid != $userid)) {
				$suffix = "_unavail";
			}
			else if (($rfullname != "" && $reservedid == $userid) || ($bfullname != "" && $boughtid == $userid)) {
				$suffix = "_res";
			}	
			?>
			<td nowrap><?php echo $rendered ?></td>
			<td>
				<?php echo htmlspecialchars($row["description"]); ?>
				<?php
				if ($url != "") {
					?>
					<a href="<?php echo $url ?>" target="_blank"><img src="images/links_view.gif" border="0" alt="URL" title="URL"></a>
					<?php
				}
				if ($comment != "") {
					?>
					<a href="javascript:void(0);" onClick="return showComment(<?php echo $row["itemid"]; ?>);"><img src="images/topic.gif" border="0" alt="Comment" title="Comment"></a>
					<?php
				}
				?>
			</td>
			<td>
				<?php echo (($category == NULL) ? "&nbsp;" : $category); ?>
			</td>
			<td align="right"><?php echo formatPrice($row["price"]); ?></td>
			<td><?php echo htmlspecialchars($row["source"]); ?></td>
			<?php
			if ($quantity > 1) {
				echo "<td>\r\n";
				// query the allocs table to see what's been bought and what's been reserved.
				$avail = $quantity;
				$query = "SELECT a.quantity, a.bought, a.userid, " .
							"ub.fullname AS bfullname, ub.userid AS boughtid, " .
							"ur.fullname AS rfullname, ur.userid AS reservedid " .
						"FROM {$OPT["table_prefix"]}allocs a " .
						"LEFT OUTER JOIN {$OPT["table_prefix"]}users ub ON ub.userid = a.userid AND a.bought = 1 " .
						"LEFT OUTER JOIN {$OPT["table_prefix"]}users ur ON ur.userid = a.userid AND a.bought = 0 " .
						"WHERE a.itemid = $itemid " .
						"ORDER BY a.bought, a.quantity";
				$allocs = mysql_query($query) or die("Could not query: " . mysql_error());
				$ibought = 0;
				$ireserved = 0;
				while ($allocrow = mysql_fetch_array($allocs,MYSQL_ASSOC)) {
					if ($allocrow["bfullname"] != "") {
						if ($allocrow["boughtid"] == $userid) {
							$ibought += $allocrow["quantity"];
							echo "<b>" . $allocrow["quantity"] . " bought by you.</b><br />\r\n";
						}
						else
						{
							if (!$OPT["anonymous_purchasing"])
								echo $allocrow["quantity"] . " bought by " . $allocrow["bfullname"] . ".<br />\r\n";
							else
								echo $allocrow["quantity"] . " bought.<br />\r\n";
						}
					}
					else {
						if ($allocrow["reservedid"] == $userid) {
							$ireserved += $allocrow["quantity"];
							echo "<b>" . $allocrow["quantity"] . " reserved by you.</b><br />\r\n";
						}
						else
						{
							if (!$OPT["anonymous_purchasing"])
								echo $allocrow["quantity"] . " reserved by " . $allocrow["rfullname"] . ".<br />\r\n";
							else
								echo $allocrow["quantity"] . " reserved.<br />\r\n";
						}
					}
					$avail -= $allocrow["quantity"];
				}
				mysql_free_result($allocs);
				echo ($avail > 0 ? $avail : "None") . " remaining.</td>\r\n";
				
				echo "<td nowrap align=\"right\">\r\n";
				if ($avail > 0 || $ireserved > 0 || $ibought > 0) {
					$reservetext = ($ireserved > 0) ? "Reserve Another" : "Reserve Item";
					$purchasetext = ($ibought > 0) ? "Purchase Another" : ($ireserved > 0) ? "Convert Reserve to Purchase" : "Purchase Item";
					if ($avail > 0) {
						?>
						<a href="shop.php?action=reserve&sort=<?php echo $sort ?>&itemid=<?php echo $itemid ?>&shopfor=<?php echo $shopfor ?>"><img alt="<?php echo $reservetext; ?>" title="<?php echo $reservetext; ?>" src="images/lock_co.gif" border="0"></a>
						<?php	
					}
					if ($avail> 0 || $ireserved > 0) {
						?>
						<a href="shop.php?action=purchase&sort=<?php echo $sort ?>&itemid=<?php echo $itemid ?>&shopfor=<?php echo $shopfor ?>"><img alt="<?php echo $purchasetext; ?>" title="<?php echo $purchasetext; ?>" src="images/step_done.gif" border="0"></a>
						<?php
					}
				}
				if ($ireserved > 0) {
					?>
					<a href="shop.php?action=release&sort=<?php echo $sort ?>&itemid=<?php echo $itemid ?>&shopfor=<?php echo $shopfor ?>"><img alt="Release Item" title="Release Item" src="images/unlock_co.gif" border="0"></a>
					<?php
				}
				if ($ibought > 0) {
					?>
					<a href="shop.php?action=return&sort=<?php echo $sort ?>&itemid=<?php echo $itemid ?>&shopfor=<?php echo $shopfor ?>"><img alt="Return Item" title="Return Item" src="images/run_exc.gif" border="0"></a>
					<?php
				}
				echo "</td>\r\n";
			}
			else {
				if ($rfullname == "" && $bfullname == "") {
					?>
					<td>
						<i>Available.</i>
					</td>
					<td nowrap align="right">
						<a href="shop.php?action=reserve&sort=<?php echo $sort ?>&itemid=<?php echo $itemid ?>&shopfor=<?php echo $shopfor ?>"><img alt="Reserve Item" title="Reserve Item" src="images/lock_co.gif" border="0"></a>&nbsp;<a href="shop.php?action=purchase&sort=<?php echo $sort ?>&itemid=<?php echo $itemid ?>&shopfor=<?php echo $shopfor ?>"><img alt="Purchase Item" title="Purchase Item" src="images/step_done.gif" border="0"></a>
					</td>
					<?php
				}
				else if ($rfullname != "") {
					if ($reservedid == $userid) {
						?>
						<td>
							<i><b>Reserved by you.</b></i>
						</td>
						<td align="right">
							<a href="shop.php?action=release&sort=<?php echo $sort ?>&itemid=<?php echo $itemid ?>&shopfor=<?php echo $shopfor ?>"><img alt="Release Item" title="Release Item" src="images/unlock_co.gif" border="0"></a>&nbsp;<a href="shop.php?action=purchase&sort=<?php echo $sort ?>&itemid=<?php echo $itemid ?>&shopfor=<?php echo $shopfor ?>"><img alt="Purchase Item" title="Purchase Item" src="images/step_done.gif" border="0"></a>
						</td>
						<?php
					}
					else {
						?>
						<td>
							<i>Reserved<?php if (!$OPT["anonymous_purchasing"]) echo " by " . htmlspecialchars($rfullname); ?>.</i>
						</td>
						<td>&nbsp;</td>
						<?php
					}
				}
				else if ($bfullname != "") {
					if ($boughtid == $userid) {
						?>
						<td>
							<i><b>Bought by you.</b></i>
						</td>
						<td align="right">
							<a href="shop.php?action=return&sort=<?php echo $sort ?>&itemid=<?php echo $itemid ?>&shopfor=<?php echo $shopfor ?>"><img alt="Return Item" title="Return Item" src="images/run_exc.gif" border="0"></a>
						</td>
						<?php
					}
					else {
						?>
						<td>
							<i>Bought<?php if (!$OPT["anonymous_purchasing"]) echo " by " . htmlspecialchars($bfullname); ?>.</i>
						</td>
						<td>&nbsp;</td>
						<?php
					}
				}
			}
			?>
			<td>
				<a href="shop.php?action=copy&itemid=<?php echo $itemid; ?>&shopfor=<?php echo $shopfor; ?>&sort=<?php echo $sort; ?>"><img alt="I Want This Too" title="I Want This Too" src="images/toolbar_replace.gif" border="0"></a>
			</td>
		</tr>
	<?php
	}
	mysql_free_result($rs);
	?>
</table></p>
<p>
	<a onClick="printPage()" href="#">Send to printer</a>&nbsp;/&nbsp;<a href="index.php">Back to main</a>
</p>
</body>
</html>
