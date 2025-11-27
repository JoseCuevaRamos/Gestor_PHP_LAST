#!/bin/sh

echo "[CRON_LOOP] Iniciando bucle de recordatorios..."

mkdir -p /var/www/html/storage/logs

while true; do
  echo "[CRON_LOOP] Ejecutando enviar_recordatorios.php: $(date)"
  php /var/www/html/scripts/enviar_recordatorios.php >> /var/www/html/storage/logs/recordatorios_tareas.log 2>&1
  # Esperar 24 horas
  sleep 86400
done
