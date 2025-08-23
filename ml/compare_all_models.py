#!/usr/bin/env python3
"""
Script de comparación completa entre todos los modelos ML
Genera predicciones con todos los modelos y las compara
"""

import json
import subprocess
import sys
import os
from typing import Dict, List, Any, Optional
import time
import pandas as pd

class ModelComparator:
    def __init__(self):
        self.ml_dir = os.path.dirname(os.path.abspath(__file__))
        self.base_path = os.path.dirname(self.ml_dir)
        
        self.models = {
            'simple': 'simple_effective_predictor.py',
            'enhanced': 'enhanced_football_predictor.py', 
            'base': 'football_predictor.py',
            'hybrid': 'hybrid_predictor.py'
        }
        
        # Test match pairs (home_id, away_id) 
        self.test_matches = [
            (1, 2), (3, 4), (5, 6), (7, 8), (9, 10),
            (100, 200), (150, 250), (300, 400), (500, 600), (700, 800),
            (1000, 1100), (1200, 1300), (1400, 1500), (1600, 1700), (1800, 1900),
            (2000, 2100), (2200, 2300), (2400, 2500), (2600, 2700), (2800, 2900)
        ]
        
    def run_model_prediction(self, model_name: str, home_id: int, away_id: int) -> Optional[Dict[str, Any]]:
        """Run prediction for a specific model"""
        script = self.models[model_name]
        
        try:
            cmd = [
                '/bin/bash', '-c',
                f'cd {self.base_path} && source ml_env/bin/activate && python ml/{script} predict {home_id} {away_id}'
            ]
            
            result = subprocess.run(
                cmd,
                capture_output=True,
                text=True,
                timeout=45
            )
            
            if result.returncode == 0:
                prediction = json.loads(result.stdout)
                
                # Standardize output format
                return {
                    'model': model_name,
                    'predicted_outcome': prediction.get('predicted_outcome'),
                    'confidence_score': prediction.get('confidence_score', 0),
                    'home_goals_prediction': prediction.get('home_goals_prediction', 0),
                    'away_goals_prediction': prediction.get('away_goals_prediction', 0),
                    'both_teams_score_probability': prediction.get('both_teams_score_probability', 0),
                    'over_2_5_probability': prediction.get('over_2_5_probability', 0),
                    'model_version': prediction.get('model_version', f'{model_name}_unknown'),
                    'success': True
                }
            else:
                print(f"❌ {model_name} failed: {result.stderr}", file=sys.stderr)
                return {'model': model_name, 'success': False, 'error': result.stderr}
                
        except Exception as e:
            print(f"❌ {model_name} error: {e}", file=sys.stderr)
            return {'model': model_name, 'success': False, 'error': str(e)}
    
    def compare_models_on_matches(self) -> Dict[str, Any]:
        """Compare all models on test matches"""
        print("🔍 COMPARACIÓN COMPLETA DE MODELOS ML")
        print("=" * 50)
        
        results = {
            'comparisons': [],
            'model_stats': {model: {'predictions': 0, 'errors': 0, 'total_confidence': 0} 
                           for model in self.models.keys()},
            'outcome_agreement': {},
            'performance_metrics': {}
        }
        
        print(f"🎯 Probando {len(self.test_matches)} parejas de equipos...")
        print()
        
        for i, (home_id, away_id) in enumerate(self.test_matches):
            print(f"🔸 Test {i+1}/{len(self.test_matches)}: Equipos {home_id} vs {away_id}")
            
            match_results = {'home_id': home_id, 'away_id': away_id, 'predictions': {}}
            
            for model_name in self.models.keys():
                print(f"  ⚙️  Ejecutando {model_name}...", end=" ")
                
                start_time = time.time()
                prediction = self.run_model_prediction(model_name, home_id, away_id)
                execution_time = time.time() - start_time
                
                if prediction and prediction.get('success'):
                    match_results['predictions'][model_name] = prediction
                    match_results['predictions'][model_name]['execution_time'] = execution_time
                    
                    # Update stats
                    results['model_stats'][model_name]['predictions'] += 1
                    results['model_stats'][model_name]['total_confidence'] += prediction['confidence_score']
                    print(f"✅ ({execution_time:.2f}s)")
                else:
                    results['model_stats'][model_name]['errors'] += 1
                    print(f"❌ ({execution_time:.2f}s)")
            
            results['comparisons'].append(match_results)
            print()
        
        return results
    
    def analyze_results(self, results: Dict[str, Any]) -> None:
        """Analyze and display comparison results"""
        print("\n" + "=" * 60)
        print("📊 ANÁLISIS DE RESULTADOS")
        print("=" * 60)
        
        # Model reliability
        print("\n🔧 CONFIABILIDAD DE MODELOS:")
        for model, stats in results['model_stats'].items():
            total_tests = stats['predictions'] + stats['errors']
            success_rate = (stats['predictions'] / total_tests * 100) if total_tests > 0 else 0
            avg_confidence = (stats['total_confidence'] / stats['predictions']) if stats['predictions'] > 0 else 0
            
            print(f"  {model:>8}: {stats['predictions']:>2}/{total_tests} éxitos ({success_rate:5.1f}%) | Confianza promedio: {avg_confidence:.3f}")
        
        # Outcome agreement analysis
        print("\n🎯 ANÁLISIS DE PREDICCIONES:")
        outcome_counts = {}
        confidence_by_model = {}
        goal_predictions = {}
        
        for comparison in results['comparisons']:
            predictions = comparison['predictions']
            if len(predictions) >= 2:  # At least 2 models made predictions
                
                # Count outcomes
                outcomes = [p['predicted_outcome'] for p in predictions.values() if p.get('predicted_outcome')]
                for outcome in outcomes:
                    outcome_counts[outcome] = outcome_counts.get(outcome, 0) + 1
                
                # Confidence analysis
                for model, pred in predictions.items():
                    if model not in confidence_by_model:
                        confidence_by_model[model] = []
                    confidence_by_model[model].append(pred['confidence_score'])
                
                # Goal predictions
                for model, pred in predictions.items():
                    if model not in goal_predictions:
                        goal_predictions[model] = {'home': [], 'away': [], 'total': []}
                    
                    home_goals = pred['home_goals_prediction']
                    away_goals = pred['away_goals_prediction'] 
                    goal_predictions[model]['home'].append(home_goals)
                    goal_predictions[model]['away'].append(away_goals)
                    goal_predictions[model]['total'].append(home_goals + away_goals)
        
        # Display outcome distribution
        print(f"\n  📈 Distribución de resultados predichos:")
        total_predictions = sum(outcome_counts.values())
        for outcome, count in sorted(outcome_counts.items(), key=lambda x: x[1], reverse=True):
            percentage = (count / total_predictions * 100) if total_predictions > 0 else 0
            print(f"    {outcome:>9}: {count:>3} ({percentage:5.1f}%)")
        
        # Model-specific metrics
        print(f"\n  📊 Métricas por modelo:")
        for model in confidence_by_model.keys():
            confidences = confidence_by_model[model]
            goals = goal_predictions[model]
            
            avg_conf = sum(confidences) / len(confidences) if confidences else 0
            avg_total_goals = sum(goals['total']) / len(goals['total']) if goals['total'] else 0
            
            print(f"    {model:>8}: Confianza {avg_conf:.3f} | Goles totales promedio: {avg_total_goals:.1f}")
        
        # Agreement analysis
        print(f"\n  🤝 Análisis de concordancia:")
        agreement_count = 0
        total_comparisons = 0
        
        for comparison in results['comparisons']:
            predictions = comparison['predictions']
            if len(predictions) >= 2:
                outcomes = [p['predicted_outcome'] for p in predictions.values() if p.get('predicted_outcome')]
                if len(outcomes) >= 2:
                    total_comparisons += 1
                    if len(set(outcomes)) == 1:  # All models agree
                        agreement_count += 1
        
        agreement_rate = (agreement_count / total_comparisons * 100) if total_comparisons > 0 else 0
        print(f"    Concordancia total: {agreement_count}/{total_comparisons} ({agreement_rate:.1f}%)")
        
        # Recommendations
        print(f"\n🏆 RECOMENDACIONES:")
        
        # Best performing model by reliability
        best_reliability = max(results['model_stats'].items(), 
                             key=lambda x: x[1]['predictions'] / (x[1]['predictions'] + x[1]['errors']) if (x[1]['predictions'] + x[1]['errors']) > 0 else 0)
        
        # Most confident model
        best_confidence = max(confidence_by_model.items(), 
                            key=lambda x: sum(x[1]) / len(x[1]) if x[1] else 0)
        
        print(f"  🥇 Modelo más confiable: {best_reliability[0]} ({best_reliability[1]['predictions']} predicciones exitosas)")
        print(f"  🎯 Modelo más seguro: {best_confidence[0]} (confianza promedio: {sum(best_confidence[1])/len(best_confidence[1]):.3f})")
        
        if agreement_rate < 50:
            print(f"  ⚠️  Baja concordancia entre modelos ({agreement_rate:.1f}%) - Considerar validación adicional")
        else:
            print(f"  ✅ Buena concordancia entre modelos ({agreement_rate:.1f}%)")

def main():
    """Main execution function"""
    if len(sys.argv) > 1 and sys.argv[1] == '--quick':
        # Quick test with fewer matches
        comparator = ModelComparator()
        comparator.test_matches = comparator.test_matches[:5]
        print("🚀 MODO RÁPIDO: Solo 5 partidos de prueba")
    else:
        comparator = ModelComparator()
    
    try:
        results = comparator.compare_models_on_matches()
        comparator.analyze_results(results)
        
        # Export results
        output_file = os.path.join(comparator.ml_dir, 'model_comparison_results.json')
        with open(output_file, 'w') as f:
            json.dump(results, f, indent=2)
        
        print(f"\n💾 Resultados guardados en: {output_file}")
        
    except KeyboardInterrupt:
        print("\n⚠️  Comparación interrumpida por el usuario")
        sys.exit(1)
    except Exception as e:
        print(f"\n❌ Error durante la comparación: {e}")
        sys.exit(1)

if __name__ == "__main__":
    main()