#!/bin/bash
set -e

echo "🔄 Service Logrotate démarré"

# Exécuter logrotate toutes les heures
while true; do
    echo "[$(date)] Rotation des logs en cours..."
    /usr/sbin/logrotate -v /etc/logrotate.d/laravel
    
    # Attendre 1 heure
    sleep 3600
done