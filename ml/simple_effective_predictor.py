#!/usr/bin/env python3
"""
Simple Effective Football Predictor
Replaces complex ML models with proven statistical approach
Target: >52% accuracy for match outcomes
"""

import sys
import json
import numpy as np
from sqlalchemy import create_engine, text
import os
from dotenv import load_dotenv

# Load environment
load_dotenv()

class SimpleEffectivePredictor:
    def __init__(self):
        """Initialize with database connection"""
        self.engine = create_engine(
            f"mysql+pymysql://{os.getenv('DB_USERNAME')}:{os.getenv('DB_PASSWORD')}@{os.getenv('DB_HOST')}/{os.getenv('DB_DATABASE')}"
        )
    
    def get_team_stats(self, team_id, days=90, home_only=False, away_only=False):
        """Get comprehensive team statistics"""
        location_filter = ""
        if home_only:
            location_filter = "AND fm.home_team_id = :team_id"
        elif away_only:
            location_filter = "AND fm.away_team_id = :team_id"
        else:
            location_filter = "AND (fm.home_team_id = :team_id OR fm.away_team_id = :team_id)"
        
        query = f"""
        SELECT 
            COUNT(*) as matches,
            AVG(CASE 
                WHEN fm.home_team_id = :team_id AND fm.home_goals > fm.away_goals THEN 3
                WHEN fm.away_team_id = :team_id AND fm.away_goals > fm.home_goals THEN 3
                WHEN fm.home_goals = fm.away_goals THEN 1
                ELSE 0
            END) as points_per_game,
            
            AVG(CASE 
                WHEN fm.home_team_id = :team_id THEN fm.home_goals 
                ELSE fm.away_goals 
            END) as goals_for_avg,
            
            AVG(CASE 
                WHEN fm.home_team_id = :team_id THEN fm.away_goals 
                ELSE fm.home_goals 
            END) as goals_against_avg,
            
            SUM(CASE 
                WHEN fm.home_team_id = :team_id AND fm.home_goals > fm.away_goals THEN 1
                WHEN fm.away_team_id = :team_id AND fm.away_goals > fm.home_goals THEN 1
                ELSE 0
            END) as wins,
            
            SUM(CASE WHEN fm.home_goals = fm.away_goals THEN 1 ELSE 0 END) as draws,
            
            AVG(CASE 
                WHEN fm.home_team_id = :team_id THEN fm.home_goals_first_half 
                ELSE fm.away_goals_first_half 
            END) as first_half_goals_avg,
            
            AVG(COALESCE(fm.home_goals_first_half, 0) + COALESCE(fm.away_goals_first_half, 0)) as match_first_half_avg
            
        FROM matches fm
        WHERE fm.status = 'finished'
            AND fm.match_date >= DATE_SUB(NOW(), INTERVAL :days DAY)
            AND fm.home_goals IS NOT NULL 
            AND fm.away_goals IS NOT NULL
            {location_filter}
        """
        
        with self.engine.connect() as conn:
            result = conn.execute(text(query), {"team_id": team_id, "days": days}).fetchone()
            
            if not result or result.matches == 0:
                return self.get_default_stats()
            
            return {
                'matches': result.matches,
                'points_per_game': float(result.points_per_game or 1.0),
                'goals_for_avg': float(result.goals_for_avg or 1.2),
                'goals_against_avg': float(result.goals_against_avg or 1.2),
                'wins': result.wins or 0,
                'draws': result.draws or 0,
                'win_rate': result.wins / result.matches if result.matches > 0 else 0.33,
                'draw_rate': result.draws / result.matches if result.matches > 0 else 0.25,
                'first_half_goals_avg': float(result.first_half_goals_avg or 0.6),
                'match_first_half_avg': float(result.match_first_half_avg or 1.1)
            }
    
    def get_default_stats(self):
        """Default stats for teams without recent matches"""
        return {
            'matches': 0,
            'points_per_game': 1.2,
            'goals_for_avg': 1.3,
            'goals_against_avg': 1.3,
            'wins': 0,
            'draws': 0,
            'win_rate': 0.35,
            'draw_rate': 0.25,
            'first_half_goals_avg': 0.65,
            'match_first_half_avg': 1.15
        }
    
    def get_head_to_head(self, home_team_id, away_team_id, days=730):
        """Get head-to-head statistics"""
        query = """
        SELECT 
            COUNT(*) as matches,
            AVG(fm.home_goals) as avg_home_goals,
            AVG(fm.away_goals) as avg_away_goals,
            SUM(CASE WHEN fm.home_goals > fm.away_goals THEN 1 ELSE 0 END) as home_wins,
            SUM(CASE WHEN fm.home_goals = fm.away_goals THEN 1 ELSE 0 END) as draws,
            SUM(CASE WHEN fm.away_goals > fm.home_goals THEN 1 ELSE 0 END) as away_wins
        FROM matches fm
        WHERE fm.status = 'finished'
            AND fm.home_team_id = :home_id 
            AND fm.away_team_id = :away_id
            AND fm.match_date >= DATE_SUB(NOW(), INTERVAL :days DAY)
            AND fm.home_goals IS NOT NULL
        """
        
        with self.engine.connect() as conn:
            result = conn.execute(text(query), {
                "home_id": home_team_id, 
                "away_id": away_team_id, 
                "days": days
            }).fetchone()
            
            return {
                'matches': result.matches or 0,
                'avg_home_goals': float(result.avg_home_goals or 1.3),
                'avg_away_goals': float(result.avg_away_goals or 1.1),
                'home_wins': result.home_wins or 0,
                'draws': result.draws or 0,
                'away_wins': result.away_wins or 0
            }
    
    def predict_match_outcome(self, home_team_id, away_team_id):
        """
        Predict match outcome using proven statistical methods
        Target: >52% accuracy
        """
        # Get team statistics
        home_overall = self.get_team_stats(home_team_id, days=60)
        away_overall = self.get_team_stats(away_team_id, days=60)
        home_home = self.get_team_stats(home_team_id, days=90, home_only=True)
        away_away = self.get_team_stats(away_team_id, days=90, away_only=True)
        h2h = self.get_head_to_head(home_team_id, away_team_id)
        
        # PROVEN ALGORITHM: Weighted strength calculation
        # Home advantage: 0.35 goals (proven in football analytics)
        home_attack = (home_overall['goals_for_avg'] * 0.6 + home_home['goals_for_avg'] * 0.4)
        home_defense = (home_overall['goals_against_avg'] * 0.6 + home_home['goals_against_avg'] * 0.4)
        
        away_attack = (away_overall['goals_for_avg'] * 0.6 + away_away['goals_for_avg'] * 0.4)
        away_defense = (away_overall['goals_against_avg'] * 0.6 + away_away['goals_against_avg'] * 0.4)
        
        # Expected goals calculation (Poisson-based)
        home_expected_goals = (home_attack + away_defense) / 2 + 0.35  # Home advantage
        away_expected_goals = (away_attack + home_defense) / 2
        
        # Head-to-head adjustment (if sufficient data)
        if h2h['matches'] >= 3:
            h2h_weight = min(0.2, h2h['matches'] * 0.05)  # Max 20% influence
            home_expected_goals = home_expected_goals * (1 - h2h_weight) + h2h['avg_home_goals'] * h2h_weight
            away_expected_goals = away_expected_goals * (1 - h2h_weight) + h2h['avg_away_goals'] * h2h_weight
        
        # Form adjustment (recent 10 games worth more)
        home_recent = self.get_team_stats(home_team_id, days=30)
        away_recent = self.get_team_stats(away_team_id, days=30)
        
        if home_recent['matches'] >= 5:
            form_factor = (home_recent['points_per_game'] - home_overall['points_per_game']) * 0.1
            home_expected_goals += form_factor
        
        if away_recent['matches'] >= 5:
            form_factor = (away_recent['points_per_game'] - away_overall['points_per_game']) * 0.1
            away_expected_goals += form_factor
        
        # Poisson probability calculation
        goal_diff = home_expected_goals - away_expected_goals
        
        # IMPROVED CALIBRATED THRESHOLDS (Enhanced draw detection)
        if goal_diff > 0.7:  # Strong home advantage
            predicted_outcome = 'home_win'
            confidence = min(0.85, 0.55 + goal_diff * 0.15)
        elif goal_diff < -0.5:  # Strong away advantage  
            predicted_outcome = 'away_win'
            confidence = min(0.85, 0.55 + abs(goal_diff) * 0.15)
        else:  # Close match - enhanced draw detection
            # Draw more likely when:
            # 1. Very similar team strength (tighter threshold)
            # 2. Both teams have high draw rates
            # 3. H2H has many draws
            # 4. Both teams score around 1.2-1.5 goals (draw-prone range)
            draw_indicators = 0
            
            # Strength similarity (very close teams)
            if abs(goal_diff) < 0.15:
                draw_indicators += 2  # Strong indicator
            elif abs(goal_diff) < 0.3:
                draw_indicators += 1
            
            # High draw rates for both teams
            if home_overall['draw_rate'] > 0.25 and away_overall['draw_rate'] > 0.25:
                draw_indicators += 1
                if home_overall['draw_rate'] > 0.35 and away_overall['draw_rate'] > 0.35:
                    draw_indicators += 1  # Very high draw rates
            
            # Head-to-head draw tendency
            if h2h['matches'] >= 3:
                h2h_draw_rate = h2h['draws'] / h2h['matches']
                if h2h_draw_rate > 0.33:
                    draw_indicators += 1
                if h2h_draw_rate > 0.5:
                    draw_indicators += 1
            
            # Both teams in "draw-prone" scoring range (1.1-1.6 goals)
            if (1.1 <= home_expected_goals <= 1.6) and (1.1 <= away_expected_goals <= 1.6):
                draw_indicators += 1
            
            # Defensive teams (both score <1.3 goals typically)
            if home_expected_goals < 1.3 and away_expected_goals < 1.3:
                draw_indicators += 1
                
            # DECISION LOGIC: Need 3+ indicators for draw prediction
            if draw_indicators >= 3:
                predicted_outcome = 'draw'
                confidence = min(0.75, 0.55 + (draw_indicators - 3) * 0.05)
            elif goal_diff > 0:
                predicted_outcome = 'home_win'
                confidence = 0.52 + abs(goal_diff) * 0.1
            else:
                predicted_outcome = 'away_win'
                confidence = 0.52 + abs(goal_diff) * 0.1
        
        return {
            'predicted_outcome': predicted_outcome,
            'confidence': round(confidence * 100, 1),
            'home_expected_goals': round(home_expected_goals, 2),
            'away_expected_goals': round(away_expected_goals, 2),
            'model_version': 'simple_effective_v1.2',
            'debug': {
                'home_attack': round(home_attack, 2),
                'home_defense': round(home_defense, 2),
                'away_attack': round(away_attack, 2),
                'away_defense': round(away_defense, 2),
                'goal_diff': round(goal_diff, 2),
                'h2h_matches': h2h['matches']
            }
        }
    
    def predict_over_under(self, home_team_id, away_team_id, threshold=2.5):
        """Predict over/under goals with improved accuracy"""
        home_stats = self.get_team_stats(home_team_id, days=60)
        away_stats = self.get_team_stats(away_team_id, days=60)
        
        # More sophisticated total goals calculation
        home_attack_strength = home_stats['goals_for_avg']
        away_attack_strength = away_stats['goals_for_avg'] 
        home_defense_weakness = home_stats['goals_against_avg']
        away_defense_weakness = away_stats['goals_against_avg']
        
        # Expected goals using attack vs defense matchup
        home_expected = (home_attack_strength + away_defense_weakness) / 2 + 0.3  # Home advantage
        away_expected = (away_attack_strength + home_defense_weakness) / 2
        
        total_expected = home_expected + away_expected
        
        # League adjustment (different leagues have different scoring patterns)
        if total_expected > 3.5:  # High-scoring teams
            total_expected *= 0.95  # Slightly reduce
        elif total_expected < 2.0:  # Defensive teams
            total_expected *= 1.1   # Slightly increase
        
        confidence = min(90, 55 + abs(total_expected - threshold) * 25)
        
        return {
            'prediction': 'over' if total_expected > threshold else 'under',
            'confidence': confidence,
            'expected_total_goals': round(total_expected, 2)
        }
    
    def predict_first_half_goals(self, home_team_id, away_team_id):
        """Predict first half over 0.5 goals"""
        home_stats = self.get_team_stats(home_team_id, days=60)
        away_stats = self.get_team_stats(away_team_id, days=60)
        
        first_half_expected = (home_stats['first_half_goals_avg'] + away_stats['first_half_goals_avg']) / 2
        first_half_expected += 0.1  # Home advantage
        
        confidence = 75 if first_half_expected > 0.8 else 65
        
        return {
            'prediction': 'over_0_5_first_half' if first_half_expected > 0.6 else 'under_0_5_first_half',
            'confidence': confidence,
            'expected_first_half_goals': round(first_half_expected, 2)
        }


def main():
    if len(sys.argv) < 3:
        print("Usage: python simple_effective_predictor.py <home_team_id> <away_team_id>")
        sys.exit(1)
    
    predictor = SimpleEffectivePredictor()
    home_id = int(sys.argv[1])
    away_id = int(sys.argv[2])
    
    try:
        # Main outcome prediction
        outcome = predictor.predict_match_outcome(home_id, away_id)
        over_under = predictor.predict_over_under(home_id, away_id)
        first_half = predictor.predict_first_half_goals(home_id, away_id)
        
        result = {
            'success': True,
            'predictions': {
                'match_outcome': outcome,
                'over_under_2_5': over_under,
                'first_half_over_0_5': first_half
            },
            'model_info': {
                'name': 'Simple Effective Predictor',
                'version': 'v1.2',
                'target_accuracy': '>52%',
                'method': 'statistical_poisson_enhanced_draw'
            }
        }
        
        print(json.dumps(result, indent=2))
        
    except Exception as e:
        print(json.dumps({
            'success': False,
            'error': str(e),
            'model': 'simple_effective_v1.2'
        }))

if __name__ == "__main__":
    main()