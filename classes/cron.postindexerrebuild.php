<?php

if(!class_exists('postindexercron')) {

	class postindexercron {

		var $build = 1;

		var $rebuildperiod = '5mins';

		// The post indexer model
		var $model;

		function postindexercron() {
			$this->__construct();
		}

		function __construct() {

			$this->model = new postindexermodel();

			add_action( 'init', array(&$this, 'set_up_schedule') );
			//add_action( 'autoblog_process_feeds', array(&$this, 'always_process_autoblog') );
			add_filter( 'cron_schedules', array(&$this, 'add_time_period') );

			// The cron action s
			add_action( 'postindexer_firstpass_cron', array( &$this, 'process_rebuild_firstpass') );
			add_action( 'postindexer_secondpass_cron', array( &$this, 'process_rebuild_secondpass') );

			add_action( 'postindexer_tagtidy_cron', array( &$this, 'process_tidy_tags') );
			add_action( 'postindexer_postmetatidy_cron', array( &$this, 'process_tidy_postmeta') );

		}

		function process_rebuild_firstpass() {

			// First pass - loop through queue entries with a 0 in the rebuild_progress and set them up for the rebuild process
			$queue = $this->model->get_justqueued_blogs();

			if(!empty( $queue )) {
				foreach($queue as $item) {
					// Switch to the blog so we can get information
					switch_to_blog( $item->blog_id );

					$indexing = get_option( 'postindexer_active', 'yes' );
					if($indexing == 'yes') {
						// Get the highest post_id
						$max_id = $this->db->get_var( $this->db->prepare( "SELECT MAX(ID) as max_id FROM {$this->db->posts}" ) );
						if(!empty($max_id) && $max_id > 0) {
							// We have posts - record the highest current post id
							$this->db->update( $this->network_rebuildqueue, array('rebuild_progress' => $max_id, 'rebuild_updatedate' => current_time('mysql')), array('blog_id' => $item->blog_id) );
						} else {
							// No posts, so we'll remove it from the queue
							$this->db->query( $this->db->prepare( "DELETE FROM {$this->network_rebuildqueue} WHERE blog_id = %d", $item->blog_id ) );
						}
						// Remove existing posts because we are going to rebuild
						$this->model->remove_indexed_entries_for_blog( $item->blog_id );
					} else {
						// Remove the blog from the queue
						$this->db->query( $this->db->prepare( "DELETE FROM {$this->network_rebuildqueue} WHERE blog_id = %d", $item->blog_id ) );
					}

					// Go back to the original blog as we are done with this one
					restore_current_blog();
				}
			}

		}

		function process_rebuild_secondpass() {

			// Second pass - loop through queue entries with a on 0 in the rebuild_progress and start rebuilding
			$queue = $this->model->get_rebuilding_blogs();

			foreach( $queue as $item ) {
				// Switch to the blog so we can get information
				switch_to_blog( $item->blog_id );

				$posttypes = get_option( 'postindexer_posttypes', array( 'post' ) );
				// Get the first five posts to work through
				$sql = $this->db->prepare( "SELECT * FROM {$this->db->posts} WHERE ID <= %d AND post_status = 'publish' AND post_type IN ('" . implode("','", $posttypes) . "') ORDER BY ID DESC LIMIT 5", $item->rebuild_progress );
				$posts = $this->db->get_results( $sql, ARRAY_A );

				if(!empty($posts)) {
					foreach($posts as $key => $post) {
						// Get the local post ID
						$local_id = $post['ID'];
						// Add in the blog id to the post record
						$post['BLOG_ID'] = $item->blog_id;
						// Add the post record to the network tables
						$this->model->insert_or_update( $this->network_posts, $post );

						// Get the post meta for this local post
						$metasql = $this->db->prepare( "SELECT * FROM {$this->db->postmeta} WHERE post_id = %d AND meta_key NOT IN ('_edit_last', '_edit_lock')", $local_id );
						$meta = $this->db->get_results( $metasql, ARRAY_A );

						if(!empty($meta)) {
							foreach($meta as $metakey => $postmeta) {
								// Add in the blog_id to the table
								$postmeta['blog_id'] = $item->blog_id;
								// Add it to the network tables
								$this->model->insert_or_update( $this->network_postmeta, $postmeta );
							}
						}

						// Get the taxonomy for this local post

						// Update the rebuild queue with the next post to be processed
						$previous_id = (int) ($local_id - 1);
						if($previous_id > 0) {
							// We may still have posts to process
							$this->model->rebuild_blog( $item->blog_id, $previous_id );
						} else {
							// We've run out of posts now so remove us from the queue
							$this->model->remove_blog_from_queue( $item->blog_id );
						}

					}
				} else {
					// We've run out of posts so remove our entry from the queue
					$this->model->remove_blog_from_queue( $item->blog_id );
				}

				// Go back to the original blog as we are done with this one
				restore_current_blog();
			}


		}

		function process_tidy_tags() {

			// Hourly tidy up of tags and tag counts

		}

		function process_tidy_postmeta() {

			// Hourly tidy up of postmeta entries

		}

		function add_time_period( $periods ) {

			if(!is_array($periods)) {
				$periods = array();
			}

			$periods['10mins'] = array( 'interval' => 600, 'display' => __('Every 10 Mins', 'postindexer') );
			$periods['5mins'] = array( 'interval' => 300, 'display' => __('Every 5 Mins', 'postindexer') );

			return $periods;
		}

		function set_up_schedule() {

			if ( !wp_next_scheduled( 'postindexer_firstpass_cron' ) ) {
					wp_schedule_event(time(), $this->rebuildperiod, 'postindexer_firstpass_cron');
			}

			if ( !wp_next_scheduled( 'postindexer_secondpass_cron' ) ) {
					wp_schedule_event(time(), $this->rebuildperiod, 'postindexer_secondpass_cron');
			}

			if ( !wp_next_scheduled( 'postindexer_tagtidy_cron' ) ) {
					wp_schedule_event(time(), 'hourly', 'postindexer_tagtidy_cron');
			}

			if ( !wp_next_scheduled( 'postindexer_postmetatidy_cron' ) ) {
					wp_schedule_event(time(), 'hourly', 'postindexer_postmetatidy_cron');
			}

		}

	}

}

$postindexercron = new postindexercron();

?>