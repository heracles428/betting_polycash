<?php
include("../includes/connect.php");
include("../includes/get_session.php");

if ($thisuser || $_REQUEST['refresh_page'] != "wallet") {
	$instance_id = (int) $_REQUEST['instance_id'];
	$game_loop_index = (int) $_REQUEST['game_loop_index'];
	$event_ids = $_REQUEST['event_ids'];
	
	if (!$game) {
		$game_id = (int) $_REQUEST['game_id'];
		$db_game = $app->run_query("SELECT * FROM games WHERE game_id='".$game_id."';")->fetch();
		$blockchain = new Blockchain($app, $db_game['blockchain_id']);
		$game = new Game($blockchain, $game_id);
	}
	$game->load_current_events();
	
	if ($thisuser) {
		$thisuser->set_user_active();
		$user_game = $thisuser->ensure_user_in_game($game, false);
	}
	
	$blockchain_last_block_id = $game->blockchain->last_block_id();
	$blockchain_current_round = $game->block_to_round($blockchain_last_block_id+1);
	$blockchain_block_within_round = $game->block_id_to_round_index($blockchain_last_block_id+1);
	
	$last_block_id = $game->last_block_id();
	$current_round = $game->block_to_round($last_block_id+1);
	$block_within_round = $game->block_id_to_round_index($last_block_id+1);
	
	if ($thisuser) {
		$account_value = $thisuser->account_coin_value($game, $user_game);
		$immature_balance = $thisuser->immature_balance($game, $user_game);
		$mature_balance = $thisuser->mature_balance($game, $user_game);
		$mature_game_io_ids_csv = $game->mature_io_ids_csv($user_game);
	}
	else {
		$account_value = 0;
		$immature_balance = 0;
		$mature_balance = 0;
		$mature_game_io_ids_csv = "";
	}
	
	$output = false;
	$output['game_loop_index'] = $game_loop_index;
	
	$output['game_status_explanation'] = $game->game_status_explanation($thisuser, $user_game);
	
	if ($game->db_game['module'] == "CoinBattles") {
		if (!empty($game->current_events[0])) {
			list($html, $js) = $game->module->currency_chart($game, $game->current_events[0]->db_event['event_starting_block'], false);
			$output['chart_html'] = $html;
			$output['chart_js'] = $js;
		}
	}
	
	$filter_arr = array();
	if (!empty($_REQUEST['filter_date'])) {
		$filter_time = strtotime($_REQUEST['filter_date']);
		$filter_arr['date'] = date("Y-m-d", $filter_time);
	}
	$new_event_ids = "";
	$js = $game->new_event_js($instance_id, $thisuser, $filter_arr, $new_event_ids);
	
	if ($new_event_ids != $event_ids) {
		$output['new_event_ids'] = 1;
		$output['new_event_js'] = $js;
		$output['event_ids'] = $new_event_ids;
	}
	else $output['new_event_ids'] = 0;
	
	if ($last_block_id != (int) $_REQUEST['last_block_id']) {
		$output['new_block'] = 1;
		$output['last_block_id'] = $last_block_id;
		$db_last_block = $blockchain->fetch_block_by_id($last_block_id);
		$output['time_last_block_loaded'] = $db_last_block['time_mined'];
	}
	else $output['new_block'] = 0;
	
	$these_events = $game->events_by_block($last_block_id, $filter_arr);
	
	$show_intro_text = false;
	for ($game_event_index=0; $game_event_index<count($these_events); $game_event_index++) {
		$output['current_round_table'][$game_event_index] = $these_events[$game_event_index]->current_round_table($thisuser, $show_intro_text, true, $instance_id, $game_event_index);
	}
	
	if ($thisuser) {
		$output['wallet_text_stats'] = $thisuser->wallet_text_stats($game, $blockchain_current_round, $blockchain_last_block_id, $blockchain_block_within_round, $mature_balance, $immature_balance, $user_game);
		
		for ($game_event_index=0; $game_event_index<count($these_events); $game_event_index++) {
			$output['my_current_votes'][$game_event_index] = $these_events[$game_event_index]->my_votes_table($current_round, $user_game);
		}
		$output['account_value'] = $game->account_value_html($account_value, $user_game['account_id']);
	}
	
	$output['vote_details_general'] = $app->vote_details_general($mature_balance);
	
	$set_options_js = "";
	
	for ($game_event_index=0; $game_event_index<count($these_events); $game_event_index++) {
		$round_stats = $these_events[$game_event_index]->round_voting_stats_all();
		$total_vote_sum = $round_stats[0];
		$option_id2rank = $round_stats[3];
		$round_stats = $round_stats[2];
		
		$sum_votes = 0;
		$sum_unconfirmed_votes = 0;
		$sum_score = 0;
		$sum_unconfirmed_score = 0;
		$sum_destroy_score = 0;
		$sum_unconfirmed_destroy_score = 0;
		$sum_effective_destroy_score = 0;
		$sum_unconfirmed_effective_destroy_score = 0;
		
		$stats_output = false;
		for ($option_id=0; $option_id<count($round_stats); $option_id++) {
			$option = $round_stats[$option_id];
			$stats_output[$option['option_id']] = '<div class=\'modal-dialog\'><div class=\'modal-content\'><div class=\'modal-header\'><h2 class=\'modal-title\'>Vote for '.$option['name'].'</h2></div><div class=\'modal-body\'><div id=\'game'.$instance_id.'_event'.$game_event_index.'_vote_option_details_'.$option['option_id'].'\'></div><div id=\'game'.$instance_id.'_event'.$game_event_index.'_vote_details_'.$option['option_id'].'\'>'.$app->vote_option_details($option, $option_id+1, $option[$game->db_game['payout_weight'].'_score'], $option['unconfirmed_'.$game->db_game['payout_weight'].'_score'], $total_vote_sum).'</div><div class=\'redtext\' id=\'game'.$instance_id.'_event'.$game_event_index.'_vote_error_'.$option['option_id'].'\'></div></div><div class=\'modal-footer\'><button class=\'btn btn-primary\' id=\'game'.$instance_id.'_event'.$game_event_index.'_vote_confirm_btn_'.$option['option_id'].'\' onclick=\'games['.$instance_id.'].add_option_to_vote('.$game_event_index.', '.$option['option_id'].', "'.$option['name'].'");\'>Add '.$option['name'].' to my vote</button><button type=\'button\' class=\'btn btn-default\' data-dismiss=\'modal\'>Close</button></div></div></div>';
			
			$option_identifier = "games[".$instance_id."].events[".$game_event_index."].options[".$option['event_option_index']."]";
			$set_options_js .= $option_identifier.".votes = ".$option['votes'].";\n";
			$set_options_js .= $option_identifier.".unconfirmed_votes = ".$option['unconfirmed_votes'].";\n";
			$set_options_js .= $option_identifier.".score = ".$option[$game->db_game['payout_weight'].'_score'].";\n";
			$set_options_js .= $option_identifier.".unconfirmed_score = ".$option['unconfirmed_'.$game->db_game['payout_weight'].'_score'].";\n";
			$set_options_js .= $option_identifier.".destroy_score = ".$option['destroy_score'].";\n";
			$set_options_js .= $option_identifier.".unconfirmed_destroy_score = ".$option['unconfirmed_destroy_score'].";\n";
			$set_options_js .= $option_identifier.".effective_destroy_score = ".$option['effective_destroy_score'].";\n";
			$set_options_js .= $option_identifier.".unconfirmed_effective_destroy_score = ".$option['unconfirmed_effective_destroy_score'].";\n";
			
			$sum_votes += $option['votes'];
			$sum_unconfirmed_votes += $option['unconfirmed_votes'];
			$sum_score += $option[$game->db_game['payout_weight'].'_score'];
			$sum_unconfirmed_score += $option['unconfirmed_'.$game->db_game['payout_weight'].'_score'];
			$sum_destroy_score += $option['destroy_score'];
			$sum_unconfirmed_destroy_score += $option['unconfirmed_destroy_score'];
			$sum_effective_destroy_score += $option['effective_destroy_score'];
			$sum_unconfirmed_effective_destroy_score += $option['unconfirmed_effective_destroy_score'];
		}
		$set_options_js .= "games[".$instance_id."].events[".$game_event_index."].sum_votes = $sum_votes;\n";
		$set_options_js .= "games[".$instance_id."].events[".$game_event_index."].sum_unconfirmed_votes = $sum_unconfirmed_votes;\n";
		$set_options_js .= "games[".$instance_id."].events[".$game_event_index."].sum_score = $sum_score;\n";
		$set_options_js .= "games[".$instance_id."].events[".$game_event_index."].sum_unconfirmed_score = $sum_unconfirmed_score;\n";
		$set_options_js .= "games[".$instance_id."].events[".$game_event_index."].sum_destroy_score = $sum_destroy_score;\n";
		$set_options_js .= "games[".$instance_id."].events[".$game_event_index."].sum_unconfirmed_destroy_score = $sum_unconfirmed_destroy_score;\n";
		$set_options_js .= "games[".$instance_id."].events[".$game_event_index."].sum_effective_destroy_score = $sum_effective_destroy_score;\n";
		$set_options_js .= "games[".$instance_id."].events[".$game_event_index."].sum_unconfirmed_effective_destroy_score = $sum_unconfirmed_effective_destroy_score;\n";
		
		$output['vote_option_details'][$game_event_index] = $stats_output;
		$output['set_options_js'] = $set_options_js;
	}
	
	if ($mature_game_io_ids_csv != $_REQUEST['mature_game_io_ids_csv'] || !empty($output['new_block'])) {
		$output['select_input_buttons'] = $thisuser? $game->select_input_buttons($user_game) : "";
		$output['mature_game_io_ids_csv'] = $mature_game_io_ids_csv;
		$output['new_mature_ios'] = 1;
	}
	else $output['new_mature_ios'] = 0;
	
	if ($thisuser) {
		$q = "SELECT * FROM user_messages WHERE game_id='".$game->db_game['game_id']."' AND to_user_id='".$thisuser->db_user['user_id']."' AND seen=0 GROUP BY from_user_id;";
		$r = $app->run_query($q);
		if ($r->rowCount() > 0) {
			$output['new_messages'] = 1;
			$output['new_message_user_ids'] = "";
			while ($thread = $r->fetch()) {
				$output['new_message_user_ids'] .= $thread['from_user_id'].",";
			}
			$output['new_message_user_ids'] = substr($output['new_message_user_ids'], 0, strlen($output['new_message_user_ids'])-1);
		}
		else $output['new_messages'] = 0;
	}
	else $output['new_messages'] = 0;
	
	echo json_encode($output);
}
else echo "0";
?>
