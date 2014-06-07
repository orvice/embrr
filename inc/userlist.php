<div id="statuses" class="column round-left">
<script src="js/userlist.js"></script>
<?php
	include_once('lib/timeline_format.php');
	
	if(!isset($_SESSION)){
		session_start();
	}
	$test_var = false;

	$t = getTwitter();
	$p = -1;
	if (isset($_GET['p'])) {
		$p = $_GET['p'] = '' ? -1 : $_GET['p'];
	}
	$c = -1;
	if (isset($_GET['c'])) {
		$c = $_GET['c'];
	}

	$id = isset($_GET['id']) ? $_GET['id'] : null;
	$userid = $id;
	{
		switch ($type) {
			case 'mutes':
				echo $userid ? "You can't view others' muting!" : "
					<h2 style='margin: 10px 0pt 20px 15px'>
					<span>Muting</span>
					</h2>
					<div id='subnav'>
					<span class='subnavLink'><a href='friends.php'>Following</a></span>
					<span class='subnavLink'><a href='followers.php'>Followers</a></span>		
					<span class='subnavNormal'>Muting</span>
					<span class='subnavLink'><a href='blocks.php'>Blocking</a></span>		
					</div>";
				break;
			case 'blocks':
				echo $userid ? "You can't view others' blocking!" : "
					<h2 style='margin: 10px 0pt 20px 15px'>
					<span>Blocking</span>
					</h2>
					<div id='subnav'>
					<span class='subnavLink'><a href='friends.php'>Following</a></span>
					<span class='subnavLink'><a href='followers.php'>Followers</a></span>		
					<span class='subnavLink'><a href='mutes.php'>Muting</a></span>		
					<span class='subnavNormal'>Blocking</span>
					</div>";
				break;
			case 'friends':
				echo $userid ? "
					<h2 style='margin: 10px 0pt 20px 15px'>
					<span><a href='user.php?id=$userid'>" . $userid . "</a> is following</span>
					</h2>
					<div id='subnav'>
					<span class='subnavNormal'><b>" . $userid . "</b> is following</span>
					<span class='subnavLink'><a href='followers.php?id=$userid'>Who follow <b>" . $userid . "</b></a></span>
					</div>" : "
					<h2 style='margin: 10px 0pt 20px 15px'>
					<span>Following</span>
					</h2>
					<div id='subnav'>
					<span class='subnavNormal'>Following</span>
					<span class='subnavLink'><a href='followers.php'>Followers</a></span>
					<span class='subnavLink'><a href='mutes.php'>Muting</a></span>		
					<span class='subnavLink'><a href='blocks.php'>Blocking</a></span>
					</div>";
				break;
			case 'followers':
				echo $userid ? "
					<h2 style='margin: 10px 0pt 20px 15px'>
					<span>Who follow <a href='user.php?id=$userid'>" . $userid . "</a></span>
					</h2>
					<div id='subnav'>
					<span class='subnavLink'><a href='friends.php?id=$userid'><b>" . $userid . "</b> is following</a></span>
					<span class='subnavNormal'>Who follow <b>" . $userid . "</b></span>
					</div>" : "
					<h2 style='margin: 10px 0pt 20px 15px'>
					<span>Followers</span>
					</h2>
					<div id='subnav'><span class='subnavLink'><a href='friends.php'>Following</a></span>
					<span class='subnavNormal'>Followers</span>
					<span class='subnavLink'><a href='mutes.php'>Muting</a></span>		
					<span class='subnavLink'><a href='blocks.php'>Blocking</a></span>
					</div>";
				break;
			case 'list_members':
				echo "
					<h2 style='margin: 10px 0pt 20px 15px'>
					<span>Members of list <span class=\"list_id\">$id</span></span>
					</h2>
					<div id='subnav'><span class='subnavNormal'>Members of list <b>$id</b></span>
					<span class='subnavLink'><a href='list.php?id=$id'>Go back to the list</a></span>
					</div>";
				break;
			case 'list_followers':
				echo "
					<h2 style='margin: 10px 0pt 20px 15px'>
					<span>Followers of list $id</span>
					</h2>
					<div id='subnav'><span class='subnavNormal'>Followers of list <b>$id</b></span>
					<span class='subnavLink'><a href='list.php?id=$id'>Go back to the list</a></span>
					</div>";
				break;
		}
	}

	echo '<div class="clear"></div>';
	switch ($type) {
		case 'mutes':
			$userlist = $t->mutesList($id, $p);
			$next_page = $userlist->next_cursor_str;
			$previous_page = $userlist->previous_cursor_str;
			$userlist = $userlist->users;
			break;
		case 'blocks':
			$userlist = $t->blockingList($id, $p);
			$next_page = $userlist->next_cursor_str;
			$previous_page = $userlist->previous_cursor_str;
			$userlist = $userlist->users;
			break;
		case 'friends':
			$userlist = $t->friends($id, $p);
			$next_page = $userlist->next_cursor_str;
			$previous_page = $userlist->previous_cursor_str;
			$userlist = $userlist->users;
			break;
		case 'followers':
			$userlist = $t->followers($id, $p);
			$next_page = $userlist->next_cursor_str;
			$previous_page = $userlist->previous_cursor_str;
			$userlist = $userlist->users;
			break;
		case 'list_members':
			$userlist = $t->listMembers($id, $c);
			$nextlist = $userlist->next_cursor_str;
			$prelist = $userlist->previous_cursor_str;
			$userlist = $userlist->users;
			break;
		case 'list_followers':
			$userlist = $t->listFollowers($id, $c);
			$nextlist = $userlist->next_cursor_str;
			$prelist = $userlist->previous_cursor_str;
			$userlist = $userlist->users;
			break;
	}
	$empty = count($userlist) == 0 ? true : false;
	if ($empty) {
		echo "<div id=\"empty\">No user to display.</div>";
	} else {
		$output = '<ol class="rank_list">';
		foreach ($userlist as $user) {
			$output .= "
				<li>
				<span class=\"rank_img\">
				<img id= \"avatar\"title=\"Click for more...\" src=\"".getAvatar($user->profile_image_url)."\" />
				</span>
				<div class=\"rank_content\">
				<span class=\"rank_num\"><span class=\"rank_name\"><a href=\"user.php?id=$user->screen_name\">$user->name</a></span>&nbsp;<span class=\"rank_screenname\">$user->screen_name</span><span id=\"rank_id\" style=\"display: none;\">$user->id</span></span>
				<span class=\"rank_count\"><b>Followers:</b> $user->followers_count  <b>Following:</b> $user->friends_count  <b>Tweets:</b> $user->statuses_count</span>
				";
			if ($user->description) $output .= "<span class=\"rank_description\"><b>Bio:</b> $user->description</span>";
			$list_id = explode("/",$id);
			if ($type == 'list_members' &&  $list_id[0] == $t->username) $output .= "<span class=\"status_info\"><a class=\"list_delete_btn fa fa-trash-o\" href=\"#\" title=\"Delete member\"></a></span>";
			$output .= "
				</div>
				</li>
				";
		}
		$output .= "</ol><div id=\"pagination\">";
		if ($type == 'list_members' || $type == 'list_followers' || $type == 'blocks') {
			if ($prelist != 0) $output .= "<a id=\"less\" class=\"btn btn-white\" style=\"float: left;\" href=\"list_members.php?id=$id&c=$prelist\">Back</a>";
			if ($nextlist != 0) $output .= "<a id=\"more\" class=\"btn btn-white\" style=\"float: right;\" href=\"list_members.php?id=$id&c=$nextlist\">Next</a>";
		} else {
			if ($id) {
				if ($previous_page !== "0")
					$output .= "<a id=\"less\" class=\"btn btn-white\" style=\"float: left;\" href=\"$type.php?id=$id&p=" . $previous_page . "\">Back</a>";
				if ($next_page !== "0")
					$output .= "<a id=\"more\" class=\"btn btn-white\" style=\"float: right;\" href=\"$type.php?id=$id&p=" . $next_page . "\">Next</a>";
			} else {
				if ($previous_page !== "0")
					$output .= "<a id=\"less\" class=\"btn btn-white\" style=\"float: left;\" href=\"$type.php?p=" . $previous_page . "\">Back</a>";
				if ($next_page !== "0")
					$output .= "<a id=\"more\" class=\"btn btn-white\" style=\"float: right;\" href=\"$type.php?p=" . $next_page . "\">Next</a>";
			}
		}
		$output .= "</div>";

		echo $output;
	}
?>
</div>
