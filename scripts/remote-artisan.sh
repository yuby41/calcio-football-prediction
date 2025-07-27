#!/bin/bash
# Script para ejecutar comandos artisan remotamente
cd ~/Homestead
vagrant ssh -c "cd /home/vagrant/code/Calcio && php artisan $*"