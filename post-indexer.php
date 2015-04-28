<?php
/*
Plugin Name: Post Indexer
Plugin URI: http://premium.wpmudev.org/project/post-indexer/
Description: Indexes all posts across your network and brings them into one spot – a very powerful tool that you use as a base to display posts in different ways or to manage your network.
Author: WPMU DEV
Version: 3.0.6.1
Author URI: http://premium.wpmudev.org
WDP ID: 30
Network: true
Text Domain: postindexer
Domain Path: /languages


*/

// +----------------------------------------------------------------------+
// | Copyright Incsub (http://incsub.com/)                                |
// +----------------------------------------------------------------------+
// | This program is free software; you can redistribute it and/or modify |
// | it under the terms of the GNU General Public License, version 2, as  |
// | published by the Free Software Foundation.                           |
// |                                                                      |
// | This program is distributed in the hope that it will be useful,      |
// | but WITHOUT ANY WARRANTY; without even the implied warranty of       |
// | MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the        |
// | GNU General Public License for more details.                         |
// |                                                                      |
// | You should have received a copy of the GNU General Public License    |
// | along with this program; if not, write to the Free Software          |
// | Foundation, Inc., 51 Franklin St, Fifth Floor, Boston,               |
// | MA 02110-1301 USA                                                    |
// +----------------------------------------------------------------------+

define( 'POST_INDEXER_PLUGIN_DIR', plugin_dir_path( __FILE__) );

require_once POST_INDEXER_PLUGIN_DIR . 'includes/config.php';
require_once POST_INDEXER_PLUGIN_DIR . 'includes/functions.php';

// Include the database model we will be using across classes
require_once POST_INDEXER_PLUGIN_DIR . 'classes/class.model.php';

// Include the network query class for other plugins to use
require_once POST_INDEXER_PLUGIN_DIR . 'classes/networkquery.php';

// Include the rebuild cron class
require_once POST_INDEXER_PLUGIN_DIR . 'classes/cron.postindexerrebuild.php';

// Include the main class
require_once POST_INDEXER_PLUGIN_DIR . 'classes/class.postindexeradmin.php';