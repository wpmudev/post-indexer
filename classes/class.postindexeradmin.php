<?php
if(!class_exists('postindexeradmin')) {

	class postindexeradmin {

		var $build = 1;

		var $db;

		// tables list
		var $oldtables =  array( 'site_posts', 'term_counts', 'site_terms', 'site_term_relationships' );
		var $tables = array( 'network_posts', 'network_rebuildqueue' );

		// old table variables
		var $site_posts;
		var $term_counts;
		var $site_terms;
		var $site_term_relationships;

		// new table variables
		var $network_posts;
		var $network_rebuildqueue;

		function postindexeradmin() {
			$this->__construct();
		}

		function __construct() {

			global $wpdb;

			$this->db =& $wpdb;

			foreach($this->tables as $table) {
				$this->$table = $this->db->base_prefix . $table;
			}

			if(defined('PI_LOAD_OLD_TABLES') && PI_LOAD_OLD_TABLES === true ) {
				foreach($this->oldtables as $table) {
					$this->$table = $this->db->base_prefix . $table;
				}
			}


			$version = get_site_option('postindexer_version', false);
			if($version === false || $version < $this->build) {
				update_site_option('postindexer_version', $this->build);
				$this->build_indexer_tables( $version );
			}

			// Add settings menu action
			add_action('network_admin_menu', array( &$this, 'add_admin_page' ) );

			add_action('load-settings_page_postindexer', array(&$this, 'add_header_postindexer_page'));
			//settings_page_postindexer

			// Sites page integration
			add_filter( 'wpmu_blogs_columns', array(&$this, 'add_sites_column_heading'), 99 );
			add_action( 'manage_sites_custom_column', array(&$this, 'add_sites_column_data'), 99, 2 );
			add_action( 'wp_ajax_editsitepostindexer', array(&$this, 'edit_site_postindexer') );
			add_action( 'admin_enqueue_scripts', array(&$this, 'add_header_sites_page'));
			add_action( 'wpmuadminedit' , array(&$this, 'process_sites_page'));
			// Sites update settings
			add_filter( 'network_sites_updated_message_disableindexing' , array(&$this, 'output_msg_sites_page') );
			add_filter( 'network_sites_updated_message_not_disableindexing' , array(&$this, 'output_msg_sites_page') );
			add_filter( 'network_sites_updated_message_enableindexing' , array(&$this, 'output_msg_sites_page') );
			add_filter( 'network_sites_updated_message_not_enableindexing' , array(&$this, 'output_msg_sites_page') );
			add_filter( 'network_sites_updated_message_rebuildindexing' , array(&$this, 'output_msg_sites_page') );
			add_filter( 'network_sites_updated_message_not_rebuildindexing' , array(&$this, 'output_msg_sites_page') );

			//index posts
			//add_action('save_post', array(&$this, 'post_indexer_post_insert_update') );
			//add_action('delete_post', array( &$this, 'post_indexer_delete') );
			//handle blog changes
			//add_action('make_spam_blog', array( &$this, 'post_indexer_change_remove') );
			//add_action('archive_blog', array( &$this, 'post_indexer_change_remove') );
			//add_action('mature_blog', array( &$this, 'post_indexer_change_remove') );
			//add_action('deactivate_blog', array( &$this, 'post_indexer_change_remove') );
			//add_action('blog_privacy_selector', array( &$this, 'post_indexer_public_update') );
			//add_action('delete_blog', array( &$this, 'post_indexer_change_remove'), 10, 1);
			//update blog types
			//add_action('blog_types_update', array( &$this, 'post_indexer_sort_terms_update') );
			//if ($post_indexer_enable_hit_tracking == '1') {
			//	add_filter('the_content', array( &$this, 'post_indexer_hit_tracking') );
		//		add_filter('the_excerpt', array( &$this, 'post_indexer_hit_tracking') );
		// 	}

		}

		//------------------------------------------------------------------------//
		//---Functions------------------------------------------------------------//
		//------------------------------------------------------------------------//

		function add_header_sites_page( $hook_suffix ) {
			if($hook_suffix == 'sites.php') {
				wp_enqueue_script('thickbox');

				wp_register_script('pi-sites-post-indexer', WP_PLUGIN_URL . '/post-indexer/js/sites.postindexer.js', array('jquery', 'thickbox'));
				wp_enqueue_script('pi-sites-post-indexer');

				wp_localize_script('pi-sites-post-indexer', 'postindexer', array( 'siteedittitle'	=>	__('Post Indexer Settings','postindexer')
																												));

				wp_enqueue_style('thickbox');
			}
		}

		function output_msg_sites_page( $msg ) {
			switch( $_GET['action'] ) {
				case 'disableindexing':			$msg = __('Indexing disabled for the requested site.','postindexer');
												break;
				case 'not_disableindexing':		$msg = __('Indexing could not be disabled for the requested site.','postindexer');
												break;
				case 'enableindexing':			$msg = __('Indexing enabled for the requested site.','postindexer');
												break;
				case 'not_enableindexing':		$msg = __('Indexing could not be enabled for the requested site.','postindexer');
												break;
				case 'rebuildindexing':			$msg = __('Index scheduled for rebuilding for the requested site.','postindexer');
												break;
				case 'not_rebuildindexing':		$msg = __('Index could not be scheduled for rebuilding for the requested site.','postindexer');
												break;
			}

			return $msg;
		}

		function process_sites_page() {
			switch($_GET['action']) {
				case 'disablesitepostindexer':	$blog_id = $_GET['blog_id'];
												check_admin_referer('disable_site_postindexer_' . $blog_id);
												if ( !current_user_can( 'manage_sites' ) )
													wp_die( __( 'You do not have permission to access this page.' ) );

												if ( $blog_id != '0' ) {
													update_blog_option( $blog_id, 'postindexer_active', 'no' );
													wp_safe_redirect( add_query_arg( array( 'updated' => 'true', 'action' => 'disableindexing' ), wp_get_referer() ) );
												} else {
													wp_safe_redirect( add_query_arg( array( 'updated' => 'true', 'action' => 'not_disableindexing' ), wp_get_referer() ) );
												}
												break;

				case 'enablesitepostindexer':	$blog_id = $_GET['blog_id'];
												check_admin_referer('enable_site_postindexer_' . $blog_id);
												if ( !current_user_can( 'manage_sites' ) )
													wp_die( __( 'You do not have permission to access this page.' ) );

												if ( $blog_id != '0' ) {
													update_blog_option( $blog_id, 'postindexer_active', 'yes' );
													wp_safe_redirect( add_query_arg( array( 'updated' => 'true', 'action' => 'enableindexing' ), wp_get_referer() ) );
												} else {
													wp_safe_redirect( add_query_arg( array( 'updated' => 'true', 'action' => 'not_enableindexing' ), wp_get_referer() ) );
												}
												break;
				case 'editsitepostindexer':
												break;

				case 'rebuildsitepostindexer':	$blog_id = $_GET['blog_id'];
												check_admin_referer('rebuild_site_postindexer_' . $blog_id);
												if ( !current_user_can( 'manage_sites' ) )
													wp_die( __( 'You do not have permission to access this page.' ) );

												if ( $blog_id != '0' ) {
													$this->rebuild_blog( $blog_id );
													wp_safe_redirect( add_query_arg( array( 'updated' => 'true', 'action' => 'rebuildindexing' ), wp_get_referer() ) );
												} else {
													wp_safe_redirect( add_query_arg( array( 'updated' => 'true', 'action' => 'not_rebuildindexing' ), wp_get_referer() ) );
												}
												break;

			}
		}

		function add_sites_column_heading( $columns ) {

			$columns['postindexer'] = __('Indexing','postindexer');

			return $columns;
		}

		function add_sites_column_data( $column_name, $blog_id ) {

			if($column_name == 'postindexer') {
				$indexing = get_blog_option( $blog_id, 'postindexer_active', 'yes' );
				if( $indexing == 'yes' ) {
					$posttypes = get_blog_option( $blog_id, 'postindexer_posttypes', array( 'post' ) );
					echo implode( '<br/>', $posttypes );
					?>
					<div class="row-actions">
						<span class="disable">
							<a class='postindexersitedisablelink' href='<?php echo wp_nonce_url( network_admin_url("sites.php?action=disablesitepostindexer&amp;blog_id=" . $blog_id . ""), 'disable_site_postindexer_' . $blog_id); ?>'><?php _e('Disable','postindexer'); ?></a>
						</span> |
						<span class="edit">
							<a class='postindexersiteeditlink' href='<?php echo wp_nonce_url( admin_url("admin-ajax.php?action=editsitepostindexer&amp;blog_id=" . $blog_id . ""), 'edit_site_postindexer_' . $blog_id); ?>'><?php _e('Edit','postindexer'); ?></a>
						</span> |
						<span class="rebuild">
							<a class='postindexersiterebuildlink' href='<?php echo wp_nonce_url( network_admin_url("sites.php?action=rebuildsitepostindexer&amp;blog_id=" . $blog_id . ""), 'rebuild_site_postindexer_' . $blog_id); ?>'><?php _e('Rebuild','postindexer'); ?></a>
						</span>
					</div>
					<?php
				} else {
					_e('Not Indexing', 'postindexer');
					?>
					<div class="row-actions">
						<span class="enable">
							<a class='postindexersiteenablelink' href='<?php echo wp_nonce_url( network_admin_url("sites.php?action=enablesitepostindexer&amp;blog_id=" . $blog_id . ""), 'enable_site_postindexer_' . $blog_id); ?>'><?php _e('Enable','postindexer'); ?></a>
						</span>
					</div>
					<?php
				}
			}

		}

		function edit_site_postindexer() {
			echo "here";
			exit;
		}

		function add_admin_page() {
			$hook = add_submenu_page( 'settings.php', __('Post Indexer', 'postindexer'), __('Post Indexer', 'postindexer'), 'manage_options', 'postindexer', array( &$this, 'handle_postindexer_page') );
		}

		function add_header_postindexer_page() {
			 $this->process_postindexer_page();
		}

		function process_postindexer_page() {

			switch($_POST['action']) {

				case 'postindexerrebuildallsites':	check_admin_referer('postindexer_rebuild_all_sites');
													$this->rebuild_all_blogs();
													wp_safe_redirect( add_query_arg( array( 'msg' => 1 ), wp_get_referer() ) );
													exit;
													break;

			}

		}

		function handle_postindexer_page() {

			$messages = array();
			$messages[1] = __('Rebuilding of the Post Index has been scheduled.','postindexer');

			?>
			<div class="wrap">
				<div id="icon-edit" class="icon32 icon32-posts-post"><br></div>
				<h2><?php _e('Post Indexer Options','postindexer'); ?></h2>

				<?php
				if($this->blogs_for_rebuilding()) {
					// Show a rebuilding message and timer
					?>
					<div id='rebuildingmessage'>
					Boo
					</div>
					<?php
				}
				?>

				<?php
				if ( isset($_GET['msg']) ) {
					echo '<div id="message" class="updated fade"><p>' . $messages[(int) $_GET['msg']] . '</p></div>';
					$_SERVER['REQUEST_URI'] = remove_query_arg(array('message'), $_SERVER['REQUEST_URI']);
				}
				?>

				<form action='' method='post'>

					<input type='hidden' name='action' value='postindexerrebuildallsites' />
					<?php
						wp_nonce_field('postindexer_rebuild_all_sites');
					?>

					<h3><?php _e('Rebuild Network Post Index','postindexer'); ?></h3>
					<p class='description'><?php _e('You can rebuild the Post Index by clicking on the <strong>Rebuild Index</strong> button below.','postindexer'); ?></p>
					<p class='description'><?php _e("Note: This may take a considerable amount of time and could impact the performance of your server.",'postindexer'); ?></p>

					<p class="submit">
						<input type="submit" name="Submit" class="button-primary" value="<?php esc_attr_e('Rebuild Index','postindexer'); ?>" />
					</p>
				</form>

				<form action='' method='post'>
					<table class="form-table">
								<tbody><tr valign="top">
									<th scope="row"><label for="site_name">Network Name</label></th>
									<td>
										<input type="text" value="My Network Sites" class="regular-text" id="site_name" name="site_name">
										<br>
										What you would like to call this network.				</td>
								</tr>

								<tr valign="top">
									<th scope="row"><label for="admin_email">Network Admin Email</label></th>
									<td>
										<input type="text" value="barry@mapinated.com" class="regular-text" id="admin_email" name="admin_email">
										<br>
										Registration and support emails will come from this address. An address such as <code>support@dev.site</code> is recommended.				</td>
								</tr>
							</tbody></table>

					<?php
						do_action( 'postindexer_options_page' );
					?>

					<p class="submit">
						<input type="submit" name="Submit" class="button-primary" value="<?php esc_attr_e('Save Changes','membership'); ?>" />
					</p>

				</form>

			</div>
			<?php
		}

		function build_indexer_tables( $old_version ) {

			switch( $old_version ) {

				case 1:
							break;

				default:	$sql = "CREATE TABLE IF NOT EXISTS `" . $this->network_posts . "` (
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
							) DEFAULT CHARSET=utf8;";

							$this->db->query( $sql );

							$sql = "CREATE TABLE IF NOT EXISTS `" . $this->network_rebuildqueue . "` (
							  `blog_id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
							  `rebuild_startdate` timestamp NULL DEFAULT NULL,
							  `rebuild_progress` bigint(20) unsigned DEFAULT NULL,
							  PRIMARY KEY (`blog_id`)
							) DEFAULT CHARSET=utf8;";

							$this->db->query( $sql );

							break;
			}

		}

		function post_indexer_make_current() {
			global $wpdb, $post_indexer_current_version;
			if (get_site_option( "post_indexer_version" ) == '') {
				add_site_option( 'post_indexer_version', '0.0.0' );
			}

			if (get_site_option( "post_indexer_version" ) == $post_indexer_current_version) {
				// do nothing
			} else {
				//update to current version
				update_site_option( "post_indexer_installed", "no" );
				update_site_option( "post_indexer_version", $post_indexer_current_version );
			}
			post_indexer_global_install();
			//--------------------------------------------------//
			if (get_option( "post_indexer_version" ) == '') {
				add_option( 'post_indexer_version', '0.0.0' );
			}

			if (get_option( "post_indexer_version" ) == $post_indexer_current_version) {
				// do nothing
			} else {
				//update to current version
				update_option( "post_indexer_version", $post_indexer_current_version );
				post_indexer_blog_install();
			}
		}

		function post_indexer_blog_install() {
			global $wpdb, $post_indexer_current_version;
			//$post_indexer_table1 = "";
			//$wpdb->query( $post_indexer_table1 );
		}

		function post_indexer_global_install() {
			global $wpdb, $post_indexer_current_version;
			if (get_site_option( "post_indexer_installed" ) == '') {
				add_site_option( 'post_indexer_installed', 'no' );
			}

			if (get_site_option( "post_indexer_installed" ) == "yes") {
				// do nothing
				$sql = "SHOW COLUMNS FROM " . $wpdb->base_prefix . "site_posts;";
				$cols = $wpdb->get_col( $sql );
				if(!in_array('post_type', $cols)) {
					$sql = "ALTER TABLE " . $wpdb->base_prefix . "site_posts ADD post_type varchar(20) NULL DEFAULT 'post'  AFTER post_modified_stamp";
					$sql2 = "ALTER TABLE " . $wpdb->base_prefix . "site_posts ADD INDEX  (post_type);";

					$wpdb->query( $sql );
					$wpdb->query( $sql2 );
				}
			} else {

				$post_indexer_table1 = "CREATE TABLE IF NOT EXISTS `" . $wpdb->base_prefix . "site_posts` (
				  `site_post_id` bigint(20) unsigned NOT NULL auto_increment,
				  `blog_id` bigint(20) default NULL,
				  `site_id` bigint(20) default NULL,
				  `sort_terms` text,
				  `blog_public` int(2) default NULL,
				  `post_id` bigint(20) default NULL,
				  `post_author` bigint(20) default NULL,
				  `post_title` text,
				  `post_content` text,
				  `post_content_stripped` text,
				  `post_terms` text,
				  `post_permalink` text,
				  `post_published_gmt` datetime NOT NULL default '0000-00-00 00:00:00',
				  `post_published_stamp` varchar(255) default NULL,
				  `post_modified_gmt` datetime NOT NULL default '0000-00-00 00:00:00',
				  `post_modified_stamp` varchar(255) default NULL,
				  `post_type` varchar(20) default 'post',
				  PRIMARY KEY  (`site_post_id`),
				  KEY `post_type` (`post_type`)
				) ENGINE=MyISAM  DEFAULT CHARSET=utf8;";

				$post_indexer_table2 = "CREATE TABLE IF NOT EXISTS `" . $wpdb->base_prefix . "term_counts` (
		  `term_count_id` bigint(20) unsigned NOT NULL auto_increment,
		  `term_id` bigint(20),
		  `term_count_type` TEXT,
		  `term_count_updated` datetime NOT NULL default '0000-00-00 00:00:00',
		  `term_count` bigint(20),
		  PRIMARY KEY  (`term_count_id`)
		) ENGINE=MyISAM  DEFAULT CHARSET=utf8;";

				$post_indexer_table3 = "CREATE TABLE IF NOT EXISTS `{$wpdb->base_prefix}site_terms` (
			                `term_id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			                `name` varchar(200) NOT NULL DEFAULT '',
			                `slug` varchar(200) NOT NULL DEFAULT '',
			                `type` varchar(20) NOT NULL DEFAULT 'product_category',
			                `count` bigint(10) NOT NULL DEFAULT '0',
			                PRIMARY KEY (`term_id`),
			                UNIQUE KEY `slug` (`slug`),
			                KEY `name` (`name`)
			              ) ENGINE=MyISAM DEFAULT CHARSET=utf8;";

				$post_indexer_table4 = 	"CREATE TABLE IF NOT EXISTS `{$wpdb->base_prefix}site_term_relationships` (
				                `site_post_id` bigint(20) unsigned NOT NULL,
				                `term_id` bigint(20) unsigned NOT NULL,
				                KEY (`site_post_id`),
				                KEY (`term_id`)
				              ) ENGINE=MyISAM DEFAULT CHARSET=utf8;";
				$post_indexer_table5 = "";

				$wpdb->query( $post_indexer_table1 );
				$wpdb->query( $post_indexer_table2 );
				$wpdb->query( $post_indexer_table3 );
				$wpdb->query( $post_indexer_table4 );
				//$wpdb->query( $post_indexer_table5 );
				update_site_option( "post_indexer_installed", "yes" );
			}
		}

		function post_indexer_get_sort_terms($tmp_blog_ID){
			$post_indexer_blog_lang = get_blog_option($tmp_blog_ID,"WPLANG");
			if ($post_indexer_blog_lang == ''){
				$post_indexer_blog_lang = 'en_EN';
			}
			$post_indexer_blog_types = get_blog_option($tmp_blog_ID,"blog_types");
			if ($post_indexer_blog_types == ''){
				$post_indexer_blog_types = '||';
			}
			$post_indexer_class = get_blog_option($tmp_blog_ID,"blog_class");

			$tmp_sort_terms = array();

			$post_indexer_blog_types = explode("|", $post_indexer_blog_types);
			foreach ( $post_indexer_blog_types as $post_indexer_blog_type ) {
				if ( $post_indexer_blog_type != '' ) {
					$tmp_sort_terms[] = 'blog_type_' . $post_indexer_blog_type;
				}
			}
			if ( $post_indexer_class != '' ) {
				$tmp_sort_terms[] = 'class_' . $post_indexer_class;
			}

			$tmp_sort_terms[] = 'blog_lang_' . strtolower( $post_indexer_blog_lang );

			return '|' . implode("|", $tmp_sort_terms) . '|all|';

		}

		function post_indexer_public( $post_type ) {

			global $wp_post_types;

			if(!empty($wp_post_types[$post_type])) {

				if(!empty($wp_post_types[$post_type]->publicly_queryable) && $wp_post_types[$post_type]->publicly_queryable) {
					return true;
				} else {
					return false;
				}

			} else {
				return false;
			}

		}

		function post_indexer_post_insert_update($tmp_post_ID){
			global $wpdb, $current_site;

			$tmp_blog_public = get_blog_status( $wpdb->blogid, 'public');
			$tmp_blog_archived = get_blog_status( $wpdb->blogid, 'archived');
			$tmp_blog_mature = get_blog_status( $wpdb->blogid, 'mature');
			$tmp_blog_spam = get_blog_status( $wpdb->blogid, 'spam');
			$tmp_blog_deleted = get_blog_status( $wpdb->blogid, 'deleted');

			$tmp_post = get_post($tmp_post_ID);

			if ($tmp_post->post_type == 'page'){
				post_indexer_delete($tmp_post_ID);
			} else if ($tmp_post->post_type == 'revision'){
				post_indexer_delete($tmp_post_ID);
			} else if ($tmp_post->post_status != 'publish'){
				post_indexer_delete($tmp_post_ID);
			} else if ($tmp_blog_archived == '1'){
				post_indexer_delete($tmp_post_ID);
			} else if ($tmp_blog_mature == '1'){
				post_indexer_delete($tmp_post_ID);
			} else if ($tmp_blog_spam == '1'){
				post_indexer_delete($tmp_post_ID);
			} else if ($tmp_blog_deleted == '1'){
				post_indexer_delete($tmp_post_ID);
			} else if ($tmp_post->post_title == ''){
				post_indexer_delete($tmp_post_ID);
			} else if ($tmp_post->post_content == ''){
				post_indexer_delete($tmp_post_ID);
			} else if ( !post_indexer_public( $tmp_post->post_type ) ) {
				post_indexer_delete($tmp_post_ID);
			} else {
				//delete post
				post_indexer_delete($tmp_post_ID);
				//get post terms
				$object_ids = array($tmp_post_ID);
				$taxonomies = array('category', 'post_tag');
				$tmp_terms = wp_get_object_terms($object_ids, $taxonomies, '');
				$tmp_post_terms = '|';
				foreach ($tmp_terms as $tmp_term) {
					$tmp_post_terms = $tmp_post_terms . $tmp_term->term_id . '|';
				}
				//get sort terms
				$tmp_sort_terms = post_indexer_get_sort_terms($wpdb->blogid);
				//post does not exist - insert site post
				$wpdb->query("INSERT IGNORE INTO " . $wpdb->base_prefix . "site_posts
				(post_id, blog_id, site_id, sort_terms, post_author, post_title, post_content, post_content_stripped, post_permalink, post_published_gmt, post_published_stamp, post_modified_gmt, post_modified_stamp, post_terms, blog_public, post_type)
				VALUES
				('" . $tmp_post_ID . "','" . $wpdb->blogid . "','" . $wpdb->siteid . "','" . $tmp_sort_terms . "','" . $tmp_post->post_author . "','" . addslashes($tmp_post->post_title) . "','" . addslashes($tmp_post->post_content) . "','" . addslashes(post_indexer_strip_content($tmp_post->post_content)) . "','" . get_permalink($tmp_post_ID) . "','" . $tmp_post->post_date_gmt . "','" . strtotime($tmp_post->post_date_gmt) . "','" . $tmp_post->post_modified_gmt . "','" . time() . "','" . $tmp_post_terms . "','" . $tmp_blog_public . "','" . $tmp_post->post_type . "')");

				$site_post_id = $wpdb->insert_id;

				//update term counts
				post_indexer_post_terms_insert_update($tmp_sort_terms,$tmp_post_terms);

				//get post terms
				$taxonomies = array( 'post_tag', 'category' );
				$new_terms = wp_get_object_terms( array( $tmp_post_ID ), $taxonomies );
				if ( count($new_terms) ) {

		      		//get existing terms
		      		foreach ($new_terms as $term)
				        $new_slugs[] = $term->slug;
				  		$slug_list = implode( "','", $new_slugs );
				      $existing_terms = $wpdb->get_results( "SELECT * FROM {$wpdb->base_prefix}site_terms WHERE slug IN ('$slug_list')" );
				      $existing_slugs = array();
				      if ( is_array($existing_terms) && count($existing_terms) ) {
				        foreach ($existing_terms as $term) {
				          $existing_slugs[$term->term_id] = $term->slug;
				        }
				      }

		        		//process
						$tids = array();
				        foreach ($new_terms as $term) {

				          //check if in terms, but not attached
				          if ( in_array($term->slug, $existing_slugs) ) {

				            //add relationship
				            $id = array_search($term->slug, $existing_slugs);
				            $wpdb->insert( $wpdb->base_prefix . 'site_term_relationships', array( 'term_id' => $id, 'site_post_id' => $site_post_id ) );



				          } else { //brand new term

				            //insert term
				            $wpdb->insert( $wpdb->base_prefix . 'site_terms', array( 'name' => $term->name, 'slug' => $term->slug, 'type' => $term->taxonomy ) );
				            $id = $wpdb->insert_id;

				            //add relationship
				            $wpdb->insert( $wpdb->base_prefix . 'site_term_relationships', array( 'term_id' => $id, 'site_post_id' => $site_post_id ) );

				          }

							$tids[] = $id;

				        }

						// Update the post terms information
						if(!empty($tids)) {
							$wpdb->query( $wpdb->prepare("UPDATE " . $wpdb->base_prefix . "site_posts SET post_terms = %s WHERE site_post_id = %d", '|' . implode('|', $tids) .  '|', $site_post_id ));
						}

		    	} else { //no terms, so adjust counts of existing

		      		//delete term relationships
		      		$wpdb->query( "DELETE FROM {$wpdb->base_prefix}site_term_relationships WHERE site_post_id = $site_post_id" );
		    	}
			}
		}

		function post_indexer_post_terms_insert_update($tmp_sort_terms,$tmp_post_terms){
			global $wpdb;

			//get existing term entries / insert if not exist
			$tmp_post_terms = explode("|",$tmp_post_terms);
			$tmp_post_terms = array_unique($tmp_post_terms);
			$tmp_sort_terms = explode("|",$tmp_sort_terms);

			$tmp_term_array_items = array();
			$tmp_loop_count = 0;
			foreach ( $tmp_sort_terms as $tmp_sort_term ) {
				foreach ( $tmp_post_terms as $tmp_post_term ) {
					if ( $tmp_sort_term != '' && $tmp_post_term != '' ) {
						$tmp_loop_count = $tmp_loop_count + 1;
						$tmp_term_array_items[$tmp_loop_count]['type'] = $tmp_sort_term;
						$tmp_term_array_items[$tmp_loop_count]['id'] = $tmp_post_term;
					}
				}
			}

		    $existing_where = "WHERE ( ";
		    $x = 1;
		    foreach ($tmp_term_array_items as $tmp_term_array_item) {
		       if ( $x == 1 ) {
		           $existing_where .= " (term_id = '" . $tmp_term_array_item['id'] ."' AND term_count_type = '" . $tmp_term_array_item['type'] ."')";
		          $x++; // only run this once
		       } else {
		           $existing_where .= " OR (term_id = '" . $tmp_term_array_item['id'] ."' AND term_count_type = '" . $tmp_term_array_item['type'] ."')";
		       }
		    }

			$existing_query = "SELECT * FROM " . $wpdb->base_prefix . "term_counts " . $existing_where . " )";
			$tmp_existing_terms = $wpdb->get_results( $existing_query, ARRAY_A );

			foreach ( $tmp_sort_terms as $tmp_sort_term ) {
				foreach ( $tmp_post_terms as $tmp_post_term ) {
					if ( $tmp_sort_term != '' && $tmp_post_term != '' ) {
						$tmp_exists = 0;
						if ( count( $tmp_existing_terms ) > 0 ) {
							foreach ( $tmp_existing_terms as $tmp_existing_term ) {
								if ( $tmp_existing_term['term_id'] == $tmp_post_term && $tmp_existing_term['term_count_type'] == $tmp_sort_term ) {
									$tmp_exists = 1;
								}
							}
						}
						if ( $tmp_exists != 1 ) {
							//insert
							$wpdb->query("INSERT IGNORE INTO " . $wpdb->base_prefix . "term_counts (term_id, term_count_type, term_count_updated, term_count)
							VALUES
							('" . $tmp_post_term . "','" . $tmp_sort_term . "','" . date("Y-m-d H:i:s") . "','0')");
						}
					}
				}
			}
			// update term counts
		    $update_where = "WHERE ( ";
		    $x = 1;
		    foreach ($tmp_term_array_items as $tmp_term_array_item) {
		       if ( $x == 1 ) {
		           $update_where .= " (term_id = '" . $tmp_term_array_item['id'] ."' AND term_count_type = '" . $tmp_term_array_item['type'] ."')";
		          $x++; // only run this once
		       } else {
		           $update_where .= " OR (term_id = '" . $tmp_term_array_item['id'] ."' AND term_count_type = '" . $tmp_term_array_item['type'] ."')";
		       }
		    }

		    $update_query = "UPDATE " . $wpdb->base_prefix . "term_counts SET term_count = term_count + 1,term_count_updated = '" . date("Y-m-d H:i:s") . "' ". $update_where . " )";
		    $wpdb->query($update_query);
		}

		function post_indexer_delete($tmp_post_ID){
			global $wpdb;
			//get post terms
			$tmp_post_terms = $wpdb->get_var("SELECT post_terms FROM " . $wpdb->base_prefix . "site_posts WHERE post_id = '" . $tmp_post_ID . "' AND blog_id = '" . $wpdb->blogid . "'");
			//get sort terms
			$tmp_sort_terms = post_indexer_get_sort_terms($wpdb->blogid);
			//adjust term counts
			$tmp_post_terms = explode("|",$tmp_post_terms);
			$tmp_sort_terms = explode("|",$tmp_sort_terms);

			$tmp_term_array_items = array();
			$tmp_loop_count = 0;
			foreach ( $tmp_sort_terms as $tmp_sort_term ) {
				foreach ( $tmp_post_terms as $tmp_post_term ) {
					if ( $tmp_sort_term != '' && $tmp_post_term != '' ) {
						$tmp_loop_count = $tmp_loop_count + 1;
						$tmp_term_array_items[$tmp_loop_count]['type'] = $tmp_sort_term;
						$tmp_term_array_items[$tmp_loop_count]['id'] = $tmp_post_term;
					}
				}
			}
			if ( count( $tmp_term_array_items ) > 0 ) {
				$update_where = "WHERE ( ";
				$x = 1;
				foreach ($tmp_term_array_items as $tmp_term_array_item) {
				   if ( $x == 1 ) {
					   $update_where .= " (term_id = '" . $tmp_term_array_item['id'] ."' AND term_count_type = '" . $tmp_term_array_item['type'] ."')";
					  $x++; // only run this once
				   } else {
					   $update_where .= " OR (term_id = '" . $tmp_term_array_item['id'] ."' AND term_count_type = '" . $tmp_term_array_item['type'] ."')";
				   }
				}

			    $update_query = "UPDATE " . $wpdb->base_prefix . "term_counts SET term_count = term_count - 1,term_count_updated = '" . date("Y-m-d H:i:s") . "' ". $update_where . " )";
			    $wpdb->query($update_query);
			}
			// get the site_post_id
			$site_post_id = $wpdb->get_var( "SELECT site_post_id FROM " . $wpdb->base_prefix . "site_posts WHERE post_id = '" . $tmp_post_ID . "' AND blog_id = '" . $wpdb->blogid . "'");

			if(!empty($site_post_id)) {
				//delete site post
				$wpdb->query( "DELETE FROM " . $wpdb->base_prefix . "site_posts WHERE site_post_id = '" . $site_post_id . "'" );

				// delete from site terms table
				$wpdb->query( "DELETE FROM " . $wpdb->base_prefix . "site_term_relationships WHERE site_post_id = $site_post_id" );
				//$wpdb->query( "DELETE FROM " . $wpdb->base_prefix . "site_terms WHERE term_id NOT IN (SELECT term_id FROM " . $wpdb->base_prefix . "site_term_relationships)" );
			}

		}

		function post_indexer_delete_by_site_post_id($tmp_site_post_ID, $tmp_blog_ID) {
			global $wpdb;
			//get post terms
			$tmp_post_terms = $wpdb->get_var("SELECT post_terms FROM " . $wpdb->base_prefix . "site_posts WHERE site_post_id = '" . $tmp_site_post_ID . "'");
			//get sort terms
			$tmp_sort_terms = post_indexer_get_sort_terms($tmp_blog_ID);
			//adjust term counts
			$tmp_post_terms = explode("|",$tmp_post_terms);
			$tmp_sort_terms = explode("|",$tmp_sort_terms);

			$tmp_term_array_items = array();
			$tmp_loop_count = 0;
			foreach ( $tmp_sort_terms as $tmp_sort_term ) {
				foreach ( $tmp_post_terms as $tmp_post_term ) {
					if ( $tmp_sort_term != '' && $tmp_post_term != '' ) {
						$tmp_loop_count = $tmp_loop_count + 1;
						$tmp_term_array_items[$tmp_loop_count]['type'] = $tmp_sort_term;
						$tmp_term_array_items[$tmp_loop_count]['id'] = $tmp_post_term;
					}
				}
			}
			if ( count( $tmp_term_array_items ) > 0 ) {
				$update_where = "WHERE ( ";
				$x = 1;
				foreach ($tmp_term_array_items as $tmp_term_array_item) {
				   if ( $x == 1 ) {
					   $update_where .= " (term_id = '" . $tmp_term_array_item['id'] ."' AND term_count_type = '" . $tmp_term_array_item['type'] ."')";
					  $x++; // only run this once
				   } else {
					   $update_where .= " OR (term_id = '" . $tmp_term_array_item['id'] ."' AND term_count_type = '" . $tmp_term_array_item['type'] ."')";
				   }
				}

				$update_query = "UPDATE " . $wpdb->base_prefix . "term_counts SET term_count = term_count - 1,term_count_updated = '" . date("Y-m-d H:i:s") . "' ". $update_where . " )";
				$wpdb->query($update_query);
			}
			//delete site post
			$wpdb->query( "DELETE FROM " . $wpdb->base_prefix . "site_posts WHERE site_post_id = '" . $tmp_site_post_ID . "'" );

			// delete from site terms table
			$wpdb->query( "DELETE FROM " . $wpdb->base_prefix . "site_term_relationships WHERE site_post_id = $site_post_id" );
			$wpdb->query( "DELETE FROM " . $wpdb->base_prefix . "site_terms WHERE term_id NOT IN (SELECT term_id FROM " . $wpdb->base_prefix . "site_term_relationships)" );
		}

		function post_indexer_public_update(){
			global $wpdb;
			if ( $_GET['updated'] == 'true' ) {
				$wpdb->query("UPDATE " . $wpdb->base_prefix . "site_posts SET blog_public = '" . get_blog_status( $wpdb->blogid, 'public') . "' WHERE blog_id = '" . $wpdb->blogid . "' AND site_id = '" . $wpdb->siteid . "'");
			}
		}
		function post_indexer_sort_terms_update(){
			global $wpdb;
			$wpdb->query("UPDATE " . $wpdb->base_prefix . "site_posts SET sort_terms = '" . post_indexer_get_sort_terms($wpdb->blogid) . "' WHERE blog_id = '" . $wpdb->blogid . "' AND site_id = '" . $wpdb->siteid . "'");
		}

		function post_indexer_change_remove($tmp_blog_ID){
			global $wpdb, $current_user, $current_site;
			//delete site posts
			$query = "SELECT * FROM " . $wpdb->base_prefix . "site_posts WHERE blog_id = '" . $tmp_blog_ID . "' AND site_id = '" . $wpdb->siteid . "'";
			$blog_site_posts = $wpdb->get_results( $query, ARRAY_A );
			if (count($blog_site_posts) > 0){
				foreach ($blog_site_posts as $blog_site_post){
					post_indexer_delete_by_site_post_id($blog_site_post['site_post_id'], $tmp_blog_ID);
				}
			}
		}

		function post_indexer_hit_tracking($tmp_content){
			global $wp_query, $wpdb, $post_indexer_enable_popularity_tracking;
			$current_post = $wp_query->posts[$wp_query->current_post];
			if ($current_post->post_type == 'post') {
				//update hit count
			    $update_query = "UPDATE " . $wpdb->base_prefix . "site_posts SET post_hits = post_hits + 1 WHERE blog_id = '" . $wpdb->blogid . "' AND post_id = '" . $current_post->ID . "'";
				$wpdb->query($update_query);
				if ( $post_indexer_enable_popularity_tracking  == '1' ) {
					//get post added stamp
					$tmp_post_modified_stamp = $wpdb->get_var("SELECT post_modified_stamp FROM " . $wpdb->base_prefix . "site_posts WHERE blog_id = '" . $wpdb->blogid . "' AND post_id = '" . $current_post->ID . "'");
					$tmp_post_hits = $wpdb->get_var("SELECT post_hits FROM " . $wpdb->base_prefix . "site_posts WHERE blog_id = '" . $wpdb->blogid . "' AND post_id = '" . $current_post->ID . "'");
					if ( $tmp_post_modified_stamp != '' ) {
						//calculate popularity score
						$tmp_now_stamp = time();
						$tmp_time_stamp = $tmp_now_stamp - $tmp_post_modified_stamp;
						$tmp_day_count = $tmp_time_stamp / 86400;
						if ($tmp_day_count < 1) {
							$tmp_day_count = $tmp_day_count + 0.5;
						}
						$tmp_post_popularity_score = $tmp_post_hits / $tmp_day_count;
						$wpdb->query("UPDATE " . $wpdb->base_prefix . "site_posts SET post_popularity_score = '" . $tmp_post_popularity_score . "' WHERE blog_id = '" . $wpdb->blogid . "' AND post_id = '" . $current_post->ID . "'");
					}
				}
			}
			return $tmp_content;
		}

		//------------------------------------------------------------------------//
		//---Output Functions-----------------------------------------------------//
		//------------------------------------------------------------------------//

		//------------------------------------------------------------------------//
		//---Page Output Functions------------------------------------------------//
		//------------------------------------------------------------------------//

		//------------------------------------------------------------------------//
		//---Support Functions----------------------------------------------------//
		//------------------------------------------------------------------------//

		function post_indexer_strip_content($content){
			$content = strip_tags($content);
			$content = apply_filters( 'post_indexer_strip_content_filter', $content );
			return $content;
		}

		function blogs_for_rebuilding() {
			$sql = $sql = $this->db->prepare( "SELECT count(*) as rebuildblogs FROM {$this->network_rebuildqueue}" );

			$var = $this->db->get_var( $sql );

			if(empty($var) || $var == 0) {
				return false;
			} else {
				return true;
			}
		}

		function rebuild_blog( $blog_id ) {

			$this->insert_or_update( $this->network_rebuildqueue, array( 'blog_id' => $blog_id, 'rebuild_startdate' => current_time('mysql'), 'rebuild_progress' => 0 ) );

		}

		function rebuild_all_blogs( $blog_id ) {

			$sql = $this->db->prepare( "TRUNCATE TABLE {$this->network_rebuildqueue}");
			$this->db->query( $sql );

			$sql = $this->db->prepare( "INSERT INTO {$this->network_rebuildqueue} SELECT blog_id, timestamp(now()), 0 FROM {$this->db->blogs}");
			$this->db->query( $sql );

		}

		function is_in_rebuild_queue( $blog_id ) {

			$sql = $this->db->prepare( "SELECT * FROM {$this->network_rebuildqueue} WHERE blog_id = %d", $blog_id );

			$row = $this->db->get_row( $sql );

			if( !empty($row) && $row->blog_id == $blog_id ) {
				return true;
			} else {
				return false;
			}

		}

		// Insert on duplicate update function
		function insert_or_update( $table, $query ) {

				$fields = array_keys($query);
				$formatted_fields = array();
				foreach ( $fields as $field ) {
					$form = '%s';
					$formatted_fields[] = $form;
				}
				$sql = "INSERT INTO `$table` (`" . implode( '`,`', $fields ) . "`) VALUES ('" . implode( "','", $formatted_fields ) . "')";
				$sql .= " ON DUPLICATE KEY UPDATE ";

				$dup = array();
				foreach($fields as $field) {
					$dup[] = "`" . $field . "` = VALUES(`" . $field . "`)";
				}

				$sql .= implode(',', $dup);

				return $this->db->query( $this->db->prepare( $sql, $query ) );

		}


	}

}

$postindexeradmin = new postindexeradmin();
?>