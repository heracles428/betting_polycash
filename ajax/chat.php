<?php
include("../includes/connect.php");
include("../includes/get_session.php");

$output_obj = array();

if ($thisuser && $game) {
	$user_id = intval($_REQUEST['user_id']);

	$to_user = new User($user_id);
	
	if ($to_user) {
		if ($thisuser->user_in_game($game->db_game['game_id']) && $to_user->user_in_game($game->db_game['game_id'])) {
			$action = $_REQUEST['action'];
			
			if ($action == "send") {
				$message = mysql_real_escape_string(strip_tags($GLOBALS['app']->utf8_clean(urldecode($_REQUEST['message']))));
				
				if ($message != "") {
					$q = "INSERT INTO user_messages SET game_id='".$game->db_game['game_id']."', from_user_id='".$thisuser->db_user['user_id']."', to_user_id='".$to_user->db_user['user_id']."', message='".$message."', send_time='".time()."';";
					$r = $GLOBALS['app']->run_query($q);
				}
			}
			
			$output_obj['username'] = $to_user->db_user['username'];
			$output_obj['content'] = "";

			$q = "SELECT * FROM user_messages WHERE game_id=".$game->db_game['game_id']." AND ((from_user_id=".$thisuser->db_user['user_id']." AND to_user_id=".$to_user->db_user['user_id'].") OR (from_user_id=".$to_user->db_user['user_id']." AND to_user_id='".$thisuser->db_user['user_id']."'));";
			$r = $GLOBALS['app']->run_query($q);
			
			while ($message = mysql_fetch_array($r)) {
				$time_disp = $GLOBALS['app']->format_seconds(time()-$message['send_time']).' ago';
				if (time()-$message['send_time'] > 3600*24) $time_disp .= " (".date("n/j/Y", $message['send_time']).")";
				
				$output_obj['content'] .= '<div class="user_message_holder"><div title="'.$time_disp.'" class="';
				if ($message['from_user_id'] == $thisuser->db_user['user_id']) $output_obj['content'] .= "user_message_sent";
				else $output_obj['content'] .= 'user_message_received';
				$output_obj['content'] .= '">';
				$output_obj['content'] .= $message['message'].'</div></div>';
			}
			
			$q = "UPDATE user_messages SET seen=1 WHERE game_id='".$game->db_game['game_id']."' AND to_user_id='".$thisuser->db_user['user_id']."' AND from_user_id='".$to_user->db_user['user_id']."';";
			$r = $GLOBALS['app']->run_query($q);
		}
	}
}
echo json_encode($output_obj);
?>