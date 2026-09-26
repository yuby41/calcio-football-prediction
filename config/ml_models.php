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
      'accuracy' => null,
      'features' => 35,
      'training_time' => 'High (5-10 minutes)',
      'prediction_time' => 'Medium (1-2 seconds)',
    ),
    'simple' => 
    array (
      'name' => 'Simple Statistical Predictor',
      'script' => 'simple_effective_predictor.py',
      'description' => 'Fast statistical model with Poisson distribution',
      'accuracy' => null,
      'features' => 12,
      'training_time' => 'None (statistical)',
      'prediction_time' => 'Fast (<0.5 seconds)',
    ),
    'ensemble' => 
    array (
      'name' => 'Hybrid Ensemble',
      'script' => 'hybrid_predictor.py',
      'description' => 'Combines enhanced ML with statistical methods',
      'accuracy' => null,
      'features' => 25,
      'training_time' => 'Medium (3-5 minutes)',
      'prediction_time' => 'Medium (1 second)',
    ),
  ),
  'paths' =>
  array (
      'ml_directory' => env('ML_DIRECTORY', base_path('ml')),
      'models_directory' => env('ML_MODELS_DIRECTORY', base_path('ml/models')),
      'python_env' => env('ML_PYTHON', base_path('ml_env/bin/python')),
  ),
  'settings' => 
  array (
    'timeout' => 30,
    'max_retries' => 3,
    'cache_predictions' => true,
    'cache_ttl' => 300,
  ),
);
