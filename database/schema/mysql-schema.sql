/*!40103 SET @OLD_TIME_ZONE=@@TIME_ZONE */;
/*!40103 SET TIME_ZONE='+00:00' */;
/*!40014 SET @OLD_UNIQUE_CHECKS=@@UNIQUE_CHECKS, UNIQUE_CHECKS=0 */;
/*!40014 SET @OLD_FOREIGN_KEY_CHECKS=@@FOREIGN_KEY_CHECKS, FOREIGN_KEY_CHECKS=0 */;
/*!40101 SET @OLD_SQL_MODE=@@SQL_MODE, SQL_MODE='NO_AUTO_VALUE_ON_ZERO' */;
/*!40111 SET @OLD_SQL_NOTES=@@SQL_NOTES, SQL_NOTES=0 */;
DROP TABLE IF EXISTS `bets`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `bets` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `budget_configuration_id` bigint unsigned NOT NULL,
  `match_id` bigint unsigned NOT NULL,
  `amount` decimal(10,2) NOT NULL,
  `odds` decimal(8,3) NOT NULL,
  `bet_type` varchar(50) COLLATE utf8mb4_unicode_ci NOT NULL,
  `potential_profit` decimal(10,2) NOT NULL,
  `status` enum('pending','won','lost','cancelled') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'pending',
  `actual_profit` decimal(10,2) DEFAULT NULL,
  `confidence` decimal(5,2) NOT NULL,
  `budget_before` decimal(10,2) NOT NULL,
  `budget_after` decimal(10,2) DEFAULT NULL,
  `sequence_step` int DEFAULT NULL,
  `notes` text COLLATE utf8mb4_unicode_ci,
  `placed_at` timestamp NOT NULL,
  `resolved_at` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_bets_budget_config_id` (`budget_configuration_id`),
  KEY `idx_bets_match_id` (`match_id`),
  KEY `idx_bets_status` (`status`),
  KEY `idx_bets_budget_status` (`budget_configuration_id`,`status`),
  KEY `idx_bets_status_placed_at` (`status`,`placed_at`),
  KEY `idx_bets_bet_type_status` (`bet_type`,`status`),
  KEY `idx_bets_created_at` (`created_at`),
  KEY `idx_bets_placed_at` (`placed_at`),
  KEY `idx_bets_resolved_at` (`resolved_at`),
  KEY `idx_bets_date_status` (`created_at`,`status`),
  CONSTRAINT `bets_ibfk_1` FOREIGN KEY (`budget_configuration_id`) REFERENCES `budget_configurations` (`id`) ON DELETE CASCADE,
  CONSTRAINT `bets_ibfk_2` FOREIGN KEY (`match_id`) REFERENCES `matches` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `betting_strategies`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `betting_strategies` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `name` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `code` varchar(50) COLLATE utf8mb4_unicode_ci NOT NULL,
  `description` text COLLATE utf8mb4_unicode_ci NOT NULL,
  `default_parameters` json NOT NULL,
  `is_active` tinyint(1) NOT NULL DEFAULT '1',
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `code` (`code`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `budget_configurations`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `budget_configurations` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `name` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `strategy` enum('mansaniello','fibonacci','martingale','fixed','percentage') COLLATE utf8mb4_unicode_ci NOT NULL,
  `initial_budget` decimal(10,2) NOT NULL,
  `current_budget` decimal(10,2) NOT NULL,
  `target_profit` decimal(10,2) DEFAULT NULL,
  `max_bet_percentage` decimal(5,2) NOT NULL DEFAULT '5.00',
  `min_confidence` decimal(5,2) NOT NULL DEFAULT '60.00',
  `strategy_parameters` json DEFAULT NULL,
  `is_active` tinyint(1) NOT NULL DEFAULT '1',
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `budget_history`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `budget_history` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `budget_configuration_id` bigint unsigned NOT NULL,
  `bet_id` bigint unsigned DEFAULT NULL,
  `amount` decimal(10,2) NOT NULL,
  `balance_before` decimal(10,2) NOT NULL,
  `balance_after` decimal(10,2) NOT NULL,
  `type` enum('bet_placed','bet_won','bet_lost','deposit','withdrawal','reset') COLLATE utf8mb4_unicode_ci NOT NULL,
  `description` text COLLATE utf8mb4_unicode_ci,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `bet_id` (`bet_id`),
  KEY `idx_budget_history_config_id` (`budget_configuration_id`),
  KEY `idx_budget_history_created_at` (`created_at`),
  KEY `idx_budget_history_config_date` (`budget_configuration_id`,`created_at`),
  CONSTRAINT `budget_history_ibfk_1` FOREIGN KEY (`budget_configuration_id`) REFERENCES `budget_configurations` (`id`) ON DELETE CASCADE,
  CONSTRAINT `budget_history_ibfk_2` FOREIGN KEY (`bet_id`) REFERENCES `bets` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `cache`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `cache` (
  `key` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `value` mediumtext COLLATE utf8mb4_unicode_ci NOT NULL,
  `expiration` int NOT NULL,
  PRIMARY KEY (`key`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `cache_locks`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `cache_locks` (
  `key` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `owner` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `expiration` int NOT NULL,
  PRIMARY KEY (`key`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `daily_pick_allocations`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `daily_pick_allocations` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `user_id` bigint unsigned NOT NULL,
  `allocation_date` date NOT NULL,
  `picks_allocated` int NOT NULL,
  `picks_used` int NOT NULL DEFAULT '0',
  `used_matches` json DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `daily_pick_allocations_user_id_allocation_date_unique` (`user_id`,`allocation_date`),
  CONSTRAINT `daily_pick_allocations_user_id_foreign` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `failed_jobs`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `failed_jobs` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `uuid` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `connection` text COLLATE utf8mb4_unicode_ci NOT NULL,
  `queue` text COLLATE utf8mb4_unicode_ci NOT NULL,
  `payload` longtext COLLATE utf8mb4_unicode_ci NOT NULL,
  `exception` longtext COLLATE utf8mb4_unicode_ci NOT NULL,
  `failed_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `failed_jobs_uuid_unique` (`uuid`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `job_batches`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `job_batches` (
  `id` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `name` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `total_jobs` int NOT NULL,
  `pending_jobs` int NOT NULL,
  `failed_jobs` int NOT NULL,
  `failed_job_ids` longtext COLLATE utf8mb4_unicode_ci NOT NULL,
  `options` mediumtext COLLATE utf8mb4_unicode_ci,
  `cancelled_at` int DEFAULT NULL,
  `created_at` int NOT NULL,
  `finished_at` int DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `jobs`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `jobs` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `queue` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `payload` longtext COLLATE utf8mb4_unicode_ci NOT NULL,
  `attempts` tinyint unsigned NOT NULL,
  `reserved_at` int unsigned DEFAULT NULL,
  `available_at` int unsigned NOT NULL,
  `created_at` int unsigned NOT NULL,
  PRIMARY KEY (`id`),
  KEY `jobs_queue_index` (`queue`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `match_predictions`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `match_predictions` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `match_id` bigint unsigned NOT NULL,
  `home_goals_prediction` decimal(3,2) DEFAULT NULL,
  `away_goals_prediction` decimal(3,2) DEFAULT NULL,
  `home_win_probability` decimal(5,4) DEFAULT NULL,
  `draw_probability` decimal(5,4) DEFAULT NULL,
  `away_win_probability` decimal(5,4) DEFAULT NULL,
  `both_teams_score_probability` decimal(8,6) DEFAULT NULL,
  `over_2_5_probability` decimal(5,4) DEFAULT NULL,
  `under_2_5_probability` decimal(5,4) DEFAULT NULL,
  `home_goals_first_half_prediction` decimal(4,2) DEFAULT NULL,
  `away_goals_first_half_prediction` decimal(4,2) DEFAULT NULL,
  `first_half_over_0_5_probability` decimal(5,4) DEFAULT NULL,
  `first_half_over_1_5_probability` decimal(5,4) DEFAULT NULL,
  `predicted_outcome` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `confidence_score` decimal(5,4) DEFAULT NULL,
  `model_version` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `features_used` json DEFAULT NULL,
  `predicted_at` datetime NOT NULL,
  `is_correct` tinyint(1) DEFAULT NULL,
  `both_teams_score_correct` tinyint(1) DEFAULT NULL,
  `over_under_correct` tinyint(1) DEFAULT NULL,
  `first_half_over_0_5_correct` tinyint(1) DEFAULT NULL,
  `first_half_over_1_5_correct` tinyint(1) DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `over_0_5_first_half_probability` decimal(5,4) DEFAULT NULL,
  `over_0_5_first_half_correct` tinyint(1) DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_predictions_match_id` (`match_id`),
  KEY `idx_predictions_is_correct` (`is_correct`),
  KEY `idx_predictions_confidence` (`confidence_score`),
  KEY `idx_predictions_predicted_at` (`predicted_at`),
  KEY `idx_predictions_correct_date` (`is_correct`,`predicted_at`),
  KEY `idx_predictions_match_version` (`match_id`,`model_version`),
  KEY `idx_predictions_outcome` (`predicted_outcome`),
  KEY `idx_predictions_predicted_at_new` (`predicted_at`),
  CONSTRAINT `match_predictions_match_id_foreign` FOREIGN KEY (`match_id`) REFERENCES `matches` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `matches`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `matches` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `home_team_id` bigint unsigned NOT NULL,
  `away_team_id` bigint unsigned NOT NULL,
  `external_id` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `match_date` datetime NOT NULL,
  `home_goals` int DEFAULT NULL,
  `away_goals` int DEFAULT NULL,
  `actual_home_goals` int DEFAULT NULL,
  `actual_away_goals` int DEFAULT NULL,
  `actual_first_half_home_goals` int DEFAULT NULL,
  `actual_first_half_away_goals` int DEFAULT NULL,
  `match_status` enum('scheduled','live','finished','postponed','cancelled') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'scheduled',
  `result_updated_at` timestamp NULL DEFAULT NULL,
  `home_goals_first_half` int DEFAULT NULL,
  `away_goals_first_half` int DEFAULT NULL,
  `status` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'scheduled',
  `league` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `season` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `round` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `odds` json DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `matches_external_id_unique` (`external_id`),
  KEY `idx_matches_status_date` (`status`,`match_date`),
  KEY `idx_matches_date_status` (`match_date`,`status`),
  KEY `idx_matches_league_status` (`league`,`status`),
  KEY `idx_matches_status_league_date` (`status`,`league`,`match_date`),
  KEY `idx_matches_home_team_id` (`home_team_id`),
  KEY `idx_matches_away_team_id` (`away_team_id`),
  KEY `idx_matches_external_id` (`external_id`),
  KEY `idx_matches_match_date` (`match_date`),
  KEY `idx_matches_teams_status` (`home_team_id`,`away_team_id`,`status`),
  KEY `idx_matches_home_team_date` (`home_team_id`,`match_date`),
  KEY `idx_matches_away_team_date` (`away_team_id`,`match_date`),
  KEY `idx_matches_teams` (`home_team_id`,`away_team_id`),
  KEY `idx_matches_league_status_new` (`league`,`status`),
  CONSTRAINT `matches_away_team_id_foreign` FOREIGN KEY (`away_team_id`) REFERENCES `teams` (`id`),
  CONSTRAINT `matches_home_team_id_foreign` FOREIGN KEY (`home_team_id`) REFERENCES `teams` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `migrations`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `migrations` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `migration` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `batch` int NOT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `password_reset_tokens`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `password_reset_tokens` (
  `email` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `token` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`email`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `payment_history`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `payment_history` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `user_id` bigint unsigned NOT NULL,
  `subscription_plan_id` bigint unsigned NOT NULL,
  `amount` decimal(8,2) NOT NULL,
  `currency` varchar(3) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'EUR',
  `status` enum('pending','completed','failed','refunded') COLLATE utf8mb4_unicode_ci NOT NULL,
  `payment_method` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `transaction_id` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `payment_data` json DEFAULT NULL,
  `processed_at` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `payment_history_transaction_id_unique` (`transaction_id`),
  KEY `payment_history_user_id_foreign` (`user_id`),
  KEY `payment_history_subscription_plan_id_foreign` (`subscription_plan_id`),
  CONSTRAINT `payment_history_subscription_plan_id_foreign` FOREIGN KEY (`subscription_plan_id`) REFERENCES `subscription_plans` (`id`),
  CONSTRAINT `payment_history_user_id_foreign` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `prediction_statistics`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `prediction_statistics` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `prediction_type` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `total_predictions` int NOT NULL DEFAULT '0',
  `correct_predictions` int NOT NULL DEFAULT '0',
  `accuracy_percentage` decimal(5,2) NOT NULL DEFAULT '0.00',
  `monthly_stats` json DEFAULT NULL,
  `league_stats` json DEFAULT NULL,
  `last_updated` datetime NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `prediction_statistics_prediction_type_unique` (`prediction_type`),
  KEY `idx_pred_stats_type` (`prediction_type`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `sessions`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `sessions` (
  `id` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `user_id` bigint unsigned DEFAULT NULL,
  `ip_address` varchar(45) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `user_agent` text COLLATE utf8mb4_unicode_ci,
  `payload` longtext COLLATE utf8mb4_unicode_ci NOT NULL,
  `last_activity` int NOT NULL,
  PRIMARY KEY (`id`),
  KEY `sessions_user_id_index` (`user_id`),
  KEY `sessions_last_activity_index` (`last_activity`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `subscription_plans`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `subscription_plans` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `name` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `slug` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `description` text COLLATE utf8mb4_unicode_ci,
  `price` decimal(8,2) NOT NULL,
  `billing_cycle` enum('monthly','yearly') COLLATE utf8mb4_unicode_ci NOT NULL,
  `features` json NOT NULL,
  `daily_picks_limit` int NOT NULL,
  `allowed_leagues` json DEFAULT NULL,
  `has_ai_analysis` tinyint(1) NOT NULL DEFAULT '0',
  `has_budget_strategies` tinyint(1) NOT NULL DEFAULT '0',
  `budget_strategies_count` int NOT NULL DEFAULT '0',
  `has_detailed_ai` tinyint(1) NOT NULL DEFAULT '0',
  `has_priority_support` tinyint(1) NOT NULL DEFAULT '0',
  `has_early_access` tinyint(1) NOT NULL DEFAULT '0',
  `is_active` tinyint(1) NOT NULL DEFAULT '1',
  `sort_order` int NOT NULL DEFAULT '0',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `subscription_plans_slug_unique` (`slug`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `team_statistics`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `team_statistics` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `team_id` bigint unsigned NOT NULL,
  `season` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `matches_played` int NOT NULL DEFAULT '0',
  `wins` int NOT NULL DEFAULT '0',
  `draws` int NOT NULL DEFAULT '0',
  `losses` int NOT NULL DEFAULT '0',
  `goals_for` int NOT NULL DEFAULT '0',
  `goals_against` int NOT NULL DEFAULT '0',
  `goals_difference` int NOT NULL DEFAULT '0',
  `points` int NOT NULL DEFAULT '0',
  `avg_goals_for` decimal(5,2) NOT NULL DEFAULT '0.00',
  `avg_goals_against` decimal(5,2) NOT NULL DEFAULT '0.00',
  `win_rate` decimal(5,2) NOT NULL DEFAULT '0.00',
  `draw_rate` decimal(5,2) NOT NULL DEFAULT '0.00',
  `loss_rate` decimal(5,2) NOT NULL DEFAULT '0.00',
  `home_matches` int NOT NULL DEFAULT '0',
  `away_matches` int NOT NULL DEFAULT '0',
  `home_wins` int NOT NULL DEFAULT '0',
  `away_wins` int NOT NULL DEFAULT '0',
  `home_draws` int NOT NULL DEFAULT '0',
  `away_draws` int NOT NULL DEFAULT '0',
  `home_losses` int NOT NULL DEFAULT '0',
  `away_losses` int NOT NULL DEFAULT '0',
  `home_goals_for` int NOT NULL DEFAULT '0',
  `home_goals_against` int NOT NULL DEFAULT '0',
  `away_goals_for` int NOT NULL DEFAULT '0',
  `away_goals_against` int NOT NULL DEFAULT '0',
  `form` json DEFAULT NULL,
  `home_stats` json DEFAULT NULL,
  `away_stats` json DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `team_statistics_team_id_season_unique` (`team_id`,`season`),
  KEY `idx_team_stats_team_id` (`team_id`),
  KEY `idx_team_stats_season` (`season`),
  KEY `idx_team_stats_team_season` (`team_id`,`season`),
  CONSTRAINT `team_statistics_team_id_foreign` FOREIGN KEY (`team_id`) REFERENCES `teams` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `teams`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `teams` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `name` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `short_name` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `logo` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `external_id` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `country` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `league` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `is_active` tinyint(1) NOT NULL DEFAULT '1',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `teams_external_id_unique` (`external_id`),
  KEY `idx_teams_external_id` (`external_id`),
  KEY `idx_teams_league` (`league`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `user_subscriptions`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `user_subscriptions` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `user_id` bigint unsigned NOT NULL,
  `subscription_plan_id` bigint unsigned NOT NULL,
  `starts_at` timestamp NOT NULL,
  `ends_at` timestamp NULL DEFAULT NULL,
  `status` enum('active','cancelled','expired','suspended') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'active',
  `price_paid` decimal(8,2) NOT NULL,
  `payment_method` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `transaction_id` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `cancelled_at` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `user_subscriptions_subscription_plan_id_foreign` (`subscription_plan_id`),
  KEY `user_subscriptions_user_id_status_index` (`user_id`,`status`),
  CONSTRAINT `user_subscriptions_subscription_plan_id_foreign` FOREIGN KEY (`subscription_plan_id`) REFERENCES `subscription_plans` (`id`) ON DELETE CASCADE,
  CONSTRAINT `user_subscriptions_user_id_foreign` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `users`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `users` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `name` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `email` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `email_verified_at` timestamp NULL DEFAULT NULL,
  `password` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `remember_token` varchar(100) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `current_subscription_id` bigint unsigned DEFAULT NULL,
  `subscription_status` enum('free','active','cancelled','expired') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'free',
  `last_login_at` timestamp NULL DEFAULT NULL,
  `timezone` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'UTC',
  `preferences` json DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `users_email_unique` (`email`),
  KEY `users_current_subscription_id_foreign` (`current_subscription_id`),
  CONSTRAINT `users_current_subscription_id_foreign` FOREIGN KEY (`current_subscription_id`) REFERENCES `user_subscriptions` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40103 SET TIME_ZONE=@OLD_TIME_ZONE */;

/*!40101 SET SQL_MODE=@OLD_SQL_MODE */;
/*!40014 SET FOREIGN_KEY_CHECKS=@OLD_FOREIGN_KEY_CHECKS */;
/*!40014 SET UNIQUE_CHECKS=@OLD_UNIQUE_CHECKS */;
/*!40111 SET SQL_NOTES=@OLD_SQL_NOTES */;

INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (1,'0001_01_01_000000_create_users_table',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (2,'0001_01_01_000001_create_cache_table',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (3,'0001_01_01_000002_create_jobs_table',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (4,'2024_01_01_000003_create_teams_table',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (5,'2024_01_01_000004_create_matches_table',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (6,'2024_01_01_000005_create_team_statistics_table',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (7,'2024_01_01_000006_create_match_predictions_table',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (8,'2025_07_25_004200_change_round_column_to_string_in_matches_table',2);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (9,'2024_01_01_000007_add_new_predictions_to_match_predictions_table',3);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (10,'2024_01_01_000008_create_prediction_statistics_table',4);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (11,'2025_07_27_023000_add_prediction_accuracy_fields_to_match_predictions_table',5);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (12,'2025_07_30_022501_add_over_0_5_first_half_to_match_predictions_table',6);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (13,'2025_08_01_053342_update_prediction_statistics_last_updated_to_datetime',7);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (14,'2025_08_01_061204_add_first_half_goals_to_matches_table',8);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (16,'2025_08_01_195803_add_performance_indexes_to_tables',7);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (17,'2025_07_28_001000_create_budget_management_tables',9);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (18,'2025_07_28_050000_add_first_half_predictions_to_match_predictions_table',10);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (19,'2025_07_30_183649_add_bet_option_to_bets_table',11);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (20,'2025_08_01_072327_consolidate_first_half_field_names',12);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (21,'2025_08_07_081058_create_subscription_system',13);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (22,'2025_08_21_054930_fix_both_teams_score_probability_precision',14);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (23,'2025_08_22_184814_update_team_statistics_precision',15);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (24,'2025_08_24_003140_add_actual_results_to_matches_table',16);
