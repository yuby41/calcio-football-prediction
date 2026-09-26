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
from sklearn.model_selection import TimeSeriesSplit
from sklearn.metrics import (
    accuracy_score,
    balanced_accuracy_score,
    f1_score,
    log_loss,
    classification_report,
    confusion_matrix,
)
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
        """
        Load finished matches used to build point-in-time ML features.

        Team statistics are intentionally not joined here because the
        team_statistics table contains season-level aggregates and would
        introduce look-ahead leakage for historical matches.
        """
        conn = self.connect_db()

        query = """
        SELECT
            m.id AS match_id,
            m.home_team_id,
            m.away_team_id,
            m.match_date,
            m.home_goals,
            m.away_goals,
            m.status,
            m.league,
            m.season,
            m.round,
            ht.name AS home_team_name,
            at.name AS away_team_name
        FROM matches m
        JOIN teams ht ON m.home_team_id = ht.id
        JOIN teams at ON m.away_team_id = at.id
        WHERE m.status = 'finished'
            AND m.home_goals IS NOT NULL
            AND m.away_goals IS NOT NULL
            AND m.external_id LIKE 'openfootball:%'
            AND m.league = 'Serie A'
            AND m.season IN (
                '2020-21',
                '2021-22',
                '2022-23',
                '2023-24',
                '2024-25'
            )
        ORDER BY m.match_date ASC, m.id ASC
        """



        df = pd.read_sql_query(query, conn)

        conn.close()

        return df

    def get_team_stats_before_match(
        self,
        team_id: int,
        match_date: str,
        season: str
    ) -> Dict:
        """
        Calculate point-in-time team statistics using only matches
        completed before the target match.
        """
        conn = self.connect_db()

        query = """
        SELECT
            home_team_id,
            away_team_id,
            home_goals,
            away_goals,
            match_date
        FROM matches
        WHERE (home_team_id = %s OR away_team_id = %s)
            AND status = 'finished'
            AND season = %s
            AND match_date < %s
            AND home_goals IS NOT NULL
            AND away_goals IS NOT NULL
        ORDER BY match_date ASC
        """

        params = (team_id, team_id, season, match_date)

        if self.db_config['type'] == 'sqlite':
            query = query.replace('%s', '?')

        matches = pd.read_sql_query(
            query,
            conn,
            params=params
        )

        conn.close()

        stats = {
            'matches_played': 0,
            'wins': 0,
            'draws': 0,
            'losses': 0,
            'goals_for': 0,
            'goals_against': 0,
            'points': 0,
            'avg_goals_for': 0.0,
            'avg_goals_against': 0.0,
        }

        if matches.empty:
            return stats

        for _, match in matches.iterrows():
            is_home = match['home_team_id'] == team_id

            goals_for = (
                match['home_goals']
                if is_home
                else match['away_goals']
            )

            goals_against = (
                match['away_goals']
                if is_home
                else match['home_goals']
            )

            stats['matches_played'] += 1
            stats['goals_for'] += goals_for
            stats['goals_against'] += goals_against

            if goals_for > goals_against:
                stats['wins'] += 1
                stats['points'] += 3
            elif goals_for == goals_against:
                stats['draws'] += 1
                stats['points'] += 1
            else:
                stats['losses'] += 1

        matches_played = stats['matches_played']

        stats['avg_goals_for'] = (
            stats['goals_for'] / matches_played
        )

        stats['avg_goals_against'] = (
            stats['goals_against'] / matches_played
        )

        return stats

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

    def add_point_in_time_team_stats(self, df: pd.DataFrame) -> pd.DataFrame:
        """
        Build historical team statistics without look-ahead leakage.

        For every match, feature values are captured BEFORE the current
        match result is incorporated into the team's season state.
        """
        df = df.copy()

        df['match_date'] = pd.to_datetime(df['match_date'])
        df = df.sort_values(
            ['match_date', 'match_id']
        ).reset_index(drop=True)

        stat_names = [
            'matches_played',
            'wins',
            'draws',
            'losses',
            'goals_for',
            'goals_against',
            'avg_goals_for',
            'avg_goals_against',
            'points',
        ]

        for side in ['home', 'away']:
            for stat in stat_names:
                df[f'{side}_{stat}'] = 0.0

        team_states = {}

        def empty_state():
            return {
                'matches_played': 0,
                'wins': 0,
                'draws': 0,
                'losses': 0,
                'goals_for': 0,
                'goals_against': 0,
                'points': 0,
            }

        def snapshot(state):
            matches_played = state['matches_played']

            return {
                'matches_played': state['matches_played'],
                'wins': state['wins'],
                'draws': state['draws'],
                'losses': state['losses'],
                'goals_for': state['goals_for'],
                'goals_against': state['goals_against'],
                'avg_goals_for': (
                    state['goals_for'] / matches_played
                    if matches_played > 0
                    else 0.0
                ),
                'avg_goals_against': (
                    state['goals_against'] / matches_played
                    if matches_played > 0
                    else 0.0
                ),
                'points': state['points'],
            }

        def update_state(state, goals_for, goals_against):
            state['matches_played'] += 1
            state['goals_for'] += goals_for
            state['goals_against'] += goals_against

            if goals_for > goals_against:
                state['wins'] += 1
                state['points'] += 3
            elif goals_for == goals_against:
                state['draws'] += 1
                state['points'] += 1
            else:
                state['losses'] += 1

        for index, row in df.iterrows():
            season = str(row['season'])
            home_team_id = int(row['home_team_id'])
            away_team_id = int(row['away_team_id'])

            home_key = (season, home_team_id)
            away_key = (season, away_team_id)

            if home_key not in team_states:
                team_states[home_key] = empty_state()

            if away_key not in team_states:
                team_states[away_key] = empty_state()

            # IMPORTANT:
            # Capture the state before processing the current result.
            home_snapshot = snapshot(team_states[home_key])
            away_snapshot = snapshot(team_states[away_key])

            for stat, value in home_snapshot.items():
                df.at[index, f'home_{stat}'] = value

            for stat, value in away_snapshot.items():
                df.at[index, f'away_{stat}'] = value

            # Only now is the current match incorporated.
            update_state(
                team_states[home_key],
                row['home_goals'],
                row['away_goals'],
            )

            update_state(
                team_states[away_key],
                row['away_goals'],
                row['home_goals'],
            )

        return df

    def _safe_rate(self, numerator, denominator):
        """Return numerator / denominator with a zero-safe denominator."""
        denominator = denominator.replace(0, np.nan)
        return (numerator / denominator).fillna(0.0)

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

        df['home_win_rate'] = self._safe_rate(
            df['home_wins'],
            df['home_matches_played'],
        )
        df['away_win_rate'] = self._safe_rate(
            df['away_wins'],
            df['away_matches_played'],
        )

        df['home_draw_rate'] = self._safe_rate(
            df['home_draws'],
            df['home_matches_played'],
        )
        df['away_draw_rate'] = self._safe_rate(
            df['away_draws'],
            df['away_matches_played'],
        )

        df['home_loss_rate'] = self._safe_rate(
            df['home_losses'],
            df['home_matches_played'],
        )
        df['away_loss_rate'] = self._safe_rate(
            df['away_losses'],
            df['away_matches_played'],
        )

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

        df['home_points_per_game'] = self._safe_rate(
            df['home_points'],
            df['home_matches_played'],
        )

        df['away_points_per_game'] = self._safe_rate(
            df['away_points'],
            df['away_matches_played'],
        )

        df['points_gap'] = (
            df['home_points_per_game']
            - df['away_points_per_game']
        )

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

        # 5. CAUSAL LEAGUE-SPECIFIC NORMALIZATION
        # League statistics for each match are calculated using only
        # earlier matches from the same league. The current observation
        # is excluded with shift(1), preventing look-ahead leakage.
        normalization_columns = [
            'home_avg_goals_for',
            'away_avg_goals_for',
            'home_avg_goals_against',
            'away_avg_goals_against',
        ]

        for column in normalization_columns:
            league_group = df.groupby('league', sort=False)[column]

            expanding_mean = league_group.transform(
                lambda values: values.expanding().mean().shift(1)
            )

            expanding_std = league_group.transform(
                lambda values: values.expanding().std().shift(1)
            )

            # Cold-start rows have no previous league observations.
            # A zero z-score represents neutral/unknown league context.
            denominator = expanding_std + 0.1

            if column == 'home_avg_goals_for':
                output_column = 'home_attack_zscore'
            elif column == 'away_avg_goals_for':
                output_column = 'away_attack_zscore'
            elif column == 'home_avg_goals_against':
                output_column = 'home_defense_zscore'
            else:
                output_column = 'away_defense_zscore'

            df[output_column] = (
                (df[column] - expanding_mean) / denominator
            ).fillna(0.0)

        # 6. MATCH CONTEXT FEATURES
        df['season_stage'] = df['round'].fillna(1).astype(str).str.extract(r'(\d+)').fillna(1).astype(float)
        df['season_stage_normalized'] = df['season_stage'] / 38  # Assuming max 38 rounds

        # REMOVE HOME ADVANTAGE BIAS - Use neutral venue approach
        # Instead of league home advantage, use only statistical strength differences
        df['league_home_advantage'] = 0.5  # Neutral - no home bias

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

        if df.empty:
            raise ValueError("Training dataset is empty")

        required_raw_columns = {
            'match_id',
            'home_team_id',
            'away_team_id',
            'match_date',
            'home_goals',
            'away_goals',
            'season',
        }

        missing_raw_columns = required_raw_columns - set(df.columns)

        if missing_raw_columns:
            raise ValueError(
                f"Missing required raw columns: {sorted(missing_raw_columns)}"
            )

        # Deterministic chronological order before creating any historical feature.
        df = df.sort_values(
            ['match_date', 'match_id']
        ).reset_index(drop=True)

        # Build team statistics using only information available
        # before each target match.
        df = self.add_point_in_time_team_stats(df)

        # Derive the complete feature set and target variables.
        df = self.create_enhanced_features(df)

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
            'season_stage_normalized', 'home_form_trend', 'away_form_trend', 'form_momentum_gap',

            # Outcome-specific features
            'goal_difference_expectation', 'win_probability_indicator',
            'draw_probability_indicator', 'home_consistency', 'away_consistency'
        ]

        # Verify features exist
        available_features = [col for col in outcome_features if col in df.columns]
        missing_features = set(outcome_features) - set(available_features)

        if missing_features:
            print(f"Warning: Missing features: {missing_features}")

        if not available_features:
         raise ValueError(
             "No configured training features were generated"
            )

        self.feature_columns = available_features
        print(f"Using {len(available_features)} features for training")

        # Preserve chronological order explicitly.
        df = df.sort_values(
            ['match_date', 'match_id']
        ).reset_index(drop=True)

        X = df[available_features].fillna(0)
        y_home_goals = df['home_goals'].reset_index(drop=True)
        y_away_goals = df['away_goals'].reset_index(drop=True)
        y_outcome = df['match_outcome'].reset_index(drop=True)

        return X, y_home_goals, y_away_goals, y_outcome

    def train_enhanced_outcome_models(
        self,
        X_train: pd.DataFrame,
        y_train: pd.Series,
        X_validation: pd.DataFrame,
        y_validation: pd.Series,
    ) -> Dict:
        """
        Train the enhanced outcome ensemble using an explicit temporal split.

        Training data is used for fitting and internal TimeSeriesSplit CV.
        Validation data is used for model evaluation and development decisions.
        The final test season must not be passed to this method.
        """
        print("Training enhanced outcome models...")

        if X_train.empty or X_validation.empty:
            raise ValueError(
                "Training and validation datasets must not be empty"
            )

        if len(X_train) != len(y_train):
            raise ValueError(
                "X_train and y_train have different lengths"
            )

        if len(X_validation) != len(y_validation):
            raise ValueError(
                "X_validation and y_validation have different lengths"
            )

        if list(X_train.columns) != list(X_validation.columns):
            raise ValueError(
                "Training and validation feature contracts differ"
            )

        # Fit preprocessing only on historical training data.
        X_train_scaled = self.scaler.fit_transform(X_train)
        X_validation_scaled = self.scaler.transform(X_validation)

        # Fit label encoder only on historical training labels.
        self.label_encoder.fit(y_train)

        unseen_labels = (
            set(y_validation.unique())
            - set(self.label_encoder.classes_)
        )
        if unseen_labels:
            raise ValueError(
                f"Test period contains unseen outcome labels: {unseen_labels}"
            )

        y_train_encoded = self.label_encoder.transform(y_train)
        y_validation_encoded = self.label_encoder.transform(
            y_validation
        )

        results = {
            'train_size': len(X_train),
            'test_size': len(X_validation),
            'split_type': 'chronological_holdout',
        }

        # Majority-class baseline learned exclusively from training data.
        majority_class = y_train.value_counts().idxmax()

        baseline_pred = np.full(
            len(y_validation),
            majority_class,
            dtype=object,
        )

        results['baseline_class'] = majority_class
        results['baseline_accuracy'] = accuracy_score(
            y_validation,
            baseline_pred,
        )

        results['baseline_balanced_accuracy'] = (
            balanced_accuracy_score(
                y_validation,
                baseline_pred,
            )
        )

        results['baseline_macro_f1'] = f1_score(
            y_validation,
            baseline_pred,
            average='macro',
            zero_division=0,
        )

        print(
            "Majority baseline "
            f"({majority_class}): "
            f"{results['baseline_accuracy']:.3f}"
        )

        # Calculate class weights for balanced training
        from sklearn.utils.class_weight import compute_class_weight
        classes = np.unique(y_train_encoded)
        class_weights = compute_class_weight('balanced', classes=classes, y=y_train_encoded)
        sample_weights = np.array([class_weights[y] for y in y_train_encoded])

        # 1. BALANCED XGBOOST with improved hyperparameters
        print("Training XGBoost classifier...")
        xgb_params = {
            'n_estimators': 500,       # Increased for better learning
            'learning_rate': 0.05,     # Slightly higher for faster convergence
            'max_depth': 6,            # Deeper trees for complex patterns
            'min_child_weight': 1,     # More flexible
            'subsample': 0.9,          # Higher sampling for more data
            'colsample_bytree': 0.9,   # Higher feature sampling
            'reg_alpha': 0.01,         # Reduced regularization
            'reg_lambda': 0.1,         # Reduced regularization
            'gamma': 0.1,              # Added gamma for pruning
            'random_state': 42,
            'n_jobs': -1,
            'objective': 'multi:softprob',
            'eval_metric': 'mlogloss'
        }

        xgb_model = xgb.XGBClassifier(**xgb_params)
        xgb_model.fit(X_train_scaled, y_train_encoded, sample_weight=sample_weights)
        xgb_pred = xgb_model.predict(X_validation_scaled)
        results['xgb_accuracy'] = accuracy_score(y_validation_encoded, xgb_pred)

        # 2. LIGHTGBM with improved parameters
        print("Training LightGBM classifier...")
        lgb_params = {
            'n_estimators': 500,        # Increased estimators
            'learning_rate': 0.05,      # Matched with XGBoost
            'max_depth': 7,             # Deeper trees
            'num_leaves': 63,           # More leaves for complexity
            'min_child_samples': 10,    # Lower minimum for flexibility
            'subsample': 0.9,           # Higher sampling
            'colsample_bytree': 0.9,    # Higher feature sampling
            'reg_alpha': 0.01,          # Reduced regularization
            'reg_lambda': 0.1,          # Reduced regularization
            'min_split_gain': 0.01,     # Added minimum split gain
            'random_state': 42,
            'n_jobs': -1,
            'objective': 'multiclass',
            'metric': 'multi_logloss',
            'verbose': -1,
            'boost_from_average': False  # Better for imbalanced classes
        }

        lgb_model = lgb.LGBMClassifier(**lgb_params)
        lgb_model.fit(X_train_scaled, y_train_encoded, sample_weight=sample_weights)
        lgb_pred = lgb_model.predict(X_validation_scaled)
        results['lgb_accuracy'] = accuracy_score(y_validation_encoded, lgb_pred)

        # 3. ENHANCED NEURAL NETWORK for non-linear patterns
        print("Training Neural Network...")
        nn_model = MLPClassifier(
            hidden_layer_sizes=(128, 64, 32, 16),  # Deeper network
            activation='relu',
            solver='adam',
            alpha=0.0001,              # Reduced regularization
            learning_rate='adaptive',
            learning_rate_init=0.002,  # Higher initial learning rate
            max_iter=800,              # More iterations
            random_state=42,
            early_stopping=False,
            beta_1=0.9,                # Adam parameters
            beta_2=0.999
        )

        nn_model.fit(X_train_scaled, y_train_encoded)
        nn_pred = nn_model.predict(X_validation_scaled)
        results['nn_accuracy'] = accuracy_score(y_validation_encoded, nn_pred)

        # 4. RANDOM FOREST for ensemble diversity
        print("Training Random Forest...")
        rf_model = RandomForestClassifier(
            n_estimators=300,        # More trees
            max_depth=12,            # Deeper trees
            min_samples_split=3,     # More flexible splits
            min_samples_leaf=1,      # More flexible leaves
            max_features='sqrt',     # Good balance
            max_samples=0.9,         # Bootstrap sampling
            random_state=42,
            n_jobs=-1,
            class_weight='balanced'  # Handle class imbalance
        )

        rf_model.fit(X_train_scaled, y_train_encoded)
        rf_pred = rf_model.predict(X_validation_scaled)
        results['rf_accuracy'] = accuracy_score(y_validation_encoded, rf_pred)

        # 5. OPTIMIZED VOTING ENSEMBLE
        print("Creating optimized ensemble...")

        # Get probabilities for soft voting
        xgb_proba = xgb_model.predict_proba(X_validation_scaled)
        lgb_proba = lgb_model.predict_proba(X_validation_scaled)
        nn_proba = nn_model.predict_proba(X_validation_scaled)
        rf_proba = rf_model.predict_proba(X_validation_scaled)

        # Optimize ensemble weights based on individual model performance
        # Fixed equal weights.
        # The final test set must not be used for model selection
        # or ensemble-weight optimization.
        weights = [0.25, 0.25, 0.25, 0.25]

        # Weighted ensemble prediction
        ensemble_proba = (weights[0] * xgb_proba +
                         weights[1] * lgb_proba +
                         weights[2] * nn_proba +
                         weights[3] * rf_proba)

        ensemble_pred = np.argmax(ensemble_proba, axis=1)
        results['ensemble_accuracy'] = accuracy_score(
            y_validation_encoded,
            ensemble_pred,
        )

        results['ensemble_balanced_accuracy'] = (
            balanced_accuracy_score(
                y_validation_encoded,
                ensemble_pred,
            )
        )

        results['ensemble_macro_f1'] = f1_score(
            y_validation_encoded,
            ensemble_pred,
            average='macro',
            zero_division=0,
        )

        results['ensemble_log_loss'] = log_loss(
            y_validation_encoded,
            ensemble_proba,
            labels=np.arange(
                len(self.label_encoder.classes_)
            ),
        )

        results['ensemble_weights'] = weights

        # Time-series cross-validation on the training period only.
        print("Performing time-series cross-validation...")

        tscv = TimeSeriesSplit(n_splits=5)
        cv_scores = []

        for fold, (train_idx, val_idx) in enumerate(
            tscv.split(X_train),
            start=1
        ):
            X_fold_train = X_train.iloc[train_idx]
            X_fold_val = X_train.iloc[val_idx]

            y_fold_train = y_train.iloc[train_idx]
            y_fold_val = y_train.iloc[val_idx]

            fold_encoder = LabelEncoder()
            y_fold_train_encoded = fold_encoder.fit_transform(y_fold_train)

            unseen_fold_labels = (
                set(y_fold_val.unique()) - set(fold_encoder.classes_)
            )

            if unseen_fold_labels:
                print(
                    f"Skipping fold {fold}: unseen labels "
                    f"{unseen_fold_labels}"
                )
                continue

            y_fold_val_encoded = fold_encoder.transform(y_fold_val)

            fold_scaler = StandardScaler()
            X_fold_train_scaled = fold_scaler.fit_transform(X_fold_train)
            X_fold_val_scaled = fold_scaler.transform(X_fold_val)

            fold_model = xgb.XGBClassifier(**xgb_params)
            fold_model.fit(
                X_fold_train_scaled,
                y_fold_train_encoded
            )

            fold_pred = fold_model.predict(X_fold_val_scaled)
            fold_accuracy = accuracy_score(
                y_fold_val_encoded,
                fold_pred
            )

            cv_scores.append(fold_accuracy)

            print(
                f"  Fold {fold}: "
                f"{fold_accuracy:.3f} "
                f"({len(train_idx)} train / {len(val_idx)} validation)"
            )

        if not cv_scores:
            raise ValueError(
                "No valid time-series cross-validation folds available"
            )

        results['cv_mean_accuracy'] = float(np.mean(cv_scores))
        results['cv_std_accuracy'] = float(np.std(cv_scores))
        results['cv_folds'] = len(cv_scores)

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

    def predict_enhanced_match(
        self,
        home_team_id: int,
        away_team_id: int,
        season: str = None,
    ) -> Dict:
        """
        Online inference is intentionally disabled for Enhanced v3.

        Enhanced v3 is currently an offline 1X2 classification model.
        Training and evaluation use causal point-in-time features built
        from historical matches.

        Online inference will be enabled only when the same 40-feature
        contract can be reconstructed without train/serve skew.
        """
        raise NotImplementedError(
            "Enhanced v3 online prediction is not available yet. "
            "The current model is an offline 1X2 classifier evaluated "
            "with causal point-in-time historical features."
        )



    def load_enhanced_models(self, path: str = None):
        """Load enhanced models from disk"""
        path = path or os.path.join(os.path.dirname(__file__), 'models')

        try:
            # Load the outcome model
            outcome_model_path = os.path.join(path, 'enhanced_outcome_model.pkl')
            if os.path.exists(outcome_model_path):
                self.outcome_model = joblib.load(outcome_model_path)

            # Load the scaler
            scaler_path = os.path.join(path, 'enhanced_scaler.pkl')
            if os.path.exists(scaler_path):
                self.scaler = joblib.load(scaler_path)

            # Load metadata
            metadata_path = os.path.join(path, 'enhanced_metadata.json')
            if os.path.exists(metadata_path):
                with open(metadata_path, 'r') as f:
                    metadata = json.load(f)
                    self.feature_columns = metadata.get('feature_columns', [])

            # print("Enhanced models loaded successfully")  # Commented for JSON parsing
            return True
        except Exception as e:
            print(f"Error loading enhanced models: {e}")
            return False

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

        print("Preparing enhanced training data...")
        X, y_home, y_away, y_outcome = predictor.prepare_enhanced_training_data(df)

        ordered_df = (
            df
            .sort_values(['match_date', 'match_id'])
            .reset_index(drop=True)
        )

        seasons = ordered_df['season'].astype(str)

        train_mask = seasons.isin([
            '2020-21',
            '2021-22',
            '2022-23',
        ])

        validation_mask = seasons.eq('2023-24')

        # IMPORTANT:
        # 2024-25 is intentionally excluded here.
        # It is reserved for final evaluation only.

        X_train = X.loc[train_mask].copy()
        y_train = y_outcome.loc[train_mask].copy()

        X_validation = X.loc[validation_mask].copy()
        y_validation = y_outcome.loc[
            validation_mask
        ].copy()

        print(
            f"Train: {len(X_train)} | "
            f"Validation: {len(X_validation)}"
        )

        results = predictor.train_enhanced_outcome_models(
            X_train,
            y_train,
            X_validation,
            y_validation,
        )

        print("\nBaseline:")
        print(
            f"Majority class: "
            f"{results['baseline_class']}"
        )
        print(
            f"Baseline Accuracy: "
            f"{results['baseline_accuracy']:.3f}"
        )
        print(
            f"Baseline Balanced Accuracy: "
            f"{results['baseline_balanced_accuracy']:.3f}"
        )
        print(
            f"Baseline Macro F1: "
            f"{results['baseline_macro_f1']:.3f}"
        )

        print("\nEnsemble Validation:")
        print(
            f"Accuracy: "
            f"{results['ensemble_accuracy']:.3f}"
        )
        print(
            f"Balanced Accuracy: "
            f"{results['ensemble_balanced_accuracy']:.3f}"
        )
        print(
            f"Macro F1: "
            f"{results['ensemble_macro_f1']:.3f}"
        )
        print(
            f"Log Loss: "
            f"{results['ensemble_log_loss']:.3f}"
        )

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

        # Load models first
        predictor.load_enhanced_models()
        prediction = predictor.predict_enhanced_match(home_team_id, away_team_id)
        print(json.dumps(prediction, indent=2))