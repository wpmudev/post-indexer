<?php
/*
Plugin Name: Post Indexer
Plugin URI:
Description:
Author: Andrew Billits (Incsub)
Version: 2.1
Author URI:
WDP ID: 30
Network: true
*/

/*
Copyright 2007-2009 Incsub (http://incsub.com)

This program is free software; you can redistribute it and/or modify
it under the terms of the GNU General Public License (Version 2 - GPLv2) as published by
the Free Software Foundation.

This program is distributed in the hope that it will be useful,
but WITHOUT ANY WARRANTY; without even the implied warranty of
MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
GNU General Public License for more details.

You should have received a copy of the GNU General Public License
along with this program; if not, write to the Free Software
Foundation, Inc., 59 Temple Place, Suite 330, Boston, MA  02111-1307  USA
*/

$post_indexer_current_version = '2.1';
//------------------------------------------------------------------------//
//---Config---------------------------------------------------------------//
//------------------------------------------------------------------------//
$post_indexer_enable_hit_tracking = '0'; //Either '1' or '0' - resource heavy
$post_indexer_enable_popularity_tracking = '0'; //Either '1' or '0' - resource heavy
$post_indexer_tags_display_days = '90'; //number of days posts are calculated into tag clouds and shown in tag listings.
//------------------------------------------------------------------------//
//---Hook-----------------------------------------------------------------//
//------------------------------------------------------------------------//
//check for activating
if ($_GET['key'] == '' || $_GET['key'] === ''){
	add_action('admin_head', 'post_indexer_make_current');
}
//index posts
add_action('save_post', 'post_indexer_post_insert_update');
add_action('delete_post', 'post_indexer_delete');
//handle blog changes
add_action('make_spam_blog', 'post_indexer_change_remove');
add_action('archive_blog', 'post_indexer_change_remove');
add_action('mature_blog', 'post_indexer_change_remove');
add_action('deactivate_blog', 'post_indexer_change_remove');
add_action('blog_privacy_selector', 'post_indexer_public_update');
add_action('delete_blog', 'post_indexer_change_remove', 10, 1);
//update blog types
add_action('blog_types_update', 'post_indexer_sort_terms_update');
if ($post_indexer_enable_hit_tracking == '1') {
	add_filter('the_content', 'post_indexer_hit_tracking');
	add_filter('the_excerpt', 'post_indexer_hit_tracking');
}
//------------------------------------------------------------------------//
//---Functions------------------------------------------------------------//
//------------------------------------------------------------------------//
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

?>
