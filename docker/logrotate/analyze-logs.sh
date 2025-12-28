#!/bin/bash

LOG_DIR="/var/log/laravel"
SECURITY_LOG="${LOG_DIR}/security-$(date +%Y%m%d).log"
ERROR_LOG="${LOG_DIR}/error-$(date +%Y%m%d).log"
SUSPICIOUS_LOG="${LOG_DIR}/suspicious-$(date +%Y%m%d).log"

# Fonction pour extraire l'IP depuis les logs Laravel
extract_ip() {
    grep -oP '(?<=ip":")[^"]+' | sort | uniq -c | sort -rn
}

# Analyser les erreurs 500 (erreurs serveur)
echo "=== Analyse des erreurs 500 ===" >> "${ERROR_LOG}"
find "${LOG_DIR}" -name "laravel-*.log" -mtime -1 -exec grep -l "500\|EMERGENCY\|ALERT\|CRITICAL" {} \; | while read -r file; do
    echo "Fichier: ${file}" >> "${ERROR_LOG}"
    grep -E "500|EMERGENCY|ALERT|CRITICAL" "${file}" | tail -50 >> "${ERROR_LOG}"
done

# Analyser les tentatives d'accès suspects (403, 401, trop de requêtes)
echo "=== Analyse des accès suspects ===" >> "${SECURITY_LOG}"

# Détecter les IPs avec trop de 403 Forbidden (> 10 en 24h)
echo "--- IPs avec nombreux 403 Forbidden ---" >> "${SECURITY_LOG}"
find "${LOG_DIR}" -name "laravel-*.log" -mtime -1 -exec grep "403\|Forbidden" {} \; | \
    extract_ip | awk '$1 > 10 {print "⚠️  IP: "$2" - Tentatives: "$1}' >> "${SECURITY_LOG}"

# Détecter les IPs avec trop de 401 Unauthorized (> 20 en 24h)
echo "--- IPs avec nombreux 401 Unauthorized ---" >> "${SECURITY_LOG}"
find "${LOG_DIR}" -name "laravel-*.log" -mtime -1 -exec grep "401\|Unauthorized" {} \; | \
    extract_ip | awk '$1 > 20 {print "⚠️  IP: "$2" - Tentatives: "$1}' >> "${SECURITY_LOG}"

# Détecter les tentatives de scan (User-Agent suspects, chemins sensibles)
echo "--- Tentatives de scan détectées ---" >> "${SUSPICIOUS_LOG}"
find "${LOG_DIR}" -name "laravel-*.log" -mtime -1 -exec grep -iE "\.env|\.git|admin|phpmyadmin|wp-admin|sqlmap|nikto|nmap" {} \; >> "${SUSPICIOUS_LOG}"

# Résumé dans stdout
echo "[$(date)] Analyse des logs terminée:"
echo "  - Erreurs 500: $(grep -c "500" "${ERROR_LOG}" 2>/dev/null || echo 0)"
echo "  - IPs suspectes 403: $(grep -c "⚠️" "${SECURITY_LOG}" 2>/dev/null || echo 0)"
echo "  - Tentatives de scan: $(wc -l < "${SUSPICIOUS_LOG}" 2>/dev/null || echo 0)"