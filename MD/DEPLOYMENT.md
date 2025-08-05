# Deployment Guide - Calcio

## Required PHP Extensions

Ensure these PHP extensions are installed for full functionality:

```bash
# Ubuntu/Debian
sudo apt-get install php8.3-mbstring php8.3-curl php8.3-gd php8.3-xml

# CentOS/RHEL
sudo yum install php-mbstring php-curl php-gd php-xml

# Docker (add to Dockerfile)
RUN docker-php-ext-install mbstring gd pdo_mysql
```

## Production Environment Variables

```env
APP_ENV=production
APP_DEBUG=false
APP_KEY=base64:...

# Database
DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=calcio_prod
DB_USERNAME=calcio_user
DB_PASSWORD=secure_password

# Football API
FOOTBALL_API_KEY=your_production_api_key
FOOTBALL_API_BASE_URL=https://api.football-data.org/v4

# Cache & Sessions
CACHE_DRIVER=redis
SESSION_DRIVER=redis
REDIS_HOST=127.0.0.1

# Queue (for background processing)
QUEUE_CONNECTION=redis

# Broadcasting (for real-time updates)
BROADCAST_DRIVER=pusher
PUSHER_APP_ID=your_app_id
PUSHER_APP_KEY=your_app_key
PUSHER_APP_SECRET=your_app_secret
PUSHER_APP_CLUSTER=mt1
```

## Automated Tasks Setup

Add to crontab for production:
```bash
# Update live scores every 5 minutes
*/5 * * * * cd /path/to/calcio && php artisan football:update-today --quiet

# Generate predictions every hour
0 * * * * cd /path/to/calcio && php artisan ml:predict

# Update statistics every 30 minutes
*/30 * * * * cd /path/to/calcio && php artisan statistics:update

# Train ML models weekly (Sundays at 3 AM)
0 3 * * 0 cd /path/to/calcio && php artisan ml:train
```

## Performance Optimizations

1. **Enable OpCache**:
```ini
opcache.enable=1
opcache.memory_consumption=128
opcache.max_accelerated_files=4000
```

2. **Configure Laravel**:
```bash
php artisan config:cache
php artisan route:cache
php artisan view:cache
php artisan event:cache
```

3. **Database Indexes** (already implemented):
- teams.external_id
- matches.external_id
- matches.home_team_id, away_team_id
- match_predictions.match_id
- budget_configurations.is_active

## Security Considerations

1. **HTTPS Only**: Force HTTPS in production
2. **Rate Limiting**: API endpoints have built-in rate limiting
3. **Input Validation**: All forms use Laravel validation
4. **CSRF Protection**: Enabled on all state-changing operations
5. **SQL Injection Protection**: Using Eloquent ORM prevents SQL injection

## Monitoring Setup

Use Laravel Telescope for debugging:
```bash
composer require laravel/telescope --dev
php artisan telescope:install
php artisan migrate
```

## Backup Strategy

1. **Database**: Daily automated backups
2. **ML Models**: Version control in `/ml/models/`
3. **Logs**: 7-day retention with automatic cleanup

## Performance Benchmarks

Expected performance targets:
- Homepage load: < 200ms
- Match predictions: < 500ms
- Live updates: < 2s interval
- ML training: < 30 minutes
- Prediction generation: < 5 minutes for 100 matches