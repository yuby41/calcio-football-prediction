<?php

return array (
  'active_model' => 'enhanced',
  'models' => 
  array (
    'enhanced' => 
    array (
      'name' => 'Enhanced ML Ensemble',
      'script' => 'enhanced_football_predictor.py',
      'description' => 'XGBoost + LightGBM + Neural Network + Random Forest ensemble',
      'accuracy' => '~58%',
      'features' => 35,
      'training_time' => 'High (5-10 minutes)',
      'prediction_time' => 'Medium (1-2 seconds)',
    ),
    'simple' => 
    array (
      'name' => 'Simple Statistical Predictor',
      'script' => 'simple_effective_predictor.py',
      'description' => 'Fast statistical model with Poisson distribution',
      'accuracy' => '~52%',
      'features' => 12,
      'training_time' => 'None (statistical)',
      'prediction_time' => 'Fast (<0.5 seconds)',
    ),
    'ensemble' => 
    array (
      'name' => 'Hybrid Ensemble',
      'script' => 'hybrid_predictor.py',
      'description' => 'Combines enhanced ML with statistical methods',
      'accuracy' => '~55%',
      'features' => 25,
      'training_time' => 'Medium (3-5 minutes)',
      'prediction_time' => 'Medium (1 second)',
    ),
  ),
  'paths' => 
  array (
    'ml_directory' => '/home/yualbe/Homestead/code/Calcio/ml',
    'models_directory' => '/home/yualbe/Homestead/code/Calcio/ml/models',
    'python_env' => '/home/yualbe/Homestead/code/Calcio/ml_env/bin/python',
  ),
  'settings' => 
  array (
    'timeout' => 30,
    'max_retries' => 3,
    'cache_predictions' => true,
    'cache_ttl' => 300,
  ),
);
