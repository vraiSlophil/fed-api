#!/bin/bash

LOG_DIR="/var/log/laravel"
SECURITY_LOG="${LOG_DIR}/security-$(date +%Y%m%d).log"
ERROR_LOG="${LOG_DIR}/error-$(date +%Y%m%d).log"
SUSPICIOUS_LOG="${LOG_DIR}/suspicious-$(date +%Y%m%d).log"

# Créer les fichiers s'ils n'existent pas
touch "${SECURITY_LOG}" "${ERROR_LOG}" "${SUSPICIOUS_LOG}"

echo "=== RAPPORT DE SÉCURITÉ - $(date) ===" > "${SECURITY_LOG}"
echo "" >> "${SECURITY_LOG}"

# Analyser les erreurs 500 dans TOUS les logs récents (y compris rotated)
echo "=== ERREURS CRITIQUES 500 - $(date) ===" > "${ERROR_LOG}"
find "${LOG_DIR}" -name "laravel*.log*" -type f -mtime -1 2>/dev/null | while read -r file; do
    grep -E "HTTP 500|emergency|EMERGENCY" "${file}" 2>/dev/null >> "${ERROR_LOG}"
done

error_count=$(grep -c "HTTP 500\|emergency" "${ERROR_LOG}" 2>/dev/null || echo 0)
echo "" >> "${ERROR_LOG}"
echo "Total: ${error_count} erreurs 500 détectées" >> "${ERROR_LOG}"

# Analyser les 403 - TOUS les fichiers de logs
echo "--- Analyse 403 Forbidden (seuil: >10 requêtes/24h) ---" >> "${SECURITY_LOG}"
temp_403="/tmp/403_ips.tmp"
find "${LOG_DIR}" -name "laravel*.log*" -type f -mtime -1 2>/dev/null -exec grep "HTTP 403" {} \; | \
    grep -oP '"ip":"[^"]+"' | cut -d'"' -f4 | sort | uniq -c | sort -rn > "${temp_403}"

while read -r count ip; do
    if [ "$count" -gt 10 ]; then
        echo "⚠️  IP: ${ip} - ${count} tentatives 403 Forbidden" >> "${SECURITY_LOG}"
        # Afficher quelques exemples de chemins accédés
        echo "   Chemins accédés:" >> "${SECURITY_LOG}"
        find "${LOG_DIR}" -name "laravel*.log*" -type f -exec grep "HTTP 403" {} \; | \
            grep "\"ip\":\"${ip}\"" | grep -oP '"path":"[^"]+"' | cut -d'"' -f4 | sort | uniq | head -5 | \
            while read -r path; do echo "     - ${path}" >> "${SECURITY_LOG}"; done
        echo "" >> "${SECURITY_LOG}"
    fi
done < "${temp_403}"

forbidden_total=$(find "${LOG_DIR}" -name "laravel*.log*" -type f -mtime -1 2>/dev/null -exec grep -c "HTTP 403" {} \; | awk '{s+=$1} END {print s}')
echo "Total 403 dans les logs: ${forbidden_total}" >> "${SECURITY_LOG}"
echo "" >> "${SECURITY_LOG}"

# Analyser les 401 - TOUS les fichiers de logs
echo "--- Analyse 401 Unauthorized (seuil: >20 requêtes/24h) ---" >> "${SECURITY_LOG}"
temp_401="/tmp/401_ips.tmp"
find "${LOG_DIR}" -name "laravel*.log*" -type f -mtime -1 2>/dev/null -exec grep "HTTP 401" {} \; | \
    grep -oP '"ip":"[^"]+"' | cut -d'"' -f4 | sort | uniq -c | sort -rn > "${temp_401}"

while read -r count ip; do
    if [ "$count" -gt 20 ]; then
        echo "⚠️  IP: ${ip} - ${count} tentatives 401 Unauthorized" >> "${SECURITY_LOG}"
        echo "   Chemins accédés:" >> "${SECURITY_LOG}"
        find "${LOG_DIR}" -name "laravel*.log*" -type f -exec grep "HTTP 401" {} \; | \
            grep "\"ip\":\"${ip}\"" | grep -oP '"path":"[^"]+"' | cut -d'"' -f4 | sort | uniq | head -5 | \
            while read -r path; do echo "     - ${path}" >> "${SECURITY_LOG}"; done
        echo "" >> "${SECURITY_LOG}"
    fi
done < "${temp_401}"

unauthorized_total=$(find "${LOG_DIR}" -name "laravel*.log*" -type f -mtime -1 2>/dev/null -exec grep -c "HTTP 401" {} \; | awk '{s+=$1} END {print s}')
echo "Total 401 dans les logs: ${unauthorized_total}" >> "${SECURITY_LOG}"
echo "" >> "${SECURITY_LOG}"

# Détecter les tentatives de scan dans TOUS les fichiers
echo "=== TENTATIVES DE SCAN - $(date) ===" > "${SUSPICIOUS_LOG}"
find "${LOG_DIR}" -name "laravel*.log*" -type f -mtime -1 2>/dev/null -exec grep -E "\.env|\.git|/admin|phpmyadmin|wp-admin|config\.php" {} \; | \
    while read -r line; do
        ip=$(echo "$line" | grep -oP '"ip":"[^"]+"' | cut -d'"' -f4)
        path=$(echo "$line" | grep -oP '"path":"[^"]+"' | cut -d'"' -f4)
        status=$(echo "$line" | grep -oP '"status":[0-9]+' | cut -d':' -f2)
        echo "🕵️  Scan détecté - IP: ${ip} - Path: ${path} - Status: ${status}" >> "${SUSPICIOUS_LOG}"
    done

scan_count=$(grep -c "🕵️" "${SUSPICIOUS_LOG}" 2>/dev/null || echo 0)
echo "" >> "${SUSPICIOUS_LOG}"
echo "Total: ${scan_count} tentatives de scan détectées" >> "${SUSPICIOUS_LOG}"

# Résumé stdout avec formatage
echo ""
echo "╔══════════════════════════════════════════════╗"
echo "║  📊 RÉSUMÉ ANALYSE SÉCURITÉ - $(date +%H:%M:%S)  ║"
echo "╠══════════════════════════════════════════════╣"
printf "║ 🔴 Erreurs 500:           %-18s ║\n" "${error_count}"
printf "║ 🚫 Total 403 Forbidden:   %-18s ║\n" "${forbidden_total}"
printf "║ 🔒 Total 401 Unauthorized: %-18s ║\n" "${unauthorized_total}"
printf "║ 🕵️  Tentatives de scan:   %-18s ║\n" "${scan_count}"
echo "╠══════════════════════════════════════════════╣"
echo "║ IPs suspectes (>seuil):                      ║"
suspicious_403=$(awk '$1 > 10 {print}' "${temp_403}" 2>/dev/null | wc -l)
suspicious_401=$(awk '$1 > 20 {print}' "${temp_401}" 2>/dev/null | wc -l)
printf "║   - 403 (>10 req):       %-18s ║\n" "${suspicious_403}"
printf "║   - 401 (>20 req):       %-18s ║\n" "${suspicious_401}"
echo "╚══════════════════════════════════════════════╝"

# Cleanup
rm -f "${temp_403}" "${temp_401}"