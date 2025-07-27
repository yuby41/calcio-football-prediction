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
from sklearn.model_selection import train_test_split, cross_val_score
from sklearn.metrics import mean_absolute_error, accuracy_score, classification_report
from sklearn.preprocessing import StandardScaler
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
        self.model_version = "1.0.0"
        
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
        """Load match and team data from database"""
        conn = self.connect_db()
        
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
        WHERE m.status = 'finished' AND m.home_goals IS NOT NULL AND m.away_goals IS NOT NULL
        """
        
        if self.db_config['type'] == 'sqlite':
            df = pd.read_sql_query(query, conn)
        else:
            # For pymysql connection
            df = pd.read_sql_query(query, conn)
            conn.close()
        
        return df
    
    def create_features(self, df: pd.DataFrame) -> pd.DataFrame:
        """Create features for machine learning models"""
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
        
        # Create derived features
        df['home_win_rate'] = df['home_wins'] / (df['home_matches_played'] + 1)
        df['away_win_rate'] = df['away_wins'] / (df['away_matches_played'] + 1)
        
        df['home_goal_difference'] = df['home_goals_for'] - df['home_goals_against']
        df['away_goal_difference'] = df['away_goals_for'] - df['away_goals_against']
        
        df['home_points_per_game'] = df['home_points'] / (df['home_matches_played'] + 1)
        df['away_points_per_game'] = df['away_points'] / (df['away_matches_played'] + 1)
        
        # Strength indicators
        df['attack_strength_home'] = df['home_avg_goals_for'] / (df['away_avg_goals_against'] + 0.1)
        df['defense_strength_home'] = df['home_avg_goals_against'] / (df['away_avg_goals_for'] + 0.1)
        df['attack_strength_away'] = df['away_avg_goals_for'] / (df['home_avg_goals_against'] + 0.1)
        df['defense_strength_away'] = df['away_avg_goals_against'] / (df['home_avg_goals_for'] + 0.1)
        
        # Form indicators (simplified)
        df['home_form'] = (df['home_wins'] * 3 + df['home_draws']) / (df['home_matches_played'] + 1)
        df['away_form'] = (df['away_wins'] * 3 + df['away_draws']) / (df['away_matches_played'] + 1)
        
        # Target variables
        df['total_goals'] = df['home_goals'] + df['away_goals']
        df['match_outcome'] = df.apply(self._get_match_outcome, axis=1)
        
        return df
    
    def _get_match_outcome(self, row) -> str:
        """Determine match outcome"""
        if row['home_goals'] > row['away_goals']:
            return 'home_win'
        elif row['home_goals'] < row['away_goals']:
            return 'away_win'
        else:
            return 'draw'
    
    def prepare_training_data(self, df: pd.DataFrame) -> Tuple[pd.DataFrame, pd.Series, pd.Series, pd.Series]:
        """Prepare data for training"""
        feature_columns = [
            'home_win_rate', 'away_win_rate', 'home_goal_difference', 'away_goal_difference',
            'home_points_per_game', 'away_points_per_game', 'attack_strength_home',
            'defense_strength_home', 'attack_strength_away', 'defense_strength_away',
            'home_form', 'away_form', 'home_avg_goals_for', 'home_avg_goals_against',
            'away_avg_goals_for', 'away_avg_goals_against'
        ]
        
        self.feature_columns = feature_columns
        
        X = df[feature_columns].fillna(0)
        y_home_goals = df['home_goals']
        y_away_goals = df['away_goals']
        y_outcome = df['match_outcome']
        
        return X, y_home_goals, y_away_goals, y_outcome
    
    def train_models(self, X: pd.DataFrame, y_home_goals: pd.Series, 
                    y_away_goals: pd.Series, y_outcome: pd.Series) -> Dict:
        """Train machine learning models"""
        results = {}
        
        # Split data
        X_train, X_test, y_home_train, y_home_test = train_test_split(
            X, y_home_goals, test_size=0.2, random_state=42
        )
        _, _, y_away_train, y_away_test = train_test_split(
            X, y_away_goals, test_size=0.2, random_state=42
        )
        _, _, y_outcome_train, y_outcome_test = train_test_split(
            X, y_outcome, test_size=0.2, random_state=42
        )
        
        # Scale features
        X_train_scaled = self.scaler.fit_transform(X_train)
        X_test_scaled = self.scaler.transform(X_test)
        
        # Train home goals model
        home_goals_model = xgb.XGBRegressor(
            n_estimators=100, learning_rate=0.1, max_depth=6, random_state=42
        )
        home_goals_model.fit(X_train_scaled, y_home_train)
        home_goals_pred = home_goals_model.predict(X_test_scaled)
        results['home_goals_mae'] = mean_absolute_error(y_home_test, home_goals_pred)
        
        # Train away goals model
        away_goals_model = xgb.XGBRegressor(
            n_estimators=100, learning_rate=0.1, max_depth=6, random_state=42
        )
        away_goals_model.fit(X_train_scaled, y_away_train)
        away_goals_pred = away_goals_model.predict(X_test_scaled)
        results['away_goals_mae'] = mean_absolute_error(y_away_test, away_goals_pred)
        
        # Train outcome model
        outcome_model = GradientBoostingClassifier(
            n_estimators=100, learning_rate=0.1, max_depth=6, random_state=42
        )
        outcome_model.fit(X_train_scaled, y_outcome_train)
        outcome_pred = outcome_model.predict(X_test_scaled)
        results['outcome_accuracy'] = accuracy_score(y_outcome_test, outcome_pred)
        
        # Store models
        self.goals_model = {
            'home': home_goals_model,
            'away': away_goals_model
        }
        self.outcome_model = outcome_model
        
        return results
    
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
        
        # Make predictions
        home_goals_pred = max(0, self.goals_model['home'].predict(features_scaled)[0])
        away_goals_pred = max(0, self.goals_model['away'].predict(features_scaled)[0])
        
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
        """Save trained models to disk"""
        path = path or os.path.join(os.path.dirname(__file__), 'models')
        os.makedirs(path, exist_ok=True)
        
        if self.goals_model:
            joblib.dump(self.goals_model['home'], os.path.join(path, 'home_goals_model.pkl'))
            joblib.dump(self.goals_model['away'], os.path.join(path, 'away_goals_model.pkl'))
        
        if self.outcome_model:
            joblib.dump(self.outcome_model, os.path.join(path, 'outcome_model.pkl'))
        
        joblib.dump(self.scaler, os.path.join(path, 'scaler.pkl'))
        
        # Save feature columns and metadata
        metadata = {
            'feature_columns': self.feature_columns,
            'model_version': self.model_version,
            'trained_at': datetime.now().isoformat()
        }
        
        with open(os.path.join(path, 'metadata.json'), 'w') as f:
            json.dump(metadata, f, indent=2)
    
    def load_models(self, path: str = None):
        """Load trained models from disk"""
        path = path or os.path.join(os.path.dirname(__file__), 'models')
        
        try:
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
        print(f"Outcome Accuracy: {results['outcome_accuracy']:.3f}")
        
        print("Saving models...")
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