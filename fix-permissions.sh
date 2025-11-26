#!/bin/bash

# Fix Laravel permissions for Apache/www-data
echo "🔧 Fixing Laravel permissions for Apache/www-data..."

# Set proper ownership for critical directories
echo "Setting ownership for storage and cache directories..."

# Method 1: Add www-data to yualbe group and set group permissions
sudo usermod -a -G yualbe www-data

# Set group ownership to yualbe (so both yualbe and www-data can access)
sudo chgrp -R yualbe /home/yualbe/Homestead/code/Calcio/storage/
sudo chgrp -R yualbe /home/yualbe/Homestead/code/Calcio/bootstrap/cache/

# Set permissions so group can write
chmod -R 775 /home/yualbe/Homestead/code/Calcio/storage/
chmod -R 775 /home/yualbe/Homestead/code/Calcio/bootstrap/cache/

# Set setgid bit so new files inherit group ownership
find /home/yualbe/Homestead/code/Calcio/storage/ -type d -exec chmod g+s {} \;
find /home/yualbe/Homestead/code/Calcio/bootstrap/cache/ -type d -exec chmod g+s {} \;

# Create a test file to verify permissions
echo "Testing permissions..."
sudo -u www-data touch /home/yualbe/Homestead/code/Calcio/storage/framework/views/test_file.php
if [ -f "/home/yualbe/Homestead/code/Calcio/storage/framework/views/test_file.php" ]; then
    echo "✅ Success: www-data can write to views directory"
    rm /home/yualbe/Homestead/code/Calcio/storage/framework/views/test_file.php
else
    echo "❌ Failed: www-data cannot write to views directory"
fi

echo "✅ Permissions fixed!"
echo "You may need to restart Apache: sudo systemctl restart apache2"