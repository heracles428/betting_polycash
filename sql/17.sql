ALTER TABLE `games` ADD `escrow_address` VARCHAR(100) NULL DEFAULT NULL AFTER `rpc_password`;
ALTER TABLE `games` ADD `genesis_tx_hash` VARCHAR(100) NULL DEFAULT NULL AFTER `escrow_address`;
ALTER TABLE `games` DROP `currency_id`;
ALTER TABLE `games` ADD `base_currency_id` INT NULL DEFAULT NULL AFTER `game_type_id`;
ALTER TABLE `currencies` ADD `short_name_plural` VARCHAR(100) NOT NULL DEFAULT '' AFTER `short_name`;
UPDATE `currencies` SET `short_name_plural` = 'dollars' WHERE `currency_id` = 1;
UPDATE `currencies` SET `short_name_plural` = 'bitcoins' WHERE `currency_id` = 2;
UPDATE `currencies` SET `short_name_plural` = 'empirecoins' WHERE `currency_id` = 3;
UPDATE `currencies` SET `short_name_plural` = 'euros' WHERE `currency_id` = 4;
UPDATE `currencies` SET `short_name_plural` = 'renminbi' WHERE `currency_id` = 5;
UPDATE `currencies` SET `short_name_plural` = 'pounds' WHERE `currency_id` = 6;
UPDATE `currencies` SET `short_name_plural` = 'yen' WHERE `currency_id` = 7;
ALTER TABLE `currencies` ADD `has_blockchain` TINYINT(1) NOT NULL DEFAULT '0' AFTER `currency_id`;
UPDATE currencies SET has_blockchain=1 WHERE currency_id IN (2,3);
RENAME TABLE `invoice_addresses` TO `currency_addresses`;
ALTER TABLE `game_buyins` CHANGE `invoice_address_id` `currency_address_id` INT(11) NULL DEFAULT NULL;
ALTER TABLE `user_games` CHANGE `buyin_invoice_address_id` `buyin_currency_address_id` INT(11) NULL DEFAULT NULL;
ALTER TABLE `currency_addresses` CHANGE `invoice_address_id` `currency_address_id` INT(11) NOT NULL AUTO_INCREMENT;
ALTER TABLE `currency_invoices` CHANGE `invoice_address_id` `currency_address_id` INT(11) NULL DEFAULT NULL;
ALTER TABLE `currency_addresses` ADD `account_id` INT NULL DEFAULT NULL AFTER `currency_id`;
ALTER TABLE `currency_accounts` ADD `current_address_id` INT NOT NULL AFTER `user_id`;
ALTER TABLE `currency_invoices` DROP `settle_currency_id`, DROP `pay_price_id`, DROP `settle_price_id`, DROP `settle_amount`;
ALTER TABLE `user_games` DROP `buyin_currency_address_id`;
ALTER TABLE `currency_invoices` DROP `game_id`, DROP `user_id`;
ALTER TABLE `currency_invoices` ADD `user_game_id` INT NULL DEFAULT NULL AFTER `invoice_id`;
ALTER TABLE `user_games` ADD `current_invoice_id` INT NULL DEFAULT NULL AFTER `strategy_id`;
ALTER TABLE `currency_invoices` ADD `invoice_type` ENUM('join_buyin','buyin','') NOT NULL DEFAULT '' AFTER `currency_address_id`;
ALTER TABLE `game_giveaways` ADD `type` ENUM('join_buyin','buyin','') NOT NULL DEFAULT '' AFTER `transaction_id`;
ALTER TABLE `game_giveaways` DROP `game_id`, DROP `user_id`;
ALTER TABLE `game_giveaways` ADD `user_game_id` INT NULL DEFAULT NULL AFTER `giveaway_id`;