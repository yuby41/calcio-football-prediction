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
    """Get database configuration from Laravel .env file."""
    env_config = load_env_config()

    db_connection = env_config.get('DB_CONNECTION', 'sqlite')

    if db_connection == 'sqlite':
        database = env_config.get(
            'DB_DATABASE',
            os.path.join(
                os.path.dirname(__file__),
                '..',
                'database',
                'database.sqlite'
            )
        )

        if not os.path.isabs(database):
            database = os.path.abspath(
                os.path.join(
                    os.path.dirname(__file__),
                    '..',
                    database
                )
            )

        return {
            'type': 'sqlite',
            'database': database
        }

    required = [
        'DB_HOST',
        'DB_PORT',
        'DB_USERNAME',
        'DB_PASSWORD',
        'DB_DATABASE',
    ]

    missing = [
        key for key in required
        if not env_config.get(key)
    ]

    if missing:
        raise RuntimeError(
            'Missing database configuration: ' + ', '.join(missing)
        )

    return {
        'type': 'mysql',
        'host': env_config['DB_HOST'],
        'port': int(env_config['DB_PORT']),
        'user': env_config['DB_USERNAME'],
        'password': env_config['DB_PASSWORD'],
        'database': env_config['DB_DATABASE']
    }