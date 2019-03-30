<?php
/**
 * Alias file for renamed maintenance script.
 * Backward compatibility for cron jobs.
 * @deprecated since REL1_31; please use maintenance/updateBlacklist.php instead.
 */
wfDeprecated( __METHOD__, '1.31' );
require_once __DIR__ . '/maintenance/updateBlacklist.php';
