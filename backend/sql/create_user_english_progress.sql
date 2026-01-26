CREATE TABLE IF NOT EXISTS `user_english_progress` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `user_id` int(11) NOT NULL,
  `level_id` int(11) NOT NULL,
  `is_completed` tinyint(1) DEFAULT 0,
  `fluency_score` int(11) DEFAULT 0,
  `stars` int(11) DEFAULT 0,
  `last_played_at` timestamp DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `user_level_unique` (`user_id`,`level_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
