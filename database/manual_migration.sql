-- Manual migration script for Calcio database
CREATE DATABASE IF NOT EXISTS calcio;
USE calcio;

-- Create migrations table
CREATE TABLE IF NOT EXISTS migrations (
    id int(10) unsigned NOT NULL AUTO_INCREMENT,
    migration varchar(255) NOT NULL,
    batch int(11) NOT NULL,
    PRIMARY KEY (id)
);

-- Create teams table
CREATE TABLE IF NOT EXISTS teams (
    id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
    name varchar(255) NOT NULL,
    short_name varchar(255) DEFAULT NULL,
    logo varchar(255) DEFAULT NULL,
    external_id varchar(255) NOT NULL,
    country varchar(255) DEFAULT NULL,
    league varchar(255) DEFAULT NULL,
    is_active tinyint(1) NOT NULL DEFAULT 1,
    created_at timestamp NULL DEFAULT NULL,
    updated_at timestamp NULL DEFAULT NULL,
    PRIMARY KEY (id),
    UNIQUE KEY teams_external_id_unique (external_id)
);

-- Create matches table
CREATE TABLE IF NOT EXISTS matches (
    id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
    home_team_id bigint(20) unsigned NOT NULL,
    away_team_id bigint(20) unsigned NOT NULL,
    external_id varchar(255) NOT NULL,
    match_date datetime NOT NULL,
    home_goals int(11) DEFAULT NULL,
    away_goals int(11) DEFAULT NULL,
    status varchar(255) NOT NULL DEFAULT 'scheduled',
    league varchar(255) DEFAULT NULL,
    season varchar(255) DEFAULT NULL,
    round int(11) DEFAULT NULL,
    odds json DEFAULT NULL,
    created_at timestamp NULL DEFAULT NULL,
    updated_at timestamp NULL DEFAULT NULL,
    PRIMARY KEY (id),
    UNIQUE KEY matches_external_id_unique (external_id),
    KEY matches_home_team_id_foreign (home_team_id),
    KEY matches_away_team_id_foreign (away_team_id),
    CONSTRAINT matches_home_team_id_foreign FOREIGN KEY (home_team_id) REFERENCES teams (id),
    CONSTRAINT matches_away_team_id_foreign FOREIGN KEY (away_team_id) REFERENCES teams (id)
);

-- Create team_statistics table
CREATE TABLE IF NOT EXISTS team_statistics (
    id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
    team_id bigint(20) unsigned NOT NULL,
    season varchar(255) NOT NULL,
    matches_played int(11) NOT NULL DEFAULT 0,
    wins int(11) NOT NULL DEFAULT 0,
    draws int(11) NOT NULL DEFAULT 0,
    losses int(11) NOT NULL DEFAULT 0,
    goals_for int(11) NOT NULL DEFAULT 0,
    goals_against int(11) NOT NULL DEFAULT 0,
    goals_difference int(11) NOT NULL DEFAULT 0,
    points int(11) NOT NULL DEFAULT 0,
    avg_goals_for decimal(3,2) NOT NULL DEFAULT 0.00,
    avg_goals_against decimal(3,2) NOT NULL DEFAULT 0.00,
    form json DEFAULT NULL,
    home_stats json DEFAULT NULL,
    away_stats json DEFAULT NULL,
    created_at timestamp NULL DEFAULT NULL,
    updated_at timestamp NULL DEFAULT NULL,
    PRIMARY KEY (id),
    UNIQUE KEY team_statistics_team_id_season_unique (team_id,season),
    KEY team_statistics_team_id_foreign (team_id),
    CONSTRAINT team_statistics_team_id_foreign FOREIGN KEY (team_id) REFERENCES teams (id)
);

-- Create match_predictions table
CREATE TABLE IF NOT EXISTS match_predictions (
    id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
    match_id bigint(20) unsigned NOT NULL,
    home_goals_prediction decimal(3,2) DEFAULT NULL,
    away_goals_prediction decimal(3,2) DEFAULT NULL,
    home_win_probability decimal(5,4) DEFAULT NULL,
    draw_probability decimal(5,4) DEFAULT NULL,
    away_win_probability decimal(5,4) DEFAULT NULL,
    predicted_outcome varchar(255) DEFAULT NULL,
    confidence_score decimal(5,4) DEFAULT NULL,
    model_version varchar(255) DEFAULT NULL,
    features_used json DEFAULT NULL,
    predicted_at datetime NOT NULL,
    is_correct tinyint(1) DEFAULT NULL,
    created_at timestamp NULL DEFAULT NULL,
    updated_at timestamp NULL DEFAULT NULL,
    PRIMARY KEY (id),
    KEY match_predictions_match_id_foreign (match_id),
    CONSTRAINT match_predictions_match_id_foreign FOREIGN KEY (match_id) REFERENCES matches (id)
);

-- Insert migration records
INSERT IGNORE INTO migrations (migration, batch) VALUES
('0001_01_01_000000_create_users_table', 1),
('0001_01_01_000001_create_cache_table', 1),
('0001_01_01_000002_create_jobs_table', 1),
('2024_01_01_000003_create_teams_table', 2),
('2024_01_01_000004_create_matches_table', 2),
('2024_01_01_000005_create_team_statistics_table', 2),
('2024_01_01_000006_create_match_predictions_table', 2);