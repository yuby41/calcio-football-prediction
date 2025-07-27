# 🚀 Setup Instructions for Calcio

## After Cloning the Repository

### 1. Install Dependencies
```bash
# PHP dependencies
composer install

# Node.js dependencies
npm install

# Python dependencies
cd ml
python3 -m venv venv
source venv/bin/activate  # Linux/Mac
# or venv\Scripts\activate  # Windows
pip install -r requirements.txt
cd ..
```

### 2. Environment Configuration
```bash
# Copy environment template
cp .env.example .env

# Generate application key
php artisan key:generate

# Edit .env file with your settings:
# - Database credentials
# - FOOTBALL_API_KEY from https://www.football-data.org
```

### 3. Database Setup
```bash
# Run migrations
php artisan migrate

# Optional: Seed with sample data
php artisan db:seed
```

### 4. ML Models Training
```bash
# Train the machine learning models
php artisan ml:train
```

### 5. Initial Data Sync
```bash
# Sync football data (requires API key)
php artisan football:sync PL --season=2024
php artisan football:sync-today
```

### 6. Start Development Server
```bash
# Option 1: All services at once
composer dev

# Option 2: Individual services
php artisan serve    # Backend server
npm run dev         # Frontend build
```

### 7. Verify Installation
Visit `http://localhost:8000` and check:
- ✅ Dashboard loads
- ✅ Matches page shows data
- ✅ Statistics page displays accuracy
- ✅ Teams page lists teams

## Production Deployment

### Environment Variables (Production)
```env
APP_ENV=production
APP_DEBUG=false
LOG_LEVEL=warning
CACHE_STORE=redis
QUEUE_CONNECTION=redis
```

### Scheduler Setup (Production)
Add to crontab:
```bash
* * * * * cd /path/to/calcio && php artisan schedule:run >> /dev/null 2>&1
```

### Security Checklist
- [ ] Change default APP_KEY
- [ ] Use strong database passwords
- [ ] Enable HTTPS
- [ ] Set up proper file permissions
- [ ] Configure firewall rules
- [ ] Regular backup strategy

## Troubleshooting

### Common Issues
1. **mbstring errors**: Use `php artisan statistics:update-sql` instead of regular statistics commands
2. **ML models not found**: Run `php artisan ml:train` first
3. **API rate limits**: Use commands during off-peak hours
4. **Permission errors**: Check storage directory permissions

### Support
- Check the logs in `storage/logs/`
- Review CLAUDE.md for detailed guidance
- Test with `php artisan list` to see all available commands