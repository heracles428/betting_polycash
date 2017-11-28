<div id="card_login" style="display: none;">
	<script type="text/javascript">
	$(document).ready(function() {
		$("#redeem_code").mask("9999-9999-9999-9999");
	});
	</script>
	
	<h3><?php echo $login_title; ?></h3>
	
	<div class="form-group">
		<label for="issuer_card_id">Card ID:</label>
		<?php if ($ask4nameid) { ?>
		<input class="form-control" type="tel" size="6" value="" placeholder="" id="issuer_card_id" name="issuer_card_id" />
		<?php } else {
			echo $card['issuer_card_id'];
		} ?>
	</div>
	
	<div class="form-group">
		<label for="redeem_code">16 digit code:</label>
		<input type="tel" size="20" maxlength="19" class="form-control" id="redeem_code" />
	</div>
	
	<div class="form-group">
		<label for="card_account_password">Password:</label>
		<input id="card_account_password" name="password" type="password" size="25" class="form-control" maxlength="100" />
	</div>
	
	<button class="btn btn-success" onclick="card_login(false, <?php echo $card_login_card_id; ?>);">Log in</button>
</div>