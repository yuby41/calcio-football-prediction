# ⚽ Calcio - Football Prediction System

A Laravel-based football betting application that uses Machine Learning to predict match outcomes and goal scores. The system fetches data from external football APIs, processes it with Python ML models, and displays predictions through a responsive web interface.

## 🚀 Features

- **ML-Powered Predictions**: XGBoost and Gradient Boosting models for accurate predictions
- **Real-time Updates**: Live match scores with intelligent polling (10-30s intervals)
- **Interactive Dashboard**: Modern UI with TailwindCSS and Chart.js
- **Automated Statistics**: Real-time prediction accuracy tracking
- **Multi-League Support**: Premier League, La Liga, Bundesliga, and more
- **Performance Analytics**: Detailed accuracy metrics and trends
- **Live Match Broadcasting**: Event-driven updates for live scores and statistics
- **API Endpoints**: RESTful APIs for real-time data access
- **Budget Management**: Advanced bankroll strategies including Mansaniello, Fibonacci, Martingale
- **Risk Control**: Automatic bet sizing based on ML confidence and strategy parameters

## 🛠️ Tech Stack

- **Backend**: Laravel 12, PHP 8.3
- **Frontend**: Blade templates, TailwindCSS 4.0, Chart.js
- **Database**: MySQL (SQLite for development)
- **ML Engine**: Python 3.8+ with scikit-learn, XGBoost, pandas
- **API**: Football-API.org integration
- **Build Tools**: Vite, Composer

## 📋 Requirements

- PHP 8.2+
- Composer
- Node.js & NPM
- Python 3.8+
- MySQL (or SQLite for development)
- Football API key from [Football-API.org](https://www.football-data.org)

## ⚡ Quick Start

### 1. Clone & Install
```bash
git clone <repository-url>
cd calcio
composer install
npm install
```

### 2. Environment Setup
```bash
cp .env.example .env
php artisan key:generate
```

Edit `.env` with your database and API credentials:
```env
DB_CONNECTION=mysql
DB_DATABASE=calcio
DB_USERNAME=your_username
DB_PASSWORD=your_password

FOOTBALL_API_KEY=your_api_key_here
```

### 3. Database & ML Setup
```bash
php artisan migrate
python3 -m pip install -r ml/requirements.txt
php artisan ml:train
```

### 4. Start Development
```bash
# Start all services
composer dev

# Or individually:
php artisan serve
npm run dev
```

## 🔄 Data Management

### Sync Football Data
```bash
# Sync specific league (PL, PD, BL1, etc.)
php artisan football:sync PL --season=2024

# Sync today's matches from all leagues
php artisan football:sync-today

# Update live scores and predictions
php artisan football:update-today
```

### Machine Learning
```bash
# Train ML models
php artisan ml:train

# Generate predictions
php artisan ml:predict

# Update prediction accuracy
php artisan statistics:update-sql
```

## 📊 Web Interface

- **Home** (/) - Dashboard with live matches and predictions
- **Matches** (/matches) - All matches with filtering and live updates
- **Teams** (/teams) - Team statistics and profiles  
- **Statistics** (/statistics) - Prediction accuracy analytics with real-time updates
- **Budget** (/budget) - Advanced bankroll management with multiple strategies

## 💰 Budget Management System

### Estrategias Disponibles
- **Mansaniello**: Progresión controlada con secuencia específica (1,1,2,2,3,4,5,7,9,12...)
- **Fibonacci**: Secuencia matemática clásica (1,1,2,3,5,8,13,21...)
- **Martingala Limitada**: Duplicación con límites de seguridad
- **Apuesta Fija**: Cantidad constante por apuesta
- **Porcentaje Kelly**: Ajuste automático según confianza de la IA

### Gestión de Riesgo
- Límite máximo por apuesta (% del bankroll)
- Confianza mínima requerida de la IA
- Control automático de secuencias
- Historial completo de transacciones
- Métricas de rendimiento (ROI, Win Rate, Drawdown)

### Comandos de Budget
```bash
# Crear configuración de ejemplo
php artisan budget:create-sample --amount=500

# Ver todas las configuraciones
curl http://localhost:8000/budget
```

## 🔗 API Endpoints

- `GET /api/live/matches` - Live match updates with timestamps
- `GET /api/live/statistics` - Real-time prediction accuracy
- `GET /api/live/live-matches` - Currently live matches only
- `GET /api/health` - System health check

## 🤖 Automated Tasks

The application includes scheduled tasks for automation:

- **Every 5 minutes**: Update live scores and match results
- **Every 10 minutes**: Auto-finish completed matches
- **Every 30 minutes**: Update prediction accuracy
- **Every hour**: Generate new predictions and update statistics
- **Daily**: Generate team statistics and clean old logs

## 🔧 Maintenance

```bash
# Clear caches
php artisan config:clear
php artisan cache:clear
php artisan view:clear

# Clean old logs
php artisan logs:clean --days=7

# Check scheduler status
php artisan schedule:list
```

## 📈 Performance Features

- **Automated Caching**: Intelligent cache invalidation
- **Daily Log Rotation**: Automatic cleanup with 7-day retention
- **Database Optimization**: Indexed queries and efficient relationships
- **Real-time Updates**: Observer pattern for automatic statistics

## 🔒 Security

- Environment variables for sensitive data
- Input validation and sanitization
- Rate limiting on API endpoints
- Secure database connections

## 🧪 Testing

```bash
php artisan test
```

## 📝 API Integration

The system integrates with Football-API.org:
- Rate limit: 10 requests/minute (free tier)
- Supports multiple leagues and competitions
- Real-time match data and statistics

## 🤝 Contributing

1. Fork the repository
2. Create your feature branch
3. Commit your changes
4. Push to the branch
5. Create a Pull Request

## 📄 License

This project is open source and available under the [MIT License](LICENSE).

## 🆘 Support

For issues and questions:
1. Check existing issues
2. Create a new issue with detailed description
3. Include environment details and error logs

---

**Built with ❤️ for football prediction enthusiasts**
EOF < /dev/null