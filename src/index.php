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

if (!empty($_GET["message"])) {
	$message = strip_tags($_GET["message"]);
}

/* if we've got `page' on the query string, set the session page indicator. */
if (!empty($_GET["page"])) {
	$_SESSION["page"] = $_GET["page"];
	$page = $_GET["page"];
}
else if (isset($_SESSION["page"])) {
	$page = $_SESSION["page"];
}
else {
	$page = 1;
}

if (!empty($_GET["action"])) {
	$action = $_GET["action"];
	if ($action == "ack") {
		$query = "UPDATE {$OPT["table_prefix"]}messages SET isread = 1 WHERE messageid = " . (int) $_GET["messageid"];
		mysql_query($query) or die("Could not query: " . mysql_error());
	}
	else if ($action == "approve") {
		$query = "UPDATE {$OPT["table_prefix"]}shoppers SET pending = 0 WHERE shopper = " . (int) $_GET["shopper"] . " AND mayshopfor = $userid";
		mysql_query($query) or die("Could not query: " . mysql_error());
		sendMessage($userid,(int) $_GET["shopper"],addslashes($_SESSION["fullname"] . " has approved your request to shop for him/her."));
	}
	else if ($action == "decline") {
		$query = "DELETE FROM {$OPT["table_prefix"]}shoppers WHERE shopper = " . (int) $_GET["shopper"] . " AND mayshopfor = $userid";
		mysql_query($query) or die("Could not query: " . mysql_error());
		sendMessage($userid,(int) $_GET["shopper"],addslashes($_SESSION["fullname"] . " has declined your request to shop for him/her."));
	}
	else if ($action == "request") {
		$query = "INSERT INTO {$OPT["table_prefix"]}shoppers(shopper,mayshopfor,pending) VALUES($userid," . (int) $_GET["shopfor"] . ",{$OPT["shop_requires_approval"]})";
		mysql_query($query) or die("Could not query: " . mysql_error());
	}
	else if ($action == "cancel") {
		// this works for either cancelling a request or "unshopping" for a user.
		$query = "DELETE FROM {$OPT["table_prefix"]}shoppers WHERE shopper = " . $userid . " AND mayshopfor = " . (int) $_GET["shopfor"];
		mysql_query($query) or die("Could not query: " . mysql_error());
	}
}

if (!empty($_GET["mysort"]))
	$_SESSION["mysort"] = $_GET["mysort"];
	
echo "<?xml version=\"1.0\" encoding=\"UTF-8\" ?>\r\n";
?>
<!DOCTYPE html PUBLIC "-//W3C//DTD XHTML 1.0 Strict//EN" "DTD/xhtml1-strict.dtd">
<html xmlns="http://www.w3.org/1999/xhtml" xml:lang="en" lang="en">
<head>
<title>Gift Registry - Home Page for <?php echo $_SESSION["fullname"]; ?></title>
<link href="styles.css" type="text/css" rel="stylesheet" />
<script language="JavaScript" type="text/javascript">
	function showItemComment(itemid) {
		window.open("comment.php?itemid=" + itemid,"","width=400,height=200,left=50,top=50,menubar=no,location=no,scrollbars=yes,resizable=yes");
	}
	function showUserComment(userid) {
		window.open("comment.php?userid=" + userid,"","width=400,height=200,left=50,top=50,menubar=no,location=no,scrollbars=yes,resizable=yes");
	}
	function confirmUnshop(fullname) {
		return window.confirm("Are you sure you no longer wish to shop for " + fullname + "?");
	}
	function confirmItemDelete(desc) {
		return window.confirm("Are you sure you want to delete " + desc + "?");
	}
</script>
</head>
<body>
<?php
if (isset($message)) {
	echo "<span class=\"message\">" . $message . "</span>";
}
if ($OPT["show_helptext"]) {
	?>
	<p>
 		<div class="helptext">
			<ul>
				<li>You can click the column headers to sort by that attribute.</li>
				<li>List each item seperately on your list - do not combine items. (i.e. list each book of a 4-part series separately.)</li>
				<li>Once you've bought or decided not to buy an item, remember to return to the recipient's gift lists and mark it accordingly.</li>
				<li>If someone purchases an item on your list, click <img src="images/refresh_nav.gif" /> to mark it as received.</li>
			</ul>
		</div>
	</p>
 	<?php
}
?>
<table border="0" cellpadding="10" cellspacing="5" width="100%">
	<tr valign="top">
		<td colspan="2">
			<p>
				<table class="partbox" width="100%" cellspacing="0">
					<tr class="partboxtitle">
						<td colspan="5" align="center">Gifts I'm asking for</td>
					</tr>
					<tr>
						<th class="colheader"><a href="index.php?mysort=description">Description</a></th>
						<th class="colheader"><a href="index.php?mysort=ranking">Ranking</a></th>
						<th class="colheader"><a href="index.php?mysort=category">Category</a></th>
						<th class="rcolheader"><a href="index.php?mysort=price">Price</a></th>
						<th>&nbsp;</th>
					</tr>
					<?php
					if (!isset($_SESSION["mysort"])) {
						$sortby = "rankorder DESC, i.description";
						$_SESSION["mysort"] = "ranking";
					}
					else {
						switch ($_SESSION["mysort"]) {
							case "ranking":
								$sortby = "rankorder DESC, i.description";
								break;
							case "description":
								$sortby = "i.description";
								break;
							case "price":
								$sortby = "price, rankorder DESC, i.description";
								break;
							case "category":
								$sortby = "c.category, rankorder DESC, i.description";
								break;
							default:
								$sortby = "rankorder DESC, i.description";
						}
					}
					$query = "SELECT itemid, description, c.category, price, url, rendered, comment FROM {$OPT["table_prefix"]}items i LEFT OUTER JOIN {$OPT["table_prefix"]}categories c ON c.categoryid = i.category LEFT OUTER JOIN {$OPT["table_prefix"]}ranks r ON r.ranking = i.ranking WHERE userid = " . $userid . " ORDER BY $sortby";
					$myitems = mysql_query($query) or die("Could not query: " . mysql_error());
					for ($i = 0; $i < ($page - 1) * $OPT["items_per_page"]; $i++) {
						$row = mysql_fetch_array($myitems,MYSQL_ASSOC);
					}

					$i = 0;
					while (($row = mysql_fetch_array($myitems,MYSQL_ASSOC)) && ($i < $OPT["items_per_page"])) {
						$itemid = $row["itemid"];
						?>
						<tr class="<?php echo (!($i++ % 2)) ? "evenrow" : "oddrow" ?>" valign="top">
							<td>
								<?php echo htmlspecialchars($row["description"]); ?>
								<?php
								if ($row["url"] != "") {
									?>
									<a href="<?php echo $row['url'] ?>" target="_blank"><img src="images/links_view.gif" border="0" alt="URL" title="URL"></a>
									<?php
								}
								if ($row["comment"] != "") {
									?>
									<a href="javascript:void(0);" onClick="return showItemComment(<?php echo $row["itemid"]; ?>);"><img src="images/topic.gif" border="0" alt="Comment" title="Comment"></a>
									<?php
								}
								?>
							</td>
							<td nowrap><?php echo $row["rendered"]; ?></td>
							<td><?php echo (($row["category"] == NULL) ? "&nbsp;" : $row["category"]) ?></td>
							<td align="right"><?php echo formatPrice($row["price"]); ?></td>
							<td align="right">
								<a href="receive.php?itemid=<?php echo $row["itemid"]; ?>"><img alt="Mark Item Received" src="images/refresh_nav.gif" border="0" title="Mark Item Received"></a>&nbsp;
								<a href="item.php?action=edit&itemid=<?php echo $itemid; ?>"><img alt="Edit Item" src="images/write_obj.gif" border="0" title="Edit Item"></a>&nbsp;
								<a href="item.php?action=delete&itemid=<?php echo $itemid; ?>" <?php if ($OPT["confirm_item_deletes"]) echo "onClick=\"return confirmItemDelete('" . jsEscape($row["description"]) . "');\""; ?>><img alt="Delete Item" src="images/remove.gif" border="0" alt="Delete" title="Delete Item"></a>&nbsp;
							</td>
						</tr>
						<?php
					}
					$rc = mysql_num_rows($myitems);
					if ($rc > $OPT["items_per_page"]) {
						$pages = ceil($rc / $OPT["items_per_page"]);
						?>
						<tr>
							<td colspan="5" align="center" class="partboxtitle">
								Page:
								<?php
								for ($i = 1; $i <= $pages; $i++) {
									if ($i != $page) {
										?>
										<a class="pagercell" href="index.php?page=<?php echo $i ?>"><?php echo $i ?></a>&nbsp;
										<?php
									}
									else {
										echo $i;
									}
								}
								?>
							</td>
						</tr>	
						<?php
					}
					mysql_free_result($myitems);
					?>
				</table>
			</p>
			<p>
				<a href="item.php?action=add">Add a new item</a>
			</p>
		</td>
	</tr>
	<tr valign="top">
		<td width="50%">
			<p>
				<table class="partbox" width="100%" cellspacing="0">
					<tr class="partboxtitle">
						<td colspan="4" align="center">People I'm shopping for</td>
					</tr>
					<tr>
						<th class="colheader">Name</th>
						<th class="rcolheader">Last Updated</th>
						<th class="rcolheader"># Items</th>
						<th>&nbsp;</th>
					</tr>
					<?php
					$query = "SELECT u.userid, u.fullname, u.comment, u.list_stamp, COUNT(i.itemid) AS itemcount " .
								"FROM {$OPT["table_prefix"]}shoppers s " .
								"INNER JOIN {$OPT["table_prefix"]}users u ON u.userid = s.mayshopfor " .
								"LEFT OUTER JOIN {$OPT["table_prefix"]}items i ON u.userid = i.userid " .
								"WHERE s.shopper = " . $userid . " " .
									"AND pending = 0 " .
								"GROUP BY u.userid, u.fullname, u.list_stamp " .
								"ORDER BY u.fullname";
					$shoppees = mysql_query($query) or die("Could not query: " . mysql_error());
					$i = 0;
					while ($row = mysql_fetch_array($shoppees,MYSQL_ASSOC)) {
						?>
						<tr class="<?php echo (!($i++ % 2)) ? "evenrow" : "oddrow" ?>">
							<td>
								<a href="shop.php?shopfor=<?php echo $row["userid"]; ?>"><?php echo htmlspecialchars($row["fullname"]); ?></a>
								<?php
								if ($row["comment"] != "") {
									?>
									<a href="javascript:void(0);" onClick="return showUserComment(<?php echo $row["userid"]; ?>);"><img src="images/view.gif" alt="Details" title="Details" border="0"></a>
									<?php
								}
								?>
							</td>
							<td align="right"><?php echo $row["list_stamp"] == 0 ? "-" : strftime("%B %d, %Y",strtotime($row["list_stamp"])); ?></td>
							<td align="right"><?php echo $row["itemcount"]; ?></td>
							<td align="right">
								<?php 
								if ($row["itemcount"] > 0) {
									?>
									<a href="shop.php?shopfor=<?php echo $row["userid"]; ?>"><img alt="Shop for <?php echo $row["fullname"]; ?>" src="images/tasks_tsk.gif" border="0" alt="Shop" title="Shop"></a>&nbsp;
									<?php
								}
								?>
								<a href="index.php?action=cancel&shopfor=<?php echo $row["userid"]; ?>" onClick="return confirmUnshop('<?php echo jsEscape($row["fullname"]); ?>')"><img src="images/remove.gif" border="0" alt="Don't shop for <?php echo htmlspecialchars($row["fullname"]); ?> anymore" title="Don't shop for <?php echo htmlspecialchars($row["fullname"]); ?> anymore"></a>
							</td>
						</tr>
						<?php
					}
					mysql_free_result($shoppees);
					?>
				</table>
			</p>
			<p>
				<table class="partbox" width="100%" cellspacing="0">
					<tr class="partboxtitle">
						<td colspan="2" align="center">People I'm <i>not</i> shopping for</td>
					</tr>
					<tr>
						<th class="colheader">Name</th>
						<th>&nbsp;</th>
					</tr>
					<?php
					/*$query = "SELECT u.userid, u.fullname, s.pending " .
								"FROM {$OPT["table_prefix"]}users u " .
								"LEFT OUTER JOIN {$OPT["table_prefix"]}shoppers s ON u.userid = s.mayshopfor " .
									"AND s.shopper = " . $userid . " " .
								"WHERE u.userid <> " . $userid . " " .
									"AND (s.pending IS NULL OR s.pending = 1) " .
									"AND approved = 1 " .
								"ORDER BY u.fullname";*/
					$query = "SELECT u.userid, u.fullname, s.pending " .
								"FROM {$OPT["table_prefix"]}memberships mymem " .
								"INNER JOIN {$OPT["table_prefix"]}memberships others " .
									"ON others.familyid = mymem.familyid AND others.userid <> " . $userid . " " .
								"INNER JOIN {$OPT["table_prefix"]}users u " .
									"ON u.userid = others.userid " .
								"LEFT OUTER JOIN {$OPT["table_prefix"]}shoppers s " .
									"ON s.mayshopfor = others.userid AND s.shopper = " . $userid . " " .
								"WHERE mymem.userid = " . $userid . " " .
									"AND (s.pending IS NULL OR s.pending = 1) " .
									"AND u.approved = 1 " .
								"ORDER BY u.fullname";
					$prospects = mysql_query($query) or die("Could not query: " . mysql_error());
					$i = 0;
					while ($row = mysql_fetch_array($prospects,MYSQL_ASSOC)) {
						?>
						<tr class="<?php echo (!($i++ % 2)) ? "evenrow" : "oddrow" ?>">
							<td><?php echo htmlspecialchars($row["fullname"]) ?></td>
							<td align="right">
							<?php 
							if ($row["pending"] == 1) {
								?>
								<a href="index.php?action=cancel&shopfor=<?php echo $row["userid"]; ?>">Cancel</a>
								<?php
							} else {
								?>
								<a href="index.php?action=request&shopfor=<?php echo $row["userid"]; ?>"><?php echo ($OPT["shop_requires_approval"] ? "Request" : "Add"); ?></a>
								<?php
							}
							?>
							</td>
						</tr>
						<?php
					}
					mysql_free_result($prospects);
					?>
				</table>
			</p>
		</td>
		<td width="50%">
			<p>
				<table class="partbox" width="100%" cellspacing="0">
					<tr class="partboxtitle">
						<td colspan="4" align="center">Messages</td>
					</tr>
					<tr>
						<th class="colheader">Date</th>
						<th class="colheader">Sender</th>
						<th class="colheader">Message</th>
						<th>&nbsp;</th>
					</tr>
					<?php
					$query = "SELECT messageid, u.fullname, message, created " .
								"FROM {$OPT["table_prefix"]}messages m " .
								"INNER JOIN {$OPT["table_prefix"]}users u ON u.userid = m.sender " .
								"WHERE m.recipient = " . $userid . " " .
									"AND m.isread = 0 " .
									"ORDER BY created DESC";
					$messages = mysql_query($query) or die("Could not query: " . mysql_error());
					$i = 0;
					while ($row = mysql_fetch_array($messages,MYSQL_ASSOC)) {
						$messageid = $row["messageid"];
						?>
						<tr class="<?php echo (!($i++ % 2)) ? "evenrow" : "oddrow" ?>" valign="top">
							<td><?php echo strftime("%a, %b %d",strtotime($row["created"])); ?></td>
							<td><?php echo htmlspecialchars($row["fullname"]); ?></td>
							<td><?php echo htmlspecialchars($row["message"]); ?></td>
							<td align="right">
								<a href="index.php?action=ack&messageid=<?php echo $messageid ?>"><img alt="Acknowledge" title="Acknowledge" src="images/step_done.gif" border="0"></a>
							</td>
						</tr>
						<?php
					}
					mysql_free_result($messages);
					?>
					<tr><td colspan="4"><a href="message.php">Send a message</a></td></tr>
				</table>
			</p>
			<p>
				<table class="partbox" width="100%" cellspacing="0">
					<tr class="partboxtitle">
						<td colspan="4" align="center">Upcoming events (within <?php echo $OPT["event_threshold"]; ?> days)</td>
					</tr>
					<tr>
						<th class="colheader">Name</th>
						<th class="colheader">Event</th>
						<th class="colheader">Date</th>
						<th class="colheader">Days left</th>
					</tr>
					<?php
					$query = "SELECT CONCAT(YEAR(CURDATE()),'-',MONTH(eventdate),'-',DAYOFMONTH(eventdate)) AS DateThisYear, " .
								"TO_DAYS(CONCAT(YEAR(CURDATE()),'-',MONTH(eventdate),'-',DAYOFMONTH(eventdate))) AS ToDaysDateThisYear, " .
								"CONCAT(YEAR(CURDATE()) + 1,'-',MONTH(eventdate),'-',DAYOFMONTH(eventdate)) AS DateNextYear, " .
								"TO_DAYS(CONCAT(YEAR(CURDATE()) + 1,'-',MONTH(eventdate),'-',DAYOFMONTH(eventdate))) AS ToDaysDateNextYear, " .
								"TO_DAYS(CURDATE()) AS ToDaysToday, " .
								"TO_DAYS(eventdate) AS ToDaysEventDate, " .
								"e.userid, u.fullname, description, eventdate, recurring, s.pending " .
							"FROM {$OPT["table_prefix"]}events e " .
							"LEFT OUTER JOIN {$OPT["table_prefix"]}users u ON u.userid = e.userid " .
							"LEFT OUTER JOIN {$OPT["table_prefix"]}shoppers s ON s.mayshopfor = e.userid AND s.shopper = $userid ";
					if ($OPT["show_own_events"])
						$query .= "WHERE (pending = 0 OR pending IS NULL)";
					else
						$query .= "WHERE (e.userid <> $userid OR e.userid IS NULL) AND (pending = 0 OR pending IS NULL)";
					$query .= "ORDER BY u.fullname";
					$events = mysql_query($query) or die("Could not query: " . mysql_error());
					$i = 0;
					$eventarray = array();
					while ($row = mysql_fetch_array($events,MYSQL_ASSOC)) {
						$event_fullname = $row["fullname"];
						$days_left = -1;
						if (!$row["recurring"] && (($row["ToDaysEventDate"] - $row["ToDaysToday"]) >= 0) && (($row["ToDaysEventDate"] - $row["ToDaysToday"]) <= $OPT["event_threshold"])) {
							$days_left = $row["ToDaysEventDate"] - $row["ToDaysToday"];
							$event_date = strtotime($row["eventdate"]);
						}
						else if ($row["recurring"] && (($row["ToDaysDateThisYear"] - $row["ToDaysToday"]) >= 0) && (($row["ToDaysDateThisYear"] - $row["ToDaysToday"]) <= $OPT["event_threshold"])) {
							$days_left = $row["ToDaysDateThisYear"] - $row["ToDaysToday"];
							$event_date = strtotime($row["DateThisYear"]);
						}
						else if ($row["recurring"] && (($row["ToDaysDateNextYear"] - $row["ToDaysToday"]) >= 0) && (($row["ToDaysDateNextYear"] - $row["ToDaysToday"]) <= $OPT["event_threshold"])) {
							$days_left = $row["ToDaysDateNextYear"] - $row["ToDaysToday"];
							$event_date = strtotime($row["DateNextYear"]);
						}
						if ($days_left >= 0) {
							$thisevent = array($days_left,$event_fullname,$row["description"],strftime("%B %d, %Y",$event_date));
							array_push($eventarray,$thisevent);
						}
					}
					mysql_free_result($events);
					
					function compareEvents($a, $b) {
						if ($a[0] == $b[0])
							return 0;
						else
							return ($a > $b) ? 1 : -1;
					}
					
					// i couldn't figure out another way to do this, so here goes.
					// sort() wanted to sort based on the array keys, which were 0..n - 1, so that was useless.
					usort($eventarray,"compareEvents");
					
					for ($i = 0; $i < count($eventarray); $i++) {
						?>
						<tr class="<?php echo (!($i % 2)) ? "evenrow" : "oddrow" ?>" valign="top">
							<td><?php echo ($eventarray[$i][1] == "" ? "<i>System event</i>" : $eventarray[$i][1]); ?></td>
							<td><?php echo $eventarray[$i][2]; ?></td>
							<td><?php echo $eventarray[$i][3]; ?></td>
							<td><?php echo ($eventarray[$i][0] == 0 ? "<b>Today</b>" : $eventarray[$i][0]); ?></td>
						</tr>
						<?php
					}
					?>
				</table>
			</p>
			<?php
			if ($OPT["shop_requires_approval"]) {
				?>
				<p>
					<table class="partbox" width="100%" cellspacing="0">
						<tr class="partboxtitle">
							<td colspan="2" align="center">People who want to shop for me</td>
						</tr>
						<tr>
							<th class="colheader">Name</th>
							<th>&nbsp;</th>
						</tr>
						<?php
						$query = "SELECT u.userid, u.fullname " .
									"FROM {$OPT["table_prefix"]}shoppers s " .
									"INNER JOIN {$OPT["table_prefix"]}users u ON u.userid = s.shopper " .
									"WHERE s.mayshopfor = " . $userid . " " .
										"AND s.pending = 1 " .
									"ORDER BY u.fullname";
						$pending = mysql_query($query) or die("Could not query: " . mysql_error());
						$i = 0;
						while ($row = mysql_fetch_array($pending,MYSQL_ASSOC)) {
							?>
							<tr class="<?php echo (!($i++ % 2)) ? "evenrow" : "oddrow" ?>">
								<td><?php echo htmlspecialchars($row["fullname"]); ?></td>
								<td align="right">
									<a href="index.php?action=approve&shopper=<?php echo $row["userid"]; ?>">Approve</a>&nbsp;/
									<a href="index.php?action=decline&shopper=<?php echo $row["userid"]; ?>">Decline</a>
								</td>
							</tr>
							<?php
						}
						mysql_free_result($pending);
						?>
					</table>
				</p>
				<?php
			}
			if (($_SESSION["admin"] == 1) && $OPT["newuser_requires_approval"]) {
				?>
				<p>
					<table class="partbox" width="100%" cellspacing="0">
						<tr class="partboxtitle">
							<td colspan="3" align="center">People waiting for approval</td>
						</tr>
						<tr>
							<th class="colheader">Name</th>
							<th class="colheader">Family</th>
							<th>&nbsp;</th>
						</tr>
						<?php
						$query = "SELECT userid, fullname, email, approved, initialfamilyid, familyname " .
									"FROM {$OPT["table_prefix"]}users u " .
									"LEFT OUTER JOIN {$OPT["table_prefix"]}families f ON f.familyid = u.initialfamilyid " .
									"WHERE approved = 0 " . 
									"ORDER BY fullname";
						$approval = mysql_query($query) or die("Could not query: " . mysql_error());
						$i = 0;
						while ($row = mysql_fetch_array($approval,MYSQL_ASSOC)) {
							?>
							<tr class="<?php echo (!($i++ % 2)) ? "evenrow" : "oddrow" ?>">
								<td><?php echo htmlspecialchars($row["fullname"]); ?> &lt;<a href="mailto:<?php echo $row["email"]; ?>"><?php echo htmlspecialchars($row["email"]); ?></a>&gt;</td>
								<td><?php echo ($row["familyname"] == "" ? "-" : $row["familyname"]); ?></td>
								<td align="right">
									<a href="admin.php?action=approve&userid=<?php echo $row["userid"]; ?>&familyid=<?php echo $row["initialfamilyid"]; ?>">Approve</a>&nbsp;/
									<a href="admin.php?action=reject&userid=<?php echo $row["userid"]; ?>">Reject</a>
								</td>
							</tr>
							<?php
						}
						mysql_free_result($approval);
						?>
					</table>
				</p>
				<?php
			}
			?>
		</td>
	</tr>
</table>
<p>
	<a href="profile.php">Change Password</a>&nbsp;/&nbsp;<a href="profile.php">Update Profile</a>&nbsp;/&nbsp;<a href="event.php">Manage Events</a>&nbsp;/&nbsp;<a href="shoplist.php">My Shopping List</a>&nbsp;/&nbsp;<a href="mylist.php">My Items (printable)</a>
	<?php
	if ($_SESSION["admin"] == 1) {
		?>
		/ <a href="users.php">Manage Users</a> / <a href="families.php">Manage Families</a> / <a href="categories.php">Manage Categories</a> / <a href="ranks.php">Manage Ranks</a>
		<?php
	}
	?>
</p>
<p>
	<a href="index.php">Refresh</a>&nbsp;/&nbsp;<a href="login.php?action=logout">Logout</a>
</p>
<?php include("footer.php"); ?>
</body>
</html>
