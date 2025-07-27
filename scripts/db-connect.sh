#!/bin/bash
# Script para conectar a la base de datos a través de SSH
cd ~/Homestead
vagrant ssh -c "cd /home/vagrant/code/Calcio && php artisan tinker --execute=\"$1\""