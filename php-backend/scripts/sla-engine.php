<?php
/**
 * SLA Engine CLI script wrapper.
 * Intended to be invoked by a system cron job every 15 minutes.
 * Example crontab entry (run every 15 minutes):
 *   0,15,30,45 * * * * /usr/bin/php /path/to/project/php-backend/scripts/sla-engine.php >> /var/log/sla-engine.log 2>&1
 */

require_once __DIR__ . '/../sla-engine.php';
