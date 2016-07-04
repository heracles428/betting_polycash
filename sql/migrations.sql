ALTER TABLE `games` ADD `invoice_address_id` INT(11) NULL DEFAULT NULL AFTER `option_group_id`;
ALTER TABLE `games` ADD `completion_datetime` DATETIME NULL DEFAULT NULL AFTER `start_datetime`;
ALTER TABLE `games` ADD `payout_reminder_datetime` DATETIME NULL DEFAULT NULL AFTER `completion_datetime`;
ALTER TABLE `games` ADD `payout_complete` TINYINT(1) NOT NULL DEFAULT '0' AFTER `payout_reminder_datetime`;
ALTER TABLE `users` ADD `bitcoin_address_id` INT(11) NULL DEFAULT NULL AFTER `game_id`;
ALTER TABLE `user_games` ADD `bitcoin_address_id` INT(11) NULL DEFAULT NULL ;
ALTER TABLE `games` ADD `payout_tx_hash` VARCHAR(255) NOT NULL DEFAULT '' AFTER `payout_complete`;
ALTER TABLE `users` ADD `authorized_games` INT(11) NOT NULL DEFAULT '0' ;
ALTER TABLE `games` ADD `buyin_policy` ENUM('unlimited','per_user_cap','game_cap','game_and_user_cap','none') NOT NULL DEFAULT 'none' AFTER `payout_weight`;
ALTER TABLE `games` DROP `buyins_allowed`;
ALTER TABLE `games` ADD `per_user_buyin_cap` DECIMAL(16,8) NOT NULL DEFAULT '0' AFTER `buyin_policy`;
ALTER TABLE `games` ADD `game_buyin_cap` DECIMAL(16,8) NOT NULL DEFAULT '0' AFTER `per_user_buyin_cap`;
ALTER TABLE `user_games` ADD `buyin_invoice_address_id` INT(11) NULL DEFAULT NULL ;
ALTER TABLE `game_giveaways` ADD `type` ENUM('initial_purchase','buyin') NOT NULL DEFAULT 'initial_purchase' ;
ALTER TABLE `game_giveaways` ADD `amount` BIGINT(20) NOT NULL DEFAULT '0' ;
UPDATE game_giveaways gg JOIN games ga ON gg.game_id=ga.game_id SET gg.amount=ga.giveaway_amount WHERE gg.type='initial_purchase';
ALTER TABLE `game_buyins` ADD INDEX (`pay_currency_id`);
ALTER TABLE `game_buyins` ADD INDEX (`settle_currency_id`);
ALTER TABLE `game_buyins` ADD INDEX (`user_id`);
ALTER TABLE `game_buyins` ADD INDEX (`game_id`);
ALTER TABLE `game_buyins` ADD INDEX (`invoice_address_id`);
ALTER TABLE `game_buyins` ADD INDEX (`giveaway_id`);
ALTER TABLE `game_buyins` ADD INDEX (`status`);
ALTER TABLE `games` CHANGE `invite_cost` `invite_cost` DECIMAL(16,8) NOT NULL DEFAULT '0.00';
ALTER TABLE `games` ADD `invitation_link` VARCHAR(200) NOT NULL DEFAULT '' AFTER `option_name_plural`;
CREATE TABLE IF NOT EXISTS `images` (
  `image_id` int(11) NOT NULL AUTO_INCREMENT,
  `access_key` varchar(50) NOT NULL DEFAULT '',
  `extension` varchar(10) NOT NULL DEFAULT '',
  PRIMARY KEY (`image_id`)
) ENGINE=InnoDB DEFAULT CHARSET=latin1;
INSERT INTO `images` (`image_id`, `access_key`, `extension`) VALUES
(1, 'CbGULeWBFdFHjLoE', 'jpg'),
(2, 'cAmH53sosXKTIans', 'jpg'),
(3, 'PJkb84shHd5JkZJN', 'jpg'),
(4, 'w5uNMflPjHZ2soyH', 'jpg'),
(5, 'ZGkf0Pn54OqNpHCG', 'jpg'),
(6, 'Xr38svRwT87qoHz5', 'jpg'),
(7, 'Xcqtmp7JMtPIXYKp', 'jpg'),
(8, '7ZbyYnuHAuqvZMeg', 'jpg'),
(9, 'QUetoFCnsqawYqta', 'jpg'),
(10, '8JNwcRyNX8jDCDFS', 'jpg'),
(11, '9SJzffH1p8QXSTQD', 'jpg'),
(12, 'pytNrzMUbLHm7404', 'jpg'),
(13, 'Clqh36lP7eLWXYJd', 'jpg'),
(14, 'jBxIsTQ7iVy7aaHO', 'jpg'),
(15, 'k49ZaVs16GC3UYRV', 'jpg'),
(16, 'hNe7REoWmxiWzSvP', 'jpg');
ALTER TABLE `voting_options` ADD `default_image_id` INT(11) NULL DEFAULT NULL ;
UPDATE `voting_options` SET default_image_id=voting_option_id WHERE voting_option_id<=16;
ALTER TABLE `game_voting_options` ADD `image_id` INT(11) NULL DEFAULT NULL AFTER `voting_option_id`;
UPDATE game_voting_options gvo JOIN voting_options vo ON gvo.voting_option_id=vo.voting_option_id SET gvo.image_id=vo.default_image_id;
ALTER TABLE `empirecoin`.`game_voting_options` ADD INDEX (`voting_option_id`);
ALTER TABLE `empirecoin`.`game_voting_options` ADD INDEX (`image_id`);
ALTER TABLE `user_games` ADD `show_planned_votes` TINYINT(1) NOT NULL DEFAULT '0' ;
ALTER TABLE `game_type_variations` ADD `url_identifier` VARCHAR(100) NOT NULL DEFAULT '' AFTER `pos_reward`;
ALTER TABLE `game_type_variations`  ADD `buyin_policy` ENUM('unlimited','per_user_cap','game_cap','game_and_user_cap','none') NOT NULL DEFAULT 'none'  AFTER `giveaway_amount`,  ADD `game_buyin_cap` DECIMAL(16,8) NOT NULL DEFAULT '0'  AFTER `buyin_policy`,  ADD `per_user_buyin_cap` DECIMAL(16,8) NOT NULL DEFAULT '0'  AFTER `game_buyin_cap`;
CREATE TABLE IF NOT EXISTS `game_join_requests` (
  `join_request_id` int(11) NOT NULL AUTO_INCREMENT,
  `user_id` int(11) DEFAULT NULL,
  `variation_id` int(11) DEFAULT NULL,
  `game_id` int(11) DEFAULT NULL,
  `request_status` enum('outstanding','complete','canceled') NOT NULL DEFAULT 'outstanding',
  `time_requested` int(20) NOT NULL,
  PRIMARY KEY (`join_request_id`),
  KEY `user_id` (`user_id`),
  KEY `variation_id` (`variation_id`),
  KEY `game_id` (`game_id`),
  KEY `request_status` (`request_status`)
) ENGINE=InnoDB  DEFAULT CHARSET=latin1;

ALTER TABLE `currencies` ADD INDEX (`oracle_url_id`);
ALTER TABLE `currencies` ADD INDEX (`abbreviation`);
ALTER TABLE `games` ADD `payout_taper_function` ENUM('constant','linear_decrease') NOT NULL DEFAULT 'constant' AFTER `payout_weight`;
