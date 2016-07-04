<?php
date_default_timezone_set('America/Chicago');

include("mysql_config.php");
include("pageview_functions.php");

mysql_connect($server, $user, $password) or die("<H3>Server unreachable</H3>");
mysql_select_db($database) or die ( "<H3>Database non existent</H3>");

function run_query($query) {
	$result = mysql_query($query) or die("Error in query: ".$query.", ".mysql_error());
	return $result;
}

function make_alphanumeric($string, $extrachars) {
	$string = strtolower($string);
	$allowed_chars = "0123456789abcdefghijklmnopqrstuvwxyz".$extrachars;
	$new_string = "";
	
	for ($i=0; $i<strlen($string); $i++) {
		if (is_numeric(strpos($allowed_chars, $string[$i])))
			$new_string .= $string[$i];
	}
	return $new_string;
}

function random_string($length) {
    $characters = "0123456789abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ";
    $string ="";

    for ($p = 0; $p < $length; $p++) {
        $string .= $characters[mt_rand(0, strlen($characters)-1)];
    }

    return $string;
}

function recaptcha_check_answer($recaptcha_privatekey, $ip_address, $g_recaptcha_response) {
	$response = json_decode(file_get_contents("https://www.google.com/recaptcha/api/siteverify?secret=".$recaptcha_privatekey."&response=".$g_recaptcha_response."&remoteip=".$ip_address), true);
	if ($response['success'] == false) return false;
	else return true;
}

function get_redirect_url($url) {
	$q = "SELECT * FROM redirect_urls WHERE url='".mysql_real_escape_string($url)."';";
	$r = run_query($q);
	if (mysql_numrows($r) > 0) {
		$redirect_url = mysql_fetch_array($r);
	}
	else {
		$q = "INSERT INTO redirect_urls SET url='".mysql_real_escape_string($url)."', time_created='".time()."';";
		$r = run_query($q);
		$redirect_url_id = mysql_insert_id();
		
		$q = "SELECT * FROM redirect_urls WHERE redirect_url_id='".$redirect_url_id."';";
		$r = run_query($q);
		$redirect_url = mysql_fetch_array($r);
	}
	return $redirect_url;
}

function mail_async($email, $from_name, $from, $subject, $message, $bcc, $cc) {
	$q = "INSERT INTO async_email_deliveries SET to_email='".mysql_real_escape_string($email)."', from_name='".$from_name."', from_email='".mysql_real_escape_string($from)."', subject='".mysql_real_escape_string($subject)."', message='".mysql_real_escape_string($message)."', bcc='".mysql_real_escape_string($bcc)."', cc='".mysql_real_escape_string($cc)."', time_created='".time()."';";
	$r = run_query($q);
	$delivery_id = mysql_insert_id();
	
	$command = "/usr/bin/php ".$GLOBALS['install_path']."/scripts/async_email_deliver.php ".$delivery_id." > /dev/null 2>/dev/null &";
	exec($command);
	
	return $delivery_id;
}
	
function account_coin_value($game_id, $user) {
	$q = "SELECT SUM(amount) FROM webwallet_transactions WHERE game_id='".$game_id."' AND user_id='".$user['user_id']."';";
	$r = run_query($q);
	$sum = mysql_fetch_row($r);
	
	return $sum[0];
}

function immature_balance($game_id, $user) {
	$q = "SELECT SUM(amount) FROM webwallet_transactions WHERE game_id='".$user['game_id']."' AND user_id='".$user['user_id']."' AND block_id > ".(last_block_id($game_id)-get_site_constant("maturity"))." AND amount > 0 AND transaction_desc != 'giveaway';";
	$r = run_query($q);
	$sum = mysql_fetch_row($r);
	
	return $sum[0];
}

function mature_balance($game_id, $user) {
	$account_value = account_coin_value($game_id, $user);
	$immature_balance = immature_balance($game_id, $user);
	
	return ($account_value - $immature_balance);
}

function current_block($game_id) {
	$q = "SELECT * FROM blocks WHERE game_id='".$game_id."' ORDER BY block_id DESC LIMIT 1;";
	$r = run_query($q);
	$block = mysql_fetch_array($r);
	return $block;
}

function last_block_id($game_id) {
	$block = current_block($game_id);
	return $block['block_id'];
}

function block_to_round($mining_block_id) {
	return ceil($mining_block_id/10);
}

function get_site_constant($constant_name) {
	$q = "SELECT * FROM site_constants WHERE constant_name='".$constant_name."';";
	$r = run_query($q);
	if (mysql_numrows($r) == 1) {
		$constant = mysql_fetch_array($r);
		return $constant['constant_value'];
	}
	else return "";
}
function round_voting_stats($game_id, $round_id) {
	$last_block_id = last_block_id($game_id);
	$current_round = block_to_round($last_block_id+1);
	
	if ($round_id == $current_round) {
		$q = "SELECT n.*, gn.* FROM nations n, game_nations gn WHERE n.nation_id=gn.nation_id AND gn.game_id='".$game_id."' ORDER BY gn.current_vote_score DESC, n.nation_id ASC;";
		return run_query($q);
	}
	else {
		$q = "SELECT n.*, gn.* FROM webwallet_transactions t, game_nations gn, nations n WHERE gn.nation_id=n.nation_id AND t.game_id='".$game_id."' AND t.nation_id=gn.nation_id AND t.amount>0 AND t.block_id >= ".((($round_id-1)*10)+1)." AND t.block_id <= ".($round_id*10-1)." GROUP BY t.nation_id ORDER BY SUM(t.amount) DESC;";
		return run_query($q);
	}
}
function total_votes_in_round($game_id, $round_id) {
	$q = "SELECT SUM(amount) FROM webwallet_transactions WHERE game_id='".$game_id."' AND nation_id > 0 AND amount > 0 AND block_id >= ".((($round_id-1)*10)+1)." AND block_id <= ".($round_id*10-1).";";
	$r = run_query($q);
	$total_votes = mysql_fetch_row($r);
	$total_votes = $total_votes[0];
	if ($total_votes > 0) {} else $total_votes = 0;
	return $total_votes;
}
function round_voting_stats_all($game_id, $voting_round) {
	$sumVotes = 0;
	$round_voting_stats = round_voting_stats($game_id, $voting_round);
	$stats_all = "";
	$counter = 0;
	$nation_id_csv = "";
	$nation_id_to_rank = "";
	
	while ($stat = mysql_fetch_array($round_voting_stats)) {
		$stats_all[$counter] = $stat;
		$nation_id_csv .= $stat['nation_id'].",";
		$nation_id_to_rank[$stat['nation_id']] = $counter;
		$counter++;
	}
	if ($nation_id_csv != "") $nation_id_csv = substr($nation_id_csv, 0, strlen($nation_id_csv)-1);
	
	$q = "SELECT * FROM nations";
	if ($nation_id_csv != "") $q .= " WHERE nation_id NOT IN (".$nation_id_csv.")";
	$q .= " ORDER BY cached_force_multiplier DESC, nation_id ASC;";
	$r = run_query($q);
	
	while ($stat = mysql_fetch_array($r)) {
		$stat['current_vote_score'] = 0;
		$stat['coins_currently_voted'] = 0;
		$stats_all[$counter] = $stat;
		$nation_id_to_rank[$stat['nation_id']] = $counter;
		$counter++;
	}
	
	$sum_votes = total_votes_in_round($game_id, $voting_round);
	$output_arr[0] = $sum_votes;
	$output_arr[1] = floor($sum_votes*get_site_constant('max_voting_fraction'));
	$output_arr[2] = $stats_all;
	$output_arr[3] = $nation_id_to_rank;
	
	return $output_arr;
}
function current_round_table($current_round, $user, $show_intro_text) {
	$last_block_id = last_block_id($user['game_id']);
	$current_round = block_to_round($last_block_id+1);
	$block_within_round = $last_block_id%get_site_constant('round_length')+1;
	
	$html = "<div style=\"padding: 5px;\">";
	
	if ($show_intro_text) {
		$html .= "<h1>Current Rankings - Round #".$current_round."</h1>\n";
		$html .= 'Last block completed: #'.$last_block_id.', currently mining #'.($last_block_id+1).'<br/>';
		$html .= 'Current votes count towards block '.$block_within_round.'/'.get_site_constant('round_length').' in round #'.$current_round.'<br/>';
		$html .= 'Approximately '.(get_site_constant('round_length')-$last_block_id%get_site_constant('round_length'))*get_site_constant('minutes_per_block').' minutes left in this round.<br/>';
	}
	
	$html .= "<div class='row'>";
	
	$round_stats = round_voting_stats_all($user['game_id'], $current_round);
	$total_vote_sum = $round_stats[0];
	$max_vote_sum = $round_stats[1];
	$round_stats = $round_stats[2];
	$winner_nation_id = FALSE;
	
	for ($i=0; $i<count($round_stats); $i++) {
		if (!$winner_nation_id && $round_stats[$i]['coins_currently_voted'] <= $max_vote_sum && $round_stats[$i]['current_vote_score'] > 0) $winner_nation_id = $round_stats[$i]['nation_id'];
		$html .= '
		<div class="col-md-3">
			<div class="vote_nation_box';
			if ($round_stats[$i]['coins_currently_voted'] > $max_vote_sum) $html .=  " redtext";
			else if ($winner_nation_id == $round_stats[$i]['nation_id']) $html .=  " greentext";
			$html .='" id="vote_nation_'.$i.'" onmouseover="nation_selected('.$i.');" onclick="nation_selected('.$i.'); start_vote('.$round_stats[$i]['nation_id'].');">
				<input type="hidden" id="nation_id2rank_'.$round_stats[$i]['nation_id'].'" value="'.$i.'" />
				<table>
					<tr>
						<td>
							<div class="vote_nation_flag '.strtolower(str_replace(' ', '', $round_stats[$i]['name'])).'"></div>
						</td>
						<td style="width: 100%;">
							<span style="float: left;">
								<div class="vote_nation_flag_label">'.($i+1).'. '.$round_stats[$i]['name'].'</div>
							</span>
							<span style="float: right; padding-right: 5px;">';
								$pct_votes = 100*(floor(1000*$round_stats[$i]['coins_currently_voted']/$total_vote_sum)/1000);
								$html .= $pct_votes;
								$html .= '%
							</span>
						</td>
					</tr>
				</table>
			</div>
		</div>';
		
		if ($ncount%4 == 1) $html .= '</div><div class="row">';
	}
	$html .= "</div>";
	$html .= "</div>";
	return $html;
}
function performance_history($user, $from_round_id, $to_round_id) {
	$html = "";
	$q = "SELECT * FROM cached_rounds r LEFT JOIN nations n ON r.winning_nation_id=n.nation_id WHERE r.game_id='".$user['game_id']."' AND r.round_id >= ".$from_round_id." AND r.round_id <= ".$to_round_id." ORDER BY r.round_id DESC;";
	$r = run_query($q);
	while ($round = mysql_fetch_array($r)) {
		$first_voting_block_id = ($round['round_id']-1)*get_site_constant('round_length')+1;
		$last_voting_block_id = $first_voting_block_id+get_site_constant('round_length')-1;
		$vote_sum = 0;
		$details_html = "";
		
		$html .= '<div class="row" style="font-size: 13px;">';
		$html .= '<div class="col-sm-1">Round&nbsp;#'.$round['round_id'].'</div>';
		$html .= '<div class="col-sm-4">';
		if ($round['name'] != "") $html .= $round['name']." won with ".number_format($round['winning_vote_sum']/pow(10,8), 2)." EMP";
		else $html .= "No winner";
		$html .= '</div>';
		
		$default_win_text = "You didn't vote for the winning empire.";
		$win_text = $default_win_text;
		$qq = "SELECT COUNT(*), SUM(t.amount), n.* FROM webwallet_transactions t, nations n WHERE t.game_id='".$user['game_id']."' AND t.block_id >= ".$first_voting_block_id." AND t.block_id <= ".$last_voting_block_id." AND t.user_id='".$user['user_id']."' AND t.nation_id=n.nation_id GROUP BY n.nation_id;";
		$rr = run_query($qq);
		if (mysql_numrows($rr) > 0) {
			while ($nation_votes = mysql_fetch_array($rr)) {
				$vote_sum += $nation_votes['SUM(t.amount)'];
				$details_html .= '<font class="';
				if ($nation_votes['nation_id'] == $round['winning_nation_id']) {
					$win_text = "You correctly voted ".round($nation_votes['SUM(t.amount)']/pow(10,8), 2)." EMP";
					$details_html .= 'greentext';
				}
				else $details_html .= 'redtext';
				$details_html .= '">You had '.$nation_votes['COUNT(*)']." vote";
				if ($nation_votes['COUNT(*)'] != 1) $details_html .= "s";
				$details_html .= " totalling ".round($nation_votes['SUM(t.amount)']/pow(10,8), 2)." EMP for ".$nation_votes['name'];
				$details_html .= '</font><br/>';
			}
		}
		else $details_html .= "You didn't cast any votes.<br/>";
		
		$details_html .= "<a href=\"/explorer/rounds/".$round['round_id']."\">See round results</a>";
		
		$html .= '<div class="col-sm-5">';
		$html .= $win_text;
		$html .= ' <a href="" onclick="$(\'#win_details_'.$round['round_id'].'\').toggle(\'fast\'); return false;">Details</a>';
		$html .= '<div id="win_details_'.$round['round_id'].'" style="margin: 4px 0px; padding: 4px; border-radius: 5px; border: 1px solid #bbb; display: none;">';
		$html .= $details_html;
		$html .= '</div>';
		$html .= '</div>';
		
		$html .= '<div class="col-sm-2">';
		$qq = "SELECT SUM(amount) FROM webwallet_transactions WHERE game_id='".$user['game_id']."' AND block_id='".$round['payout_block_id']."' AND user_id='".$user['user_id']."' AND transaction_desc='votebase';";
		$rr = run_query($qq);
		$win_amount = mysql_fetch_row($rr);
		$win_amount = $win_amount[0]/pow(10,8);
		$html .= '<font class="';
		if ($win_amount > 0) $html .= 'greentext';
		else $html .= 'redtext';
		$html .= '">+'.number_format($win_amount, 2).' EMP</font>';
		$html .= '</div>';
		
		$html .= "</div>\n";
	}
	return $html;
}
function last_voting_transaction_id($game_id) {
	$q = "SELECT transaction_id FROM webwallet_transactions WHERE game_id='".$game_id."' AND nation_id > 0 ORDER BY transaction_id DESC LIMIT 1;";
	$r = run_query($q);
	$r = mysql_fetch_row($r);
	if ($r[0] > 0) {} else $r[0] = 0;
	return $r[0];
}
function last_transaction_id($game_id) {
	$q = "SELECT transaction_id FROM webwallet_transactions WHERE game_id='".$game_id."' ORDER BY transaction_id DESC LIMIT 1;";
	$r = run_query($q);
	$r = mysql_fetch_row($r);
	if ($r[0] > 0) {} else $r[0] = 0;
	return $r[0];
}
function wallet_text_stats($thisuser, $current_round, $last_block_id, $block_within_round, $mature_balance, $immature_balance) {
	$html = "<div class=\"row\"><div class=\"col-sm-2\">Available&nbsp;funds:</div><div class=\"col-sm-3\" style=\"text-align: right;\"><font class=\"greentext\">".number_format(floor($mature_balance/pow(10,5))/1000, 2)."</font> EmpireCoins</div></div>\n";
	$html .= "<div class=\"row\"><div class=\"col-sm-2\">Locked&nbsp;funds:</div><div class=\"col-sm-3\" style=\"text-align: right;\"><font class=\"redtext\">".number_format($immature_balance/pow(10,8), 2)."</font> EmpireCoins</div>";
	if ($immature_balance > 0) $html .= "<div class=\"col-sm-1\"><a href=\"\" onclick=\"$('#lockedfunds_details').toggle('fast'); return false;\">Details</a></div>";
	$html .= "</div>\n";
	$html .= "Last block completed: #".$last_block_id.", currently mining #".($last_block_id+1)."<br/>\n";
	$html .= "Current votes count towards block ".$block_within_round."/".get_site_constant('round_length')." in round #".$current_round."<br/>\n";
	
	if ($immature_balance > 0) {
		$q = "SELECT * FROM webwallet_transactions t LEFT JOIN nations n ON t.nation_id=n.nation_id WHERE t.game_id='".$thisuser['game_id']."' AND t.amount > 0 AND t.user_id='".$thisuser['user_id']."' AND t.block_id > ".(last_block_id($thisuser['game_id']) - get_site_constant('maturity'))." AND t.transaction_desc != 'giveaway' ORDER BY t.block_id ASC, t.transaction_id ASC;";
		$r = run_query($q);
		
		$html .= "<div style='display: none; border: 1px solid #ccc; padding: 8px; border-radius: 8px; margin-top: 8px;' id='lockedfunds_details'>";
		while ($next_transaction = mysql_fetch_array($r)) {
			$avail_block = get_site_constant('maturity') + $next_transaction['block_id'] + 1;
			$minutes_to_avail = ($avail_block - $last_block_id - 1)*get_site_constant("minutes_per_block");
			
			if ($next_transaction['transaction_desc'] == "votebase") $html .= "You won ";
			$html .= "<font class=\"greentext\">".round($next_transaction['amount']/(pow(10, 8)), 2)."</font> ";
			if ($next_transaction['transaction_desc'] == "votebase") $html .= "coins in block ".$next_transaction['block_id'].". Coins";
			else $html .= "coins received in block #".$next_transaction['block_id'];
			$html .= " can be spent in block #".$avail_block.". (Approximately ".$minutes_to_avail." minutes). ";
			if ($next_transaction['nation_id'] > 0) {
				$html .= "You voted for ".$next_transaction['name']." in round #".block_to_round($next_transaction['block_id']).". ";
			}
			$html .= "<br/>\n";
		}
		$html .= "</div>\n";
	}
	return $html;
}
function vote_details_general($mature_balance) {
	$html = '
	<div class="row">
		<div class="col-xs-6">Your balance:</div>
		<div class="col-xs-6 greentext">'.number_format(floor($mature_balance/pow(10,5))/1000, 2).' EMP</div>
	</div>	';
	return $html;
}
function vote_nation_details($nation, $rank, $voting_sum, $total_vote_sum, $losing_streak) {
	$html .= '
	<div class="row">
		<div class="col-xs-6">Current&nbsp;rank:</div>
		<div class="col-xs-6">'.$rank.date("S", strtotime("1/".$rank."/2015")).'</div>
	</div>
	<div class="row">
		<div class="col-xs-6">Coin&nbsp;votes:</div>
		<div class="col-xs-5">'.number_format($voting_sum/pow(10,8), 2).' EMP</div>
	</div>
	<div class="row">
		<div class="col-xs-6">Percent&nbsp;of&nbsp;votes:</div>
		<div class="col-xs-5">';
	if ($total_vote_sum > 0) $html .= (ceil(100*10000*$voting_sum/$total_vote_sum)/10000);
	else $html .= '0';
	$html .= '%</div>
	</div>
	<div class="row">
		<div class="col-xs-6">Last&nbsp;win:</div>
		<div class="col-xs-5">';
	if ($losing_streak > 0) $html .= ($losing_streak+1).'&nbsp;rounds&nbsp;ago';
	else $html .= "Last&nbsp;round";
	$html .= '
		</div>
	</div>';
	return $html;
}
function generate_user_addresses($game_id, $user_id) {
	$q = "SELECT * FROM nations n WHERE NOT EXISTS(SELECT * FROM addresses a WHERE a.user_id='".$user_id."' AND a.game_id='".$game_id."' AND a.nation_id=n.nation_id) ORDER BY n.nation_id ASC;";
	$r = run_query($q);
	while ($nation = mysql_fetch_array($r)) {
		$new_address = "E";
		$rand1 = rand(0, 1);
		$rand2 = rand(0, 1);
		if ($rand1 == 0) $new_address .= "e";
		else $new_address .= "E";
		if ($rand2 == 0) $new_address .= strtoupper($nation['address_character']);
		else $new_address .= $nation['address_character'];
		$new_address .= random_string(31);
		
		$qq = "INSERT INTO addresses SET game_id='".$game_id."', nation_id='".$nation['nation_id']."', user_id='".$user_id."', address='".$new_address."', time_created='".time()."';";
		$rr = run_query($qq);
	}
	
	$q = "SELECT * FROM addresses WHERE nation_id IS NULL AND game_id='".$game_id."' AND user_id='".$user_id."';";
	$r = run_query($q);
	if (mysql_numrows($r) == 0) {
		$new_address = "Ex";
		$new_address .= random_string(32);
		
		$qq = "INSERT INTO addresses SET game_id='".$game_id."', user_id='".$user_id."', address='".$new_address."', time_created='".time()."';";
		$rr = run_query($qq);
	}
}
function user_address_id($game_id, $user_id, $nation_id) {
	$q = "SELECT * FROM addresses WHERE game_id='".$game_id."' AND user_id='".$user_id."'";
	if ($nation_id) $q .= " AND nation_id='".$nation_id."'";
	else $q .= " AND nation_id IS NULL";
	$q .= ";";
	$r = run_query($q);
	
	if (mysql_numrows($r) > 0) {
		$address = mysql_fetch_array($r);
		return $address['address_id'];
	}
	else return false;
}

function new_webwallet_transaction($game_id, $nation_id, $amount, $user_id, $block_id, $type) {
	if (!$type || $type == "") $type = "transaction";
	
	$q = "INSERT INTO webwallet_transactions SET game_id='".$game_id."'";
	if ($nation_id) $q .= ", nation_id='".$nation_id."'";
	$q .= ", transaction_desc='".$type."', amount=".$amount.", user_id='".$user_id."', address_id='".user_address_id($game_id, $user_id, $nation_id)."', block_id='".$block_id."', time_created='".time()."';";
	$r = run_query($q);
	$transaction_id = mysql_insert_id();
	
	if ($type != "giveaway" && $type != "votebase") {
		$q = "INSERT INTO webwallet_transactions SET game_id='".$game_id."', transaction_desc='transaction', amount=".(-1)*$amount.", user_id='".$user_id."', block_id='".$block_id."', time_created='".time()."';";
		$r = run_query($q);
	}
	$round_id = block_to_round($block_id);
	
	if ($nation_id > 0) {
		$q = "UPDATE game_nations n INNER JOIN (
			SELECT nation_id, SUM(amount) sum_amount FROM webwallet_transactions 
			WHERE game_id='".$game_id."' AND block_id >= ".((($round_id-1)*10)+1)." AND block_id <= ".($round_id*10-1)." AND amount > 0
			GROUP BY nation_id
		) tt ON n.nation_id=tt.nation_id SET n.coins_currently_voted=tt.sum_amount, n.current_vote_score=tt.sum_amount WHERE n.game_id='".$game_id."';";
		$r = run_query($q);
	}
	
	return $transaction_id;
}

function nation_score_in_round($game_id, $nation_id, $round_id) {
	$q = "SELECT SUM(amount) FROM webwallet_transactions WHERE block_id >= ".((($round_id-1)*10)+1)." AND block_id <= ".($round_id*10-1)." AND amount > 0 AND nation_id='".$nation_id."';";
	$r = run_query($q);
	$score = mysql_fetch_row($r);
	return $score[0];
}

function my_votes_table($round_id, $user) {
	$html = "";
	$q = "SELECT n.*, SUM(t.amount) FROM webwallet_transactions t, nations n WHERE t.game_id='".$user['game_id']."' AND t.nation_id=n.nation_id AND t.block_id >= ".((($round_id-1)*10)+1)." AND t.block_id <= ".($round_id*10-1)." AND t.user_id='".$user['user_id']."' AND t.amount > 0 GROUP BY t.nation_id ORDER BY SUM(t.amount) DESC;";
	$r = run_query($q);
	if (mysql_numrows($r) > 0) {
		$html .= "<div style=\"border: 1px solid #ddd; padding: 5px;\">";
		$html .= "<div class=\"row\" style=\"font-weight: bold;\">";
		$html .= "<div class=\"col-sm-4\">Nation</div>";
		$html .= "<div class=\"col-sm-4\">Amount</div>";
		$html .= "<div class=\"col-sm-4\">Payout</div>";
		$html .= "</div>\n";
		while ($my_vote = mysql_fetch_array($r)) {
			$expected_payout = floor(750*pow(10,8)*($my_vote['SUM(t.amount)']/nation_score_in_round($user['game_id'], $my_vote['nation_id'], $round_id)))/pow(10,8);
			$html .= "<div class=\"row\">";
			$html .= "<div class=\"col-sm-4\">".$my_vote['name']."</div>";
			$html .= "<div class=\"col-sm-4 greentext\">".number_format(round($my_vote['SUM(t.amount)']/pow(10,8), 5))." EMP</div>";
			$html .= "<div class=\"col-sm-4 greentext\">+".number_format(round($expected_payout, 5))." EMP</div>";
			$html .= "</div>\n";
		}
		$html .= "</div>";
	}
	else {
		$html .= "You haven't voted yet in this round.";
	}
	return $html;
}
function set_user_active($user_id) {
	$q = "UPDATE users SET logged_in=1, last_active='".time()."' WHERE user_id='".$user_id."';";
	$r = run_query($q);
}
function initialize_vote_nation_details($game_id, $nation_id2rank, $total_vote_sum) {
	$html = "";
	$nation_q = "SELECT * FROM game_nations WHERE game_id='".$game_id."' ORDER BY nation_id ASC;";
	$nation_r = run_query($nation_q);
	
	$nation_id = 0;
	while ($nation = mysql_fetch_array($nation_r)) {
		$rank = $nation_id2rank[$nation['nation_id']]+1;
		$voting_sum = $nation['coins_currently_voted'];
		$html .= '
		<div style="display: none;" class="modal fade" id="vote_confirm_'.$nation['nation_id'].'">
			<div class="modal-dialog">
				<div class="modal-content">
					<div class="modal-body">
						<h2>Vote for '.$nation['name'].'</h2>
						<div id="vote_nation_details_'.$nation['nation_id'].'">
							'.vote_nation_details($nation, $rank, $voting_sum, $total_vote_sum, $nation['losing_streak']).'
						</div>
						<div id="vote_details_'.$nation['nation_id'].'"></div>
						<br/>
						How many EmpireCoins do you want to vote?<br/>
						<div class="row">
							<div class="col-xs-4">
								Amount:
							</div>
							<div class="col-sm-4">
								<input type="text" class="responsive_input" placeholder="0.00" size="10" id="vote_amount_'.$nation['nation_id'].'" />
							</div>
							<div class="col-sm-4">
								EmpireCoins
							</div>
						</div>
						<div class="redtext" id="vote_error_'.$nation['nation_id'].'"></div>
					</div>
					<div class="modal-footer">
						<button class="btn btn-primary" id="vote_confirm_btn_'.$nation['nation_id'].'" onclick="confirm_vote('.$nation['nation_id'].');">Confirm Vote</button>
						<button type="button" class="btn btn-default" data-dismiss="modal">Close</button>
					</div>
				</div>
			</div>
		</div>';
		$n_counter++;
	}
	return $html;
}
function try_apply_invite_key($game_id, $user_id, $invite_key) {
	$invite_key = mysql_real_escape_string($invite_key);
	
	$q = "SELECT * FROM invitations WHERE invitation_key='".$invite_key."';";
	$r = run_query($q);
	if (mysql_numrows($r) == 1) {
		$invitation = mysql_fetch_array($r);
		
		if ($invitation['used'] == 0 && $invitation['used_user_id'] == "" && $invitation['used_time'] == 0) {
			$qq = "UPDATE invitations SET used_user_id='".$user_id."' WHERE invitation_id='".$invitation['invitation_id']."';";
			$rr = run_query($qq);
			return true;
		}
		else return false;
	}
	else return false;
}
function ensure_user_in_game($user_id, $game_id) {
	$q = "SELECT * FROM user_games WHERE user_id='".$user_id."' AND game_id='".$game_id."';";
	$r = run_query($q);
	if (mysql_numrows($r) == 0) {
		$q = "INSERT INTO user_games SET user_id='".$user_id."', game_id='".$game_id."';";
		$r = run_query($q);
	}
}
?>