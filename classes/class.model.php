<?php

// A class that contains the database functions used within the post indexer plugin

if ( ! class_exists( 'postindexermodel' ) ) {

	class postindexermodel {

		var $build = 2;

		var $db;

		// tables list
		var $oldtables = array( 'site_posts', 'term_counts', 'site_terms', 'site_term_relationships' );
		var $tables = array(
			'network_posts',
			'network_rebuildqueue',
			'network_postmeta',
			'network_terms',
			'network_term_taxonomy',
			'network_term_relationships',
			'network_log'
		);

		// old table variables
		var $site_posts;
		var $term_counts;
		var $site_terms;
		var $site_term_relationships;

		// new table variables
		var $network_posts;
		var $network_rebuildqueue;
		var $network_postmeta;
		var $network_terms;
		var $network_term_taxonomy;
		var $network_term_relationships;
		var $network_log;

		// variable to identify if we've switched blogs or not
		var $on_blog_id = 0;

		var $global_post_types;

		function __construct() {

			global $wpdb;

			$this->db =& $wpdb;

			foreach ( $this->tables as $table ) {
				$this->$table = $this->db->base_prefix . $table;
			}

			if ( defined( 'PI_LOAD_OLD_TABLES' ) && PI_LOAD_OLD_TABLES === true ) {
				foreach ( $this->oldtables as $table ) {
					$this->$table = $this->db->base_prefix . $table;
				}
			}

			$version = get_site_option( 'postindexer_version', false );

			if ( $version === false || $version < $this->build ) {
				update_site_option( 'postindexer_version', $this->build );
				$this->build_indexer_tables( $version );
			}

			// Set the global / default post types that we will be using
			$this->global_post_types = get_site_option( 'postindexer_globalposttypes', array( 'post' ) );

		}

		function postindexermodel() {
			$this->__construct();
		}

		function build_indexer_tables( $old_version ) {

			$charset_collate = '';

			if ( ! empty( $this->db->charset ) ) {
				$charset_collate = "DEFAULT CHARACTER SET " . $this->db->charset;
			}

			if ( ! empty( $this->db->collate ) ) {
				$charset_collate .= " COLLATE " . $this->db->collate;
			}

			switch ( $old_version ) {

				case 1:        // Add in log table
					$sql = "CREATE TABLE IF NOT EXISTS `" . $this->network_log . "` (
							  `id` bigint(11) unsigned NOT NULL AUTO_INCREMENT,
							  `log_title` varchar(250) DEFAULT NULL,
							  `log_details` text,
							  `log_datetime` datetime DEFAULT NULL,
							  PRIMARY KEY (`id`)
							) $charset_collate;";

					$this->db->query( $sql );

					break;

				default:
					$sql = "CREATE TABLE IF NOT EXISTS `" . $this->network_posts . "` (
							  `BLOG_ID` bigint(20) unsigned NOT NULL DEFAULT '0',
							  `ID` bigint(20) unsigned NOT NULL,
							  `post_author` bigint(20) unsigned NOT NULL DEFAULT '0',
							  `post_date` datetime NOT NULL DEFAULT '0000-00-00 00:00:00',
							  `post_date_gmt` datetime NOT NULL DEFAULT '0000-00-00 00:00:00',
							  `post_content` longtext NOT NULL,
							  `post_title` text NOT NULL,
							  `post_excerpt` text NOT NULL,
							  `post_status` varchar(20) NOT NULL DEFAULT 'publish',
							  `comment_status` varchar(20) NOT NULL DEFAULT 'open',
							  `ping_status` varchar(20) NOT NULL DEFAULT 'open',
							  `post_password` varchar(20) NOT NULL DEFAULT '',
							  `post_name` varchar(200) NOT NULL DEFAULT '',
							  `to_ping` text NOT NULL,
							  `pinged` text NOT NULL,
							  `post_modified` datetime NOT NULL DEFAULT '0000-00-00 00:00:00',
							  `post_modified_gmt` datetime NOT NULL DEFAULT '0000-00-00 00:00:00',
							  `post_content_filtered` longtext NOT NULL,
							  `post_parent` bigint(20) unsigned NOT NULL DEFAULT '0',
							  `guid` varchar(255) NOT NULL DEFAULT '',
							  `menu_order` int(11) NOT NULL DEFAULT '0',
							  `post_type` varchar(20) NOT NULL DEFAULT 'post',
							  `post_mime_type` varchar(100) NOT NULL DEFAULT '',
							  `comment_count` bigint(20) NOT NULL DEFAULT '0',
							  PRIMARY KEY (`BLOG_ID`,`ID`),
							  KEY `post_name` (`post_name`),
							  KEY `type_status_date` (`post_type`,`post_status`,`post_date`,`ID`),
							  KEY `post_parent` (`post_parent`),
							  KEY `post_author` (`post_author`)
							) $charset_collate;";

					$this->db->query( $sql );

					$sql = "CREATE TABLE IF NOT EXISTS `" . $this->network_rebuildqueue . "` (
							  `blog_id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
							  `rebuild_updatedate` timestamp NULL DEFAULT NULL,
							  `rebuild_progress` bigint(20) unsigned DEFAULT NULL,
							  PRIMARY KEY (`blog_id`)
							) $charset_collate;";

					$this->db->query( $sql );

					$sql = "CREATE TABLE IF NOT EXISTS `" . $this->network_postmeta . "` (
							  `blog_id` bigint(20) unsigned NOT NULL,
							  `meta_id` bigint(20) unsigned NOT NULL,
							  `post_id` bigint(20) unsigned NOT NULL DEFAULT '0',
							  `meta_key` varchar(255) DEFAULT NULL,
							  `meta_value` longtext,
							  PRIMARY KEY (`blog_id`,`meta_id`),
							  KEY `post_id` (`post_id`),
							  KEY `meta_key` (`meta_key`)
							) $charset_collate;";

					$this->db->query( $sql );

					$sql = "CREATE TABLE IF NOT EXISTS `" . $this->network_terms . "` (
							  `term_id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
							  `name` varchar(200) NOT NULL DEFAULT '',
							  `slug` varchar(191) NOT NULL DEFAULT '',
							  `term_group` bigint(10) NOT NULL DEFAULT '0',
							  PRIMARY KEY (`term_id`),
							  UNIQUE KEY `slug` (`slug`),
							  KEY `name` (`name`)
							) $charset_collate;";

					$this->db->query( $sql );

					$sql = "CREATE TABLE IF NOT EXISTS `" . $this->network_term_taxonomy . "` (
							  `term_taxonomy_id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
							  `term_id` bigint(20) unsigned NOT NULL DEFAULT '0',
							  `taxonomy` varchar(32) NOT NULL DEFAULT '',
							  `description` longtext NOT NULL,
							  `parent` bigint(20) unsigned NOT NULL DEFAULT '0',
							  `count` bigint(20) NOT NULL DEFAULT '0',
							  PRIMARY KEY (`term_taxonomy_id`),
							  UNIQUE KEY `term_id_taxonomy` (`term_id`,`taxonomy`),
							  KEY `taxonomy` (`taxonomy`)
							) $charset_collate;";

					$this->db->query( $sql );

					$sql = "CREATE TABLE IF NOT EXISTS `" . $this->network_term_relationships . "` (
							  `blog_id` bigint(20) unsigned NOT NULL,
							  `object_id` bigint(20) unsigned NOT NULL DEFAULT '0',
							  `term_taxonomy_id` bigint(20) unsigned NOT NULL DEFAULT '0',
							  `term_order` int(11) NOT NULL DEFAULT '0',
							  PRIMARY KEY (`blog_id`,`object_id`,`term_taxonomy_id`),
							  KEY `term_taxonomy_id` (`term_taxonomy_id`)
							) $charset_collate;";

					$this->db->query( $sql );

					$sql = "CREATE TABLE IF NOT EXISTS `" . $this->network_log . "` (
							  `id` bigint(11) unsigned NOT NULL AUTO_INCREMENT,
							  `log_title` varchar(250) DEFAULT NULL,
							  `log_details` text,
							  `log_datetime` datetime DEFAULT NULL,
							  PRIMARY KEY (`id`)
							) $charset_collate;";

					$this->db->query( $sql );

					break;
			}

		}

		function get_justqueued_blogs( $limit = 25 ) {

			$sql   = $this->db->prepare( "SELECT * FROM {$this->network_rebuildqueue} WHERE rebuild_progress = 0 ORDER BY rebuild_updatedate ASC LIMIT %d", $limit );
			$queue = $this->db->get_results( $sql );

			return $queue;
		}

		function get_rebuilding_blogs( $limit = 5 ) {

			$sql   = $this->db->prepare( "SELECT * FROM {$this->network_rebuildqueue} WHERE rebuild_progress > 0 ORDER BY rebuild_updatedate ASC LIMIT %d", $limit );
			$queue = $this->db->get_results( $sql );

			return $queue;

		}

		function blogs_for_rebuilding() {

			$sql = "SELECT count(*) as rebuildblogs FROM {$this->network_rebuildqueue}";

			$var = $this->db->get_var( $sql );

			if ( empty( $var ) || $var == 0 ) {
				return false;
			} else {
				return true;
			}
		}

		// Rebuild blogs

		function rebuild_blog( $blog_id ) {

			$this->insert_or_update( $this->network_rebuildqueue, array(
				'blog_id'            => $blog_id,
				'rebuild_updatedate' => current_time( 'mysql' ),
				'rebuild_progress'   => 0
			) );

		}

		function rebuild_all_blogs() {

			global $site_id;

			$sql = "DELETE FROM {$this->network_rebuildqueue}";
			$this->db->query( $sql );

			if ( ! empty( $site_id ) && $site_id != 0 ) {
				$sql = $this->db->prepare( "INSERT INTO {$this->network_rebuildqueue} SELECT blog_id, timestamp(now()), 0 FROM {$this->db->blogs} where site_id = %d", $site_id );
			} else {
				$sql = "INSERT INTO {$this->network_rebuildqueue} SELECT blog_id, timestamp(now()), 0 FROM {$this->db->blogs}";
			}

			$this->db->query( $sql );

		}

		function is_in_rebuild_queue( $blog_id ) {

			$sql = $this->db->prepare( "SELECT * FROM {$this->network_rebuildqueue} WHERE blog_id = %d", $blog_id );

			$row = $this->db->get_row( $sql );

			if ( ! empty( $row ) && $row->blog_id == $blog_id ) {
				return true;
			} else {
				return false;
			}

		}

		function is_blog_indexable( $blog_id ) {

			$this->switch_to_blog( $blog_id );

			$indexing = get_option( 'postindexer_active', 'yes' );

			if ( $indexing == 'yes' ) {

				$blog_archived = get_blog_status( $blog_id, 'archived' );
				$blog_mature   = get_blog_status( $blog_id, 'mature' );
				$blog_spam     = get_blog_status( $blog_id, 'spam' );
				$blog_deleted  = get_blog_status( $blog_id, 'deleted' );

				if ( $blog_archived == '1' ) {
					$indexing = 'no';
				}

				if ( $blog_mature == '1' ) {
					$indexing = 'no';
				}

				if ( $blog_spam == '1' ) {
					$indexing = 'no';
				}

				if ( $blog_deleted == '1' ) {
					$indexing = 'no';
				}

			}

			$this->restore_current_blog();

			$indexing = apply_filters( 'postindexer_is_blog_indexable', $indexing, $blog_id );

			if ( $indexing == 'yes' ) {
				return true;
			} else {
				return false;
			}

		}

		function disable_indexing_for_blog( $blog_id ) {

			// Switch off indexing
			update_blog_option( $blog_id, 'postindexer_active', 'no' );

			// Remove any entry from the rebuild queue
			$this->remove_blog_from_queue( $blog_id );

			// Remove the existing entries
			$this->remove_indexed_entries_for_blog( $blog_id );

		}

		function enable_indexing_for_blog( $blog_id ) {

			// Switch on the indexing
			update_blog_option( $blog_id, 'postindexer_active', 'yes' );

			// Queue the site for rebuilding
			$this->rebuild_blog( $blog_id );

		}

		function remove_indexed_entries_for_blog( $blog_id ) {

			// Remove all the networked posts for the blog id
			$sql_str = $this->db->prepare( "DELETE FROM {$this->network_posts} WHERE BLOG_ID = %d", $blog_id );
			//$this->log_message( __FUNCTION__, $sql_str );
			$this->db->query( $sql_str );

			// Remove all the networked postmeta for the blog id
			$sql_str = $this->db->prepare( "DELETE FROM {$this->network_postmeta} WHERE blog_id = %d", $blog_id );
			//$this->log_message( __FUNCTION__, $sql_str );
			$this->db->query( $sql_str );

			// Remove all the networked term relationship information for the blog_id
			$sql_str = $this->db->prepare( "DELETE FROM {$this->network_term_relationships} WHERE blog_id = %d", $blog_id );
			//$this->log_message( __FUNCTION__, $sql_str );
			$this->db->query( $sql_str );

		}

		function remove_blog_from_queue( $blog_id ) {

			// Remove the blog from the queue
			$this->db->query( $this->db->prepare( "DELETE FROM {$this->network_rebuildqueue} WHERE blog_id = %d", $blog_id ) );

		}

		function update_blog_queue( $blog_id, $progress ) {

			$this->db->update( $this->network_rebuildqueue, array(
				'rebuild_progress'   => $progress,
				'rebuild_updatedate' => current_time( 'mysql' )
			), array( 'blog_id' => $blog_id ) );

		}

		function get_highest_post_for_blog( $blog_id = false ) {

			if ( $blog_id !== false ) {
				$this->switch_to_blog( $blog_id );
			}

			$max_id = $this->db->get_var( "SELECT MAX(ID) as max_id FROM {$this->db->posts}" );

			if ( $blog_id !== false ) {
				$this->restore_current_blog();
			}

			return $max_id;
		}

		function get_posts_for_indexing( $blog_id = false, $startat = 0 ) {

			if ( $blog_id !== false ) {
				$this->switch_to_blog( $blog_id );
			}

			$posttypes = get_option( 'postindexer_posttypes', $this->global_post_types );
			// Get the first five posts to work through that are published, in the selected post types and not password protected
			$sql    = "SELECT * FROM {$this->db->posts} WHERE ID <= %d AND post_status IN ('publish','inherit') AND post_type IN (" . implode( ', ', array_fill( 0, count( $posttypes ), '%s' ) ) . ") AND post_password = '' ORDER BY ID DESC LIMIT %d";
			$params = array( $startat );
			$params = array_merge( $params, $posttypes );
			$params = array_merge( $params, array( PI_CRON_POST_PROCESS_SECONDPASS ) );
			$sql    = call_user_func_array( array( $this->db, 'prepare' ), array_merge( array( $sql ), $params ) );

			$posts = $this->db->get_results( $sql, ARRAY_A );
			if ( $blog_id !== false ) {
				$this->restore_current_blog();
			}

			return $posts;
		}

		function get_post_for_indexing( $post_id, $blog_id = false, $restrict = true ) {

			if ( $blog_id !== false ) {
				$this->switch_to_blog( $blog_id );
			}

			if ( $restrict === true ) {
				$posttypes = get_option( 'postindexer_posttypes', $this->global_post_types );
				$posttypes = $this->build_in_params( $posttypes );
				// Get the post to work with that is published, in the selected post types and not password protected
				//$sql   = $this->db->prepare( "SELECT * FROM {$this->db->posts} WHERE ID = %d AND post_status IN ('publish','inherit') AND post_type IN (%s) AND post_password = ''", $post_id, implode( "','", $posttypes ) );
				$sql    = "SELECT * FROM {$this->db->posts} WHERE ID = %d AND post_status IN ('publish','inherit') AND post_type IN (" . implode( ', ', array_fill( 0, count( $posttypes ), '%s' ) ) . ") AND post_password = ''";
				$params = array( $post_id );
				$params = array_merge( $params, $posttypes );
				$sql    = call_user_func_array( array( $this->db, 'prepare' ), array_merge( array( $sql ), $params ) );
				$posts  = $this->db->get_results( $sql, ARRAY_A );
				$post   = $this->db->get_row( $sql, ARRAY_A );
			} else {
				$sql  = $this->db->prepare( "SELECT * FROM {$this->db->posts} WHERE ID = %d", $post_id );
				$post = $this->db->get_row( $sql, ARRAY_A );
			}

			if ( $blog_id !== false ) {
				$this->restore_current_blog();
			}

			return $post;
		}

		function get_postmeta_for_indexing( $post_id, $blog_id = false ) {

			if ( $blog_id !== false ) {
				$this->switch_to_blog( $blog_id );
			}

			// Get the post meta for this local post
			$metasql = $this->db->prepare( "SELECT * FROM {$this->db->postmeta} WHERE post_id = %d AND meta_key NOT IN ('_edit_last', '_edit_lock', '_encloseme', '_pingme')", $post_id );
			$meta    = $this->db->get_results( $metasql, ARRAY_A );

			if ( $blog_id !== false ) {
				$this->restore_current_blog();
			}

			return $meta;

		}

		function get_taxonomy_for_indexing( $post_id, $blog_id = false ) {

			if ( $blog_id !== false ) {
				$this->switch_to_blog( $blog_id );
			}

			$taxsql = $this->db->prepare( "SELECT t.term_id, t.name, t.slug, t.term_group, tt.term_taxonomy_id, tt.taxonomy, tt.description, tt.parent, tr.term_order FROM {$this->db->terms} AS t INNER JOIN {$this->db->term_taxonomy} AS tt ON t.term_id = tt.term_id INNER JOIN {$this->db->term_relationships} AS tr ON tt.term_taxonomy_id = tr.term_taxonomy_id WHERE tr.object_id = %d", $post_id );
			$tax    = $this->db->get_results( $taxsql, ARRAY_A );

			if ( $blog_id !== false ) {
				$this->restore_current_blog();
			}

			return $tax;

		}

		function is_post_indexable( $post, $blog_id = false ) {

			if ( $blog_id !== false ) {
				$this->switch_to_blog( $blog_id );
			}

			$posttypes = get_option( 'postindexer_posttypes', $this->global_post_types );

			// Checking for inherit here as well so we can get the media attachments for the post
			if ( in_array( $post['post_type'], $posttypes ) && in_array( $post['post_status'], array(
					'publish',
					'inherit'
				) ) && $post['post_password'] == ''
			) {
				$indexing = 'yes';
				//Do not insert aged posts.
				$agedposts      = get_site_option( 'postindexer_agedposts', array(
					'agedunit'   => 1,
					'agedperiod' => 'year'
				) );
				$post_timestamp = strtotime( $post['post_date'] );
				$post_age_limit = strtotime( '-' . $agedposts['agedunit'] . ' ' . $agedposts['agedperiod'] );
				if ( $post_timestamp < $post_age_limit ) {
					$indexing = 'no';
				}
			} else {
				$indexing = 'no';
			}

			if ( $blog_id !== false ) {
				$this->restore_current_blog();
			}

			$indexing = apply_filters( 'postindexer_is_post_indexable', $indexing, $post, $blog_id );

			if ( $indexing == 'yes' ) {
				return true;
			} else {
				return false;
			}

		}

		function index_post( $post ) {

			// Add the post record to the network tables
			$this->insert_or_update( $this->network_posts, $post );

			do_action( 'postindexer_index_post', $post );

		}

		function index_postmeta( $postmeta ) {

			$this->insert_or_update( $this->network_postmeta, $postmeta );

		}

		function index_tax( $tax ) {

			if ( $tax['parent'] == 0 ) {
				// There isn't a parent for this tax item so we can attempt to add it without more difficulty
				//$this->log_message( __FUNCTION__, "tax<pre>". print_r($tax, true)."</pre>" );

				$term_id = $this->insert_or_get_term( $tax['name'], $tax['slug'], $tax['term_group'] );
				if ( ! empty( $term_id ) ) {
					$term_taxonomy_id = $this->insert_or_get_taxonomy( $term_id, $tax['taxonomy'], $tax['description'], $tax['parent'] );

					// Now that we have the taxonomy_id and the post_id we can insert the relationship
					$this->insert_or_update( $this->network_term_relationships, array(
						'blog_id'          => $tax['blog_id'],
						'object_id'        => $tax['object_id'],
						'term_taxonomy_id' => $term_taxonomy_id,
						'term_order'       => $tax['term_order']
					) );
				}
			} else {
				// There is a parent tax, we are not going to do anything more advanced with it, but this part of the if statement is here in case we want to later.
				$term_id = $this->insert_or_get_term( $tax['name'], $tax['slug'], $tax['term_group'] );
				if ( ! empty( $term_id ) ) {
					$term_taxonomy_id = $this->insert_or_get_taxonomy( $term_id, $tax['taxonomy'], $tax['description'], 0 );

					// Now that we have the taxonomy_id and the post_id we can insert the relationship
					$this->insert_or_update( $this->network_term_relationships, array(
						'blog_id'          => $tax['blog_id'],
						'object_id'        => $tax['object_id'],
						'term_taxonomy_id' => $term_taxonomy_id,
						'term_order'       => $tax['term_order']
					) );
				}
			}

		}

		function remove_postmeta_for_post( $post_id, $blog_id = false ) {

			if ( $blog_id == false ) {
				$blog_id = $this->db->blogid;
			}

			// Remove all the networked postmeta for the blog id
			$this->db->query( $this->db->prepare( "DELETE FROM {$this->network_postmeta} WHERE blog_id = %d AND post_id = %d", $blog_id, $post_id ) );

		}

		function remove_term_relationships_for_post( $post_id, $blog_id = false ) {

			//$this->log_message( __FUNCTION__, "post_id[". $post_id."] blog_id[". $blog_id ."]" );
			//global $current_blog;
			//$this->log_message( __FUNCTION__, "current_blog<pre>". print_r($current_blog, true)."</pre> blog_id[". $blog_id ."]" );

			if ( $blog_id == false ) {
				$blog_id = $this->db->blogid;
			}

			// Remove all the networked term relationship information for the blog_id
			$sql_str = $this->db->prepare( "DELETE FROM {$this->network_term_relationships} WHERE blog_id = %d AND object_id = %d", $blog_id, $post_id );
			//$this->log_message( __FUNCTION__, $sql_str );
			$this->db->query( $sql_str );

		}

		function remove_indexed_entry_for_blog( $post_id, $blog_id = false ) {

			if ( $blog_id == false ) {
				$blog_id = $this->db->blogid;
			}

			// Remove all the networked posts for the blog id
			$sql_str = $this->db->prepare( "DELETE FROM {$this->network_posts} WHERE BLOG_ID = %d AND ID = %d", $blog_id, $post_id );
			//$this->log_message( __FUNCTION__, $sql_str );
			$this->db->query( $sql_str );

			// Remove all the networked postmeta for the blog id
			$sql_str = $this->db->prepare( "DELETE FROM {$this->network_postmeta} WHERE blog_id = %d AND post_id = %d", $blog_id, $post_id );
			//$this->log_message( __FUNCTION__, $sql_str );
			$this->db->query( $sql_str );

			// Remove all the networked term relationship information for the blog_id
			$sql_str = $this->db->prepare( "DELETE FROM {$this->network_term_relationships} WHERE blog_id = %d AND object_id = %d", $blog_id, $post_id );
			//$this->log_message( __FUNCTION__, $sql_str );
			$this->db->query( $sql_str );

			do_action( 'postindexer_remove_indexed_post', $post_id, $blog_id );
		}

		function remove_orphaned_postmeta_entries() {

			//$sql = $this->db->prepare( "DELETE FROM {$this->network_postmeta} WHERE " );

		}

		function remove_orphaned_tax_entries() {

			// Remove any taxonomy entries that aren't in a relationship
			$sql_str = $this->db->prepare( "DELETE FROM {$this->network_term_taxonomy} WHERE term_taxonomy_id NOT IN ( SELECT term_taxonomy_id FROM {$this->network_term_relationships} ) LIMIT %d", PI_CRON_TIDY_DELETE_LIMIT );
			//$this->log_message( __FUNCTION__, $sql_str );
			$this->db->query( $sql_str );
			//$this->log_message( __FUNCTION__, 'query<pre>'. print_r($this->db, true) ."</pre>" );

			// Remove any terms that aren't in a taxonomy
			$sql_str = $this->db->prepare( "DELETE FROM {$this->network_terms} WHERE term_id NOT IN ( SELECT term_id FROM {$this->network_term_taxonomy} ) LIMIT %d", PI_CRON_TIDY_DELETE_LIMIT );
			//$this->log_message( __FUNCTION__, $sql_str );
			$this->db->query( $sql_str );
			//$this->log_message( __FUNCTION__, 'query<pre>'. print_r($this->db, true) ."</pre>" );

		}

		function remove_posts_older_than( $unit, $period ) {

			switch ( $period ) {
				case 'hour':
				case 'day':
				case 'week':
				case 'month':
				case 'year':
					$period = strtoupper( $period );
					break;
				default:
					$period = null;
			}

			if ( $period == null ) {
				return false;
			}

			$sql   = $this->db->prepare( "SELECT BLOG_ID, ID FROM {$this->network_posts} WHERE DATE_ADD(post_date, INTERVAL %d {$period}) < CURRENT_DATE() LIMIT %d", $unit, PI_CRON_TIDY_DELETE_LIMIT );
			$posts = $this->db->get_results( $sql );

			if ( ! empty( $posts ) ) {
				foreach ( $posts as $post ) {
					$this->remove_indexed_entry_for_blog( $post->ID, $post->BLOG_ID );
				}
			}
		}

		function recalculate_tax_counts() {

			// Calculate and update the counts for the tax terms
			$sql = $this->db->prepare( "SELECT tr.term_taxonomy_id, count(*) as calculatedcount, tt.count FROM {$this->network_term_relationships} AS tr INNER JOIN {$this->network_term_taxonomy} AS tt ON tr.term_taxonomy_id = tt.term_taxonomy_id GROUP BY tr.term_taxonomy_id HAVING calculatedcount != tt.count LIMIT %d", PI_CRON_TIDY_COUNT_LIMIT );

			$counts = $this->db->get_results( $sql, ARRAY_A );
			if ( ! empty( $counts ) ) {
				foreach ( $counts as $count ) {
					$this->db->update( $this->network_term_taxonomy, array( 'count' => $count['calculatedcount'] ), array( 'term_taxonomy_id' => $count['term_taxonomy_id'] ) );
				}
			}

		}

		// Insert taxonomy term
		function insert_or_get_term( $name, $slug, $term_group ) {

			$sql     = $this->db->prepare( "SELECT term_id FROM {$this->network_terms} WHERE name = %s AND slug = %s AND term_group = %d", $name, $slug, $term_group );
			$term_id = $this->db->get_var( $sql );

			if ( empty( $term_id ) ) {
				// We need to insert the term as we don't have one
				$this->db->insert( $this->network_terms, array(
					'name'       => $name,
					'slug'       => $slug,
					'term_group' => $term_group
				) );
				$term_id = $this->db->insert_id;
			}

			return $term_id;

		}

		function insert_or_get_taxonomy( $term_id, $taxonomy, $description, $parent ) {

			$sql              = $this->db->prepare( "SELECT term_taxonomy_id FROM {$this->network_term_taxonomy} WHERE term_id = %d AND taxonomy = %s AND parent = %d", $term_id, $taxonomy, $parent );
			$term_taxonomy_id = $this->db->get_var( $sql );

			if ( empty( $term_taxonomy_id ) ) {
				// We nned to insert the taxonomy as we don't have one
				$this->db->insert( $this->network_term_taxonomy, array(
					'term_id'     => $term_id,
					'taxonomy'    => $taxonomy,
					'description' => $description,
					'parent'      => $parent,
					'count'       => 1
				) );
				$term_taxonomy_id = $this->db->insert_id;
			}

			return $term_taxonomy_id;

		}

		// Insert on duplicate update function
		function insert_or_update( $table, $query ) {

			$fields           = array_keys( $query );
			$formatted_fields = array();
			foreach ( $fields as $field ) {
				$form               = '%s';
				$formatted_fields[] = $form;
			}
			$sql = "INSERT INTO `$table` (`" . implode( '`,`', $fields ) . "`) VALUES ('" . implode( "','", $formatted_fields ) . "')";
			$sql .= " ON DUPLICATE KEY UPDATE ";

			$dup = array();
			foreach ( $fields as $field ) {
				$dup[] = "`" . $field . "` = VALUES(`" . $field . "`)";
			}

			$sql .= implode( ',', $dup );

			$sql_str = $this->db->prepare( $sql, $query );

			//$this->log_message( __FUNCTION__, $sql_str );
			return $this->db->query( $sql_str );

		}

		function switch_to_blog( $blog_id ) {

			if ( $blog_id != $this->db->blogid ) {
				$this->on_blog_id = $blog_id;
				switch_to_blog( $blog_id );
			}

		}

		function restore_current_blog() {

			if ( $this->on_blog_id != 0 ) {
				$this->on_blog_id = 0;
				restore_current_blog();
			}

		}

		function get_active_post_types( $blog_id = false ) {

			if ( $blog_id != false ) {
				$this->switch_to_blog( $blog_id );
			}

			$sql = "SELECT DISTINCT post_type FROM {$this->db->posts}";

			$post_types = $this->db->get_col( $sql );

			if ( $blog_id != false ) {
				$this->restore_current_blog();
			}

			return $post_types;

		}

		// Useful functions
		function &get_post( $blog_id, $network_post_id ) {

			$sql     = $this->db->prepare( "SELECT * FROM {$this->network_posts} WHERE BLOG_ID = %d AND ID = %d", $blog_id, $network_post_id );
			$results = $this->db->get_row( $sql, OBJECT );

			return $results;

		}

		function term_is_tag( $term ) {

			$sql      = $this->db->prepare( "SELECT taxonomy FROM {$this->network_term_taxonomy} AS tt INNER JOIN {$this->network_terms} AS t ON tt.term_id = t.term_id WHERE t.slug = %s", $term );
			$taxonomy = $this->db->get_var( $sql );

			if ( ! empty( $taxonomy ) && $taxonomy == 'post_tag' ) {
				return true;
			} else {
				return false;
			}

		}

		function term_is_category( $term ) {

			$sql      = $this->db->prepare( "SELECT taxonomy FROM {$this->network_term_taxonomy} AS tt INNER JOIN {$this->network_terms} AS t ON tt.term_id = t.term_id WHERE t.slug = %s", $term );
			$taxonomy = $this->db->get_var( $sql );

			if ( ! empty( $taxonomy ) && $taxonomy == 'category' ) {
				return true;
			} else {
				return false;
			}

		}

		function log_message( $title, $msg ) {
			$title .= ' (' . getmypid() . ')';
			$this->db->insert( $this->network_log, array(
				'log_title'    => $title,
				'log_details'  => $msg,
				'log_datetime' => current_time( 'mysql' )
			) );
		}

		function clear_messages( $keep = 25 ) {

			$ids = $this->db->get_col( $this->db->prepare( "SELECT id FROM {$this->network_log} ORDER BY id DESC LIMIT %d", $keep ) );
			$ids = "'" . implode( "','", $ids ) . "'";

			$sql = $this->db->prepare( "DELETE FROM {$this->network_log} WHERE id NOT IN (" . $ids . ") LIMIT %d", PI_CRON_TIDY_DELETE_LIMIT );

			$this->db->query( $sql );
		}

		function get_log_messages( $show = 25 ) {
			$sql = $this->db->prepare( "SELECT * FROM {$this->network_log} ORDER BY id DESC LIMIT %d", $show );

			return $this->db->get_results( $sql );
		}

		function get_summary_post_types() {

			$sql = "SELECT post_type, count(*) AS post_type_count FROM {$this->network_posts} GROUP BY post_type ORDER BY post_type_count DESC";

			return $this->db->get_results( $sql );

		}

		function get_summary_blog_totals() {

			$sql = "SELECT BLOG_ID, count(*) AS blog_count FROM {$this->network_posts} GROUP BY BLOG_ID ORDER BY blog_count DESC LIMIT 15";

			return $this->db->get_results( $sql );

		}

		function get_summary_blog_post_type_totals( $ids = array() ) {

			$ids = $this->db->get_col( "SELECT BLOG_ID, count(*) AS blog_count FROM {$this->network_posts} GROUP BY BLOG_ID ORDER BY blog_count DESC LIMIT 15" );
			$ids = "'" . implode( "','", $ids ) . "'";

			$sql = "SELECT BLOG_ID, post_type, count(*) AS blog_type_count FROM {$this->network_posts} WHERE BLOG_ID IN (" . $ids . ") GROUP BY BLOG_ID, post_type ORDER BY blog_id, post_type DESC LIMIT 15";

			return $this->db->get_results( $sql );

		}

		function get_summary_single_site_blog_post_type_totals( $id ) {

			$sql = $this->db->prepare( "SELECT BLOG_ID, post_type, count(*) AS blog_type_count FROM {$this->network_posts} WHERE BLOG_ID = %d GROUP BY BLOG_ID, post_type ORDER BY blog_id, post_type DESC LIMIT %d", $id, 15 );

			return $this->db->get_results( $sql );

		}

		function get_summary_recently_indexed() {

			$sql = $this->db->prepare( "SELECT * FROM {$this->network_posts} ORDER BY post_modified_gmt DESC LIMIT %d", 15 );

			return $this->db->get_results( $sql );

		}

		function get_summary_sites_in_queue() {

			$sql = "SELECT count(*) AS inqueue FROM {$this->network_rebuildqueue}";

			return $this->db->get_var( $sql );

		}

		function get_summary_sites_in_queue_processing() {

			$sql = $this->db->prepare( "SELECT count(*) AS inqueue FROM {$this->network_rebuildqueue} WHERE rebuild_progress > %d", 0 );

			return $this->db->get_var( $sql );

		}

		function get_summary_sites_in_queue_not_processing() {

			$sql = $this->db->prepare( "SELECT count(*) AS inqueue FROM {$this->network_rebuildqueue} WHERE rebuild_progress = %d", 0 );

			return $this->db->get_var( $sql );

		}

		function get_summary_sites_in_queue_finish_next_pass() {

			$sql = $this->db->prepare( "SELECT count(*) AS inqueue FROM {$this->network_rebuildqueue} WHERE rebuild_progress > 0 AND rebuild_progress <= %d", PI_CRON_POST_PROCESS_SECONDPASS );

			return $this->db->get_var( $sql );

		}

	}

}

?>