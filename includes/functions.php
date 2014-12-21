<?php
// Common post indexer functions - primarily for use in associated plugins

/**
 * Network functions based on existing WordPress functions but tweaked where required for network use
 */

function network_the_title($before = '', $after = '', $echo = true) {
	$title = network_get_the_title();

	if ( strlen($title) == 0 )
		return;

	$title = $before . $title . $after;

	if ( $echo )
		echo $title;
	else
		return $title;
}

function network_the_title_attribute( $args = '' ) {
	$title = network_get_the_title();

	if ( strlen($title) == 0 )
		return;

	$defaults = array('before' => '', 'after' =>  '', 'echo' => true);
	$r = wp_parse_args($args, $defaults);
	extract( $r, EXTR_SKIP );

	$title = $before . $title . $after;
	$title = esc_attr(strip_tags($title));

	if ( $echo )
		echo $title;
	else
		return $title;
}

function network_get_the_title( $blog_id = 0, $id = 0 ) {

	$network_post = &network_get_post( $blog_id, $id );

	$title = isset($network_post->post_title) ? $network_post->post_title : '';
	$id = isset($network_post->ID) ? $network_post->ID : (int) $id;

	return apply_filters( 'network_the_title', $title, $id );
}

function network_get_the_title_rss() {
	$title = network_get_the_title();
	$title = apply_filters('network_the_title_rss', $title);
	return $title;
}

function network_the_title_rss() {
	echo network_get_the_title_rss();
}

function network_get_the_content_feed($feed_type = null) {
	if ( !$feed_type )
		$feed_type = get_default_feed();

	$content = apply_filters('network_the_content', network_get_the_content());
	$content = str_replace(']]>', ']]&gt;', $content);
	return apply_filters('network_the_content_feed', $content, $feed_type);
}

function network_the_content_feed($feed_type = null) {
	echo network_get_the_content_feed($feed_type);
}

function network_the_excerpt_rss() {
	$output = network_get_the_excerpt();
	echo apply_filters('network_the_excerpt_rss', $output);
}

function network_get_permalink( $blog_id = 0, $id = 0 ) {

	$post = &network_get_post( $blog_id, $id );

	if(!empty($post)) {
		switch_to_blog( $post->BLOG_ID );
		$permalink = get_permalink( $post->ID );
		restore_current_blog();

		return $permalink;
	}
}

function network_the_permalink_rss() {
	echo esc_url( apply_filters('network_the_permalink_rss', network_get_permalink() ));
}

function network_comments_link_feed() {
	echo esc_url( network_get_comments_link() );
}

function network_get_comments_link() {
	return network_get_permalink() . '#comments';
}

function network_get_post_comments_feed_link( $blog_id = 0, $id = 0 ) {

	$post = &network_get_post( $blog_id, $id );

	if(!empty($post)) {
		switch_to_blog( $post->BLOG_ID );
		$feedlink = get_post_comments_feed_link( $post->ID );
		restore_current_blog();

		return $feedlink;
	}

}

function network_get_comments_number( $blog_id = 0, $id = 0 ) {

	$post = &network_get_post( $blog_id, $id );

	if(!empty($post)) {
		switch_to_blog( $post->BLOG_ID );
		$number = get_comments_number( $post->ID );
		restore_current_blog();

		return $number;
	}

}

function network_get_object_terms( $blog_id, $object_ids, $taxonomies, $args = array()) {
	global $wpdb;

	if ( empty( $object_ids ) || empty( $taxonomies ) )
		return array();

	if ( !is_array($taxonomies) )
		$taxonomies = array($taxonomies);

	if ( !is_array($object_ids) )
		$object_ids = array($object_ids);

	$object_ids = array_map('intval', $object_ids);

	$defaults = array('orderby' => 'name', 'order' => 'ASC', 'fields' => 'all');
	$args = wp_parse_args( $args, $defaults );

	extract($args, EXTR_SKIP);

	if ( 'count' == $orderby )
		$orderby = 'tt.count';
	else if ( 'name' == $orderby )
		$orderby = 't.name';
	else if ( 'slug' == $orderby )
		$orderby = 't.slug';
	else if ( 'term_group' == $orderby )
		$orderby = 't.term_group';
	else if ( 'term_order' == $orderby )
		$orderby = 'tr.term_order';
	else if ( 'none' == $orderby ) {
		$orderby = '';
		$order = '';
	} else {
		$orderby = 't.term_id';
	}

	// tt_ids queries can only be none or tr.term_taxonomy_id
	if ( ('tt_ids' == $fields) && !empty($orderby) )
		$orderby = 'tr.term_taxonomy_id';

	if ( !empty($orderby) )
		$orderby = "ORDER BY $orderby";

	$order = strtoupper( $order );
	if ( '' !== $order && ! in_array( $order, array( 'ASC', 'DESC' ) ) )
		$order = 'ASC';

	$taxonomies = "'" . implode("', '", $taxonomies) . "'";
	$object_ids = implode(', ', $object_ids);

	$select_this = '';
	if ( 'all' == $fields )
		$select_this = 't.*, tt.*';
	else if ( 'ids' == $fields )
		$select_this = 't.term_id';
	else if ( 'names' == $fields )
		$select_this = 't.name';
	else if ( 'slugs' == $fields )
		$select_this = 't.slug';
	else if ( 'all_with_object_id' == $fields )
		$select_this = 't.*, tt.*, tr.object_id';

	$query = "SELECT $select_this FROM {$wpdb->base_prefix}network_terms AS t INNER JOIN {$wpdb->base_prefix}network_term_taxonomy AS tt ON tt.term_id = t.term_id INNER JOIN {$wpdb->base_prefix}network_term_relationships AS tr ON tr.term_taxonomy_id = tt.term_taxonomy_id WHERE tr.blog_id = {$blog_id} AND tt.taxonomy IN ($taxonomies) AND tr.object_id IN ($object_ids) $orderby $order";

	if ( 'all' == $fields || 'all_with_object_id' == $fields ) {
		$terms = $wpdb->get_results($query);
	} else if ( 'ids' == $fields || 'names' == $fields || 'slugs' == $fields ) {
		$terms = $wpdb->get_col($query);
	} else if ( 'tt_ids' == $fields ) {
		$terms = $wpdb->get_col("SELECT tr.term_taxonomy_id FROM {$wpdb->base_prefix}network_term_relationships AS tr INNER JOIN {$wpdb->base_prefix}term_taxonomy AS tt ON tr.term_taxonomy_id = tt.term_taxonomy_id WHERE  tr.blog_id = {$blog_id} AND tr.object_id IN ($object_ids) AND tt.taxonomy IN ($taxonomies) $orderby $order");
	}

	if ( ! $terms )
		$terms = array();

	return apply_filters('network_get_object_terms', $terms, $object_ids, $taxonomies, $args);
}

function network_term_is_tag( $tag ) {

	$model = new postindexermodel();

	return $model->term_is_tag( $tag );
}

function network_term_is_category( $cat ) {

	$model = new postindexermodel();

	return $model->term_is_category( $tag );

}

function network_get_the_terms( $blog_id, $id, $taxonomy ) {

	$post = &network_get_post( (int) $blog_id, (int) $id);

	$terms = network_get_object_terms( $post->BLOG_ID, $post->ID, $taxonomy );

	$terms = apply_filters( 'network_get_the_terms', $terms, $id, $taxonomy );

	if ( empty( $terms ) )
		return false;

	return $terms;
}

function network_get_the_category( $blog_id = 0, $id = 0 ) {

	$categories = network_get_the_terms( $blog_id, $id, 'category' );
	if ( ! $categories )
		$categories = array();

	$categories = array_values( $categories );

	foreach ( array_keys( $categories ) as $key ) {
		_make_cat_compat( $categories[$key] );
	}

	// Filter name is plural because we return alot of categories (possibly more than #13237) not just one
	return apply_filters( 'network_get_the_categories', $categories );
}

function network_get_the_tags( $blog_id = 0, $id = 0 ) {
	return apply_filters( 'network_get_the_tags', network_get_the_terms( $blog_id, $id, 'post_tag' ) );
}

function network_get_the_category_rss($type = null) {
	if ( empty($type) )
		$type = get_default_feed();

	$categories = network_get_the_category();
	$tags = network_get_the_tags();

	$the_list = '';
	$cat_names = array();

	$filter = 'rss';
	if ( 'atom' == $type )
		$filter = 'raw';

	if ( !empty($categories) ) foreach ( (array) $categories as $category ) {
		$cat_names[] = sanitize_term_field('name', $category->name, $category->term_id, 'category', $filter);
	}

	if ( !empty($tags) ) foreach ( (array) $tags as $tag ) {
		$cat_names[] = sanitize_term_field('name', $tag->name, $tag->term_id, 'post_tag', $filter);
	}

	$cat_names = array_unique($cat_names);

	foreach ( $cat_names as $cat_name ) {
		if ( 'rdf' == $type )
			$the_list .= "\t\t<dc:subject><![CDATA[$cat_name]]></dc:subject>\n";
		elseif ( 'atom' == $type )
			$the_list .= sprintf( '<category scheme="%1$s" term="%2$s" />', esc_attr( apply_filters( 'get_bloginfo_rss', get_bloginfo( 'url' ) ) ), esc_attr( $cat_name ) );
		else
			$the_list .= "\t\t<category><![CDATA[" . @html_entity_decode( $cat_name, ENT_COMPAT, get_option('blog_charset') ) . "]]></category>\n";
	}

	return apply_filters('network_the_category_rss', $the_list, $type);
}

function network_the_category_rss($type = null) {
	echo network_get_the_category_rss($type);
}

/* TODO */
function network_rss_enclosure() {

	foreach ( (array) network_get_post_custom() as $key => $val) {
		if ($key == 'enclosure') {
			foreach ( (array) $val as $enc ) {
				$enclosure = explode("\n", $enc);

				//only get the the first element eg, audio/mpeg from 'audio/mpeg mpga mp2 mp3'
				$t = preg_split('/[ \t]/', trim($enclosure[2]) );
				$type = $t[0];

				echo apply_filters('rss_enclosure', '<enclosure url="' . trim(htmlspecialchars($enclosure[0])) . '" length="' . trim($enclosure[1]) . '" type="' . $type . '" />' . "\n");
			}
		}
	}
}

/* TODO */
function network_atom_enclosure() {

	foreach ( (array) network_get_post_custom() as $key => $val ) {
		if ($key == 'enclosure') {
			foreach ( (array) $val as $enc ) {
				$enclosure = explode("\n", $enc);
				echo apply_filters('atom_enclosure', '<link href="' . trim(htmlspecialchars($enclosure[0])) . '" rel="enclosure" length="' . trim($enclosure[1]) . '" type="' . trim($enclosure[2]) . '" />' . "\n");
			}
		}
	}
}

/* TODO */
function network_get_post_custom( $blog_id = 0, $post_id = 0 ) {
	$post_id = absint( $post_id );
	if ( ! $post_id )
		$post_id = network_get_the_ID();

	return get_post_meta( $post_id );
}

function network_the_ID() {
	echo network_get_the_ID();
}

function network_get_the_ID() {
	global $network_post;

	return $network_post->ID;
}

function network_the_guid( $blog_id = 0, $id = 0 ) {
	echo esc_url( network_get_the_guid( $blog_id, $id ) );
}

function network_get_the_guid( $blog_id = 0, $id = 0 ) {

	$post = &network_get_post($blog_id, $id);

	return apply_filters('network_get_the_guid', $post->guid);
}

function network_the_content($more_link_text = null, $stripteaser = false) {
	$content = network_get_the_content($more_link_text, $stripteaser);
	$content = apply_filters('network_the_content', $content);
	$content = str_replace(']]>', ']]&gt;', $content);
	echo $content;
}

function network_get_the_content($more_link_text = null, $stripteaser = false) {
	global $network_post, $more, $page, $pages, $multipage, $preview;

	if ( null === $more_link_text )
		$more_link_text = __( '(more...)' );

	$output = '';
	$hasTeaser = false;

	if ( $page > count($pages) ) // if the requested page doesn't exist
		$page = count($pages); // give them the highest numbered page that DOES exist

	$content = $pages[$page-1];
	if ( preg_match('/<!--more(.*?)?-->/', $content, $matches) ) {
		$content = explode($matches[0], $content, 2);
		if ( !empty($matches[1]) && !empty($more_link_text) )
			$more_link_text = strip_tags(wp_kses_no_null(trim($matches[1])));

		$hasTeaser = true;
	} else {
		$content = array($content);
	}
	if ( (false !== strpos($network_post->post_content, '<!--noteaser-->') && ((!$multipage) || ($page==1))) )
		$stripteaser = true;
	$teaser = $content[0];
	if ( $more && $stripteaser && $hasTeaser )
		$teaser = '';
	$output .= $teaser;
	if ( count($content) > 1 ) {
		if ( $more ) {
			$output .= '<span id="more-' . $post->ID . '"></span>' . $content[1];
		} else {
			if ( ! empty($more_link_text) ) {
				$output .= apply_filters( 'network_the_content_more_link', ' <a href="' . network_get_permalink( $network_post->BLOG_ID, $network_post->ID ) . "#more-{$network_post->ID}\" class=\"more-link\">$more_link_text</a>", $more_link_text );
			}
			$output = force_balance_tags($output);
		}

	}
	if ( $preview ) // preview fix for javascript bug with foreign languages
		$output =	preg_replace_callback('/\%u([0-9A-F]{4})/', '_convert_urlencoded_to_entities', $output);

	return $output;
}

function network_the_excerpt() {
	echo apply_filters('network_the_excerpt', network_get_the_excerpt());
}

function network_get_the_excerpt() {
	global $network_post, $post;

	$output = $network_post->post_excerpt;

	// back up post as we need it later
	$oldpost = $post;
	// set the post to our network post
	$post = $network_post;
	// get the excerpt
	$excerpt = apply_filters('get_the_excerpt', $output);
	// reset the post variable in case it's needed elsewhere
	$post = $oldpost;
	// return the excerpt
	return $excerpt;
}

function network_get_post_time( $d = 'U', $gmt = false, $post = null, $translate = false ) { // returns timestamp
	global $network_post;

	$post = &network_get_post( $network_post->BLOG_ID, $network_post->ID );

	if ( $gmt )
		$time = $post->post_date_gmt;
	else
		$time = $post->post_date;

	$time = mysql2date($d, $time, $translate);
	return apply_filters('network_get_post_time', $time, $d, $gmt);
}

function network_get_the_author() {

	global $wpdb;

	$post = &network_get_post();

	if(!empty($post)) {
		$author_id = $post->post_author;

		$sql = $wpdb->prepare( "SELECT * FROM {$wpdb->users} WHERE ID = %d", $author_id );
		$author = $wpdb->get_row( $sql );

		if(is_object($author)) {
			return $author->display_name;
		} else {
			return false;
		}
	}

	return apply_filters('network_the_author', is_object($authordata) ? $authordata->display_name : null);
}

function network_get_the_author_id() {

	global $wpdb;

	$post = &network_get_post();

	if(!empty($post)) {
		$author_id = $post->post_author;

		if(!empty($author_id)) {
			return $author_id;
		} else {
			return false;
		}
	}

	return apply_filters('network_the_author', is_object($authordata) ? $authordata->display_name : null);
}

function network_the_author() {
	echo network_get_the_author();
}

function &network_get_post( $blog_id = 0, $network_post_id = 0, $output = OBJECT, $filter = 'raw') {
	global $wpdb, $network_post;

	$blog_id = (int) $blog_id;
	$network_post_id = (int) $network_post_id;

	if($blog_id == 0 && $network_post_id == 0 && is_object( $network_post )) {
		$blog_id = $network_post->BLOG_ID;
		$network_post_id = $network_post->ID;
	}

	if( is_object($network_post) && $network_post->BLOG_ID == $blog_id && $network_post->ID == $network_post_id ) {
		$_network_post = $network_post;
	} else {
		$model = new postindexermodel();

		$_network_post = $model->get_post( $blog_id, $network_post_id );
		$_network_post = sanitize_post($_network_post, 'raw');
	}

	if ($filter != 'raw') {
		$_network_post = sanitize_post($_network_post, $filter);
	}

	if ( $output == OBJECT ) {
		return $_network_post;
	} elseif ( $output == ARRAY_A ) {
		$_network_post = get_object_vars($_network_post);
		return $_network_post;
	} elseif ( $output == ARRAY_N ) {
		$_network_post = array_values(get_object_vars($_network_post));
		return $_network_post;
	} else {
		return $_network_post;
	}


}

function network_get_lastpostmodified( $timezone = 'server', $post_types = 'post'  ) {

	global $wpdb;

	$add_seconds_server = date('Z');

	if(!is_array($post_types)) {
		$post_types = array( $post_types );
	}

	$post_types = "'" . implode( "', '", $post_types ) . "'";

	switch ( strtolower($timezone) ) {
		case 'gmt':
			$date = $wpdb->get_var("SELECT post_modified_gmt FROM {$wpdb->base_prefix}network_posts WHERE post_status = 'publish' AND post_type IN ({$post_types}) ORDER BY post_modified_gmt DESC LIMIT 1");
			break;
		case 'blog':
			$date = $wpdb->get_var("SELECT post_modified FROM {$wpdb->base_prefix}network_posts WHERE post_status = 'publish' AND post_type IN ({$post_types}) ORDER BY post_modified_gmt DESC LIMIT 1");
			break;
		case 'server':
			$date = $wpdb->get_var("SELECT DATE_ADD(post_modified_gmt, INTERVAL '$add_seconds_server' SECOND) FROM {$wpdb->base_prefix}network_posts WHERE post_status = 'publish' AND post_type IN ({$post_types}) ORDER BY post_modified_gmt DESC LIMIT 1");
			break;
	}

	return $date;

}