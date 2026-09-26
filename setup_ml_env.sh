#!/bin/bash

# Configuración automática del entorno ML para Calcio
# Este script resuelve definitivamente los problemas de dependencias Python

set -e

echo "🚀 Configurando entorno ML definitivo para Calcio..."

# Directorios
PROJECT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
ML_ENV_DIR="$PROJECT_DIR/ml_env"

# Crear entorno virtual si no existe
if [ ! -d "$ML_ENV_DIR" ]; then
    echo "📦 Creando entorno virtual Python..."
    python3 -m venv "$ML_ENV_DIR"
fi

# Activar entorno virtual
echo "🔄 Activando entorno virtual..."
source "$ML_ENV_DIR/bin/activate"

# Actualizar pip
echo "⬆️  Actualizando pip..."
python -m pip install --upgrade pip

# Instalar dependencias desde requirements.txt
echo "📚 Instalando dependencias ML..."
python -m pip install -r "$PROJECT_DIR/ml/requirements.txt"

# Verificar instalación
echo "✅ Verificando dependencias..."
python -c "
import pandas as pd
import numpy as np
import sklearn
import xgboost as xgb
import lightgbm as lgb
import joblib
import sqlalchemy
import pymysql
print('✅ Todas las dependencias ML instaladas correctamente')
print(f'Python: {__import__(\"sys\").version}')
print(f'Pandas: {pd.__version__}')
print(f'NumPy: {np.__version__}')
print(f'Scikit-learn: {sklearn.__version__}')
print(f'XGBoost: {xgb.__version__}')
print(f'LightGBM: {lgb.__version__}')
"

# Crear archivo de activación rápida
echo "🔧 Creando script de activación rápida..."
cat > "$PROJECT_DIR/activate_ml.sh" <<'EOF'
#!/bin/bash

PROJECT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
source "$PROJECT_DIR/ml_env/bin/activate"

echo "✅ Entorno ML activado"
echo "Para desactivar: deactivate"
EOF

chmod +x "$PROJECT_DIR/activate_ml.sh"

# Crear verificador de entorno
echo "🧪 Creando verificador de entorno..."
cat > "$PROJECT_DIR/check_ml_env.py" << EOF
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
EOF

chmod +x "$PROJECT_DIR/check_ml_env.py"

echo ""
echo "🎉 Configuración ML completada!"
echo ""
echo "📋 Scripts creados:"
echo "   • ./activate_ml.sh - Activa el entorno ML rápidamente"
echo "   • ./check_ml_env.py - Verifica el entorno ML"
echo "   • ./setup_ml_env.sh - Este script (re-ejecutable)"
echo ""
echo "🔧 Para usar:"
echo "   source ./activate_ml.sh"
echo "   python ml/enhanced_football_predictor.py predict 3430 4775"
echo ""