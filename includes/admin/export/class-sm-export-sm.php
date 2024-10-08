<?php
/**
 * Sermon Manager data export.
 *
 * @package SM/Core/Admin/Importing
 */

defined( 'ABSPATH' ) or die;

/**
 * Export data from Sermon Manager
 *
 * @since 2.12.0
 */
class SM_Export_SM {
	/**
	 * Do the export.
	 *
	 * @param array $args The parameters.
	 */
	function sermon_export_wp( $args = array() ) {
		global $wpdb, $post;

		require_once( ABSPATH . 'wp-admin/includes/export.php' );

		$defaults = array(
			'content'    => 'wpfc_sermon',
			'author'     => false,
			'category'   => false,
			'start_date' => false,
			'end_date'   => false,
			'status'     => false,
		);
		$args     = wp_parse_args( $args, $defaults );

		$sitename = sanitize_key( get_bloginfo( 'name' ) );
		if ( ! empty( $sitename ) ) {
			$sitename .= '.';
		}
		$filename = $sitename . 'wordpress.' . date( 'Y-m-d' ) . '.xml';

		header( 'Content-Description: File Transfer' );
		header( 'Content-Disposition: attachment; filename=' . $filename );
		header( 'Content-Type: text/xml; charset=' . get_option( 'blog_charset' ), true );

		if ( post_type_exists( $args['content'] ) ) {
			$ptype = get_post_type_object( $args['content'] );
			if ( ! $ptype->can_export ) {
				return;
			}

			$where = $wpdb->prepare( "{$wpdb->posts}.post_type = %s", $args['content'] );
		} else {
			return;
		}

		$where .= " AND {$wpdb->posts}.post_status != 'auto-draft'";

		$join = '';

		// Grab a snapshot of post IDs, just in case it changes during the export.
		$post_ids         = apply_filters( 'export_post_ids', $wpdb->get_col( "SELECT ID FROM {$wpdb->posts} $join WHERE $where" ), $args );
		$post_type_export = apply_filters( 'export_post_type', 'wpfc_sermon', $args );

		// Get the requested terms ready, empty unless posts filtered by category or all content.
		$cats              = array();
		$tags              = array();
		$terms             = array();
		$custom_taxonomies = array(
			'wpfc_preacher',
			'wpfc_sermon_series',
			'wpfc_sermon_topics',
			'wpfc_bible_book',
			'wpfc_service_type',
		);
		$custom_terms      = (array) get_terms( $custom_taxonomies, array( 'get' => 'all' ) );

		// put terms in order with no child going before its parent.
		while ( $t = array_shift( $custom_terms ) ) {
			if ( 0 == $t->parent || isset( $terms[ $t->parent ] ) ) {
				$terms[ $t->term_id ] = $t;
			} else {
				$custom_terms[] = $t;
			}
		}

		unset( $categories, $custom_taxonomies, $custom_terms );

		/**
		 * Wrap given string in XML CDATA tag.
		 *
		 * @since 2.1.0
		 *
		 * @param string $str String to wrap in XML CDATA tag.
		 *
		 * @return string
		 */
		function wxr_cdata( $str ) {
			if ( seems_utf8( $str ) == false ) {
				$str = utf8_encode( $str );
			}

			$str = '<![CDATA[' . str_replace( ']]>', ']]]]><![CDATA[>', $str ) . ']]>';

			return $str;
		}

		/**
		 * Return the URL of the site
		 *
		 * @since 2.5.0
		 *
		 * @return string Site URL.
		 */
		function wxr_site_url() {
			// ms: the base url.
			if ( is_multisite() ) {
				return network_home_url();
			} // wp: the blog url.
			else {
				return get_bloginfo_rss( 'url' );
			}
		}

		/**
		 * Output list of taxonomy terms, in XML tag format, associated with a post
		 *
		 * @since 2.3.0
		 *
		 * @param int $post_id The post ID.
		 */
		function wxr_post_taxonomy( $post_id ) {
			$custom_taxonomies = array(
				'wpfc_preacher',
				'wpfc_sermon_series',
				'wpfc_sermon_topics',
				'wpfc_bible_book',
				'wpfc_service_type',
			);
			$terms             = wp_get_object_terms( $post_id, $custom_taxonomies );

			foreach ( (array) $terms as $term ) {
				echo "\t\t<category domain=\"{$term->taxonomy}\" nicename=\"{$term->slug}\">" . wxr_cdata( $term->name ) . "</category>\n";
			}
		}

		/**
		 * Output a cat_name XML tag from a given category object
		 *
		 * @since 2.1.0
		 *
		 * @param object $category Category Object.
		 */
		function wxr_cat_name( $category ) {
			if ( empty( $category->name ) ) {
				return;
			}

			echo '<wp:cat_name>' . wxr_cdata( $category->name ) . '</wp:cat_name>';
		}

		/**
		 * Output a category_description XML tag from a given category object
		 *
		 * @since 2.1.0
		 *
		 * @param object $category Category Object.
		 */
		function wxr_category_description( $category ) {
			if ( empty( $category->description ) ) {
				return;
			}

			echo '<wp:category_description>' . wxr_cdata( $category->description ) . '</wp:category_description>';
		}

		/**
		 * Output a tag_name XML tag from a given tag object
		 *
		 * @since 2.3.0
		 *
		 * @param object $tag Tag Object.
		 */
		function wxr_tag_name( $tag ) {
			if ( empty( $tag->name ) ) {
				return;
			}

			echo '<wp:tag_name>' . wxr_cdata( $tag->name ) . '</wp:tag_name>';
		}

		/**
		 * Output a tag_description XML tag from a given tag object
		 *
		 * @since 2.3.0
		 *
		 * @param object $tag Tag Object.
		 */
		function wxr_tag_description( $tag ) {
			if ( empty( $tag->description ) ) {
				return;
			}

			echo '<wp:tag_description>' . wxr_cdata( $tag->description ) . '</wp:tag_description>';
		}

		/**
		 * Output a term_name XML tag from a given term object
		 *
		 * @since 2.9.0
		 *
		 * @param object $term Term Object.
		 */
		function wxr_term_name( $term ) {
			if ( empty( $term->name ) ) {
				return;
			}

			echo '<wp:term_name>' . wxr_cdata( $term->name ) . '</wp:term_name>';
		}

		/**
		 * Output a term_description XML tag from a given term object
		 *
		 * @since 2.9.0
		 *
		 * @param object $term Term Object.
		 */
		function wxr_term_description( $term ) {
			if ( empty( $term->description ) ) {
				return;
			}

			echo '<wp:term_description>' . wxr_cdata( $term->description ) . '</wp:term_description>';
		}

		/**
		 * Output list of authors with posts
		 *
		 * @since 3.1.0
		 */
		function wxr_authors_list() {
			global $wpdb;

			$authors = array();
			$results = $wpdb->get_results( "SELECT DISTINCT post_author FROM $wpdb->posts WHERE post_status != 'auto-draft'" );
			foreach ( (array) $results as $result ) {
				$authors[] = get_userdata( $result->post_author );
			}

			$authors = array_filter( $authors );

			foreach ( $authors as $author ) {
				echo "\t<wp:author>";
				echo '<wp:author_id>' . $author->ID . '</wp:author_id>';
				echo '<wp:author_login>' . $author->user_login . '</wp:author_login>';
				echo '<wp:author_email>' . $author->user_email . '</wp:author_email>';
				echo '<wp:author_display_name>' . wxr_cdata( $author->display_name ) . '</wp:author_display_name>';
				echo '<wp:author_first_name>' . wxr_cdata( $author->user_firstname ) . '</wp:author_first_name>';
				echo '<wp:author_last_name>' . wxr_cdata( $author->user_lastname ) . '</wp:author_last_name>';
				echo "</wp:author>\n";
			}
		}

		/**
		 * Filters post meta fields.
		 *
		 * @param mixed  $return_me Meta value.
		 * @param string $meta_key  Meta key.
		 *
		 * @return mixed
		 */
		function wxr_filter_postmeta( $return_me, $meta_key ) {
			if ( '_edit_lock' == $meta_key ) {
				$return_me = true;
			}

			return $return_me;
		}

		add_filter( 'wxr_export_skip_postmeta', 'wxr_filter_postmeta', 10, 2 );

		/**
		 * Find the attachment ID.
		 *
		 * @param string $attachment_url The URL.
		 *
		 * @return null|string
		 */
		function sermon_return_attachment_id_from_url( $attachment_url ) {
			global $wpdb;
			$attachment_id = '';

			// is attachment url set?
			if ( '' !== $attachment_url ) {
				// prepare query.
				$query = $wpdb->prepare( "SELECT ID FROM {$wpdb->posts} WHERE guid=%s", $attachment_url );

				// get attachment id.
				$attachment_id = $wpdb->get_var( $query );
			}

			// return id.
			return $attachment_id;
		}

		echo '<?xml version="1.0" encoding="' . get_bloginfo( 'charset' ) . "\" ?>\n";

		?>
		<!-- This is a WordPress eXtended RSS file generated by WordPress as an export of your site. -->
		<!-- It contains information about your site's posts, pages, comments, categories, and other content. -->
		<!-- You may use this file to transfer that content from one site to another. -->
		<!-- This file is not intended to serve as a complete backup of your site. -->

		<!-- To import this information into a WordPress site follow these steps: -->
		<!-- 1. Log in to that site as an administrator. -->
		<!-- 2. Go to Tools: Import in the WordPress admin panel. -->
		<!-- 3. Install the "WordPress" importer from the list. -->
		<!-- 4. Activate & Run Importer. -->
		<!-- 5. Upload this file using the form provided on that page. -->
		<!-- 6. You will first be asked to map the authors in this export file to users -->
		<!--    on the site. For each author, you may choose to map to an -->
		<!--    existing user on the site or to create a new user. -->
		<!-- 7. WordPress will then import each of the posts, pages, comments, categories, etc. -->
		<!--    contained in this file into your site. -->

		<?php the_generator( 'export' ); ?>
		<rss version="2.0"
				xmlns:excerpt="http://wordpress.org/export/<?php echo WXR_VERSION; ?>/excerpt/"
				xmlns:content="http://purl.org/rss/1.0/modules/content/"
				xmlns:dc="http://purl.org/dc/elements/1.1/"
				xmlns:wp="http://wordpress.org/export/<?php echo WXR_VERSION; ?>/"
		>

			<channel>
				<title><?php bloginfo_rss( 'name' ); ?></title>
				<link><?php bloginfo_rss( 'url' ); ?></link>
				<description><?php bloginfo_rss( 'description' ); ?></description>
				<pubDate><?php echo date( 'D, d M Y H:i:s +0000' ); ?></pubDate>
				<language><?php bloginfo_rss( 'language' ); ?></language>
				<wp:wxr_version><?php echo WXR_VERSION; ?></wp:wxr_version>
				<wp:base_site_url><?php echo wxr_site_url(); ?></wp:base_site_url>
				<wp:base_blog_url><?php bloginfo_rss( 'url' ); ?></wp:base_blog_url>

				<?php wxr_authors_list(); ?>
				<?php $assigned_term_images = get_option( 'sermon_image_plugin' ); ?>
				<?php foreach ( $terms as $t ) : ?>
					<wp:term>
						<wp:term_id><?php echo $t->term_id; ?></wp:term_id>
						<wp:term_taxonomy><?php echo $t->taxonomy; ?></wp:term_taxonomy>
						<wp:term_slug><?php echo $t->slug; ?></wp:term_slug>
						<wp:term_parent><?php echo $t->parent ? $terms[ $t->parent ]->slug : ''; ?></wp:term_parent>
						<?php wxr_term_name( $t ); ?>
						<?php wxr_term_description( $t ); ?>
						<?php
						$termmeta = $wpdb->get_results( $wpdb->prepare( "SELECT * FROM $wpdb->termmeta WHERE term_id = %d", $t->term_id ) );
						foreach ( $termmeta as $meta ) :
							/**
							 * Filter whether to selectively skip post meta used for WXR exports.
							 *
							 * Returning a truthy value to the filter will skip the current meta
							 * object from being exported.
							 *
							 * @since 3.3.0
							 *
							 * @param bool   $skip     Whether to skip the current post meta. Default false.
							 * @param string $meta_key Current meta key.
							 * @param object $meta     Current meta object.
							 */
							if ( apply_filters( 'wxr_export_skip_postmeta', false, $meta->meta_key, $meta ) ) {
								continue;
							}
							?>
							<wp:termmeta>
								<wp:meta_key><?php echo $meta->meta_key; ?></wp:meta_key>
								<wp:meta_value><?php echo wxr_cdata( $meta->meta_value ); ?></wp:meta_value>
							</wp:termmeta>
						<?php endforeach; ?>
						<?php if ( array_key_exists( $t->term_id, $assigned_term_images ) ) { ?>
							<wp:termmeta>
								<wp:meta_key>sm_term_image_id</wp:meta_key>
								<wp:meta_value><?php echo $assigned_term_images[ $t->term_id ]; ?></wp:meta_value>
							</wp:termmeta>
						<?php } ?>
					</wp:term>
				<?php endforeach; ?>

				<?php
				/** This action is documented in wp-includes/feed-rss2.php */
				do_action( 'rss2_head' );
				?>

				<?php
				if ( $post_ids ) {
					global $wp_query;
					$wp_query->in_the_loop = true; // Fake being in the loop.

					/* NEW CODE START */
					$meta_value_array = array();
					$meta_key_name    = array(
						'sermon_audio',
						'sermon_video',
						'sermon_video_link',
						'sermon_notes',
						'sermon_bulletin',
					);
					foreach ( $post_ids as $post ) {
						$postmeta   = $wpdb->get_results( $wpdb->prepare( "SELECT * FROM $wpdb->postmeta WHERE post_id = %d", $post ) );
						$postobject = $wpdb->get_results( $wpdb->prepare( "SELECT * FROM $wpdb->posts WHERE ID = %d", $post ) );
						$meta_value = array();
						if ( $postobject[0]->post_type == $post_type_export ) {
							foreach ( $postmeta as $meta ) {
								if ( '_thumbnail_id' == $meta->meta_key ) {
									$meta_value[] = $meta->meta_value;
								}
								if ( in_array( $meta->meta_key, $meta_key_name ) ) {
									$attachment_id_temp = sermon_return_attachment_id_from_url( $meta->meta_value );
									if ( ! empty( $attachment_id_temp ) ) {
										$meta_value[] = $attachment_id_temp;
									}
								}
							}
						}
						if ( isset( $meta_value ) && is_array( $meta_value ) ) {
							$meta_value_array = array_unique( array_merge( $meta_value_array, $meta_value ), SORT_REGULAR );
						}
					}
					$attachment_array = array();
					foreach ( $assigned_term_images as $term => $attachment ) {
						$attachment_array[] = $attachment;
					}
					$attachment_array = array_unique( $attachment_array, SORT_REGULAR );
					$post_ids         = array_merge( $post_ids, $meta_value_array, $attachment_array );
					/* NEW CODE END */

					// fetch 20 posts at a time rather than loading the entire table into memory.
					while ( $next_posts = array_splice( $post_ids, 0, 20 ) ) {
						$where = 'WHERE ID IN (' . join( ',', $next_posts ) . ')';
						$posts = $wpdb->get_results( "SELECT * FROM {$wpdb->posts} $where" );

						// Begin Loop.
						foreach ( $posts as $post ) {
							setup_postdata( $post );
							$is_sticky = is_sticky( $post->ID ) ? 1 : 0;
							?>
							<item>
								<?php /** This filter is documented in wp-includes/feed.php */ ?>
								<title><?php echo apply_filters( 'the_title_rss', $post->post_title ); ?></title>
								<link><?php the_permalink_rss(); ?></link>
								<pubDate><?php echo mysql2date( 'D, d M Y H:i:s +0000', get_post_time( 'Y-m-d H:i:s', true ), false ); ?></pubDate>
								<dc:creator><?php echo wxr_cdata( get_the_author_meta( 'login' ) ); ?></dc:creator>
								<guid isPermaLink="false"><?php the_guid(); ?></guid>
								<description></description>
								<content:encoded>
									<?php
									/**
									 * Filter the post content used for WXR exports.
									 *
									 * @since 2.5.0
									 *
									 * @param string $post_content Content of the current post.
									 */
									echo wxr_cdata( apply_filters( 'the_content_export', $post->post_content ) );
									?>
								</content:encoded>
								<excerpt:encoded>
									<?php
									/**
									 * Filter the post excerpt used for WXR exports.
									 *
									 * @since 2.6.0
									 *
									 * @param string $post_excerpt Excerpt for the current post.
									 */
									echo wxr_cdata( apply_filters( 'the_excerpt_export', $post->post_excerpt ) );
									?>
								</excerpt:encoded>
								<wp:post_id><?php echo $post->ID; ?></wp:post_id>
								<wp:post_content><?php echo $post->post_content; ?></wp:post_content>
								<wp:post_date><?php echo $post->post_date; ?></wp:post_date>
								<wp:post_date_gmt><?php echo $post->post_date_gmt; ?></wp:post_date_gmt>
								<wp:comment_status><?php echo $post->comment_status; ?></wp:comment_status>
								<wp:ping_status><?php echo $post->ping_status; ?></wp:ping_status>
								<wp:post_name><?php echo $post->post_name; ?></wp:post_name>
								<wp:status><?php echo $post->post_status; ?></wp:status>
								<wp:post_parent><?php echo $post->post_parent; ?></wp:post_parent>
								<wp:menu_order><?php echo $post->menu_order; ?></wp:menu_order>
								<wp:post_type><?php echo $post->post_type; ?></wp:post_type>
								<wp:post_password><?php echo $post->post_password; ?></wp:post_password>
								<wp:is_sticky><?php echo $is_sticky; ?></wp:is_sticky>
								<?php wxr_post_taxonomy( $post->ID ); ?>
								<?php if ( 'attachment' == $post->post_type ) : ?>
									<wp:attachment_url><?php echo wp_get_attachment_url( $post->ID ); ?></wp:attachment_url>
								<?php endif; ?>
								<?php
								$postmeta = $wpdb->get_results( $wpdb->prepare( "SELECT * FROM $wpdb->postmeta WHERE post_id = %d", $post->ID ) );
								foreach ( $postmeta as $meta ) :
									/**
									 * Filter whether to selectively skip post meta used for WXR exports.
									 *
									 * Returning a truthy value to the filter will skip the current meta
									 * object from being exported.
									 *
									 * @since 3.3.0
									 *
									 * @param bool   $skip     Whether to skip the current post meta. Default false.
									 * @param string $meta_key Current meta key.
									 * @param object $meta     Current meta object.
									 */
									if ( apply_filters( 'wxr_export_skip_postmeta', false, $meta->meta_key, $meta ) ) {
										continue;
									}
									?>
									<wp:postmeta>
										<wp:meta_key><?php echo $meta->meta_key; ?></wp:meta_key>
										<wp:meta_value><?php echo wxr_cdata( $meta->meta_value ); ?></wp:meta_value>
									</wp:postmeta>
								<?php endforeach; ?>
								<?php
								$comments = $wpdb->get_results( $wpdb->prepare( "SELECT * FROM $wpdb->comments WHERE comment_post_ID = %d AND comment_approved <> 'spam'", $post->ID ) );
								foreach ( $comments as $c ) :
									?>
									<wp:comment>
										<wp:comment_id><?php echo $c->comment_ID; ?></wp:comment_id>
										<wp:comment_author><?php echo wxr_cdata( $c->comment_author ); ?></wp:comment_author>
										<wp:comment_author_email><?php echo $c->comment_author_email; ?></wp:comment_author_email>
										<wp:comment_author_url><?php echo esc_url_raw( $c->comment_author_url ); ?></wp:comment_author_url>
										<wp:comment_author_IP><?php echo $c->comment_author_IP; ?></wp:comment_author_IP>
										<wp:comment_date><?php echo $c->comment_date; ?></wp:comment_date>
										<wp:comment_date_gmt><?php echo $c->comment_date_gmt; ?></wp:comment_date_gmt>
										<wp:comment_content><?php echo wxr_cdata( $c->comment_content ); ?></wp:comment_content>
										<wp:comment_approved><?php echo $c->comment_approved; ?></wp:comment_approved>
										<wp:comment_type><?php echo $c->comment_type; ?></wp:comment_type>
										<wp:comment_parent><?php echo $c->comment_parent; ?></wp:comment_parent>
										<wp:comment_user_id><?php echo $c->user_id; ?></wp:comment_user_id>
										<?php
										$c_meta = $wpdb->get_results( $wpdb->prepare( "SELECT * FROM $wpdb->commentmeta WHERE comment_id = %d", $c->comment_ID ) );
										foreach ( $c_meta as $meta ) :
											?>
											<wp:commentmeta>
												<wp:meta_key><?php echo $meta->meta_key; ?></wp:meta_key>
												<wp:meta_value><?php echo wxr_cdata( $meta->meta_value ); ?></wp:meta_value>
											</wp:commentmeta>
										<?php endforeach; ?>
									</wp:comment>
								<?php endforeach; ?>
							</item>
							<?php
						}
					}
				}
				?>
			</channel>
		</rss>
		<?php
	}
}
