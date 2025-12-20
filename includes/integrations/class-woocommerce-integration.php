<?php
/**
 * WooCommerce Integration Class
 *
 * Provides WooCommerce product search, comparison, and add-to-cart functionality
 * for the AI Agent chat widget.
 *
 * @package AI_Agent_For_Website
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class AIAGENT_WooCommerce_Integration
 *
 * Handles WooCommerce integration for the AI Agent plugin.
 */
class AIAGENT_WooCommerce_Integration {

	/**
	 * Option name for WooCommerce integration settings.
	 *
	 * @var string
	 */
	const OPTION_NAME = 'aiagent_woocommerce_settings';

	/**
	 * Check if WooCommerce is installed and active.
	 *
	 * @return bool True if WooCommerce is active.
	 */
	public static function is_woocommerce_active() {
		return class_exists( 'WooCommerce' ) && function_exists( 'WC' );
	}

	/**
	 * Check if WooCommerce integration is enabled.
	 *
	 * @return bool True if enabled and WooCommerce is active.
	 */
	public static function is_enabled() {
		if ( ! self::is_woocommerce_active() ) {
			return false;
		}

		$settings = self::get_settings();
		return ! empty( $settings['enabled'] );
	}

	/**
	 * Option name for last sync timestamp.
	 *
	 * @var string
	 */
	const SYNC_TIMESTAMP_OPTION = 'aiagent_woocommerce_last_sync';

	/**
	 * Get WooCommerce integration settings.
	 *
	 * @return array Settings array.
	 */
	public static function get_settings() {
		$defaults = [
			'enabled'                 => false,
			'show_prices'             => true,
			'show_add_to_cart'        => true,
			'show_related_products'   => true,
			'show_product_comparison' => true,
			'max_products_display'    => 6,
			'search_in_description'   => true,
			'include_out_of_stock'    => false,
			// Knowledge base sync settings.
			'sync_to_kb'              => true,
			'auto_sync'               => false,
			'kb_include_descriptions' => true,
			'kb_include_prices'       => true,
			'kb_include_categories'   => true,
			'kb_include_attributes'   => true,
			'kb_include_stock_status' => true,
		];

		$settings = get_option( self::OPTION_NAME, [] );
		return wp_parse_args( $settings, $defaults );
	}

	/**
	 * Update WooCommerce integration settings.
	 *
	 * @param array $settings Settings to save.
	 * @return bool True if updated successfully.
	 */
	public static function update_settings( $settings ) {
		return update_option( self::OPTION_NAME, $settings );
	}

	/**
	 * Search products by keyword with relevance scoring.
	 *
	 * @param string $keyword      Search keyword.
	 * @param int    $limit        Maximum number of products to return.
	 * @param bool   $include_desc Whether to search in description.
	 * @return array Array of product data sorted by relevance.
	 */
	public function search_products( $keyword, $limit = 10, $include_desc = true ) {
		if ( ! self::is_woocommerce_active() || empty( $keyword ) ) {
			return [];
		}

		$settings       = self::get_settings();
		$keyword_lower  = strtolower( trim( $keyword ) );
		$all_products   = [];
		$scored_results = [];

		// First, try exact title match.
		$exact_args = [
			'post_type'      => 'product',
			'post_status'    => 'publish',
			'posts_per_page' => $limit,
			'title'          => $keyword,
		];

		// Then search with LIKE on title.
		$like_args = [
			'post_type'      => 'product',
			'post_status'    => 'publish',
			'posts_per_page' => $limit * 3, // Get more to filter.
			's'              => $keyword,
			'orderby'        => 'relevance',
		];

		// Exclude out of stock if configured.
		if ( empty( $settings['include_out_of_stock'] ) ) {
			$stock_filter = [
				[
					'key'     => '_stock_status',
					'value'   => 'instock',
					'compare' => '=',
				],
			];
			// phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query -- Required for stock filtering.
			$exact_args['meta_query'] = $stock_filter;
			// phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query -- Required for stock filtering.
			$like_args['meta_query'] = $stock_filter;
		}

		// Run both queries.
		$exact_query = new WP_Query( $exact_args );
		$like_query  = new WP_Query( $like_args );

		// Collect all unique product IDs.
		$product_ids = [];

		if ( $exact_query->have_posts() ) {
			while ( $exact_query->have_posts() ) {
				$exact_query->the_post();
				$product_ids[ get_the_ID() ] = true;
			}
			wp_reset_postdata();
		}

		if ( $like_query->have_posts() ) {
			while ( $like_query->have_posts() ) {
				$like_query->the_post();
				$product_ids[ get_the_ID() ] = true;
			}
			wp_reset_postdata();
		}

		// Score each product for relevance.
		foreach ( array_keys( $product_ids ) as $product_id ) {
			$product = wc_get_product( $product_id );
			if ( ! $product ) {
				continue;
			}

			$title       = strtolower( $product->get_name() );
			$description = strtolower( wp_strip_all_tags( $product->get_short_description() . ' ' . $product->get_description() ) );
			$sku         = strtolower( $product->get_sku() );
			$categories  = strtolower( implode( ' ', wp_get_post_terms( $product_id, 'product_cat', [ 'fields' => 'names' ] ) ) );

			// Calculate relevance score.
			$score = 0;

			// Exact title match = highest score.
			if ( $title === $keyword_lower ) {
				$score += 100;
			} elseif ( strpos( $title, $keyword_lower ) === 0 ) {
				// Title starts with keyword.
				$score += 80;
			} elseif ( strpos( $title, $keyword_lower ) !== false ) {
				// Title contains keyword.
				$score += 60;
			}

			// Check individual words in title.
			$title_words = explode( ' ', $title );
			foreach ( $title_words as $word ) {
				if ( $word === $keyword_lower ) {
					$score += 50;
					break;
				} elseif ( strpos( $word, $keyword_lower ) !== false || strpos( $keyword_lower, $word ) !== false ) {
					$score += 30;
				}
			}

			// SKU match.
			if ( $sku === $keyword_lower ) {
				$score += 70;
			} elseif ( strpos( $sku, $keyword_lower ) !== false ) {
				$score += 40;
			}

			// Category match.
			if ( strpos( $categories, $keyword_lower ) !== false ) {
				$score += 35;
			}

			// Description match (lower weight).
			if ( $include_desc && strpos( $description, $keyword_lower ) !== false ) {
				$score += 15;
			}

			// Only include products with a minimum relevance score.
			if ( $score > 0 ) {
				$scored_results[] = [
					'product' => $product,
					'score'   => $score,
				];
			}
		}

		// Sort by score descending.
		usort(
			$scored_results,
			function ( $a, $b ) {
				return $b['score'] - $a['score'];
			}
		);

		// Take top results.
		$scored_results = array_slice( $scored_results, 0, $limit );

		// Format results.
		$products = [];
		foreach ( $scored_results as $result ) {
			$products[] = $this->format_product_data( $result['product'] );
		}

		return $products;
	}

	/**
	 * Get all products (no search filter).
	 *
	 * @param int $limit Maximum number of products to return.
	 * @return array Array of product data.
	 */
	public function get_all_products( $limit = 10 ) {
		if ( ! self::is_woocommerce_active() ) {
			return [];
		}

		$settings = self::get_settings();

		$args = [
			'post_type'      => 'product',
			'post_status'    => 'publish',
			'posts_per_page' => $limit,
			'orderby'        => 'date',
			'order'          => 'DESC',
		];

		// Exclude out of stock if configured.
		if ( empty( $settings['include_out_of_stock'] ) ) {
			// phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query -- Required for stock filtering.
			$args['meta_query'] = [
				[
					'key'     => '_stock_status',
					'value'   => 'instock',
					'compare' => '=',
				],
			];
		}

		$query    = new WP_Query( $args );
		$products = [];

		if ( $query->have_posts() ) {
			while ( $query->have_posts() ) {
				$query->the_post();
				$product = wc_get_product( get_the_ID() );

				if ( $product ) {
					$products[] = $this->format_product_data( $product );
				}
			}
			wp_reset_postdata();
		}

		return $products;
	}

	/**
	 * Search products by category.
	 *
	 * @param string $category Category slug or name.
	 * @param int    $limit    Maximum number of products to return.
	 * @return array Array of product data.
	 */
	public function search_by_category( $category, $limit = 10 ) {
		if ( ! self::is_woocommerce_active() ) {
			return [];
		}

		$settings = self::get_settings();

		// Try to find category by slug or name.
		$term = get_term_by( 'slug', sanitize_title( $category ), 'product_cat' );
		if ( ! $term ) {
			$term = get_term_by( 'name', $category, 'product_cat' );
		}

		if ( ! $term ) {
			return [];
		}

		$args = [
			'post_type'      => 'product',
			'post_status'    => 'publish',
			'posts_per_page' => $limit,
			// phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_tax_query -- Required for category filtering.
			'tax_query'      => [
				[
					'taxonomy' => 'product_cat',
					'field'    => 'term_id',
					'terms'    => $term->term_id,
				],
			],
			'orderby'        => 'menu_order',
			'order'          => 'ASC',
		];

		// Exclude out of stock if configured.
		if ( empty( $settings['include_out_of_stock'] ) ) {
			// phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query -- Required for stock filtering.
			$args['meta_query'] = [
				[
					'key'     => '_stock_status',
					'value'   => 'instock',
					'compare' => '=',
				],
			];
		}

		$query    = new WP_Query( $args );
		$products = [];

		if ( $query->have_posts() ) {
			while ( $query->have_posts() ) {
				$query->the_post();
				$product = wc_get_product( get_the_ID() );

				if ( $product ) {
					$products[] = $this->format_product_data( $product );
				}
			}
			wp_reset_postdata();
		}

		return $products;
	}

	/**
	 * Get product by ID.
	 *
	 * @param int $product_id Product ID.
	 * @return array|null Product data or null if not found.
	 */
	public function get_product( $product_id ) {
		if ( ! self::is_woocommerce_active() ) {
			return null;
		}

		$product = wc_get_product( $product_id );

		if ( ! $product ) {
			return null;
		}

		return $this->format_product_data( $product, true );
	}

	/**
	 * Get related products for a given product.
	 *
	 * @param int $product_id Product ID.
	 * @param int $limit      Maximum number of related products.
	 * @return array Array of related product data.
	 */
	public function get_related_products( $product_id, $limit = 4 ) {
		if ( ! self::is_woocommerce_active() ) {
			return [];
		}

		$related_ids = wc_get_related_products( $product_id, $limit );
		$products    = [];

		foreach ( $related_ids as $id ) {
			$product = wc_get_product( $id );
			if ( $product ) {
				$products[] = $this->format_product_data( $product );
			}
		}

		return $products;
	}

	/**
	 * Get upsell products for a given product.
	 *
	 * @param int $product_id Product ID.
	 * @param int $limit      Maximum number of upsell products.
	 * @return array Array of upsell product data.
	 */
	public function get_upsell_products( $product_id, $limit = 4 ) {
		if ( ! self::is_woocommerce_active() ) {
			return [];
		}

		$product = wc_get_product( $product_id );
		if ( ! $product ) {
			return [];
		}

		$upsell_ids = $product->get_upsell_ids();
		$upsell_ids = array_slice( $upsell_ids, 0, $limit );
		$products   = [];

		foreach ( $upsell_ids as $id ) {
			$upsell_product = wc_get_product( $id );
			if ( $upsell_product ) {
				$products[] = $this->format_product_data( $upsell_product );
			}
		}

		return $products;
	}

	/**
	 * Get cross-sell products for a given product.
	 *
	 * @param int $product_id Product ID.
	 * @param int $limit      Maximum number of cross-sell products.
	 * @return array Array of cross-sell product data.
	 */
	public function get_crosssell_products( $product_id, $limit = 4 ) {
		if ( ! self::is_woocommerce_active() ) {
			return [];
		}

		$product = wc_get_product( $product_id );
		if ( ! $product ) {
			return [];
		}

		$crosssell_ids = $product->get_cross_sell_ids();
		$crosssell_ids = array_slice( $crosssell_ids, 0, $limit );
		$products      = [];

		foreach ( $crosssell_ids as $id ) {
			$crosssell_product = wc_get_product( $id );
			if ( $crosssell_product ) {
				$products[] = $this->format_product_data( $crosssell_product );
			}
		}

		return $products;
	}

	/**
	 * Compare multiple products.
	 *
	 * @param array $product_ids Array of product IDs to compare.
	 * @return array Comparison data.
	 */
	public function compare_products( $product_ids ) {
		if ( ! self::is_woocommerce_active() ) {
			return [];
		}

		$products          = [];
		$all_attributes    = [];
		$comparison_fields = [
			'name',
			'price',
			'regular_price',
			'sale_price',
			'rating',
			'stock_status',
			'sku',
			'weight',
			'dimensions',
		];

		// First pass: collect all products and attributes.
		foreach ( $product_ids as $product_id ) {
			$product = wc_get_product( $product_id );
			if ( ! $product ) {
				continue;
			}

			$product_data = $this->format_product_data( $product, true );

			// Get product attributes.
			$attributes = $product->get_attributes();
			foreach ( $attributes as $attr_key => $attribute ) {
				$attr_name = wc_attribute_label( $attr_key );
				if ( ! in_array( $attr_name, $all_attributes, true ) ) {
					$all_attributes[] = $attr_name;
				}

				if ( $attribute instanceof WC_Product_Attribute ) {
					$values      = $attribute->get_options();
					$value_names = [];
					foreach ( $values as $value ) {
						if ( is_int( $value ) ) {
							$term = get_term( $value );
							if ( $term && ! is_wp_error( $term ) ) {
								$value_names[] = $term->name;
							}
						} else {
							$value_names[] = $value;
						}
					}
					$product_data['attributes'][ $attr_name ] = implode( ', ', $value_names );
				}
			}

			$products[] = $product_data;
		}

		return [
			'products'          => $products,
			'attributes'        => $all_attributes,
			'comparison_fields' => $comparison_fields,
		];
	}

	/**
	 * Add product to cart.
	 *
	 * @param int   $product_id   Product ID.
	 * @param int   $quantity     Quantity to add.
	 * @param int   $variation_id Variation ID (for variable products).
	 * @param array $variation    Variation attributes.
	 * @return array Result with success status and message.
	 */
	public function add_to_cart( $product_id, $quantity = 1, $variation_id = 0, $variation = [] ) {
		if ( ! self::is_woocommerce_active() ) {
			return [
				'success' => false,
				'message' => __( 'WooCommerce is not available.', 'ai-agent-for-website' ),
			];
		}

		// Ensure WooCommerce session and cart are initialized for REST API.
		$this->ensure_cart_session();

		$product = wc_get_product( $product_id );
		if ( ! $product ) {
			return [
				'success' => false,
				'message' => __( 'Product not found.', 'ai-agent-for-website' ),
			];
		}

		// Check if product is purchasable.
		if ( ! $product->is_purchasable() ) {
			return [
				'success' => false,
				'message' => __( 'This product cannot be purchased.', 'ai-agent-for-website' ),
			];
		}

		// Check stock.
		if ( ! $product->is_in_stock() ) {
			return [
				'success' => false,
				'message' => __( 'This product is out of stock.', 'ai-agent-for-website' ),
			];
		}

		// Handle variable products.
		if ( $product->is_type( 'variable' ) && empty( $variation_id ) ) {
			return [
				'success'            => false,
				'message'            => __( 'Please select product options.', 'ai-agent-for-website' ),
				'requires_variation' => true,
				'variations'         => $this->get_product_variations( $product_id ),
			];
		}

		try {
			// Add to cart.
			$cart_item_key = WC()->cart->add_to_cart( $product_id, $quantity, $variation_id, $variation );

			if ( $cart_item_key ) {
				return [
					'success'       => true,
					'message'       => sprintf(
						/* translators: %s: Product name */
						__( '%s has been added to your cart.', 'ai-agent-for-website' ),
						$product->get_name()
					),
					'cart_item_key' => $cart_item_key,
					'cart_url'      => wc_get_cart_url(),
					'checkout_url'  => wc_get_checkout_url(),
					'cart_count'    => WC()->cart->get_cart_contents_count(),
					'cart_total'    => WC()->cart->get_cart_total(),
				];
			}
		} catch ( \Exception $e ) {
			return [
				'success' => false,
				'message' => $e->getMessage(),
			];
		}

		return [
			'success' => false,
			'message' => __( 'Could not add product to cart. Please try again.', 'ai-agent-for-website' ),
		];
	}

	/**
	 * Ensure WooCommerce cart session is initialized for REST API requests.
	 *
	 * @return void
	 */
	private function ensure_cart_session() {
		if ( ! WC()->session ) {
			// Initialize session handler.
			WC()->session = new \WC_Session_Handler();
			WC()->session->init();
		}

		if ( ! WC()->cart ) {
			// Initialize cart.
			WC()->cart = new \WC_Cart();
		}

		if ( ! WC()->customer ) {
			// Initialize customer.
			WC()->customer = new \WC_Customer( get_current_user_id(), true );
		}

		// Ensure cart is loaded from session.
		if ( WC()->cart && method_exists( WC()->cart, 'get_cart_from_session' ) ) {
			WC()->cart->get_cart_from_session();
		}
	}

	/**
	 * Get product variations for variable products.
	 *
	 * @param int $product_id Product ID.
	 * @return array Array of variation data.
	 */
	public function get_product_variations( $product_id ) {
		if ( ! self::is_woocommerce_active() ) {
			return [];
		}

		$product = wc_get_product( $product_id );
		if ( ! $product || ! $product->is_type( 'variable' ) ) {
			return [];
		}

		$variations     = $product->get_available_variations();
		$variation_data = [];

		foreach ( $variations as $variation ) {
			$variation_product = wc_get_product( $variation['variation_id'] );
			if ( ! $variation_product ) {
				continue;
			}

			$variation_data[] = [
				'id'         => $variation['variation_id'],
				'attributes' => $variation['attributes'],
				'price'      => $variation_product->get_price(),
				'price_html' => $variation_product->get_price_html(),
				'in_stock'   => $variation_product->is_in_stock(),
				'stock_qty'  => $variation_product->get_stock_quantity(),
				'image'      => wp_get_attachment_image_url( $variation_product->get_image_id(), 'woocommerce_thumbnail' ),
			];
		}

		return [
			'variation_attributes' => $product->get_variation_attributes(),
			'variations'           => $variation_data,
		];
	}

	/**
	 * Get cart contents.
	 *
	 * @return array Cart data.
	 */
	public function get_cart() {
		if ( ! self::is_woocommerce_active() ) {
			return [];
		}

		$cart_items = [];
		foreach ( WC()->cart->get_cart() as $cart_item_key => $cart_item ) {
			$product      = $cart_item['data'];
			$cart_items[] = [
				'key'          => $cart_item_key,
				'product_id'   => $cart_item['product_id'],
				'variation_id' => $cart_item['variation_id'] ?? 0,
				'name'         => $product->get_name(),
				'quantity'     => $cart_item['quantity'],
				'price'        => $product->get_price(),
				'subtotal'     => WC()->cart->get_product_subtotal( $product, $cart_item['quantity'] ),
				'image'        => wp_get_attachment_image_url( $product->get_image_id(), 'woocommerce_thumbnail' ),
			];
		}

		return [
			'items'        => $cart_items,
			'total'        => WC()->cart->get_cart_total(),
			'subtotal'     => WC()->cart->get_cart_subtotal(),
			'count'        => WC()->cart->get_cart_contents_count(),
			'cart_url'     => wc_get_cart_url(),
			'checkout_url' => wc_get_checkout_url(),
		];
	}

	/**
	 * Remove item from cart.
	 *
	 * @param string $cart_item_key Cart item key.
	 * @return array Result with success status.
	 */
	public function remove_from_cart( $cart_item_key ) {
		if ( ! self::is_woocommerce_active() ) {
			return [
				'success' => false,
				'message' => __( 'WooCommerce is not available.', 'ai-agent-for-website' ),
			];
		}

		$removed = WC()->cart->remove_cart_item( $cart_item_key );

		if ( $removed ) {
			return [
				'success'    => true,
				'message'    => __( 'Item removed from cart.', 'ai-agent-for-website' ),
				'cart_count' => WC()->cart->get_cart_contents_count(),
				'cart_total' => WC()->cart->get_cart_total(),
			];
		}

		return [
			'success' => false,
			'message' => __( 'Could not remove item from cart.', 'ai-agent-for-website' ),
		];
	}

	/**
	 * Get all product categories.
	 *
	 * @param bool $hide_empty Whether to hide empty categories.
	 * @return array Array of category data.
	 */
	public function get_categories( $hide_empty = true ) {
		if ( ! self::is_woocommerce_active() ) {
			return [];
		}

		$categories = get_terms(
			[
				'taxonomy'   => 'product_cat',
				'hide_empty' => $hide_empty,
				'orderby'    => 'name',
				'order'      => 'ASC',
			]
		);

		if ( is_wp_error( $categories ) ) {
			return [];
		}

		$result = [];
		foreach ( $categories as $category ) {
			$result[] = [
				'id'          => $category->term_id,
				'name'        => $category->name,
				'slug'        => $category->slug,
				'description' => $category->description,
				'count'       => $category->count,
				'parent'      => $category->parent,
			];
		}

		return $result;
	}

	/**
	 * Format product data for API response.
	 *
	 * @param WC_Product $product     Product object.
	 * @param bool       $include_all Whether to include all details.
	 * @return array Formatted product data.
	 */
	private function format_product_data( $product, $include_all = false ) {
		$settings = self::get_settings();

		$data = [
			'id'             => $product->get_id(),
			'name'           => $product->get_name(),
			'slug'           => $product->get_slug(),
			'type'           => $product->get_type(),
			'permalink'      => $product->get_permalink(),
			'image'          => wp_get_attachment_image_url( $product->get_image_id(), 'woocommerce_thumbnail' ),
			'image_full'     => wp_get_attachment_image_url( $product->get_image_id(), 'full' ),
			'short_desc'     => wp_strip_all_tags( $product->get_short_description() ),
			'in_stock'       => $product->is_in_stock(),
			'stock_status'   => $product->get_stock_status(),
			'is_purchasable' => $product->is_purchasable(),
		];

		// Include prices if enabled.
		if ( ! empty( $settings['show_prices'] ) ) {
			$data['price']         = $product->get_price();
			$data['regular_price'] = $product->get_regular_price();
			$data['sale_price']    = $product->get_sale_price();
			$data['price_html']    = $product->get_price_html();
			$data['on_sale']       = $product->is_on_sale();
			$data['currency']      = get_woocommerce_currency_symbol();
		}

		// Include rating.
		$data['rating']       = $product->get_average_rating();
		$data['review_count'] = $product->get_review_count();

		// Include additional details if requested.
		if ( $include_all ) {
			$data['description']    = wp_strip_all_tags( $product->get_description() );
			$data['sku']            = $product->get_sku();
			$data['weight']         = $product->get_weight();
			$data['dimensions']     = $product->get_dimensions( false );
			$data['categories']     = wp_get_post_terms( $product->get_id(), 'product_cat', [ 'fields' => 'names' ] );
			$data['tags']           = wp_get_post_terms( $product->get_id(), 'product_tag', [ 'fields' => 'names' ] );
			$data['gallery_images'] = [];
			$data['attributes']     = [];

			// Gallery images.
			$gallery_ids = $product->get_gallery_image_ids();
			foreach ( $gallery_ids as $image_id ) {
				$data['gallery_images'][] = wp_get_attachment_image_url( $image_id, 'woocommerce_single' );
			}

			// Variation info for variable products.
			if ( $product->is_type( 'variable' ) ) {
				$data['is_variable'] = true;
				$data['min_price']   = $product->get_variation_price( 'min' );
				$data['max_price']   = $product->get_variation_price( 'max' );
			}
		}

		return $data;
	}

	/**
	 * AI-powered product recommendation based on user query.
	 *
	 * @param string $query User query.
	 * @param array  $context Conversation context (reserved for future use).
	 * @return array Recommended products and suggestions.
	 */
	public function get_ai_recommendations( $query, $context = [] ) {
		// Context parameter reserved for future AI context-aware recommendations.
		unset( $context );

		if ( ! self::is_enabled() ) {
			return [];
		}

		// Extract potential product keywords from query.
		$keywords = $this->extract_product_keywords( $query );

		// Search for products.
		$products = [];
		foreach ( $keywords as $keyword ) {
			$found    = $this->search_products( $keyword, 5 );
			$products = array_merge( $products, $found );
		}

		// Remove duplicates.
		$unique_products = [];
		$seen_ids        = [];
		foreach ( $products as $product ) {
			if ( ! in_array( $product['id'], $seen_ids, true ) ) {
				$unique_products[] = $product;
				$seen_ids[]        = $product['id'];
			}
		}

		// Limit to max display.
		$settings        = self::get_settings();
		$unique_products = array_slice( $unique_products, 0, $settings['max_products_display'] );

		// Get suggestions.
		$suggestions = [];
		if ( ! empty( $unique_products ) ) {
			// Get related products from first result.
			if ( $settings['show_related_products'] ) {
				$related = $this->get_related_products( $unique_products[0]['id'], 3 );
				if ( ! empty( $related ) ) {
					$suggestions['related'] = $related;
				}
			}
		}

		return [
			'products'    => $unique_products,
			'suggestions' => $suggestions,
			'query'       => $query,
			'keywords'    => $keywords,
		];
	}

	/**
	 * Extract potential product keywords from user query.
	 *
	 * @param string $query User query.
	 * @return array Array of keywords.
	 */
	private function extract_product_keywords( $query ) {
		// Remove common stop words and extract meaningful terms.
		$stop_words = [
			'i',
			'want',
			'to',
			'buy',
			'looking',
			'for',
			'need',
			'can',
			'you',
			'show',
			'me',
			'find',
			'search',
			'get',
			'the',
			'a',
			'an',
			'some',
			'any',
			'please',
			'help',
			'with',
			'about',
			'what',
			'which',
			'do',
			'have',
			'is',
			'are',
			'there',
			'how',
			'much',
			'does',
			'it',
			'cost',
			'price',
			'of',
			'products',
			'product',
			'item',
			'items',
			'thing',
			'things',
		];

		// Convert to lowercase and split.
		$query = strtolower( $query );
		$words = preg_split( '/\s+/', $query );

		// Filter out stop words and short words.
		$keywords = [];
		foreach ( $words as $word ) {
			$word = preg_replace( '/[^a-z0-9]/', '', $word );
			if ( strlen( $word ) > 2 && ! in_array( $word, $stop_words, true ) ) {
				$keywords[] = $word;
			}
		}

		// Also keep the original query as a phrase search.
		if ( count( $keywords ) > 1 ) {
			array_unshift( $keywords, implode( ' ', $keywords ) );
		}

		return array_unique( $keywords );
	}

	/**
	 * Get featured products.
	 *
	 * @param int $limit Maximum number of products.
	 * @return array Array of featured products.
	 */
	public function get_featured_products( $limit = 6 ) {
		if ( ! self::is_woocommerce_active() ) {
			return [];
		}

		$args = [
			'post_type'      => 'product',
			'post_status'    => 'publish',
			'posts_per_page' => $limit,
			// phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_tax_query -- Required for featured product filtering.
			'tax_query'      => [
				[
					'taxonomy' => 'product_visibility',
					'field'    => 'name',
					'terms'    => 'featured',
				],
			],
		];

		$query    = new WP_Query( $args );
		$products = [];

		if ( $query->have_posts() ) {
			while ( $query->have_posts() ) {
				$query->the_post();
				$product = wc_get_product( get_the_ID() );
				if ( $product ) {
					$products[] = $this->format_product_data( $product );
				}
			}
			wp_reset_postdata();
		}

		return $products;
	}

	/**
	 * Get products on sale.
	 *
	 * @param int $limit Maximum number of products.
	 * @return array Array of sale products.
	 */
	public function get_sale_products( $limit = 6 ) {
		if ( ! self::is_woocommerce_active() ) {
			return [];
		}

		$product_ids = wc_get_product_ids_on_sale();
		$product_ids = array_slice( $product_ids, 0, $limit );
		$products    = [];

		foreach ( $product_ids as $id ) {
			$product = wc_get_product( $id );
			if ( $product ) {
				$products[] = $this->format_product_data( $product );
			}
		}

		return $products;
	}

	/**
	 * Get best-selling products.
	 *
	 * @param int $limit Maximum number of products.
	 * @return array Array of best-selling products.
	 */
	public function get_bestsellers( $limit = 6 ) {
		if ( ! self::is_woocommerce_active() ) {
			return [];
		}

		$args = [
			'post_type'      => 'product',
			'post_status'    => 'publish',
			'posts_per_page' => $limit,
			// phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_key -- Required for best-seller ordering.
			'meta_key'       => 'total_sales',
			'orderby'        => 'meta_value_num',
			'order'          => 'DESC',
		];

		$query    = new WP_Query( $args );
		$products = [];

		if ( $query->have_posts() ) {
			while ( $query->have_posts() ) {
				$query->the_post();
				$product = wc_get_product( get_the_ID() );
				if ( $product ) {
					$products[] = $this->format_product_data( $product );
				}
			}
			wp_reset_postdata();
		}

		return $products;
	}

	/**
	 * Get newest products.
	 *
	 * @param int $limit Maximum number of products.
	 * @return array Array of newest products.
	 */
	public function get_new_products( $limit = 6 ) {
		if ( ! self::is_woocommerce_active() ) {
			return [];
		}

		$args = [
			'post_type'      => 'product',
			'post_status'    => 'publish',
			'posts_per_page' => $limit,
			'orderby'        => 'date',
			'order'          => 'DESC',
		];

		$query    = new WP_Query( $args );
		$products = [];

		if ( $query->have_posts() ) {
			while ( $query->have_posts() ) {
				$query->the_post();
				$product = wc_get_product( get_the_ID() );
				if ( $product ) {
					$products[] = $this->format_product_data( $product );
				}
			}
			wp_reset_postdata();
		}

		return $products;
	}

	/**
	 * Sync WooCommerce products to knowledge base.
	 *
	 * @return array Result with success status and message.
	 */
	public static function sync_products_to_knowledge_base() {
		if ( ! self::is_woocommerce_active() ) {
			return [
				'success' => false,
				'message' => __( 'WooCommerce is not active.', 'ai-agent-for-website' ),
			];
		}

		$settings = self::get_settings();

		if ( empty( $settings['sync_to_kb'] ) ) {
			return [
				'success' => false,
				'message' => __( 'Product sync to knowledge base is disabled.', 'ai-agent-for-website' ),
			];
		}

		// Get all published products.
		$args = [
			'post_type'      => 'product',
			'post_status'    => 'publish',
			'posts_per_page' => -1,
			'orderby'        => 'title',
			'order'          => 'ASC',
		];

		$query         = new WP_Query( $args );
		$product_count = 0;
		$kb_content    = [];

		// Build product catalog content.
		$kb_content[] = '# WooCommerce Product Catalog';
		$kb_content[] = '';
		$kb_content[] = 'This is the complete product catalog available in our store. Use this information to help customers find products, answer questions about prices, availability, and features.';
		$kb_content[] = '';

		// Get categories summary.
		$categories = get_terms(
			[
				'taxonomy'   => 'product_cat',
				'hide_empty' => true,
			]
		);

		if ( ! empty( $categories ) && ! is_wp_error( $categories ) ) {
			$kb_content[] = '## Product Categories';
			$kb_content[] = '';
			foreach ( $categories as $category ) {
				$description  = ! empty( $category->description ) ? $category->description : 'Browse our ' . $category->name . ' collection.';
				$kb_content[] = sprintf( '- **%s**: %s', $category->name, $description );
			}
			$kb_content[] = '';
		}

		$kb_content[] = '## Products';
		$kb_content[] = '';

		if ( $query->have_posts() ) {
			while ( $query->have_posts() ) {
				$query->the_post();
				$product = wc_get_product( get_the_ID() );

				if ( ! $product ) {
					continue;
				}

				$product_content = self::format_product_for_kb( $product, $settings );
				if ( ! empty( $product_content ) ) {
					$kb_content[] = $product_content;
					$kb_content[] = '';
					++$product_count;
				}
			}
			wp_reset_postdata();
		}

		// Join all content.
		$full_content = implode( "\n", $kb_content );

		// Use Knowledge Manager to save to the actual knowledge base (JSON file).
		$knowledge_manager = new AIAGENT_Knowledge_Manager();
		$kb                = $knowledge_manager->get_knowledge_base();

		// First, remove any existing WooCommerce entries.
		$documents = $kb->getDocuments();
		$indices   = [];
		foreach ( $documents as $index => $doc ) {
			if ( isset( $doc['source'] ) && 0 === strpos( $doc['source'], 'woocommerce-sync' ) ) {
				$indices[] = $index;
			}
		}
		// Remove in reverse order to maintain correct indices.
		rsort( $indices );
		foreach ( $indices as $index ) {
			$kb->remove( $index );
		}

		// Add the new WooCommerce product catalog.
		$source = 'woocommerce-sync-' . time();
		$title  = __( 'WooCommerce Product Catalog', 'ai-agent-for-website' );
		$result = $kb->addText( $full_content, $source, $title );

		if ( ! $result ) {
			return [
				'success' => false,
				'message' => __( 'Failed to add products to knowledge base.', 'ai-agent-for-website' ),
			];
		}

		// Save the knowledge base.
		$knowledge_manager->save_knowledge_base( $kb );

		// Store sync metadata in options for display purposes.
		update_option(
			self::SYNC_TIMESTAMP_OPTION,
			[
				'synced_at'     => current_time( 'mysql' ),
				'product_count' => $product_count,
			]
		);

		return [
			'success'       => true,
			'message'       => sprintf(
				/* translators: %d is the number of products synced */
				__( 'Successfully synced %d products to knowledge base.', 'ai-agent-for-website' ),
				$product_count
			),
			'product_count' => $product_count,
			'synced_at'     => current_time( 'mysql' ),
		];
	}

	/**
	 * Format a single product for knowledge base content.
	 *
	 * @param WC_Product $product  The product object.
	 * @param array      $settings Integration settings.
	 * @return string Formatted product content for KB.
	 */
	private static function format_product_for_kb( $product, $settings ) {
		$lines = [];

		// Product name as heading.
		$lines[] = sprintf( '### %s', $product->get_name() );

		// Short description.
		$short_desc = wp_strip_all_tags( $product->get_short_description() );
		if ( ! empty( $short_desc ) ) {
			$lines[] = $short_desc;
		}

		// Product details.
		$details = [];

		// Price.
		if ( ! empty( $settings['kb_include_prices'] ) ) {
			$price = $product->get_price();
			if ( ! empty( $price ) ) {
				$currency  = get_woocommerce_currency_symbol();
				$details[] = sprintf( 'Price: %s%s', $currency, number_format( (float) $price, 2 ) );
				$regular   = $product->get_regular_price();
				$sale      = $product->get_sale_price();
				if ( $product->is_on_sale() && $sale ) {
					$details[] = sprintf( '(On Sale! Regular price: %s%s)', $currency, number_format( (float) $regular, 2 ) );
				}
			}
		}

		// Categories.
		if ( ! empty( $settings['kb_include_categories'] ) ) {
			$categories = wp_get_post_terms( $product->get_id(), 'product_cat', [ 'fields' => 'names' ] );
			if ( ! empty( $categories ) && ! is_wp_error( $categories ) ) {
				$details[] = sprintf( 'Category: %s', implode( ', ', $categories ) );
			}
		}

		// SKU.
		$sku = $product->get_sku();
		if ( ! empty( $sku ) ) {
			$details[] = sprintf( 'SKU: %s', $sku );
		}

		// Stock status.
		if ( ! empty( $settings['kb_include_stock_status'] ) ) {
			if ( $product->is_in_stock() ) {
				$stock_qty = $product->get_stock_quantity();
				if ( null !== $stock_qty ) {
					$details[] = sprintf( 'Availability: In Stock (%d available)', $stock_qty );
				} else {
					$details[] = 'Availability: In Stock';
				}
			} else {
				$details[] = 'Availability: Out of Stock';
			}
		}

		// Attributes.
		if ( ! empty( $settings['kb_include_attributes'] ) ) {
			$attributes = $product->get_attributes();
			foreach ( $attributes as $attr_key => $attribute ) {
				if ( $attribute instanceof WC_Product_Attribute ) {
					$attr_name = wc_attribute_label( $attr_key );
					$values    = $attribute->get_options();
					$names     = [];
					foreach ( $values as $value ) {
						if ( is_int( $value ) ) {
							$term = get_term( $value );
							if ( $term && ! is_wp_error( $term ) ) {
								$names[] = $term->name;
							}
						} else {
							$names[] = $value;
						}
					}
					if ( ! empty( $names ) ) {
						$details[] = sprintf( '%s: %s', $attr_name, implode( ', ', $names ) );
					}
				}
			}
		}

		// Add details to lines.
		if ( ! empty( $details ) ) {
			$lines[] = '';
			$lines[] = implode( ' | ', $details );
		}

		// Full description.
		if ( ! empty( $settings['kb_include_descriptions'] ) ) {
			$full_desc = wp_strip_all_tags( $product->get_description() );
			if ( ! empty( $full_desc ) ) {
				$lines[] = '';
				// Truncate very long descriptions.
				if ( strlen( $full_desc ) > 500 ) {
					$full_desc = substr( $full_desc, 0, 500 ) . '...';
				}
				$lines[] = $full_desc;
			}
		}

		// Product URL.
		$lines[] = '';
		$lines[] = sprintf( 'Product URL: %s', $product->get_permalink() );

		return implode( "\n", $lines );
	}

	/**
	 * Get last sync timestamp.
	 *
	 * @return string|null Last sync timestamp or null if never synced.
	 */
	public static function get_last_sync_time() {
		$sync_data = get_option( self::SYNC_TIMESTAMP_OPTION, null );

		if ( is_array( $sync_data ) && isset( $sync_data['synced_at'] ) ) {
			return $sync_data['synced_at'];
		}

		// Fallback for old format.
		if ( is_string( $sync_data ) ) {
			return $sync_data;
		}

		return null;
	}

	/**
	 * Get synced product count from knowledge base.
	 *
	 * @return int Number of synced products.
	 */
	public static function get_synced_product_count() {
		$sync_data = get_option( self::SYNC_TIMESTAMP_OPTION, null );

		if ( is_array( $sync_data ) && isset( $sync_data['product_count'] ) ) {
			return (int) $sync_data['product_count'];
		}

		return 0;
	}

	/**
	 * Remove WooCommerce products from knowledge base.
	 *
	 * @return bool True if removed successfully.
	 */
	public static function remove_products_from_knowledge_base() {
		$knowledge_manager = new AIAGENT_Knowledge_Manager();
		$kb                = $knowledge_manager->get_knowledge_base();

		// Find and remove WooCommerce entries.
		$documents = $kb->getDocuments();
		$indices   = [];
		foreach ( $documents as $index => $doc ) {
			if ( isset( $doc['source'] ) && 0 === strpos( $doc['source'], 'woocommerce-sync' ) ) {
				$indices[] = $index;
			}
		}

		// Remove in reverse order to maintain correct indices.
		rsort( $indices );
		foreach ( $indices as $index ) {
			$kb->remove( $index );
		}

		$knowledge_manager->save_knowledge_base( $kb );
		delete_option( self::SYNC_TIMESTAMP_OPTION );

		return true;
	}

	/**
	 * Initialize auto-sync hooks if enabled.
	 *
	 * @return void
	 */
	public static function init_auto_sync() {
		$settings = self::get_settings();

		if ( ! empty( $settings['enabled'] ) && ! empty( $settings['auto_sync'] ) ) {
			// Sync when product is created or updated.
			add_action( 'woocommerce_update_product', [ __CLASS__, 'schedule_sync' ] );
			add_action( 'woocommerce_new_product', [ __CLASS__, 'schedule_sync' ] );
			add_action( 'before_delete_post', [ __CLASS__, 'schedule_sync_on_delete' ] );
		}
	}

	/**
	 * Schedule a sync (debounced to avoid multiple syncs).
	 *
	 * @return void
	 */
	public static function schedule_sync() {
		// Use transient to debounce multiple product updates.
		if ( get_transient( 'aiagent_woo_sync_scheduled' ) ) {
			return;
		}

		set_transient( 'aiagent_woo_sync_scheduled', true, 30 );

		// Schedule sync to run after current request.
		if ( ! wp_next_scheduled( 'aiagent_woocommerce_sync' ) ) {
			wp_schedule_single_event( time() + 5, 'aiagent_woocommerce_sync' );
		}
	}

	/**
	 * Schedule sync when product is deleted.
	 *
	 * @param int $post_id Post ID.
	 * @return void
	 */
	public static function schedule_sync_on_delete( $post_id ) {
		if ( 'product' === get_post_type( $post_id ) ) {
			self::schedule_sync();
		}
	}
}
