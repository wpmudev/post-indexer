<?php
// Post indexer configuration file
if(!defined('PI_LOAD_OLD_TABLES')) define('PI_LOAD_OLD_TABLES', true);

// Send debug messages to the post indexer log for the CRON
if(!defined('PI_CRON_DEBUG')) define('PI_CRON_DEBUG', false);

// Keep the last x messages in the log
if(!defined('PI_CRON_DEBUG_KEEP_LAST')) define('PI_CRON_DEBUG_KEEP_LAST', 25);

// The number of sites to check on every CRON first pass
if(!defined('PI_CRON_SITE_PROCESS_FIRSTPASS')) define('PI_CRON_SITE_PROCESS_FIRSTPASS', 25);

// The number of sites to process on every CRON second pass
if(!defined('PI_CRON_SITE_PROCESS_SECONDPASS')) define('PI_CRON_SITE_PROCESS_SECONDPASS', 5);

// The number of posts to process for every site on the CRON second pass
if(!defined('PI_CRON_POST_PROCESS_SECONDPASS')) define('PI_CRON_POST_PROCESS_SECONDPASS', 5);

// The number of posts to delete at a time whilst tidying up via the CRON
if(!defined('PI_CRON_TIDY_DELETE_LIMIT')) define('PI_CRON_TIDY_DELETE_LIMIT', 50);

// The number of counts to update at a time whilst tidying up via the CRON
if(!defined('PI_CRON_TIDY_COUNT_LIMIT')) define('PI_CRON_TIDY_COUNT_LIMIT', 50);