#!/usr/bin/env python3
"""
Verificador del entorno ML
Comprueba que todas las dependencias estén disponibles
"""
import sys
import os

def check_ml_dependencies():
    """Verifica que todas las dependencias ML estén disponibles"""
    try:
        import pandas as pd
        import numpy as np
        import sklearn
        import xgboost as xgb
        import lightgbm as lgb
        import joblib
        import sqlalchemy
        import pymysql
        
        print("✅ Verificación del entorno ML completada")
        print(f"✅ Python: {sys.version.split()[0]}")
        print(f"✅ Pandas: {pd.__version__}")
        print(f"✅ NumPy: {np.__version__}")
        print(f"✅ Scikit-learn: {sklearn.__version__}")
        print(f"✅ XGBoost: {xgb.__version__}")
        print(f"✅ LightGBM: {lgb.__version__}")
        
        return True
        
    except ImportError as e:
        print(f"❌ Error de dependencia: {e}")
        print("💡 Ejecuta: ./setup_ml_env.sh")
        return False

if __name__ == "__main__":
    success = check_ml_dependencies()
    sys.exit(0 if success else 1)
