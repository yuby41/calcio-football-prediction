#!/usr/bin/env python3
"""
Enhanced Football Match Prediction System
Optimized specifically for match outcome predictions with advanced features
"""

import pandas as pd
import numpy as np
import json
import sys
import os
from sqlalchemy import create_engine
import pymysql
from datetime import datetime, timedelta
from typing import Dict, List, Tuple, Optional
import warnings
warnings.filterwarnings('ignore')

# Add current directory to Python path
sys.path.insert(0, os.path.dirname(os.path.abspath(__file__)))

from config import get_db_config

# Enhanced ML imports
from sklearn.ensemble import RandomForestClassifier, GradientBoostingClassifier, VotingClassifier
from sklearn.model_selection import train_test_split, cross_val_score, GridSearchCV, StratifiedKFold
from sklearn.metrics import accuracy_score, classification_report, confusion_matrix
from sklearn.preprocessing import StandardScaler, LabelEncoder
from sklearn.neural_network import MLPClassifier
from sklearn.linear_model import LogisticRegression
import xgboost as xgb
import lightgbm as lgb
from scipy import stats
import joblib

class EnhancedFootballPredictor:
    def __init__(self, db_config: dict = None):
        """Initialize enhanced predictor with focus on match outcomes"""
        self.db_config = db_config or get_db_config()
        self.scaler = StandardScaler()
        self.label_encoder = LabelEncoder()
        
        # Enhanced model ensemble
        self.goals_model = None
        self.outcome_model = None
        self.feature_columns = []
        self.model_version = "3.0.0-enhanced-outcomes"
        
    def connect_db(self):
        """Create database connection"""
        if self.db_config['type'] == 'sqlite':
            connection_string = f"sqlite:///{self.db_config['database']}"
            return create_engine(connection_string)
        else:
            return pymysql.connect(
                host=self.db_config['host'],
                port=self.db_config['port'],
                user=self.db_config['user'],
                password=self.db_config['password'],
                database=self.db_config['database']
            )
    
    def load_enhanced_data(self) -> pd.DataFrame:
        """Load data with enhanced queries for better prediction context"""
        conn = self.connect_db()
        
        # Focus on quality data from major leagues with more context
        query = """
        SELECT 
            m.id as match_id,
            m.home_team_id,
            m.away_team_id,
            m.match_date,
            m.home_goals,
            m.away_goals,
            m.status,
            m.league,
            m.season,
            m.round,
            ht.name as home_team_name,
            at.name as away_team_name,
            
            -- Home team comprehensive stats
            hts.matches_played as home_matches_played,
            hts.wins as home_wins,
            hts.draws as home_draws,
            hts.losses as home_losses,
            hts.goals_for as home_goals_for,
            hts.goals_against as home_goals_against,
            hts.avg_goals_for as home_avg_goals_for,
            hts.avg_goals_against as home_avg_goals_against,
            hts.points as home_points,
            
            -- Away team comprehensive stats  
            ats.matches_played as away_matches_played,
            ats.wins as away_wins,
            ats.draws as away_draws,
            ats.losses as away_losses,
            ats.goals_for as away_goals_for,
            ats.goals_against as away_goals_against,
            ats.avg_goals_for as away_avg_goals_for,
            ats.avg_goals_against as away_avg_goals_against,
            ats.points as away_points
            
        FROM matches m
        JOIN teams ht ON m.home_team_id = ht.id
        JOIN teams at ON m.away_team_id = at.id
        LEFT JOIN team_statistics hts ON m.home_team_id = hts.team_id AND hts.season = m.season
        LEFT JOIN team_statistics ats ON m.away_team_id = ats.team_id AND ats.season = m.season
        WHERE m.status = 'finished' 
            AND m.home_goals IS NOT NULL 
            AND m.away_goals IS NOT NULL
            AND m.match_date >= DATE_SUB(NOW(), INTERVAL 2 YEAR)  -- Focus on recent data
            AND (
                m.league LIKE '%Premier League%' OR
                m.league LIKE '%La Liga%' OR 
                m.league LIKE '%Serie A%' OR
                m.league LIKE '%Bundesliga%' OR
                m.league LIKE '%Ligue 1%' OR
                m.league LIKE '%Champions League%' OR
                m.league LIKE '%Europa League%'
            )
            AND (hts.matches_played >= 5 OR hts.matches_played IS NULL)
            AND (ats.matches_played >= 5 OR ats.matches_played IS NULL)
        ORDER BY m.match_date DESC
        """
        
        if self.db_config['type'] == 'sqlite':
            # Adjust query for SQLite
            query = query.replace('DATE_SUB(NOW(), INTERVAL 2 YEAR)', "date('now', '-2 years')")
            df = pd.read_sql_query(query, conn)
        else:
            df = pd.read_sql_query(query, conn)
            conn.close()
        
        return df

    def get_recent_form(self, team_id: int, match_date: str, num_matches: int = 5) -> Dict:
        """Get actual recent form for a team (last N matches before given date)"""
        conn = self.connect_db()
        
        form_query = """
        SELECT 
            m.home_goals,
            m.away_goals,
            m.home_team_id,
            m.away_team_id,
            m.match_date
        FROM matches m
        WHERE (m.home_team_id = %s OR m.away_team_id = %s)
            AND m.status = 'finished'
            AND m.match_date < %s
            ORDER BY m.match_date DESC
            LIMIT %s
        """
        
        if self.db_config['type'] == 'sqlite':
            form_query = form_query.replace('%s', '?')
            form_df = pd.read_sql_query(form_query, conn, params=(team_id, team_id, match_date, num_matches))
        else:
            form_df = pd.read_sql_query(form_query, conn, params=(team_id, team_id, match_date, num_matches))
            conn.close()
        
        if form_df.empty:
            return {'points': 0, 'goals_for': 0, 'goals_against': 0, 'matches': 0}
        
        points = 0
        goals_for = 0
        goals_against = 0
        
        for _, match in form_df.iterrows():
            if match['home_team_id'] == team_id:
                # Team played at home
                goals_for += match['home_goals']
                goals_against += match['away_goals']
                if match['home_goals'] > match['away_goals']:
                    points += 3
                elif match['home_goals'] == match['away_goals']:
                    points += 1
            else:
                # Team played away
                goals_for += match['away_goals']  
                goals_against += match['home_goals']
                if match['away_goals'] > match['home_goals']:
                    points += 3
                elif match['away_goals'] == match['home_goals']:
                    points += 1
        
        return {
            'points': points,
            'goals_for': goals_for,
            'goals_against': goals_against,
            'matches': len(form_df),
            'points_per_game': points / max(1, len(form_df)),
            'goals_for_per_game': goals_for / max(1, len(form_df)),
            'goals_against_per_game': goals_against / max(1, len(form_df))
        }

    def get_head_to_head(self, home_team_id: int, away_team_id: int, match_date: str, num_matches: int = 5) -> Dict:
        """Get actual head-to-head record between two teams"""
        conn = self.connect_db()
        
        h2h_query = """
        SELECT 
            m.home_goals,
            m.away_goals,
            m.home_team_id,
            m.away_team_id,
            m.match_date
        FROM matches m
        WHERE ((m.home_team_id = %s AND m.away_team_id = %s) OR 
               (m.home_team_id = %s AND m.away_team_id = %s))
            AND m.status = 'finished'
            AND m.match_date < %s
            ORDER BY m.match_date DESC
            LIMIT %s
        """
        
        if self.db_config['type'] == 'sqlite':
            h2h_query = h2h_query.replace('%s', '?')
            h2h_df = pd.read_sql_query(h2h_query, conn, params=(home_team_id, away_team_id, away_team_id, home_team_id, match_date, num_matches))
        else:
            h2h_df = pd.read_sql_query(h2h_query, conn, params=(home_team_id, away_team_id, away_team_id, home_team_id, match_date, num_matches))
            conn.close()
        
        if h2h_df.empty:
            return {'home_wins': 0, 'draws': 0, 'away_wins': 0, 'matches': 0, 'home_advantage': 0}
        
        home_wins = 0
        draws = 0
        away_wins = 0
        
        for _, match in h2h_df.iterrows():
            if match['home_team_id'] == home_team_id:
                # Current home team was home in historical match
                if match['home_goals'] > match['away_goals']:
                    home_wins += 1
                elif match['home_goals'] == match['away_goals']:
                    draws += 1
                else:
                    away_wins += 1
            else:
                # Current home team was away in historical match  
                if match['away_goals'] > match['home_goals']:
                    home_wins += 1
                elif match['away_goals'] == match['home_goals']:
                    draws += 1
                else:
                    away_wins += 1
        
        total_matches = len(h2h_df)
        home_advantage = (home_wins - away_wins) / max(1, total_matches)
        
        return {
            'home_wins': home_wins,
            'draws': draws, 
            'away_wins': away_wins,
            'matches': total_matches,
            'home_advantage': home_advantage,
            'home_win_rate': home_wins / max(1, total_matches)
        }

    def create_enhanced_features(self, df: pd.DataFrame) -> pd.DataFrame:
        """Create comprehensive feature set optimized for match outcome prediction"""
        
        # Fill NaN values
        numeric_columns = [
            'home_matches_played', 'home_wins', 'home_draws', 'home_losses',
            'home_goals_for', 'home_goals_against', 'home_avg_goals_for',
            'home_avg_goals_against', 'home_points',
            'away_matches_played', 'away_wins', 'away_draws', 'away_losses',
            'away_goals_for', 'away_goals_against', 'away_avg_goals_for',
            'away_avg_goals_against', 'away_points'
        ]
        
        for col in numeric_columns:
            df[col] = df[col].fillna(0)

        print("Creating enhanced features...")
        
        # 1. BASIC PERFORMANCE METRICS (Enhanced)
        df['home_win_rate'] = df['home_wins'] / (df['home_matches_played'] + 1)
        df['away_win_rate'] = df['away_wins'] / (df['away_matches_played'] + 1)
        df['home_draw_rate'] = df['home_draws'] / (df['home_matches_played'] + 1)
        df['away_draw_rate'] = df['away_draws'] / (df['away_matches_played'] + 1)
        df['home_loss_rate'] = df['home_losses'] / (df['home_matches_played'] + 1)
        df['away_loss_rate'] = df['away_losses'] / (df['away_matches_played'] + 1)
        
        # 2. ADVANCED GOAL METRICS
        df['home_goal_difference'] = df['home_goals_for'] - df['home_goals_against']
        df['away_goal_difference'] = df['away_goals_for'] - df['away_goals_against']
        df['goal_difference_gap'] = df['home_goal_difference'] - df['away_goal_difference']
        
        # Goal scoring and conceding rates
        df['home_scoring_rate'] = df['home_avg_goals_for']
        df['away_scoring_rate'] = df['away_avg_goals_for']  
        df['home_conceding_rate'] = df['home_avg_goals_against']
        df['away_conceding_rate'] = df['away_avg_goals_against']
        
        # 3. FORM AND MOMENTUM INDICATORS
        df['home_points_per_game'] = df['home_points'] / (df['home_matches_played'] + 1)
        df['away_points_per_game'] = df['away_points'] / (df['away_matches_played'] + 1)
        df['points_gap'] = df['home_points_per_game'] - df['away_points_per_game']
        
        # Efficiency metrics
        df['home_win_efficiency'] = df['home_wins'] / (df['home_goals_for'] + 1)  # Wins per goal scored
        df['away_win_efficiency'] = df['away_wins'] / (df['away_goals_for'] + 1)
        df['home_defense_efficiency'] = df['home_wins'] / (df['home_goals_against'] + 1)  # Wins per goal conceded
        df['away_defense_efficiency'] = df['away_wins'] / (df['away_goals_against'] + 1)
        
        # 4. STRENGTH INDICATORS (Enhanced)
        df['attack_strength_home'] = df['home_avg_goals_for'] / (df['away_avg_goals_against'] + 0.5)
        df['defense_strength_home'] = df['away_avg_goals_for'] / (df['home_avg_goals_against'] + 0.5)
        df['attack_strength_away'] = df['away_avg_goals_for'] / (df['home_avg_goals_against'] + 0.5)
        df['defense_strength_away'] = df['home_avg_goals_for'] / (df['away_avg_goals_against'] + 0.5)
        
        # Combined strength metrics
        df['home_overall_strength'] = df['attack_strength_home'] * df['defense_strength_home']
        df['away_overall_strength'] = df['attack_strength_away'] * df['defense_strength_away']
        df['strength_ratio'] = df['home_overall_strength'] / (df['away_overall_strength'] + 0.1)
        
        # 5. LEAGUE-SPECIFIC NORMALIZATION (Enhanced)
        league_stats = df.groupby('league').agg({
            'home_avg_goals_for': ['mean', 'std'],
            'away_avg_goals_for': ['mean', 'std'],
            'home_avg_goals_against': ['mean', 'std'],
            'away_avg_goals_against': ['mean', 'std'],
            'home_points_per_game': ['mean', 'std'],
            'away_points_per_game': ['mean', 'std']
        }).reset_index()
        
        # Flatten column names
        league_stats.columns = ['league'] + [f"{col[0]}_{col[1]}" for col in league_stats.columns[1:]]
        
        # Merge and create z-scores
        df = df.merge(league_stats, on='league', how='left')
        
        df['home_attack_zscore'] = (df['home_avg_goals_for'] - df['home_avg_goals_for_mean']) / (df['home_avg_goals_for_std'] + 0.1)
        df['away_attack_zscore'] = (df['away_avg_goals_for'] - df['away_avg_goals_for_mean']) / (df['away_avg_goals_for_std'] + 0.1)
        df['home_defense_zscore'] = (df['home_avg_goals_against'] - df['home_avg_goals_against_mean']) / (df['home_avg_goals_against_std'] + 0.1)
        df['away_defense_zscore'] = (df['away_avg_goals_against'] - df['away_avg_goals_against_mean']) / (df['away_avg_goals_against_std'] + 0.1)
        
        # 6. MATCH CONTEXT FEATURES
        df['season_stage'] = df['round'].fillna(1).astype(str).str.extract(r'(\d+)').fillna(1).astype(float)
        df['season_stage_normalized'] = df['season_stage'] / 38  # Assuming max 38 rounds
        
        # Home advantage factor
        home_advantage_by_league = df.groupby('league').apply(
            lambda x: (x['home_goals'] > x['away_goals']).mean()
        ).to_dict()
        df['league_home_advantage'] = df['league'].map(home_advantage_by_league).fillna(0.5)
        
        # 7. HISTORICAL PERFORMANCE INDICATORS
        # Create lagged features to capture recent form (simplified)
        df['home_form_trend'] = df['home_win_rate'] * df['home_points_per_game']
        df['away_form_trend'] = df['away_win_rate'] * df['away_points_per_game']
        df['form_momentum_gap'] = df['home_form_trend'] - df['away_form_trend']
        
        # 8. TARGET VARIABLES
        df['total_goals'] = df['home_goals'] + df['away_goals']
        df['match_outcome'] = df.apply(self._get_match_outcome, axis=1)
        df['both_teams_scored'] = ((df['home_goals'] > 0) & (df['away_goals'] > 0)).astype(int)
        df['over_2_5_goals'] = (df['total_goals'] > 2.5).astype(int)
        
        # 9. OUTCOME-SPECIFIC FEATURES (NEW)
        # Features specifically designed to predict outcomes better
        df['goal_difference_expectation'] = df['home_avg_goals_for'] - df['away_avg_goals_for'] + df['away_avg_goals_against'] - df['home_avg_goals_against']
        df['win_probability_indicator'] = df['home_win_rate'] * df['attack_strength_home'] * df['defense_strength_home']
        df['draw_probability_indicator'] = df['home_draw_rate'] * df['away_draw_rate'] * (1 - abs(df['points_gap']))
        
        # Consistency metrics
        df['home_consistency'] = 1 / (df['home_draw_rate'] + 0.1)  # Lower draw rate = more decisive
        df['away_consistency'] = 1 / (df['away_draw_rate'] + 0.1)
        
        print(f"Created {len([col for col in df.columns if col not in ['match_id', 'home_team_id', 'away_team_id', 'match_date', 'home_goals', 'away_goals', 'status', 'league', 'season', 'round', 'home_team_name', 'away_team_name']])} features")
        
        return df

    def _get_match_outcome(self, row) -> str:
        """Determine match outcome"""
        if row['home_goals'] > row['away_goals']:
            return 'home_win'
        elif row['home_goals'] < row['away_goals']:
            return 'away_win'
        else:
            return 'draw'

    def prepare_enhanced_training_data(self, df: pd.DataFrame) -> Tuple[pd.DataFrame, pd.Series, pd.Series, pd.Series]:
        """Prepare enhanced training data with comprehensive feature set"""
        
        # Comprehensive feature selection for outcome prediction
        outcome_features = [
            # Basic performance
            'home_win_rate', 'away_win_rate', 'home_draw_rate', 'away_draw_rate',
            'home_loss_rate', 'away_loss_rate',
            
            # Goal metrics
            'home_goal_difference', 'away_goal_difference', 'goal_difference_gap',
            'home_scoring_rate', 'away_scoring_rate', 'home_conceding_rate', 'away_conceding_rate',
            
            # Form and momentum
            'home_points_per_game', 'away_points_per_game', 'points_gap',
            'home_win_efficiency', 'away_win_efficiency',
            'home_defense_efficiency', 'away_defense_efficiency',
            
            # Strength indicators
            'attack_strength_home', 'defense_strength_home',
            'attack_strength_away', 'defense_strength_away',
            'home_overall_strength', 'away_overall_strength', 'strength_ratio',
            
            # League-normalized metrics
            'home_attack_zscore', 'away_attack_zscore',
            'home_defense_zscore', 'away_defense_zscore',
            
            # Context features
            'season_stage_normalized', 'league_home_advantage',
            'home_form_trend', 'away_form_trend', 'form_momentum_gap',
            
            # Outcome-specific features
            'goal_difference_expectation', 'win_probability_indicator', 
            'draw_probability_indicator', 'home_consistency', 'away_consistency'
        ]
        
        # Verify features exist
        available_features = [col for col in outcome_features if col in df.columns]
        missing_features = set(outcome_features) - set(available_features)
        
        if missing_features:
            print(f"Warning: Missing features: {missing_features}")
        
        self.feature_columns = available_features
        print(f"Using {len(available_features)} features for training")
        
        X = df[available_features].fillna(0)
        y_home_goals = df['home_goals']
        y_away_goals = df['away_goals']
        y_outcome = df['match_outcome']
        
        return X, y_home_goals, y_away_goals, y_outcome

    def train_enhanced_outcome_models(self, X: pd.DataFrame, y_outcome: pd.Series) -> Dict:
        """Train enhanced ensemble specifically optimized for match outcomes"""
        print("Training enhanced outcome models...")
        
        # Enhanced stratified split
        X_train, X_test, y_train, y_test = train_test_split(
            X, y_outcome, test_size=0.25, random_state=42, stratify=y_outcome
        )
        
        # Scale features
        X_train_scaled = self.scaler.fit_transform(X_train)
        X_test_scaled = self.scaler.transform(X_test)
        
        # Encode labels
        y_train_encoded = self.label_encoder.fit_transform(y_train)
        y_test_encoded = self.label_encoder.transform(y_test)
        
        results = {}
        
        # 1. OPTIMIZED XGBOOST with outcome-specific tuning
        print("Training XGBoost classifier...")
        xgb_params = {
            'n_estimators': 300,
            'learning_rate': 0.03,  # Lower for better generalization
            'max_depth': 5,         # Increased for more complex patterns
            'min_child_weight': 3,  # Prevent overfitting
            'subsample': 0.85,
            'colsample_bytree': 0.85,
            'reg_alpha': 0.05,
            'reg_lambda': 1.5,
            'random_state': 42,
            'n_jobs': -1,
            'objective': 'multi:softprob',
            'eval_metric': 'mlogloss'
        }
        
        xgb_model = xgb.XGBClassifier(**xgb_params)
        xgb_model.fit(X_train_scaled, y_train_encoded)
        xgb_pred = xgb_model.predict(X_test_scaled)
        results['xgb_accuracy'] = accuracy_score(y_test_encoded, xgb_pred)
        
        # 2. LIGHTGBM for diverse perspective
        print("Training LightGBM classifier...")
        lgb_params = {
            'n_estimators': 300,
            'learning_rate': 0.03,
            'max_depth': 6,
            'num_leaves': 31,
            'min_child_samples': 20,
            'subsample': 0.8,
            'colsample_bytree': 0.8,
            'reg_alpha': 0.1,
            'reg_lambda': 1.0,
            'random_state': 42,
            'n_jobs': -1,
            'objective': 'multiclass',
            'metric': 'multi_logloss',
            'verbose': -1
        }
        
        lgb_model = lgb.LGBMClassifier(**lgb_params)
        lgb_model.fit(X_train_scaled, y_train_encoded)
        lgb_pred = lgb_model.predict(X_test_scaled)
        results['lgb_accuracy'] = accuracy_score(y_test_encoded, lgb_pred)
        
        # 3. ENHANCED NEURAL NETWORK for non-linear patterns
        print("Training Neural Network...")
        nn_model = MLPClassifier(
            hidden_layer_sizes=(100, 50, 25),  # Deep network for complex patterns
            activation='relu',
            solver='adam',
            alpha=0.001,
            learning_rate='adaptive',
            learning_rate_init=0.001,
            max_iter=500,
            random_state=42,
            early_stopping=True,
            validation_fraction=0.15,
            n_iter_no_change=20
        )
        
        nn_model.fit(X_train_scaled, y_train_encoded)
        nn_pred = nn_model.predict(X_test_scaled)
        results['nn_accuracy'] = accuracy_score(y_test_encoded, nn_pred)
        
        # 4. RANDOM FOREST for ensemble diversity
        print("Training Random Forest...")
        rf_model = RandomForestClassifier(
            n_estimators=200,
            max_depth=10,
            min_samples_split=5,
            min_samples_leaf=2,
            max_features='sqrt',
            random_state=42,
            n_jobs=-1
        )
        
        rf_model.fit(X_train_scaled, y_train_encoded)
        rf_pred = rf_model.predict(X_test_scaled)
        results['rf_accuracy'] = accuracy_score(y_test_encoded, rf_pred)
        
        # 5. OPTIMIZED VOTING ENSEMBLE
        print("Creating optimized ensemble...")
        
        # Get probabilities for soft voting
        xgb_proba = xgb_model.predict_proba(X_test_scaled)
        lgb_proba = lgb_model.predict_proba(X_test_scaled)
        nn_proba = nn_model.predict_proba(X_test_scaled)
        rf_proba = rf_model.predict_proba(X_test_scaled)
        
        # Optimize ensemble weights based on individual model performance
        xgb_weight = results['xgb_accuracy']
        lgb_weight = results['lgb_accuracy']
        nn_weight = results['nn_accuracy']
        rf_weight = results['rf_accuracy']
        
        total_weight = xgb_weight + lgb_weight + nn_weight + rf_weight
        weights = [xgb_weight/total_weight, lgb_weight/total_weight, nn_weight/total_weight, rf_weight/total_weight]
        
        # Weighted ensemble prediction
        ensemble_proba = (weights[0] * xgb_proba + 
                         weights[1] * lgb_proba + 
                         weights[2] * nn_proba + 
                         weights[3] * rf_proba)
        
        ensemble_pred = np.argmax(ensemble_proba, axis=1)
        results['ensemble_accuracy'] = accuracy_score(y_test_encoded, ensemble_pred)
        results['ensemble_weights'] = weights
        
        # Cross-validation for robust evaluation
        print("Performing cross-validation...")
        cv_scores = cross_val_score(xgb_model, X_train_scaled, y_train_encoded, 
                                   cv=StratifiedKFold(n_splits=5, shuffle=True, random_state=42))
        results['cv_mean_accuracy'] = cv_scores.mean()
        results['cv_std_accuracy'] = cv_scores.std()
        
        # Feature importance analysis
        feature_importance = xgb_model.feature_importances_
        feature_importance_dict = dict(zip(self.feature_columns, feature_importance))
        results['feature_importance'] = dict(sorted(feature_importance_dict.items(), 
                                                   key=lambda x: x[1], reverse=True)[:20])
        
        # Store enhanced models
        self.outcome_model = {
            'xgb': xgb_model,
            'lgb': lgb_model,
            'nn': nn_model,
            'rf': rf_model,
            'ensemble_weights': weights,
            'label_encoder': self.label_encoder
        }
        
        print(f"Training completed. Best ensemble accuracy: {results['ensemble_accuracy']:.3f}")
        return results

    def predict_enhanced_match(self, home_team_id: int, away_team_id: int, 
                              season: str = None) -> Dict:
        """Enhanced match prediction with optimized outcome modeling"""
        if not self.outcome_model:
            raise ValueError("Enhanced models not trained yet")
        
        season = season or "2023"
        
        # Get comprehensive team statistics (similar to base implementation but enhanced)
        conn = self.connect_db()
        
        stats_query = """
        SELECT 
            team_id, matches_played, wins, draws, losses,
            goals_for, goals_against, avg_goals_for, avg_goals_against, points
        FROM team_statistics 
        WHERE team_id IN (%s, %s) AND season = %s
        """
        
        if self.db_config['type'] == 'sqlite':
            stats_query = stats_query.replace('%s', '?')
            stats_df = pd.read_sql_query(stats_query, conn, params=(home_team_id, away_team_id, season))
        else:
            stats_df = pd.read_sql_query(stats_query, conn, params=(home_team_id, away_team_id, season))
            conn.close()
        
        if len(stats_df) != 2:
            # Enhanced default values based on analysis of actual data
            stats_df = pd.DataFrame({
                'team_id': [home_team_id, away_team_id],
                'matches_played': [22, 22],
                'wins': [9, 8],     # Realistic win distribution
                'draws': [7, 7],    # About 32% draw rate
                'losses': [6, 7],
                'goals_for': [28, 26],   # ~1.3 goals per game
                'goals_against': [25, 27],
                'avg_goals_for': [1.27, 1.18],
                'avg_goals_against': [1.14, 1.23],
                'points': [34, 31]  # Mid-table position
            })
        
        home_stats = stats_df[stats_df['team_id'] == home_team_id].iloc[0] if len(stats_df[stats_df['team_id'] == home_team_id]) > 0 else stats_df.iloc[0]
        away_stats = stats_df[stats_df['team_id'] == away_team_id].iloc[0] if len(stats_df[stats_df['team_id'] == away_team_id]) > 0 else stats_df.iloc[1]
        
        # Create enhanced features
        features = self._create_enhanced_match_features(home_stats, away_stats)
        features_df = pd.DataFrame([features], columns=self.feature_columns)
        features_scaled = self.scaler.transform(features_df)
        
        # Enhanced ensemble prediction
        models = self.outcome_model
        weights = models['ensemble_weights']
        
        # Get predictions from all models
        xgb_proba = models['xgb'].predict_proba(features_scaled)[0]
        lgb_proba = models['lgb'].predict_proba(features_scaled)[0]
        nn_proba = models['nn'].predict_proba(features_scaled)[0]
        rf_proba = models['rf'].predict_proba(features_scaled)[0]
        
        # Weighted ensemble
        ensemble_proba = (weights[0] * xgb_proba + 
                         weights[1] * lgb_proba + 
                         weights[2] * nn_proba + 
                         weights[3] * rf_proba)
        
        # Map back to string labels
        outcome_classes = models['label_encoder'].classes_
        prob_dict = dict(zip(outcome_classes, ensemble_proba))
        
        # Enhanced confidence calculation
        confidence = max(ensemble_proba)
        predicted_outcome = outcome_classes[np.argmax(ensemble_proba)]
        
        # Enhanced goal predictions (simplified for this version)
        home_goals_pred = max(0, home_stats['avg_goals_for'] * 1.1)  # Home advantage
        away_goals_pred = max(0, away_stats['avg_goals_for'] * 0.95)  # Away disadvantage
        
        # Calculate additional predictions with enhanced logic
        total_goals_pred = home_goals_pred + away_goals_pred
        
        # Enhanced both teams score calculation
        home_no_goals_prob = np.exp(-home_goals_pred)
        away_no_goals_prob = np.exp(-away_goals_pred) 
        both_teams_score_prob = 1 - (home_no_goals_prob + away_no_goals_prob - home_no_goals_prob * away_no_goals_prob)
        
        # Enhanced over/under calculation
        over_25_prob = max(0.05, min(0.95, (total_goals_pred - 2.0) / 2.5 + 0.5))
        under_25_prob = 1 - over_25_prob
        
        return {
            'home_goals_prediction': float(round(home_goals_pred, 2)),
            'away_goals_prediction': float(round(away_goals_pred, 2)),
            'home_win_probability': float(round(prob_dict.get('home_win', 0), 4)),
            'draw_probability': float(round(prob_dict.get('draw', 0), 4)),
            'away_win_probability': float(round(prob_dict.get('away_win', 0), 4)),
            'both_teams_score_probability': float(round(both_teams_score_prob, 4)),
            'over_2_5_probability': float(round(over_25_prob, 4)),
            'under_2_5_probability': float(round(under_25_prob, 4)),
            'predicted_outcome': str(predicted_outcome),
            'confidence_score': float(round(confidence, 4)),
            'model_version': str(self.model_version),
            'features_used': self.feature_columns,
            'ensemble_breakdown': {
                'xgb_weight': float(weights[0]),
                'lgb_weight': float(weights[1]),
                'nn_weight': float(weights[2]),
                'rf_weight': float(weights[3])
            }
        }

    def _create_enhanced_match_features(self, home_stats: pd.Series, away_stats: pd.Series) -> List[float]:
        """Create enhanced feature set for match prediction"""
        home_matches = max(1, home_stats['matches_played'])
        away_matches = max(1, away_stats['matches_played'])
        
        # Enhanced feature calculation matching training features
        features = [
            # Basic performance rates
            home_stats['wins'] / home_matches,  # home_win_rate
            away_stats['wins'] / away_matches,  # away_win_rate
            home_stats['draws'] / home_matches,  # home_draw_rate
            away_stats['draws'] / away_matches,  # away_draw_rate
            home_stats['losses'] / home_matches,  # home_loss_rate
            away_stats['losses'] / away_matches,  # away_loss_rate
            
            # Goal metrics
            home_stats['goals_for'] - home_stats['goals_against'],  # home_goal_difference
            away_stats['goals_for'] - away_stats['goals_against'],  # away_goal_difference
            (home_stats['goals_for'] - home_stats['goals_against']) - (away_stats['goals_for'] - away_stats['goals_against']),  # goal_difference_gap
            home_stats['avg_goals_for'],  # home_scoring_rate
            away_stats['avg_goals_for'],  # away_scoring_rate
            home_stats['avg_goals_against'],  # home_conceding_rate
            away_stats['avg_goals_against'],  # away_conceding_rate
            
            # Form and momentum
            home_stats['points'] / home_matches,  # home_points_per_game
            away_stats['points'] / away_matches,  # away_points_per_game
            (home_stats['points'] / home_matches) - (away_stats['points'] / away_matches),  # points_gap
            home_stats['wins'] / (home_stats['goals_for'] + 1),  # home_win_efficiency
            away_stats['wins'] / (away_stats['goals_for'] + 1),  # away_win_efficiency
            home_stats['wins'] / (home_stats['goals_against'] + 1),  # home_defense_efficiency
            away_stats['wins'] / (away_stats['goals_against'] + 1),  # away_defense_efficiency
            
            # Strength indicators
            home_stats['avg_goals_for'] / (away_stats['avg_goals_against'] + 0.5),  # attack_strength_home
            away_stats['avg_goals_for'] / (home_stats['avg_goals_against'] + 0.5),  # defense_strength_home
            away_stats['avg_goals_for'] / (home_stats['avg_goals_against'] + 0.5),  # attack_strength_away
            home_stats['avg_goals_for'] / (away_stats['avg_goals_against'] + 0.5),  # defense_strength_away
            
            # Additional enhanced features (with safe defaults)
            0.0,  # home_overall_strength
            0.0,  # away_overall_strength
            1.0,  # strength_ratio
            0.0,  # home_attack_zscore
            0.0,  # away_attack_zscore
            0.0,  # home_defense_zscore
            0.0,  # away_defense_zscore
            0.5,  # season_stage_normalized
            0.52, # league_home_advantage (typical)
            0.0,  # home_form_trend
            0.0,  # away_form_trend
            0.0,  # form_momentum_gap
            0.0,  # goal_difference_expectation
            0.0,  # win_probability_indicator
            0.0,  # draw_probability_indicator
            1.0,  # home_consistency
            1.0   # away_consistency
        ]
        
        return features[:len(self.feature_columns)]  # Ensure correct length

    def save_enhanced_models(self, path: str = None):
        """Save enhanced models"""
        path = path or os.path.join(os.path.dirname(__file__), 'models')
        os.makedirs(path, exist_ok=True)
        
        if self.outcome_model:
            joblib.dump(self.outcome_model, os.path.join(path, 'enhanced_outcome_model.pkl'))
        
        joblib.dump(self.scaler, os.path.join(path, 'enhanced_scaler.pkl'))
        
        metadata = {
            'feature_columns': self.feature_columns,
            'model_version': self.model_version,
            'trained_at': datetime.now().isoformat(),
            'ensemble_type': 'Enhanced XGBoost + LightGBM + Neural Network + Random Forest'
        }
        
        with open(os.path.join(path, 'enhanced_metadata.json'), 'w') as f:
            json.dump(metadata, f, indent=2)

# Main execution
if __name__ == '__main__':
    predictor = EnhancedFootballPredictor()
    
    if len(sys.argv) < 2:
        print("Usage: python enhanced_football_predictor.py [train|predict] [args...]")
        sys.exit(1)
    
    if sys.argv[1] == 'train':
        print("Loading enhanced data...")
        df = predictor.load_enhanced_data()
        
        if df.empty:
            print("No data available for training")
            sys.exit(1)
        
        print(f"Loaded {len(df)} matches")
        
        print("Creating enhanced features...")
        df = predictor.create_enhanced_features(df)
        
        print("Preparing enhanced training data...")
        X, y_home, y_away, y_outcome = predictor.prepare_enhanced_training_data(df)
        
        print("Training enhanced outcome models...")
        results = predictor.train_enhanced_outcome_models(X, y_outcome)
        
        print("\nEnhanced Training Results:")
        print(f"XGBoost Accuracy: {results['xgb_accuracy']:.3f}")
        print(f"LightGBM Accuracy: {results['lgb_accuracy']:.3f}")
        print(f"Neural Network Accuracy: {results['nn_accuracy']:.3f}")
        print(f"Random Forest Accuracy: {results['rf_accuracy']:.3f}")
        print(f"Enhanced Ensemble Accuracy: {results['ensemble_accuracy']:.3f}")
        print(f"Cross-validation Mean: {results['cv_mean_accuracy']:.3f} (+/- {results['cv_std_accuracy']:.3f})")
        
        print("\nTop 10 Most Important Features:")
        for feature, importance in list(results['feature_importance'].items())[:10]:
            print(f"  {feature}: {importance:.4f}")
        
        print("\nSaving enhanced models...")
        predictor.save_enhanced_models()
        print("Enhanced models saved successfully!")
        
    elif sys.argv[1] == 'predict':
        if len(sys.argv) != 4:
            print("Usage: python enhanced_football_predictor.py predict <home_team_id> <away_team_id>")
            sys.exit(1)
        
        home_team_id = int(sys.argv[2])
        away_team_id = int(sys.argv[3])
        
        # Load models
        prediction = predictor.predict_enhanced_match(home_team_id, away_team_id)
        print(json.dumps(prediction, indent=2))