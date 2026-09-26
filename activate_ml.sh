#!/bin/bash

PROJECT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
source "$PROJECT_DIR/ml_env/bin/activate"

echo "✅ Entorno ML activado"
echo "Para desactivar: deactivate"
