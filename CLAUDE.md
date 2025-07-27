# CLAUDE.md

This file provides guidance to Claude Code (claude.ai/code) when working with code in this repository.

## Project Overview

Calcio is a Laravel-based football betting application that uses Machine Learning to predict match outcomes and goal scores. The system fetches data from external football APIs, processes it with Python ML models, and displays predictions through a web interface.

## Architecture

- **Backend**: Laravel 12 with standard MVC structure
- **Frontend**: Blade templates with TailwindCSS 4.0
- **Machine Learning**: Python with scikit-learn, XGBoost, and pandas
- **Database**: MySQL (development), supports PostgreSQL/SQLite for production
- **Asset Pipeline**: Vite with TailwindCSS integration
- **API Integration**: Football-API.org for match and team data

## Database Schema

### Core Tables
- `teams` - Football team information with external API IDs
- `matches` - Match data including scores, dates, and status
- `team_statistics` - Seasonal team performance metrics
- `match_predictions` - ML-generated predictions with confidence scores

### Key Relationships
- Teams have many matches (home/away)
- Teams have statistics by season
- Matches have one prediction
- All models use external_id for API synchronization

## Machine Learning System

### Python Environment
- Location: `ml/` directory
- Dependencies: Listed in `ml/requirements.txt`
- Main script: `ml/football_predictor.py`
- Models saved in: `ml/models/` (created during training)

### ML Pipeline
1. Data extraction from SQLite database
2. Feature engineering (team stats, form, strength indicators)
3. Model training (XGBoost for goals, GradientBoost for outcomes)
4. Prediction generation with confidence scores
5. Model persistence and versioning

### Features Used
- Win rates, goal averages, points per game
- Attack/defense strength ratios
- Recent form indicators
- Head-to-head statistics

## Development Commands

**IMPORTANT: All commands should be executed from within the Homestead VM (vagrant@homestead)**

### Access VM
```bash
# Connect to Homestead VM
vagrant ssh

# Navigate to project directory
cd /home/vagrant/code/Calcio
```

### Laravel Application
```bash
# Start development environment (recommended)
composer dev

# Individual services
php artisan serve
npm run dev
php artisan test

# Code formatting
./vendor/bin/pint
```

### Football Data Management
```bash
# Sync teams and matches from API (includes automatic predictions)
php artisan football:sync PL --season=2024

# Different leagues: PL (Premier League), PD (La Liga), BL1 (Bundesliga)
php artisan football:sync PD

# Sync today's matches from ALL leagues/competitions (includes automatic predictions)
php artisan football:sync-today

# Update today's matches and live scores (recommended for cron jobs, includes predictions and statistics)
php artisan football:update-today --quiet

# Skip predictions and/or statistics with flags
php artisan football:sync-today --no-predictions
php artisan football:update-today --quiet --no-predictions --no-statistics

# Update only statistics
php artisan statistics:update
```

### Machine Learning Operations
```bash
# Train ML models (requires Python 3.8+ - execute from VM)
php artisan ml:train

# Generate predictions for upcoming matches
php artisan ml:predict

# Predict specific match
php artisan ml:predict --match-id=123

# Test predictions (useful for debugging)
php artisan predictions:test

# Update prediction accuracy retroactively
php artisan predictions:update-accuracy-retroactive

# Generate predictions for finished matches that don't have them
php artisan predictions:generate-finished --limit=100

# Check environment configuration
php artisan env:check
```

### Database Operations
```bash
# Run migrations
php artisan migrate

# Fresh database with sample data
php artisan migrate:fresh --seed

# Clear application caches
php artisan config:clear
php artisan cache:clear

# Clean old log files
php artisan logs:clean --days=7
```

## API Configuration

### Required Environment Variables
```bash
# Football API (https://www.football-data.org)
FOOTBALL_API_KEY=your_api_key_here
FOOTBALL_API_BASE_URL=https://api.football-data.org/v4

# Database (MySQL for production, configured for Homestead VM)
DB_CONNECTION=mysql
DB_HOST=192.168.56.56  # Use VM IP for Homestead
DB_PORT=3306
DB_DATABASE=calcio
DB_USERNAME=homestead
DB_PASSWORD=secret

# Logging (daily rotation with 7 days retention)
LOG_CHANNEL=daily
LOG_DAILY_DAYS=7
```

### API Rate Limits
- Football-API.org: 10 requests/minute (free tier)
- Implement caching for frequently accessed data
- Use sync commands during off-peak hours

## Web Interface Structure

### Routes
- `/` - Dashboard with today's matches and predictions
- `/matches` - All matches with filtering options
- `/matches/{match}` - Match details with prediction analysis
- `/teams` - Team standings and statistics
- `/teams/{team}` - Team profile with recent form
- `/statistics` - Prediction accuracy statistics with interactive charts

### Controllers
- `HomeController` - Dashboard and overview data
- `MatchController` - Match listings and detailed views
- `TeamController` - Team statistics and profiles
- `StatisticsController` - Prediction accuracy analytics and charts

### Key Features
- Real-time prediction accuracy tracking
- Match filtering by date, team, and status
- Team performance metrics and form analysis
- Confidence indicators for predictions
- Interactive statistics dashboard with Chart.js
- Automated statistics updates with match results
- Responsive design with TailwindCSS

## Statistics Dashboard

### Metrics Tracked
- **Match Outcome Accuracy**: Precision in predicting win/draw/loss
- **Both Teams Score**: Accuracy of both teams scoring predictions
- **Over/Under 2.5 Goals**: Success rate for total goals predictions
- **Monthly Performance**: Accuracy trends over time
- **League Performance**: Accuracy by competition/league

### Interactive Charts
- Line charts showing monthly accuracy trends
- Bar charts comparing performance across leagues
- Detailed tables with recent prediction results
- Real-time updates with match completion

### Auto-Update System
- Statistics recalculate automatically when matches finish
- Historical data preserved for trend analysis
- Performance metrics updated in real-time

## Model Accuracy and Validation

### Metrics Tracked
- Mean Absolute Error (MAE) for goal predictions
- Classification accuracy for match outcomes
- Confidence scores for prediction reliability
- Historical accuracy per team and league

### Model Versioning
- Models are versioned and metadata is stored
- Training timestamps and feature sets tracked
- Automatic model retraining recommended weekly

## Development Workflow

### Adding New Leagues
1. Update `FootballApiService` with league codes
2. Modify sync commands to handle new league data
3. Update ML model to account for league differences
4. Test prediction accuracy for new league

### Improving Predictions
1. Add new features to `football_predictor.py`
2. Update feature engineering in `create_features()`
3. Retrain models with `php artisan ml:train`
4. Validate accuracy on test data

### API Integration
- External IDs are used to maintain sync with APIs
- Handle API rate limits and errors gracefully
- Store raw API responses for debugging when needed

## Troubleshooting

### Common Issues
- **Python not found**: Ensure Python 3.8+ is installed and accessible
- **ML models missing**: Run `php artisan ml:train` first
- **API errors**: Check API key and rate limit status
- **Missing predictions**: Ensure teams have sufficient historical data

### Performance Optimization
- Use database indexes on frequently queried columns
- Cache team statistics and predictions
- Batch API requests when possible
- Consider Redis for session and cache storage in production