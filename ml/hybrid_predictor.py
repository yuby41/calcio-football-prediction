#!/usr/bin/env python3
"""
Hybrid Ensemble Predictor
Combines Enhanced ML models with Simple Statistical methods for optimal accuracy
"""

import json
import sys
import subprocess
import os
from typing import Dict, Any, Optional

class HybridPredictor:
    def __init__(self):
        """Initialize hybrid predictor"""
        self.model_version = "hybrid_ensemble_v1.0"
        self.ml_dir = os.path.dirname(os.path.abspath(__file__))
        
    def predict(self, home_team_id: int, away_team_id: int) -> Dict[str, Any]:
        """
        Combine enhanced ML and simple statistical predictions
        Weights: Enhanced 70%, Simple 30%
        """
        enhanced_result = self._get_enhanced_prediction(home_team_id, away_team_id)
        simple_result = self._get_simple_prediction(home_team_id, away_team_id)
        
        if not enhanced_result and not simple_result:
            raise ValueError("Both models failed to generate predictions")
        
        # If only one model works, use that one
        if enhanced_result and not simple_result:
            enhanced_result['model_version'] = 'hybrid_enhanced_only'
            return enhanced_result
            
        if simple_result and not enhanced_result:
            simple_result['model_version'] = 'hybrid_simple_only'
            return simple_result
        
        # Combine predictions with weighted average
        return self._combine_predictions(enhanced_result, simple_result, 0.7, 0.3)
    
    def _get_enhanced_prediction(self, home_team_id: int, away_team_id: int) -> Optional[Dict[str, Any]]:
        """Get prediction from enhanced model"""
        try:
            # Use virtual environment for enhanced model
            base_path = os.path.dirname(self.ml_dir)
            cmd = [
                '/bin/bash', '-c',
                f'cd {base_path} && source ml_env/bin/activate && python ml/enhanced_football_predictor.py predict {home_team_id} {away_team_id}'
            ]
            
            result = subprocess.run(
                cmd, 
                capture_output=True, 
                text=True, 
                timeout=30
            )
            
            if result.returncode == 0:
                return json.loads(result.stdout)
            else:
                print(f"Enhanced model failed: {result.stderr}", file=sys.stderr)
                return None
                
        except Exception as e:
            print(f"Enhanced model error: {e}", file=sys.stderr)
            return None
    
    def _get_simple_prediction(self, home_team_id: int, away_team_id: int) -> Optional[Dict[str, Any]]:
        """Get prediction from simple model"""
        try:
            # Use virtual environment for consistency
            base_path = os.path.dirname(self.ml_dir)
            cmd = [
                '/bin/bash', '-c',
                f'cd {base_path} && source ml_env/bin/activate && python ml/simple_effective_predictor.py predict {home_team_id} {away_team_id}'
            ]
            
            result = subprocess.run(
                cmd,
                capture_output=True,
                text=True,
                timeout=15
            )
            
            if result.returncode == 0:
                return json.loads(result.stdout)
            else:
                print(f"Simple model failed: {result.stderr}", file=sys.stderr)
                return None
                
        except Exception as e:
            print(f"Simple model error: {e}", file=sys.stderr)
            return None
    
    def _combine_predictions(self, enhanced: Dict[str, Any], simple: Dict[str, Any], 
                           enhanced_weight: float, simple_weight: float) -> Dict[str, Any]:
        """Combine predictions using weighted average"""
        
        # Weighted goal predictions
        home_goals = (enhanced['home_goals_prediction'] * enhanced_weight + 
                     simple['home_goals_prediction'] * simple_weight)
        away_goals = (enhanced['away_goals_prediction'] * enhanced_weight + 
                     simple['away_goals_prediction'] * simple_weight)
        
        # Weighted probabilities
        home_win_prob = (enhanced['home_win_probability'] * enhanced_weight + 
                        simple['home_win_probability'] * simple_weight)
        draw_prob = (enhanced['draw_probability'] * enhanced_weight + 
                    simple['draw_probability'] * simple_weight)
        away_win_prob = (enhanced['away_win_probability'] * enhanced_weight + 
                        simple['away_win_probability'] * simple_weight)
        
        # Normalize probabilities
        total_prob = home_win_prob + draw_prob + away_win_prob
        home_win_prob /= total_prob
        draw_prob /= total_prob  
        away_win_prob /= total_prob
        
        # Determine outcome based on highest probability
        outcomes = {
            'home_win': home_win_prob,
            'draw': draw_prob,
            'away_win': away_win_prob
        }
        predicted_outcome = max(outcomes, key=outcomes.get)
        confidence_score = max(outcomes.values())
        
        # Other weighted predictions
        both_teams_score_prob = (enhanced['both_teams_score_probability'] * enhanced_weight + 
                               simple['both_teams_score_probability'] * simple_weight)
        over_25_prob = (enhanced['over_2_5_probability'] * enhanced_weight + 
                       simple['over_2_5_probability'] * simple_weight)
        first_half_prob = (enhanced['first_half_over_0_5_probability'] * enhanced_weight + 
                          simple['first_half_over_0_5_probability'] * simple_weight)
        
        # First half goals
        home_first_half = (enhanced.get('home_goals_first_half_prediction', home_goals * 0.42) * enhanced_weight + 
                          simple.get('home_goals_first_half_prediction', home_goals * 0.42) * simple_weight)
        away_first_half = (enhanced.get('away_goals_first_half_prediction', away_goals * 0.42) * enhanced_weight + 
                          simple.get('away_goals_first_half_prediction', away_goals * 0.42) * simple_weight)
        
        return {
            'home_goals_prediction': round(home_goals, 2),
            'away_goals_prediction': round(away_goals, 2),
            'home_win_probability': round(home_win_prob, 4),
            'draw_probability': round(draw_prob, 4),
            'away_win_probability': round(away_win_prob, 4),
            'both_teams_score_probability': round(both_teams_score_prob, 4),
            'over_2_5_probability': round(over_25_prob, 4),
            'under_2_5_probability': round(1 - over_25_prob, 4),
            'first_half_over_0_5_probability': round(first_half_prob, 4),
            'home_goals_first_half_prediction': round(home_first_half, 2),
            'away_goals_first_half_prediction': round(away_first_half, 2),
            'predicted_outcome': predicted_outcome,
            'confidence_score': round(confidence_score, 4),
            'model_version': self.model_version,
            'ensemble_weights': {
                'enhanced_weight': enhanced_weight,
                'simple_weight': simple_weight
            },
            'component_models': {
                'enhanced': enhanced.get('model_version', 'enhanced'),
                'simple': simple.get('model_version', 'simple')
            }
        }

def main():
    """Main function for command line usage"""
    if len(sys.argv) < 2:
        print("Usage: python hybrid_predictor.py [predict] <home_team_id> <away_team_id>")
        sys.exit(1)
    
    # Handle 'predict' command
    start_idx = 1
    if sys.argv[1] == 'predict':
        start_idx = 2
    
    if len(sys.argv) < start_idx + 2:
        print("Usage: python hybrid_predictor.py [predict] <home_team_id> <away_team_id>")
        sys.exit(1)
    
    try:
        home_id = int(sys.argv[start_idx])
        away_id = int(sys.argv[start_idx + 1])
        
        predictor = HybridPredictor()
        result = predictor.predict(home_id, away_id)
        
        print(json.dumps(result))
        
    except ValueError as e:
        print(json.dumps({
            'error': str(e),
            'model_version': 'hybrid_ensemble_v1.0'
        }))
        sys.exit(1)
    except Exception as e:
        print(json.dumps({
            'error': f"Unexpected error: {str(e)}",
            'model_version': 'hybrid_ensemble_v1.0'
        }))
        sys.exit(1)

if __name__ == "__main__":
    main()