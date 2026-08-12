<?php
namespace CoderEmbassy\CheckoutFieldsManager\Modules\OrderMeta;

defined( 'ABSPATH' ) || exit;

use CoderEmbassy\CheckoutFieldsManager\Frontend\CheckoutContext;
use CoderEmbassy\CheckoutFieldsManager\Modules\Fields\FieldRepository;
use CoderEmbassy\CheckoutFieldsManager\Modules\Fields\VisibilityResolver;
use CoderEmbassy\CheckoutFieldsManager\Modules\Sections\SectionRepository;

/**
 * Handles saving custom checkout field values to order meta and
 * displaying them on the thank-you page and admin order screens.
 */
class OrderMetaHandler {

	/** Field types that carry no user-entered value. */
	private const DISPLAY_ONLY_TYPES = array( 'heading', 'paragraph' );

	public function __construct(
		private FieldRepository $fieldRepository,
		private VisibilityResolver $visibilityResolver,
		private CheckoutContext $context,
		private SectionRepository $sectionRepository
	) {}

	// --- Classic Checkout -----------------------------------------------------

	/**
	 * Save custom field values to order meta on classic (shortcode) checkout.
	 * Hooked to: woocommerce_checkout_update_order_meta (int $order_id, array $data)
	 *
	 * @param int   $order_id    WooCommerce order ID.
	 * @param array $posted_data WooCommerce processed POST data (unused; we read $_POST directly).
	 */
	public function saveCheckoutMeta( int $order_id, array $posted_data ): void {
		try {
			$order = \wc_get_order( $order_id );
			if ( ! $order instanceof \WC_Order ) {
				return;
			}
			$nonce = isset( $_POST['woocommerce-process-checkout-nonce'] ) ? \sanitize_text_field( \wp_unslash( (string) $_POST['woocommerce-process-checkout-nonce'] ) ) : '';
			if ( '' !== $nonce && ! \wp_verify_nonce( $nonce, 'woocommerce-process_checkout' ) ) {
				return;
			}

			$context = $this->buildContext();
			$fields  = $this->fieldRepository->findAll( array( 'enabled' => true ) );
			$map     = $this->visibilityResolver->resolveAll( $context );

			$repeat_data    = $this->buildRepeatData( $fields );
			$suffix_map     = $repeat_data['suffix_map'];
			$section_counts = $repeat_data['section_counts'];

			foreach ( $section_counts as $section_key => $count ) {
				$order->update_meta_data( '_cecfm_repeat_' . $section_key, (int) $count );
			}

			foreach ( $fields as $field ) {
				if ( \in_array( $field->type, self::DISPLAY_ONLY_TYPES, true ) ) {
					continue;
				}

				$status = $map[ $field->field_key ] ?? array( 'visible' => true );
				if ( empty( $status['visible'] ) ) {
					continue;
				}

				$post_keys = isset( $suffix_map[ $field->field_key ] )
					? array_map( static fn ( string $suffix ): string => $field->field_key . $suffix, $suffix_map[ $field->field_key ] )
					: array( $field->field_key );

				foreach ( $post_keys as $post_key ) {
					$this->saveOneFieldValue( $order, $field, $post_key );
				}
			}

			if ( '' !== $context['customer_type'] ) {
				$order->update_meta_data( '_cecfm_customer_type', $context['customer_type'] );
			}

			$order->save();
		} catch ( \Throwable $e ) {
			if ( \defined( 'WP_DEBUG' ) && WP_DEBUG ) {
				// phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
				\error_log( '[Checkout Fields Manager] saveCheckoutMeta error: ' . $e->getMessage() );
			}
		}
	}

	/**
	 * Save one field's value from $_POST (or $_FILES) to order meta.
	 */
	private function saveOneFieldValue( \WC_Order $order, \CoderEmbassy\CheckoutFieldsManager\Models\CECFM_Field $field, string $post_key ): void {
		// phpcs:ignore WordPress.Security.NonceVerification.Missing -- Nonce is verified in the next line.
		$nonce = isset( $_POST['woocommerce-process-checkout-nonce'] ) ? \sanitize_text_field( \wp_unslash( (string) $_POST['woocommerce-process-checkout-nonce'] ) ) : '';
		if ( '' === $nonce || ! \wp_verify_nonce( $nonce, 'woocommerce-process_checkout' ) ) {
			return;
		}

		if ( 'file' === $field->type ) {
			// phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- file uploads come from $_FILES.
			$file = $_FILES[ $post_key ] ?? null;
			if ( empty( $file['tmp_name'] ) || UPLOAD_ERR_OK !== (int) $file['error'] ) {
				return;
			}
			if ( ! \function_exists( 'wp_handle_upload' ) ) {
				require_once ABSPATH . 'wp-admin/includes/file.php';
			}
			$uploaded = \wp_handle_upload( $file, array( 'test_form' => false ) );
			if ( ! empty( $uploaded['error'] ) || empty( $uploaded['url'] ) ) {
				return;
			}
			$order->update_meta_data( '_cecfm_' . $post_key, \esc_url_raw( $uploaded['url'] ) );
			return;
		}

		if ( 'repeater' === $field->type ) {
			// phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- Sanitized later via specialized field handlers.
			$raw_rows = isset( $_POST[ $post_key ] ) ? \wp_unslash( $_POST[ $post_key ] ) : null;
			if ( ! is_array( $raw_rows ) ) {
				return;
			}
			$sanitized = array();
			foreach ( $raw_rows as $row ) {
				if ( ! is_array( $row ) ) {
					continue;
				}
				$clean_row = array();
				foreach ( $row as $sub_key => $sub_val ) {
					$clean_row[ sanitize_key( (string) $sub_key ) ] = sanitize_text_field( wp_unslash( is_array( $sub_val ) ? implode( ',', $sub_val ) : (string) $sub_val ) );
				}
				if ( ! empty( $clean_row ) ) {
					$sanitized[] = $clean_row;
				}
			}
			$order->update_meta_data( '_cecfm_' . $post_key, wp_json_encode( $sanitized ) );
			return;
		}

		if ( ! isset( $_POST[ $post_key ] ) ) {
			return;
		}
		// phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- Sanitized based on field type in the next step.
		$raw_val = \wp_unslash( $_POST[ $post_key ] );

		if ( \is_array( $raw_val ) ) {
			$value = \implode( ',', \array_map( 'sanitize_text_field', $raw_val ) );
		} else {
			$value = \sanitize_text_field( (string) $raw_val );
		}

		$order->update_meta_data( '_cecfm_' . $post_key, $value );
	}

	/**
	 * @param \CoderEmbassy\CheckoutFieldsManager\Models\CECFM_Field[] $fields
	 * @return array{suffix_map: array<string,string[]>, section_counts: array<string,int>}
	 */
	private function buildRepeatData( array $fields ): array {
		$suffix_map     = array();
		$section_counts = array();
		$sections       = $this->sectionRepository->findAll();

		foreach ( $sections as $section ) {
			if ( ! $section->enabled ) {
				continue;
			}

			$rule = isset( $section->meta['repeat_rule'] ) && is_array( $section->meta['repeat_rule'] )
				? $section->meta['repeat_rule']
				: array();

			if ( empty( $rule['enabled'] ) ) {
				continue;
			}

			$count    = $this->getRepeatCountFromCart( $rule );
			$start    = max( 1, (int) ( $rule['start_index'] ?? 1 ) );
			$name_pat = isset( $rule['name_suffix'] ) && '' !== (string) $rule['name_suffix']
				? (string) $rule['name_suffix']
				: '_{n}';

			$suffixes = array();
			for ( $i = $start; $i < $start + $count; $i++ ) {
				$suffixes[] = str_replace( '{n}', (string) $i, $name_pat );
			}

			$section_counts[ $section->section_key ] = $count;

			foreach ( $fields as $field ) {
				if ( (int) $field->section_id === (int) $section->id ) {
					$suffix_map[ $field->field_key ] = $suffixes;
				}
			}
		}

		return array(
			'suffix_map'     => $suffix_map,
			'section_counts' => $section_counts,
		);
	}

	/**
	 * @param array<string, mixed> $rule
	 */
	private function getRepeatCountFromCart( array $rule ): int {
		if ( ! \function_exists( 'WC' ) || ! \WC()->cart ) {
			return 1;
		}

		$repeat_for = (string) ( $rule['repeat_for'] ?? 'product_quantity' );
		$apply_to   = (string) ( $rule['apply_to'] ?? 'all' );
		$items      = \WC()->cart->get_cart();
		if ( ! is_array( $items ) || empty( $items ) ) {
			return 1;
		}

		if ( 'products' === $apply_to ) {
			$product_ids = array_values(
				array_filter(
					array_map( 'absint', is_array( $rule['product_ids'] ?? null ) ? $rule['product_ids'] : array() )
				)
			);
			if ( ! empty( $product_ids ) ) {
				$items = array_filter(
					$items,
					static function ( array $item ) use ( $product_ids ): bool {
						$product_id = (int) ( $item['product_id'] ?? 0 );
						return $product_id > 0 && in_array( $product_id, $product_ids, true );
					}
				);
			}
		} elseif ( 'categories' === $apply_to ) {
			$category_ids = array_values(
				array_filter(
					array_map( 'absint', is_array( $rule['category_ids'] ?? null ) ? $rule['category_ids'] : array() )
				)
			);
			if ( ! empty( $category_ids ) ) {
				$items = array_filter(
					$items,
					static function ( array $item ) use ( $category_ids ): bool {
						$product_id = (int) ( $item['product_id'] ?? 0 );
						if ( $product_id < 1 ) {
							return false;
						}
						$terms = get_the_terms( $product_id, 'product_cat' );
						if ( ! is_array( $terms ) || empty( $terms ) ) {
							return false;
						}
						foreach ( $terms as $term ) {
							if ( in_array( (int) $term->term_id, $category_ids, true ) ) {
								return true;
							}
						}
						return false;
					}
				);
			}
		}

		if ( empty( $items ) ) {
			return 1;
		}

		if ( 'cart_count' === $repeat_for ) {
			return max( 1, count( $items ) );
		}

		$total_qty = 0;
		foreach ( $items as $item ) {
			$total_qty += max( 0, (int) ( $item['quantity'] ?? 0 ) );
		}

		return max( 1, $total_qty );
	}

	/**
	 * @param \CoderEmbassy\CheckoutFieldsManager\Models\CECFM_Field[] $fields
	 * @return array<string, string[]>
	 */
	private function buildDisplaySuffixMap( array $fields, \WC_Order $order ): array {
		$suffix_map = array();
		$sections   = $this->sectionRepository->findAll();

		foreach ( $sections as $section ) {
			if ( ! $section->enabled ) {
				continue;
			}

			$rule = isset( $section->meta['repeat_rule'] ) && is_array( $section->meta['repeat_rule'] )
				? $section->meta['repeat_rule']
				: array();
			if ( empty( $rule['enabled'] ) ) {
				continue;
			}

			$count = (int) $order->get_meta( '_cecfm_repeat_' . $section->section_key );
			if ( $count < 1 ) {
				continue;
			}

			$start    = max( 1, (int) ( $rule['start_index'] ?? 1 ) );
			$name_pat = isset( $rule['name_suffix'] ) && '' !== (string) $rule['name_suffix']
				? (string) $rule['name_suffix']
				: '_{n}';

			$suffixes = array();
			for ( $i = $start; $i < $start + $count; $i++ ) {
				$suffixes[] = str_replace( '{n}', (string) $i, $name_pat );
			}

			foreach ( $fields as $field ) {
				if ( (int) $field->section_id === (int) $section->id ) {
					$suffix_map[ $field->field_key ] = $suffixes;
				}
			}
		}

		return $suffix_map;
	}

	/**
	 * Build a field_key => (name_suffix => label_suffix) map for display contexts.
	 *
	 * @param \CoderEmbassy\CheckoutFieldsManager\Models\CECFM_Field[] $fields
	 * @return array<string, array<string, string>>
	 */
	private function buildDisplayLabelSuffixMap( array $fields, \WC_Order $order ): array {
		$label_map = array();
		$sections  = $this->sectionRepository->findAll();

		foreach ( $sections as $section ) {
			if ( ! $section->enabled ) {
				continue;
			}

			$rule = isset( $section->meta['repeat_rule'] ) && is_array( $section->meta['repeat_rule'] )
				? $section->meta['repeat_rule']
				: array();
			if ( empty( $rule['enabled'] ) ) {
				continue;
			}

			$count = (int) $order->get_meta( '_cecfm_repeat_' . $section->section_key );
			if ( $count < 1 ) {
				continue;
			}

			$start      = max( 1, (int) ( $rule['start_index'] ?? 1 ) );
			$name_pat   = isset( $rule['name_suffix'] ) && '' !== (string) $rule['name_suffix']
				? (string) $rule['name_suffix']
				: '_{n}';
			$label_pat  = isset( $rule['label_suffix'] )
				? (string) $rule['label_suffix']
				: ' {n}';

			foreach ( $fields as $field ) {
				if ( (int) $field->section_id !== (int) $section->id ) {
					continue;
				}

				for ( $i = $start; $i < $start + $count; $i++ ) {
					$name_suffix  = str_replace( '{n}', (string) $i, $name_pat );
					$label_suffix = str_replace( '{n}', (string) $i, $label_pat );
					$label_map[ $field->field_key ][ $name_suffix ] = $label_suffix;
				}
			}
		}

		return $label_map;
	}

	// --- Front-end Display (Thank-you + Account) -----------------------------

	/**
	 * @param int|\WC_Order $order_or_id
	 */
	public function displayOrderMeta( int|\WC_Order $order_or_id ): void {
		try {
			$order = $order_or_id instanceof \WC_Order
				? $order_or_id
				: \wc_get_order( (int) $order_or_id );
			if ( ! $order instanceof \WC_Order ) {
				return;
			}

			$is_thankyou_context = \function_exists( 'is_order_received_page' ) && \is_order_received_page();
			$is_account_context  = \function_exists( 'is_account_page' ) && \is_account_page();
			$settings_raw        = \get_option( 'cecfm_settings', '' );
			$settings            = is_string( $settings_raw ) && '' !== $settings_raw ? ( \json_decode( $settings_raw, true ) ?? array() ) : array();
			$show_on_thankyou    = ! array_key_exists( 'show_on_thankyou_page', $settings ) || (bool) $settings['show_on_thankyou_page'];
			if ( $is_thankyou_context && ! $show_on_thankyou ) {
				return;
			}

			$rows = $this->buildMetaRows( $order, $is_thankyou_context, $is_account_context, $settings );
			if ( '' === $rows ) {
				return;
			}

			echo '<section class="cecfm-order-meta woocommerce-order-details">';
			echo '<h2 class="woocommerce-column__title">' . \esc_html__( 'Additional Information', 'coderembassy-checkout-fields-manager' ) . '</h2>';
			echo '<table class="woocommerce-table shop_table cecfm-order-meta-table"><tbody>';
			// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- $rows is composed of escaped fragments and passed through wp_kses_post for safety.
			echo \wp_kses_post( $rows );
			echo '</tbody></table>';
			echo '</section>';
		} catch ( \Throwable $e ) {
			if ( \defined( 'WP_DEBUG' ) && WP_DEBUG ) {
				// phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
				\error_log( '[Checkout Fields Manager] displayOrderMeta error: ' . $e->getMessage() );
			}
		}
	}

	public function displayAdminOrderMeta( \WC_Order $order ): void {
		try {
			$settings_raw = \get_option( 'cecfm_settings', '' );
			$settings     = is_string( $settings_raw ) && '' !== $settings_raw ? ( \json_decode( $settings_raw, true ) ?? array() ) : array();
			$items        = '';
			$fields       = $this->fieldRepository->findAll( array( 'enabled' => true ) );
			$suffix_map   = $this->buildDisplaySuffixMap( $fields, $order );
			$label_map    = $this->buildDisplayLabelSuffixMap( $fields, $order );

			foreach ( $fields as $field ) {
				if ( \in_array( $field->type, self::DISPLAY_ONLY_TYPES, true ) ) {
					continue;
				}
				if ( ! $this->shouldShowInAdmin( $field->meta ) ) {
					continue;
				}

				if ( isset( $suffix_map[ $field->field_key ] ) ) {
					foreach ( $suffix_map[ $field->field_key ] as $sfx ) {
						$raw_value = $order->get_meta( '_cecfm_' . $field->field_key . $sfx );
						if ( null === $raw_value || false === $raw_value || '' === (string) $raw_value ) {
							continue;
						}
						$label_sfx   = $label_map[ $field->field_key ][ $sfx ] ?? $sfx;
						$admin_value = 'date' === $field->type
							? $this->formatStoredDateForDisplay( (string) $raw_value, $settings )
							: (string) $raw_value;
						$items .= '<p><strong>' . \esc_html( $field->label . $label_sfx ) . ':</strong> ' . \esc_html( $admin_value ) . '</p>';
					}
					continue;
				}

				$raw_value = $order->get_meta( '_cecfm_' . $field->field_key );
				if ( null === $raw_value || false === $raw_value || '' === (string) $raw_value ) {
					continue;
				}
				$admin_value = 'date' === $field->type
					? $this->formatStoredDateForDisplay( (string) $raw_value, $settings )
					: (string) $raw_value;
				$items .= '<p><strong>' . \esc_html( $field->label ) . ':</strong> ' . \esc_html( $admin_value ) . '</p>';
			}

			if ( '' === $items ) {
				return;
			}

			echo '<div class="cecfm-order-meta-admin" style="margin-top:12px">';
			echo '<h4 style="margin:0 0 4px">' . \esc_html__( 'Custom Checkout Fields', 'coderembassy-checkout-fields-manager' ) . '</h4>';
			// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- $items is composed of escaped fragments and passed through wp_kses_post for safety.
			echo \wp_kses_post( $items );
			echo '</div>';
		} catch ( \Throwable $e ) {
			if ( \defined( 'WP_DEBUG' ) && WP_DEBUG ) {
				// phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
				\error_log( '[Checkout Fields Manager] displayAdminOrderMeta error: ' . $e->getMessage() );
			}
		}
	}

	/**
	 * Display custom field values in WooCommerce transactional emails.
	 * Hooked to: woocommerce_email_order_meta (WC_Order $order, bool $sent_to_admin, bool $plain_text)
	 */
	public function displayEmailMeta( \WC_Order $order, bool $sent_to_admin, bool $plain_text ): void {
		try {
			$fields     = $this->fieldRepository->findAll( array( 'enabled' => true ) );
			$settings   = \get_option( 'cecfm_settings', '' );
			$settings   = is_string( $settings ) && '' !== $settings ? ( \json_decode( $settings, true ) ?? array() ) : array();
			$heading    = ! empty( $settings['email_section_heading'] ) ? $settings['email_section_heading'] : \__( 'Additional Information', 'coderembassy-checkout-fields-manager' );
			$skip_types = array( 'heading', 'paragraph', 'hidden' );
			$rows       = array();
			$suffix_map = $this->buildDisplaySuffixMap( $fields, $order );
			$label_map  = $this->buildDisplayLabelSuffixMap( $fields, $order );

			foreach ( $fields as $field ) {
				if ( \in_array( $field->type, $skip_types, true ) ) {
					continue;
				}

				if ( ! $this->shouldShowInEmail( $field->meta ) ) {
					continue;
				}

				if ( isset( $suffix_map[ $field->field_key ] ) ) {
					foreach ( $suffix_map[ $field->field_key ] as $sfx ) {
						$sfx_value = $order->get_meta( '_cecfm_' . $field->field_key . $sfx );
						if ( null === $sfx_value || false === $sfx_value || '' === (string) $sfx_value ) {
							continue;
						}
						$label_sfx = $label_map[ $field->field_key ][ $sfx ] ?? $sfx;
						$disp      = 'date' === $field->type
							? $this->formatStoredDateForDisplay( (string) $sfx_value, $settings )
							: (string) $sfx_value;
						$rows[]    = array(
							'label'   => \esc_html( $field->label . $label_sfx ),
							'display' => \esc_html( $disp ),
						);
					}
					continue;
				}

				$raw_value = $order->get_meta( '_cecfm_' . $field->field_key );
				if ( null === $raw_value || false === $raw_value || '' === (string) $raw_value ) {
					continue;
				}

				if ( 'checkbox' === $field->type ) {
					$display = ( '1' === (string) $raw_value ) ? \__( 'Yes', 'coderembassy-checkout-fields-manager' ) : '';
					if ( '' === $display ) {
						continue;
					}
				} elseif ( 'date' === $field->type ) {
					$display = \esc_html( $this->formatStoredDateForDisplay( (string) $raw_value, $settings ) );
				} elseif ( 'repeater' === $field->type ) {
					$decoded = is_string( $raw_value ) ? json_decode( (string) $raw_value, true ) : null;
					if ( ! is_array( $decoded ) || empty( $decoded ) ) {
						continue;
					}
					$lines = array();
					foreach ( $decoded as $i => $row ) {
						if ( ! is_array( $row ) ) {
							continue;
						}
						$pair_strs = array();
						foreach ( $row as $k => $v ) {
							$pair_strs[] = (string) $k . ': ' . (string) $v;
						}
						if ( empty( $pair_strs ) ) {
							continue;
						}
						$lines[] = \sprintf(
							/* translators: %d: repeater row number (1-based). */
							\__( 'Row %d', 'coderembassy-checkout-fields-manager' ),
							(int) $i + 1
						) . ': ' . implode( ' | ', $pair_strs );
					}
					if ( empty( $lines ) ) {
						continue;
					}
					$display = $plain_text
						? implode( "\n", $lines )
						: implode( '<br />', array_map( static fn( $line ) => \esc_html( (string) $line ), $lines ) );
				} elseif ( \is_array( $raw_value ) ) {
					$display = \esc_html( \implode( ', ', \array_map( 'strval', $raw_value ) ) );
				} else {
					$display = \esc_html( (string) $raw_value );
				}

				$rows[] = array(
					'label'   => \esc_html( $field->label ),
					'display' => $display,
				);
			}

			if ( empty( $rows ) ) {
				return;
			}

			if ( $plain_text ) {
				echo "\n" . \esc_html( $heading ) . "\n";
				echo \esc_html( str_repeat( '-', 30 ) ) . "\n";
				foreach ( $rows as $row ) {
					// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Plain text output for emails, labels and displays are pre-sanitized.
					echo \esc_html( (string) $row['label'] ) . ': ' . \esc_html( (string) $row['display'] ) . "\n";
				}
			} else {
				echo '<h2>' . \esc_html( $heading ) . '</h2>';
				echo '<table cellspacing="0" cellpadding="6" border="1" style="width:100%;border-collapse:collapse;border:1px solid #eee;">';
				echo '<tbody>';
				foreach ( $rows as $row ) {
					echo '<tr>';
					echo '<th scope="row" style="text-align:left;border:1px solid #eee;padding:12px;color:#636363;">' . \esc_html( (string) $row['label'] ) . '</th>';
					echo '<td style="text-align:left;border:1px solid #eee;padding:12px;color:#636363;">' . \esc_html( (string) $row['display'] ) . '</td>';
					echo '</tr>';
				}
				echo '</tbody></table>';
			}
		} catch ( \Throwable $e ) {
			if ( \defined( 'WP_DEBUG' ) && WP_DEBUG ) {
				// phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
				\error_log( '[Checkout Fields Manager] displayEmailMeta error: ' . $e->getMessage() );
			}
		}
	}

	// --- Helpers --------------------------------------------------------------

	private function buildContext(): array {
		return $this->context->build();
	}

	private function buildMetaRows( \WC_Order $order, bool $is_thankyou_context, bool $is_account_context, array $settings = array() ): string {
		$rows       = '';
		$fields     = $this->fieldRepository->findAll( array( 'enabled' => true ) );
		$suffix_map = $this->buildDisplaySuffixMap( $fields, $order );
		$label_map  = $this->buildDisplayLabelSuffixMap( $fields, $order );

		foreach ( $fields as $field ) {
			if ( \in_array( $field->type, self::DISPLAY_ONLY_TYPES, true ) ) {
				continue;
			}
			if ( ! $this->shouldShowOnFrontend( $field->meta, $is_thankyou_context, $is_account_context ) ) {
				continue;
			}

			if ( isset( $suffix_map[ $field->field_key ] ) ) {
				foreach ( $suffix_map[ $field->field_key ] as $sfx ) {
					$raw_value = $order->get_meta( '_cecfm_' . $field->field_key . $sfx );
					if ( null === $raw_value || false === $raw_value || '' === (string) $raw_value ) {
						continue;
					}
					$label_sfx = $label_map[ $field->field_key ][ $sfx ] ?? $sfx;

					if ( 'repeater' === $field->type ) {
						$decoded = is_string( $raw_value ) ? json_decode( $raw_value, true ) : null;
						if ( ! is_array( $decoded ) || empty( $decoded ) ) {
							continue;
						}
						$rows .= '<tr><th scope="row" colspan="2" style="padding-bottom:2px">' . \esc_html( $field->label . $label_sfx ) . '</th></tr>';
						foreach ( $decoded as $i => $row ) {
							if ( ! is_array( $row ) ) {
								continue;
							}
							$row_label = \sprintf(
								/* translators: %d: repeater row number (1-based). */
								\__( 'Row %d', 'coderembassy-checkout-fields-manager' ),
								$i + 1
							);
							$pairs = array();
							foreach ( $row as $k => $v ) {
								$pairs[] = \esc_html( (string) $k ) . ': ' . \esc_html( (string) $v );
							}
							$rows .= '<tr><td style="padding-left:16px;color:#888">' . \esc_html( $row_label ) . '</td><td>' . implode( ' &bull; ', $pairs ) . '</td></tr>';
						}
					} else {
						$cell  = 'date' === $field->type
							? $this->formatStoredDateForDisplay( (string) $raw_value, $settings )
							: (string) $raw_value;
						$rows .= '<tr><th scope="row">' . \esc_html( $field->label . $label_sfx ) . '</th><td>' . \esc_html( $cell ) . '</td></tr>';
					}
				}
				continue;
			}

			$raw_value = $order->get_meta( '_cecfm_' . $field->field_key );
			if ( null === $raw_value || false === $raw_value || '' === (string) $raw_value ) {
				continue;
			}

			if ( 'repeater' === $field->type ) {
				$decoded = is_string( $raw_value ) ? json_decode( $raw_value, true ) : null;
				if ( ! is_array( $decoded ) || empty( $decoded ) ) {
					continue;
				}
				$rows .= '<tr><th scope="row" colspan="2" style="padding-bottom:2px">' . \esc_html( $field->label ) . '</th></tr>';
				foreach ( $decoded as $i => $row ) {
					if ( ! is_array( $row ) ) {
						continue;
					}
					$row_label = \sprintf(
						/* translators: %d: repeater row number (1-based). */
						\__( 'Row %d', 'coderembassy-checkout-fields-manager' ),
						$i + 1
					);
					$pairs = array();
					foreach ( $row as $k => $v ) {
						$pairs[] = \esc_html( (string) $k ) . ': ' . \esc_html( (string) $v );
					}
					$rows .= '<tr><td style="padding-left:16px;color:#888">' . \esc_html( $row_label ) . '</td><td>' . implode( ' &bull; ', $pairs ) . '</td></tr>';
				}
				continue;
			}

			$cell  = 'date' === $field->type
				? $this->formatStoredDateForDisplay( (string) $raw_value, $settings )
				: (string) $raw_value;
			$rows .= '<tr><th scope="row">' . \esc_html( $field->label ) . '</th><td>' . \esc_html( $cell ) . '</td></tr>';
		}

		return $rows;
	}

	/**
	 * Format a stored date (typically Y-m-d from checkout) using the plugin date_format setting when set.
	 *
	 * @param array<string, mixed> $settings Decoded cecfm_settings.
	 */
	private function formatStoredDateForDisplay( string $stored, array $settings ): string {
		$stored = trim( $stored );
		if ( '' === $stored ) {
			return '';
		}
		$ts = strtotime( $stored );
		if ( false === $ts ) {
			return $stored;
		}
		$fmt = '';
		if ( isset( $settings['date_format'] ) && is_string( $settings['date_format'] ) ) {
			$fmt = trim( $settings['date_format'] );
		}
		if ( '' === $fmt ) {
			$fmt = (string) \get_option( 'date_format', 'Y-m-d' );
		}

		return \date_i18n( $fmt, $ts );
	}

	/**
	 * @param array<string, mixed> $meta
	 */
	private function shouldShowOnFrontend( array $meta, bool $is_thankyou_context, bool $is_account_context ): bool {
		if ( $is_thankyou_context ) {
			return $this->metaBool( $meta, 'show_on_thankyou', true );
		}
		if ( $is_account_context ) {
			return $this->metaBool( $meta, 'show_on_order_page', true );
		}
		return true;
	}

	/**
	 * @param array<string, mixed> $meta
	 */
	private function shouldShowInEmail( array $meta ): bool {
		return $this->metaBool( $meta, 'show_in_email', true );
	}

	/**
	 * @param array<string, mixed> $meta
	 */
	private function shouldShowInAdmin( array $meta ): bool {
		return $this->metaBool( $meta, 'show_in_admin_order', true );
	}

	/**
	 * @param array<string, mixed> $meta
	 */
	private function metaBool( array $meta, string $key, bool $default ): bool {
		if ( ! array_key_exists( $key, $meta ) ) {
			return $default;
		}
		$value = $meta[ $key ];
		return ! ( false === $value || '0' === (string) $value || '' === (string) $value );
	}
}


