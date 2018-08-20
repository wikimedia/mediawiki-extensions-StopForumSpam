<?php
/**
 * Alias file for renamed maintenance script.
 * Backward compatibility for cron jobs.
 * @deprecated since REL1_31; please use maintenance/updateBlacklist.php instead.
 */
require_once __DIR__ . '/maintenance/updateBlacklist.php';
