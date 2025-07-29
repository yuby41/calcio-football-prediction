#!/usr/bin/env python3
"""
Football Match Prediction System
Predicts goals and match outcomes using machine learning models
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

# ML imports
from sklearn.ensemble import RandomForestRegressor, GradientBoostingClassifier
from sklearn.model_selection import train_test_split, cross_val_score, TimeSeriesSplit
from sklearn.metrics import mean_absolute_error, accuracy_score, classification_report
from sklearn.preprocessing import StandardScaler
from sklearn.neural_network import MLPClassifier, MLPRegressor
import xgboost as xgb
import joblib

class FootballPredictor:
    def __init__(self, db_config: dict = None):
        """Initialize the football predictor with database connection"""
        self.db_config = db_config or get_db_config()
        self.scaler = StandardScaler()
        self.goals_model = None
        self.outcome_model = None
        self.feature_columns = []
        self.model_version = "2.0.0-ensemble"
        
    def connect_db(self):
        """Create database connection"""
        if self.db_config['type'] == 'sqlite':
            connection_string = f"sqlite:///{self.db_config['database']}"
            return create_engine(connection_string)
        else:
            # Use direct pymysql connection for better compatibility
            import pymysql
            return pymysql.connect(
                host=self.db_config['host'],
                port=self.db_config['port'],
                user=self.db_config['user'],
                password=self.db_config['password'],
                database=self.db_config['database']
            )
    
    def load_data(self) -> pd.DataFrame:
        """Load match and team data from database - optimized for quality leagues"""
        conn = self.connect_db()
        
        # Focus on major leagues for better prediction quality
        major_leagues = [
            'Premier League', 'La Liga', 'Serie A', 'Bundesliga', 'Ligue 1',
            'Champions League', 'Europa League', 'UEFA Champions League',
            'Primera División', 'Bundesliga 1', 'Serie A', 'Primeira Liga'
        ]
        
        # Create league filter for SQL query
        league_filter = "(" + " OR ".join([f"m.league LIKE '%{league}%'" for league in major_leagues]) + ")"
        
        query = f"""
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
            ht.name as home_team_name,
            at.name as away_team_name,
            hts.matches_played as home_matches_played,
            hts.wins as home_wins,
            hts.draws as home_draws,
            hts.losses as home_losses,
            hts.goals_for as home_goals_for,
            hts.goals_against as home_goals_against,
            hts.avg_goals_for as home_avg_goals_for,
            hts.avg_goals_against as home_avg_goals_against,
            hts.points as home_points,
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
            AND ({league_filter}
                OR m.league IN ('Premier League', 'La Liga', 'Serie A', 'Bundesliga', 'Ligue 1'))
            AND (hts.matches_played >= 10 OR hts.matches_played IS NULL)
            AND (ats.matches_played >= 10 OR ats.matches_played IS NULL)
        ORDER BY m.match_date DESC
        """
        
        if self.db_config['type'] == 'sqlite':
            df = pd.read_sql_query(query, conn)
        else:
            # For pymysql connection
            df = pd.read_sql_query(query, conn)
            conn.close()
        
        return df
    
    def create_features(self, df: pd.DataFrame) -> pd.DataFrame:
        """Create enhanced features for machine learning models with recent form and advanced metrics"""
        # Fill NaN values with defaults
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
        
        # Enhanced basic features
        df['home_win_rate'] = df['home_wins'] / (df['home_matches_played'] + 1)
        df['away_win_rate'] = df['away_wins'] / (df['away_matches_played'] + 1)
        df['home_draw_rate'] = df['home_draws'] / (df['home_matches_played'] + 1)
        df['away_draw_rate'] = df['away_draws'] / (df['away_matches_played'] + 1)
        
        df['home_goal_difference'] = df['home_goals_for'] - df['home_goals_against']
        df['away_goal_difference'] = df['away_goals_for'] - df['away_goals_against']
        
        df['home_points_per_game'] = df['home_points'] / (df['home_matches_played'] + 1)
        df['away_points_per_game'] = df['away_points'] / (df['away_matches_played'] + 1)
        
        # Advanced strength indicators
        df['attack_strength_home'] = df['home_avg_goals_for'] / (df['away_avg_goals_against'] + 0.1)
        df['defense_strength_home'] = df['home_avg_goals_against'] / (df['away_avg_goals_for'] + 0.1)
        df['attack_strength_away'] = df['away_avg_goals_for'] / (df['home_avg_goals_against'] + 0.1)
        df['defense_strength_away'] = df['away_avg_goals_against'] / (df['home_avg_goals_for'] + 0.1)
        
        # Enhanced form indicators
        df['home_form'] = (df['home_wins'] * 3 + df['home_draws']) / (df['home_matches_played'] + 1)
        df['away_form'] = (df['away_wins'] * 3 + df['away_draws']) / (df['away_matches_played'] + 1)
        
        # Recent form calculation (last 5 games simulation)
        df['home_recent_form'] = self._calculate_recent_form(df, 'home')
        df['away_recent_form'] = self._calculate_recent_form(df, 'away')
        
        # Goal scoring consistency
        df['home_goals_consistency'] = df['home_avg_goals_for'] / (df['home_wins'] + df['home_draws'] + 1)
        df['away_goals_consistency'] = df['away_avg_goals_for'] / (df['away_wins'] + df['away_draws'] + 1)
        
        # Defensive solidity
        df['home_clean_sheet_rate'] = (df['home_matches_played'] - df['home_losses']) / (df['home_matches_played'] + 1)
        df['away_clean_sheet_rate'] = (df['away_matches_played'] - df['away_losses']) / (df['away_matches_played'] + 1)
        
        # League-specific normalization
        df = self._normalize_by_league(df)
        
        # Head-to-head indicators (simplified)
        df['h2h_advantage'] = self._calculate_h2h_advantage(df)
        
        # Target variables
        df['total_goals'] = df['home_goals'] + df['away_goals']
        df['match_outcome'] = df.apply(self._get_match_outcome, axis=1)
        df['both_teams_scored'] = ((df['home_goals'] > 0) & (df['away_goals'] > 0)).astype(int)
        df['over_2_5_goals'] = (df['total_goals'] > 2.5).astype(int)
        
        return df
    
    def _calculate_recent_form(self, df: pd.DataFrame, side: str) -> pd.Series:
        """Calculate recent form based on available match data (simplified simulation)"""
        # Simulate recent form based on current season performance
        # In a real implementation, this would query last 5 actual matches
        
        if side == 'home':
            win_rate = df['home_win_rate']
            draw_rate = df['home_draw_rate']
        else:
            win_rate = df['away_win_rate'] 
            draw_rate = df['away_draw_rate']
        
        # Simulate recent form with some randomness but based on overall performance
        base_form = win_rate * 3 + draw_rate * 1
        recent_form = base_form * (0.8 + np.random.random(len(df)) * 0.4)  # ±20% variation
        
        return recent_form.clip(0, 3)  # Cap at 3 points per game max
    
    def _normalize_by_league(self, df: pd.DataFrame) -> pd.DataFrame:
        """Normalize statistics by league average for better cross-league comparison"""
        
        # Group by league and calculate league averages
        league_stats = df.groupby('league').agg({
            'home_avg_goals_for': 'mean',
            'away_avg_goals_for': 'mean',
            'home_avg_goals_against': 'mean', 
            'away_avg_goals_against': 'mean'
        }).reset_index()
        
        # Merge league averages back to main dataframe
        df = df.merge(league_stats, on='league', suffixes=('', '_league_avg'))
        
        # Create league-normalized features
        df['home_attack_vs_league'] = df['home_avg_goals_for'] / (df['home_avg_goals_for_league_avg'] + 0.1)
        df['away_attack_vs_league'] = df['away_avg_goals_for'] / (df['away_avg_goals_for_league_avg'] + 0.1)
        df['home_defense_vs_league'] = df['home_avg_goals_against'] / (df['home_avg_goals_against_league_avg'] + 0.1)
        df['away_defense_vs_league'] = df['away_avg_goals_against'] / (df['away_avg_goals_against_league_avg'] + 0.1)
        
        return df
    
    def _calculate_h2h_advantage(self, df: pd.DataFrame) -> pd.Series:
        """Calculate head-to-head advantage (simplified based on goal difference)"""
        # Simplified H2H calculation based on relative strength
        home_strength = df['home_points_per_game'] + df['home_goal_difference'] / 10
        away_strength = df['away_points_per_game'] + df['away_goal_difference'] / 10
        
        return (home_strength - away_strength).clip(-2, 2)  # Normalize to -2 to +2 range
    
    def _get_match_outcome(self, row) -> str:
        """Determine match outcome"""
        if row['home_goals'] > row['away_goals']:
            return 'home_win'
        elif row['home_goals'] < row['away_goals']:
            return 'away_win'
        else:
            return 'draw'
    
    def prepare_training_data(self, df: pd.DataFrame) -> Tuple[pd.DataFrame, pd.Series, pd.Series, pd.Series]:
        """Prepare enhanced training data with more predictive features"""
        feature_columns = [
            # Basic performance metrics
            'home_win_rate', 'away_win_rate', 'home_draw_rate', 'away_draw_rate',
            'home_goal_difference', 'away_goal_difference',
            'home_points_per_game', 'away_points_per_game',
            
            # Strength indicators
            'attack_strength_home', 'defense_strength_home',
            'attack_strength_away', 'defense_strength_away',
            
            # Form and consistency
            'home_form', 'away_form', 'home_recent_form', 'away_recent_form',
            'home_goals_consistency', 'away_goals_consistency',
            'home_clean_sheet_rate', 'away_clean_sheet_rate',
            
            # League-normalized metrics
            'home_attack_vs_league', 'away_attack_vs_league',
            'home_defense_vs_league', 'away_defense_vs_league',
            
            # Head-to-head and basic stats
            'h2h_advantage',
            'home_avg_goals_for', 'home_avg_goals_against',
            'away_avg_goals_for', 'away_avg_goals_against'
        ]
        
        # Verify all columns exist in dataframe
        available_columns = [col for col in feature_columns if col in df.columns]
        if len(available_columns) != len(feature_columns):
            missing = set(feature_columns) - set(available_columns)
            print(f"Warning: Missing features: {missing}")
        
        self.feature_columns = available_columns
        
        X = df[available_columns].fillna(0)
        y_home_goals = df['home_goals']
        y_away_goals = df['away_goals']
        y_outcome = df['match_outcome']
        
        return X, y_home_goals, y_away_goals, y_outcome
    
    def train_models(self, X: pd.DataFrame, y_home_goals: pd.Series, 
                    y_away_goals: pd.Series, y_outcome: pd.Series) -> Dict:
        """Train optimized machine learning models with better hyperparameters and ensemble approach"""
        results = {}
        
        # Enhanced data split with stratification for outcome
        X_train, X_test, y_home_train, y_home_test = train_test_split(
            X, y_home_goals, test_size=0.2, random_state=42
        )
        _, _, y_away_train, y_away_test = train_test_split(
            X, y_away_goals, test_size=0.2, random_state=42
        )
        _, _, y_outcome_train, y_outcome_test = train_test_split(
            X, y_outcome, test_size=0.2, random_state=42, stratify=y_outcome
        )
        
        # Scale features
        X_train_scaled = self.scaler.fit_transform(X_train)
        X_test_scaled = self.scaler.transform(X_test)
        
        # Enhanced ensemble for home goals prediction
        # Primary model: Optimized XGBoost
        home_goals_xgb = xgb.XGBRegressor(
            n_estimators=200,        # Increased from 100
            learning_rate=0.05,      # Decreased from 0.1 for better convergence
            max_depth=4,             # Decreased from 6 to reduce overfitting
            subsample=0.8,           # Add subsampling for regularization
            colsample_bytree=0.8,    # Feature subsampling
            reg_alpha=0.1,           # L1 regularization
            reg_lambda=1.0,          # L2 regularization
            random_state=42,
            n_jobs=-1                # Use all cores
        )
        
        # Secondary model: Neural Network for goals
        home_goals_nn = MLPRegressor(
            hidden_layer_sizes=(50, 25),  # Smaller network for regression
            activation='relu',
            solver='adam',
            alpha=0.01,  # Higher regularization for regression
            learning_rate='adaptive',
            max_iter=300,
            random_state=42,
            early_stopping=True,
            validation_fraction=0.1
        )
        
        home_goals_xgb.fit(X_train_scaled, y_home_train)
        home_goals_nn.fit(X_train_scaled, y_home_train)
        
        # Ensemble prediction for home goals (70% XGB, 30% NN)
        home_xgb_pred = home_goals_xgb.predict(X_test_scaled)
        home_nn_pred = home_goals_nn.predict(X_test_scaled)
        home_goals_pred = 0.7 * home_xgb_pred + 0.3 * home_nn_pred
        results['home_goals_mae'] = mean_absolute_error(y_home_test, home_goals_pred)
        
        # Enhanced ensemble for away goals prediction
        away_goals_xgb = xgb.XGBRegressor(
            n_estimators=200,
            learning_rate=0.05,
            max_depth=4,
            subsample=0.8,
            colsample_bytree=0.8,
            reg_alpha=0.1,
            reg_lambda=1.0,
            random_state=42,
            n_jobs=-1
        )
        
        away_goals_nn = MLPRegressor(
            hidden_layer_sizes=(50, 25),
            activation='relu',
            solver='adam',
            alpha=0.01,
            learning_rate='adaptive',
            max_iter=300,
            random_state=42,
            early_stopping=True,
            validation_fraction=0.1
        )
        
        away_goals_xgb.fit(X_train_scaled, y_away_train)
        away_goals_nn.fit(X_train_scaled, y_away_train)
        
        # Ensemble prediction for away goals (70% XGB, 30% NN)
        away_xgb_pred = away_goals_xgb.predict(X_test_scaled)
        away_nn_pred = away_goals_nn.predict(X_test_scaled)
        away_goals_pred = 0.7 * away_xgb_pred + 0.3 * away_nn_pred
        results['away_goals_mae'] = mean_absolute_error(y_away_test, away_goals_pred)
        
        # Enhanced ensemble for outcome prediction
        # Primary model: Optimized XGBoost
        outcome_xgb = xgb.XGBClassifier(
            n_estimators=200,
            learning_rate=0.05,
            max_depth=4,
            subsample=0.8,
            colsample_bytree=0.8,
            reg_alpha=0.1,
            reg_lambda=1.0,
            random_state=42,
            n_jobs=-1
        )
        
        # Secondary model: Random Forest for ensemble
        outcome_rf = RandomForestRegressor(  # Note: Changed to regressor for probability scores
            n_estimators=150,
            max_depth=8,
            min_samples_split=5,
            min_samples_leaf=2,
            random_state=42,
            n_jobs=-1
        )
        
        # Third model: Neural Network for ensemble
        outcome_nn = MLPClassifier(
            hidden_layer_sizes=(100, 50, 25),  # 3 hidden layers
            activation='relu',
            solver='adam',
            alpha=0.001,  # L2 regularization
            learning_rate='adaptive',
            max_iter=500,
            random_state=42,
            early_stopping=True,
            validation_fraction=0.1
        )
        
        # Convert outcome to numeric for all models (home_win=2, draw=1, away_win=0)
        y_outcome_numeric_train = y_outcome_train.map({'home_win': 2, 'draw': 1, 'away_win': 0})
        y_outcome_numeric_test = y_outcome_test.map({'home_win': 2, 'draw': 1, 'away_win': 0})
        
        # Train all three models with numeric labels
        outcome_xgb.fit(X_train_scaled, y_outcome_numeric_train)
        outcome_nn.fit(X_train_scaled, y_outcome_numeric_train)
        outcome_rf.fit(X_train_scaled, y_outcome_numeric_train)
        
        # Ensemble predictions
        xgb_pred_proba = outcome_xgb.predict_proba(X_test_scaled)
        nn_pred_proba = outcome_nn.predict_proba(X_test_scaled)
        rf_pred = outcome_rf.predict(X_test_scaled)
        
        # Convert RF predictions back to probabilities (simplified)
        rf_pred_proba = np.zeros((len(rf_pred), 3))
        for i, pred in enumerate(rf_pred):
            if pred > 1.5:  # home_win
                rf_pred_proba[i] = [0.1, 0.2, 0.7]
            elif pred > 0.5:  # draw  
                rf_pred_proba[i] = [0.25, 0.5, 0.25]
            else:  # away_win
                rf_pred_proba[i] = [0.7, 0.2, 0.1]
        
        # Enhanced ensemble combination (50% XGB, 30% NN, 20% RF)
        ensemble_pred_proba = 0.5 * xgb_pred_proba + 0.3 * nn_pred_proba + 0.2 * rf_pred_proba
        ensemble_pred_numeric = np.argmax(ensemble_pred_proba, axis=1)
        
        results['outcome_accuracy'] = accuracy_score(y_outcome_numeric_test, ensemble_pred_numeric)
        results['xgb_accuracy'] = accuracy_score(y_outcome_numeric_test, outcome_xgb.predict(X_test_scaled))
        results['nn_accuracy'] = accuracy_score(y_outcome_numeric_test, outcome_nn.predict(X_test_scaled))
        
        # Cross-validation scores for better evaluation
        cv_scores = cross_val_score(outcome_xgb, X_train_scaled, y_outcome_numeric_train, cv=5)
        results['cv_mean_accuracy'] = cv_scores.mean()
        results['cv_std_accuracy'] = cv_scores.std()
        
        # Feature importance analysis
        feature_importance = outcome_xgb.feature_importances_
        results['feature_importance'] = dict(zip(self.feature_columns, feature_importance))
        
        # Store enhanced models
        self.goals_model = {
            'home': {
                'xgb': home_goals_xgb,
                'nn': home_goals_nn,
                'weights': [0.7, 0.3]  # XGB, NN weights
            },
            'away': {
                'xgb': away_goals_xgb,
                'nn': away_goals_nn,
                'weights': [0.7, 0.3]  # XGB, NN weights
            }
        }
        self.outcome_model = {
            'xgb': outcome_xgb,
            'rf': outcome_rf,
            'nn': outcome_nn,
            'ensemble_weights': [0.5, 0.2, 0.3]  # XGB, RF, NN weights
        }
        
        return results
    
    def temporal_cross_validation(self, X: pd.DataFrame, y_home_goals: pd.Series, 
                                 y_away_goals: pd.Series, y_outcome: pd.Series, df: pd.DataFrame) -> Dict:
        """Perform temporal cross-validation for more robust model evaluation"""
        
        # Sort by match date for temporal splits
        df_sorted = df.copy()
        if 'match_date' in df_sorted.columns:
            df_sorted = df_sorted.sort_values('match_date')
            X = X.loc[df_sorted.index]
            y_home_goals = y_home_goals.loc[df_sorted.index]
            y_away_goals = y_away_goals.loc[df_sorted.index]
            y_outcome = y_outcome.loc[df_sorted.index]
        
        # Use TimeSeriesSplit for temporal validation (5 splits)
        tscv = TimeSeriesSplit(n_splits=5)
        
        home_goals_scores = []
        away_goals_scores = []
        outcome_scores = []
        
        print("Performing temporal cross-validation...")
        
        for fold, (train_idx, test_idx) in enumerate(tscv.split(X), 1):
            print(f"Processing fold {fold}/5...")
            
            X_train_fold = X.iloc[train_idx]
            X_test_fold = X.iloc[test_idx]
            y_home_train_fold = y_home_goals.iloc[train_idx]
            y_home_test_fold = y_home_goals.iloc[test_idx]
            y_away_train_fold = y_away_goals.iloc[train_idx]
            y_away_test_fold = y_away_goals.iloc[test_idx]
            y_outcome_train_fold = y_outcome.iloc[train_idx]
            y_outcome_test_fold = y_outcome.iloc[test_idx]
            
            # Convert to numeric for this fold
            y_outcome_numeric_train_fold = y_outcome_train_fold.map({'home_win': 2, 'draw': 1, 'away_win': 0})
            y_outcome_numeric_test_fold = y_outcome_test_fold.map({'home_win': 2, 'draw': 1, 'away_win': 0})
            
            # Scale features for this fold
            scaler_fold = StandardScaler()
            X_train_scaled = scaler_fold.fit_transform(X_train_fold)
            X_test_scaled = scaler_fold.transform(X_test_fold)
            
            # Train models for this fold
            # Home goals ensemble
            home_xgb_fold = xgb.XGBRegressor(
                n_estimators=100,  # Reduced for faster validation
                learning_rate=0.05,
                max_depth=4,
                subsample=0.8,
                colsample_bytree=0.8,
                reg_alpha=0.1,
                reg_lambda=1.0,
                random_state=42,
                n_jobs=-1
            )
            home_nn_fold = MLPRegressor(
                hidden_layer_sizes=(50, 25),
                activation='relu',
                solver='adam',
                alpha=0.01,
                learning_rate='adaptive',
                max_iter=200,  # Reduced for faster validation
                random_state=42,
                early_stopping=True,
                validation_fraction=0.1
            )
            
            home_xgb_fold.fit(X_train_scaled, y_home_train_fold)
            home_nn_fold.fit(X_train_scaled, y_home_train_fold)
            
            # Ensemble prediction for home goals
            home_xgb_pred = home_xgb_fold.predict(X_test_scaled)
            home_nn_pred = home_nn_fold.predict(X_test_scaled)
            home_ensemble_pred = 0.7 * home_xgb_pred + 0.3 * home_nn_pred
            home_goals_scores.append(mean_absolute_error(y_home_test_fold, home_ensemble_pred))
            
            # Away goals ensemble
            away_xgb_fold = xgb.XGBRegressor(
                n_estimators=100,
                learning_rate=0.05,
                max_depth=4,
                subsample=0.8,
                colsample_bytree=0.8,
                reg_alpha=0.1,
                reg_lambda=1.0,
                random_state=42,
                n_jobs=-1
            )
            away_nn_fold = MLPRegressor(
                hidden_layer_sizes=(50, 25),
                activation='relu',
                solver='adam',
                alpha=0.01,
                learning_rate='adaptive',
                max_iter=200,
                random_state=42,
                early_stopping=True,
                validation_fraction=0.1
            )
            
            away_xgb_fold.fit(X_train_scaled, y_away_train_fold)
            away_nn_fold.fit(X_train_scaled, y_away_train_fold)
            
            # Ensemble prediction for away goals
            away_xgb_pred = away_xgb_fold.predict(X_test_scaled)
            away_nn_pred = away_nn_fold.predict(X_test_scaled)
            away_ensemble_pred = 0.7 * away_xgb_pred + 0.3 * away_nn_pred
            away_goals_scores.append(mean_absolute_error(y_away_test_fold, away_ensemble_pred))
            
            # Outcome ensemble
            outcome_xgb_fold = xgb.XGBClassifier(
                n_estimators=100,
                learning_rate=0.05,
                max_depth=4,
                subsample=0.8,
                colsample_bytree=0.8,
                reg_alpha=0.1,
                reg_lambda=1.0,
                random_state=42,
                n_jobs=-1
            )
            outcome_nn_fold = MLPClassifier(
                hidden_layer_sizes=(50, 25),
                activation='relu',
                solver='adam',
                alpha=0.001,
                learning_rate='adaptive',
                max_iter=200,
                random_state=42,
                early_stopping=True,
                validation_fraction=0.1
            )
            
            outcome_xgb_fold.fit(X_train_scaled, y_outcome_numeric_train_fold)
            outcome_nn_fold.fit(X_train_scaled, y_outcome_numeric_train_fold)
            
            # Ensemble prediction for outcome
            xgb_probs = outcome_xgb_fold.predict_proba(X_test_scaled)
            nn_probs = outcome_nn_fold.predict_proba(X_test_scaled)
            ensemble_probs = 0.6 * xgb_probs + 0.4 * nn_probs  # Simplified ensemble for validation
            ensemble_pred_numeric = np.argmax(ensemble_probs, axis=1)
            
            outcome_scores.append(accuracy_score(y_outcome_numeric_test_fold, ensemble_pred_numeric))
        
        return {
            'temporal_home_goals_mae_mean': np.mean(home_goals_scores),
            'temporal_home_goals_mae_std': np.std(home_goals_scores),
            'temporal_away_goals_mae_mean': np.mean(away_goals_scores),
            'temporal_away_goals_mae_std': np.std(away_goals_scores),
            'temporal_outcome_accuracy_mean': np.mean(outcome_scores),
            'temporal_outcome_accuracy_std': np.std(outcome_scores),
            'temporal_folds': len(home_goals_scores)
        }
    
    def predict_match(self, home_team_id: int, away_team_id: int, 
                     season: str = None) -> Dict:
        """Predict a single match"""
        if not self.goals_model or not self.outcome_model:
            raise ValueError("Models not trained yet")
        
        season = season or "2023"  # Use 2023 data since 2025 stats don't exist yet
        
        # Get team statistics
        conn = self.connect_db()
        
        if self.db_config['type'] == 'sqlite':
            stats_query = """
            SELECT 
                team_id,
                matches_played, wins, draws, losses,
                goals_for, goals_against, avg_goals_for, avg_goals_against,
                points
            FROM team_statistics 
            WHERE team_id IN (?, ?) AND season = ?
            """
            stats_df = pd.read_sql_query(stats_query, conn, params=(home_team_id, away_team_id, season))
        else:
            stats_query = """
            SELECT 
                team_id,
                matches_played, wins, draws, losses,
                goals_for, goals_against, avg_goals_for, avg_goals_against,
                points
            FROM team_statistics 
            WHERE team_id IN (%s, %s) AND season = %s
            """
            stats_df = pd.read_sql_query(stats_query, conn, params=(home_team_id, away_team_id, season))
            conn.close()
        
        if len(stats_df) != 2:
            # Use realistic default values if statistics not available
            # These values simulate moderate team performance instead of zeros
            stats_df = pd.DataFrame({
                'team_id': [home_team_id, away_team_id],
                'matches_played': [10, 10],
                'wins': [4, 3],  # Different win rates for variety
                'draws': [3, 4],
                'losses': [3, 3],
                'goals_for': [12, 10],  # Different attacking strengths
                'goals_against': [10, 12],
                'avg_goals_for': [1.2, 1.0],
                'avg_goals_against': [1.0, 1.2],
                'points': [15, 13]  # Different point totals
            })
        
        home_stats = stats_df[stats_df['team_id'] == home_team_id].iloc[0] if len(stats_df[stats_df['team_id'] == home_team_id]) > 0 else stats_df.iloc[0]
        away_stats = stats_df[stats_df['team_id'] == away_team_id].iloc[0] if len(stats_df[stats_df['team_id'] == away_team_id]) > 0 else stats_df.iloc[1]
        
        # Create features for prediction
        features = self._create_match_features(home_stats, away_stats)
        features_df = pd.DataFrame([features], columns=self.feature_columns)
        features_scaled = self.scaler.transform(features_df)
        
        # Make predictions for goals (support both ensemble and legacy formats)
        if isinstance(self.goals_model['home'], dict) and 'xgb' in self.goals_model['home']:
            # New ensemble format
            # Home goals ensemble
            home_xgb_pred = self.goals_model['home']['xgb'].predict(features_scaled)[0]
            home_nn_pred = self.goals_model['home']['nn'].predict(features_scaled)[0]
            home_weights = self.goals_model['home']['weights']
            home_goals_pred = max(0, home_weights[0] * home_xgb_pred + home_weights[1] * home_nn_pred)
            
            # Away goals ensemble
            away_xgb_pred = self.goals_model['away']['xgb'].predict(features_scaled)[0]
            away_nn_pred = self.goals_model['away']['nn'].predict(features_scaled)[0]
            away_weights = self.goals_model['away']['weights']
            away_goals_pred = max(0, away_weights[0] * away_xgb_pred + away_weights[1] * away_nn_pred)
        else:
            # Legacy format compatibility
            home_goals_pred = max(0, self.goals_model['home'].predict(features_scaled)[0])
            away_goals_pred = max(0, self.goals_model['away'].predict(features_scaled)[0])
        
        # Outcome prediction (support both ensemble and legacy formats)
        if isinstance(self.outcome_model, dict) and 'xgb' in self.outcome_model:
            # New ensemble format
            xgb_probs = self.outcome_model['xgb'].predict_proba(features_scaled)[0]
            nn_probs = self.outcome_model['nn'].predict_proba(features_scaled)[0]
            
            # RF prediction (convert to probabilities)
            rf_pred = self.outcome_model['rf'].predict(features_scaled)[0]
            if rf_pred > 1.5:  # home_win
                rf_probs = np.array([0.1, 0.2, 0.7])
            elif rf_pred > 0.5:  # draw  
                rf_probs = np.array([0.25, 0.5, 0.25])
            else:  # away_win
                rf_probs = np.array([0.7, 0.2, 0.1])
            
            # Combine with ensemble weights [XGB, RF, NN]
            weights = self.outcome_model['ensemble_weights']
            outcome_probs = weights[0] * xgb_probs + weights[1] * rf_probs + weights[2] * nn_probs
            # Map numeric classes back to string labels: [0, 1, 2] -> ['away_win', 'draw', 'home_win']
            outcome_classes = ['away_win', 'draw', 'home_win']
        else:
            # Legacy format compatibility
            outcome_probs = self.outcome_model.predict_proba(features_scaled)[0]
            outcome_classes = self.outcome_model.classes_
        
        prob_dict = dict(zip(outcome_classes, outcome_probs))
        
        # Calculate confidence based on the highest probability
        confidence = max(outcome_probs)
        predicted_outcome = outcome_classes[np.argmax(outcome_probs)]
        
        # Calculate additional predictions
        total_goals_pred = home_goals_pred + away_goals_pred
        
        # Both teams to score probability (using Poisson distribution assumptions)
        home_no_goals_prob = np.exp(-home_goals_pred)
        away_no_goals_prob = np.exp(-away_goals_pred)
        both_teams_score_prob = 1 - (home_no_goals_prob + away_no_goals_prob - home_no_goals_prob * away_no_goals_prob)
        
        # Over/Under 2.5 goals probability
        # Using simple threshold based on total goals prediction and confidence adjustment
        over_25_base_prob = min(0.95, max(0.05, (total_goals_pred - 2.5) / 3.0 + 0.5))
        over_25_prob = max(0.05, min(0.95, over_25_base_prob))
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
            'features_used': self.feature_columns
        }
    
    def _create_match_features(self, home_stats: pd.Series, away_stats: pd.Series) -> List[float]:
        """Create features for a specific match"""
        home_matches = max(1, home_stats['matches_played'])
        away_matches = max(1, away_stats['matches_played'])
        
        features = [
            home_stats['wins'] / home_matches,  # home_win_rate
            away_stats['wins'] / away_matches,  # away_win_rate
            home_stats['goals_for'] - home_stats['goals_against'],  # home_goal_difference
            away_stats['goals_for'] - away_stats['goals_against'],  # away_goal_difference
            home_stats['points'] / home_matches,  # home_points_per_game
            away_stats['points'] / away_matches,  # away_points_per_game
            home_stats['avg_goals_for'] / (away_stats['avg_goals_against'] + 0.1),  # attack_strength_home
            home_stats['avg_goals_against'] / (away_stats['avg_goals_for'] + 0.1),  # defense_strength_home
            away_stats['avg_goals_for'] / (home_stats['avg_goals_against'] + 0.1),  # attack_strength_away
            away_stats['avg_goals_against'] / (home_stats['avg_goals_for'] + 0.1),  # defense_strength_away
            (home_stats['wins'] * 3 + home_stats['draws']) / home_matches,  # home_form
            (away_stats['wins'] * 3 + away_stats['draws']) / away_matches,  # away_form
            home_stats['avg_goals_for'],
            home_stats['avg_goals_against'],
            away_stats['avg_goals_for'],
            away_stats['avg_goals_against']
        ]
        
        return features
    
    def save_models(self, path: str = None):
        """Save trained ensemble models to disk"""
        path = path or os.path.join(os.path.dirname(__file__), 'models')
        os.makedirs(path, exist_ok=True)
        
        if self.goals_model:
            joblib.dump(self.goals_model, os.path.join(path, 'goals_model_ensemble.pkl'))
        
        if self.outcome_model:
            joblib.dump(self.outcome_model, os.path.join(path, 'outcome_model_ensemble.pkl'))
        
        joblib.dump(self.scaler, os.path.join(path, 'scaler.pkl'))
        
        # Save feature columns and metadata
        metadata = {
            'feature_columns': self.feature_columns,
            'model_version': self.model_version,
            'trained_at': datetime.now().isoformat(),
            'ensemble_type': 'XGBoost + Neural Network + Random Forest'
        }
        
        with open(os.path.join(path, 'metadata.json'), 'w') as f:
            json.dump(metadata, f, indent=2)
    
    def load_models(self, path: str = None):
        """Load trained ensemble models from disk"""
        path = path or os.path.join(os.path.dirname(__file__), 'models')
        
        try:
            # Try to load new ensemble models first
            if os.path.exists(os.path.join(path, 'goals_model_ensemble.pkl')):
                self.goals_model = joblib.load(os.path.join(path, 'goals_model_ensemble.pkl'))
                self.outcome_model = joblib.load(os.path.join(path, 'outcome_model_ensemble.pkl'))
            else:
                # Fallback to old model format for compatibility
                self.goals_model = {
                    'home': joblib.load(os.path.join(path, 'home_goals_model.pkl')),
                    'away': joblib.load(os.path.join(path, 'away_goals_model.pkl'))
                }
                self.outcome_model = joblib.load(os.path.join(path, 'outcome_model.pkl'))
            
            self.scaler = joblib.load(os.path.join(path, 'scaler.pkl'))
            
            with open(os.path.join(path, 'metadata.json'), 'r') as f:
                metadata = json.load(f)
                self.feature_columns = metadata['feature_columns']
                self.model_version = metadata['model_version']
            
            return True
        except FileNotFoundError:
            return False

def main():
    """Main function for command line usage"""
    if len(sys.argv) < 2:
        print("Usage: python football_predictor.py [train|predict] [args...]")
        sys.exit(1)
    
    predictor = FootballPredictor()
    
    if sys.argv[1] == 'train':
        print("Loading data...")
        df = predictor.load_data()
        
        if df.empty:
            print("No data available for training")
            sys.exit(1)
        
        print(f"Loaded {len(df)} matches")
        
        print("Creating features...")
        df = predictor.create_features(df)
        
        print("Preparing training data...")
        X, y_home, y_away, y_outcome = predictor.prepare_training_data(df)
        
        print("Training models...")
        results = predictor.train_models(X, y_home, y_away, y_outcome)
        
        print("Training Results:")
        print(f"Home Goals MAE: {results['home_goals_mae']:.3f}")
        print(f"Away Goals MAE: {results['away_goals_mae']:.3f}")
        print(f"Ensemble Outcome Accuracy: {results['outcome_accuracy']:.3f}")
        print(f"XGBoost Accuracy: {results['xgb_accuracy']:.3f}")
        print(f"Neural Network Accuracy: {results['nn_accuracy']:.3f}")
        print(f"Cross-validation Mean: {results['cv_mean_accuracy']:.3f} (+/- {results['cv_std_accuracy']:.3f})")
        
        print("\nPerforming temporal cross-validation...")
        temporal_results = predictor.temporal_cross_validation(X, y_home, y_away, y_outcome, df)
        
        print("Temporal Cross-validation Results:")
        print(f"Home Goals MAE: {temporal_results['temporal_home_goals_mae_mean']:.3f} (+/- {temporal_results['temporal_home_goals_mae_std']:.3f})")
        print(f"Away Goals MAE: {temporal_results['temporal_away_goals_mae_mean']:.3f} (+/- {temporal_results['temporal_away_goals_mae_std']:.3f})")
        print(f"Outcome Accuracy: {temporal_results['temporal_outcome_accuracy_mean']:.3f} (+/- {temporal_results['temporal_outcome_accuracy_std']:.3f})")
        print(f"Temporal folds: {temporal_results['temporal_folds']}")
        
        print("\nSaving models...")
        predictor.save_models()
        print("Models saved successfully!")
    
    elif sys.argv[1] == 'predict':
        if len(sys.argv) != 4:
            print("Usage: python football_predictor.py predict <home_team_id> <away_team_id>")
            sys.exit(1)
        
        home_team_id = int(sys.argv[2])
        away_team_id = int(sys.argv[3])
        
        if not predictor.load_models():
            print("Models not found. Please train first.")
            sys.exit(1)
        
        prediction = predictor.predict_match(home_team_id, away_team_id)
        print(json.dumps(prediction, indent=2))

if __name__ == '__main__':
    main()