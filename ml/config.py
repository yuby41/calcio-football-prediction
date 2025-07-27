#!/usr/bin/env python3
"""
Configuration loader for Football Predictor
Reads Laravel .env file for database configuration
"""

import os
import re
from typing import Dict

def load_env_config(env_path: str = None) -> Dict[str, str]:
    """Load configuration from Laravel .env file"""
    if not env_path:
        env_path = os.path.join(os.path.dirname(__file__), '..', '.env')
    
    config = {}
    
    try:
        with open(env_path, 'r') as f:
            for line in f:
                line = line.strip()
                if line and not line.startswith('#') and '=' in line:
                    key, value = line.split('=', 1)
                    config[key] = value.strip('"').strip("'")
    except FileNotFoundError:
        print(f"Warning: .env file not found at {env_path}")
        return {}
    
    return config

def get_db_config() -> Dict[str, str]:
    """Get database configuration from .env file"""
    env_config = load_env_config()
    
    db_connection = env_config.get('DB_CONNECTION', 'mysql')
    
    if db_connection == 'sqlite':
        return {
            'type': 'sqlite',
            'database': env_config.get('DB_DATABASE', 'database/database.sqlite')
        }
    else:
        # For Homestead/Vagrant VM configuration
        host = env_config.get('DB_HOST', 'localhost')
        
        # If running from within Homestead VM, use localhost
        # If running from host machine, might need different host
        return {
            'type': 'mysql',
            'host': host,
            'port': int(env_config.get('DB_PORT', 3306)),
            'user': env_config.get('DB_USERNAME', 'homestead'),
            'password': env_config.get('DB_PASSWORD', 'secret'),
            'database': env_config.get('DB_DATABASE', 'calcio')
        }