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

					if( $this->model->is_blog_indexable( $item->blog_id ) ) {

						// Get the highest post_id
						$max_id = $this->model->get_highest_post_for_blog( $item->blog_id );
						if(!empty($max_id) && $max_id > 0) {
							// We have posts - record the highest current post id
							$this->model->update_blog_queue( $item->blog_id, $max );
						} else {
							// No posts, so we'll remove it from the queue
							$this->model->remove_blog_from_queue( $item->blog_id );
						}
						// Remove existing posts because we are going to rebuild
						$this->model->remove_indexed_entries_for_blog( $item->blog_id );

					} else {
						// Remove the blog from the queue
						$this->model->remove_blog_from_queue( $item->blog_id );
					}

				}
			}

		}

		function process_rebuild_secondpass() {

			// Second pass - loop through queue entries with a on 0 in the rebuild_progress and start rebuilding
			$queue = $this->model->get_rebuilding_blogs();

			foreach( $queue as $item ) {

				if( $this->model->is_blog_indexable( $item->blog_id ) ) {
					// Swtich to the blog so we don't have to keep doing it
					$this->model->switch_to_blog( $item->blog_id );

					$posts = $this->model->get_posts_for_indexing( $item->blog_id, false );
					if(!empty($posts)) {
						foreach($posts as $key => $post) {
							// Check if the post should be indexed or not
							if($this->model->is_post_indexable( $post['ID'], $item->blog_id ) ) {

								// Get the local post ID
								$local_id = $post['ID'];
								// Add in the blog id to the post record
								$post['BLOG_ID'] = $item->blog_id;

								// Add the post record to the network tables
								$this->model->index_post( $post );

								// Get the post meta for this local post
								$meta = $this->model->get_postmeta_for_indexing( $local_id, false );
								if(!empty($meta)) {
									foreach( $meta as $metakey => $postmeta ) {
										// Add in the blog_id to the table
										$postmeta['blog_id'] = $item->blog_id;
										// Add it to the network tables
										$this->model->index_postmeta( $postmeta );
									}
								}

								// Get the taxonomy for this local post
								$taxonomy = $this->model->get_taxonomy_for_indexing( $local_id, false );
								if(!empty($tax)) {
									foreach( $taxonomy as $taxkey => $tax ) {

									}
								}

							}

							// Update the rebuild queue with the next post to be processed
							$previous_id = (int) ($local_id - 1);
							if($previous_id > 0) {
								// We may still have posts to process
								$this->model->update_blog_queue( $item->blog_id, $previous_id );
							} else {
								// We've run out of posts now so remove us from the queue
								$this->model->remove_blog_from_queue( $item->blog_id );
							}

						}
					} else {
						// We've run out of posts so remove our entry from the queue
						$this->model->remove_blog_from_queue( $item->blog_id );
					}

					// Switch back from the blog
					$this->model->restore_current_blog();

				} else {
					// Remove the blog from the queue as something has changed
					$this->model->remove_blog_from_queue( $item->blog_id );
					// Remove any existing posts in case we've already indexed them
					$this->model->remove_indexed_entries_for_blog( $item->blog_id );
				}

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