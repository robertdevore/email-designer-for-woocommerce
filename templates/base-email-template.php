<?php
/**
 * Base Email Template
 *
 * Outputs only the content from the assigned Email Template CPT.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

// If the $email variable is not set, try to retrieve it from the global.
if ( ! isset( $email ) && isset( $GLOBALS['current_email'] ) ) {
	$email = $GLOBALS['current_email'];
}

$saved_templates = get_option( 'email_designer_templates', [] );
$email_id        = isset( $email->id ) ? $email->id : '';
$template_id     = isset( $saved_templates[ $email_id ] ) ? absint( $saved_templates[ $email_id ] ) : 0;

if ( $template_id ) {
	$template_post = get_post( $template_id );
	if ( $template_post && 'email_template' === $template_post->post_type ) {

		// Retrieve and filter the content.
		$content = apply_filters( 'the_content', $template_post->post_content );

		/**
		 * Load the core block library CSS files.
		 */
		$block_css_paths = [
			ABSPATH . WPINC . '/css/dist/block-library/style.css',
			ABSPATH . WPINC . '/css/dist/block-library/theme.css',
		];
		$block_css = '';
		foreach ( $block_css_paths as $path ) {
			if ( file_exists( $path ) ) {
				$block_css .= file_get_contents( $path ) . "\n";
			}
		}

		// Retrieve any custom CSS from your settings.
		$custom_css = get_option( 'email_designer_custom_css', '' );

		// Additional CSS override to force a sans-serif font on groups.
		$additional_css = "
.wp-block-group {
    font-family: 'Helvetica Neue', Helvetica, Roboto, Arial, sans-serif !important;
}
";

		// Combine all CSS.
		$combined_css = $block_css . "\n" . $custom_css . "\n" . $additional_css;

		/**
		 * Get CSS variables from theme.json.
		 *
		 * Checks first in the child theme then in the parent theme.
		 * Returns an associative array with keys like "--wp--preset--color--primary"
		 * (all lowercased) and their hex color values.
		 */
		if ( ! function_exists( 'get_css_variables_from_theme_json' ) ) {
			function get_css_variables_from_theme_json() {
				$theme_json_path = get_stylesheet_directory() . '/theme.json';
				if ( ! file_exists( $theme_json_path ) ) {
					$theme_json_path = get_template_directory() . '/theme.json';
				}
				if ( ! file_exists( $theme_json_path ) ) {
					error_log( "theme.json not found in theme directories." );
					return [];
				}
				$theme_json = json_decode( file_get_contents( $theme_json_path ), true );
				if ( ! isset( $theme_json['settings']['color']['palette'] ) ) {
					error_log( "Color palette not found in theme.json." );
					return [];
				}
				$variables = [];
				foreach ( $theme_json['settings']['color']['palette'] as $color ) {
					if ( isset( $color['slug'], $color['color'] ) ) {
						$slug = strtolower( $color['slug'] );
						$variables[ '--wp--preset--color--' . $slug ] = $color['color'];
					}
				}
				error_log( 'Extracted CSS variables: ' . print_r( $variables, true ) );
				return $variables;
			}
		}

		/**
		 * Replace CSS variable references in a CSS string.
		 */
		if ( ! function_exists( 'replace_css_variables_in_css' ) ) {
			function replace_css_variables_in_css( $css, $variables ) {
				foreach ( $variables as $name => $value ) {
					$css = str_replace( $name, $value, $css );
					$css = str_replace( "var($name)", $value, $css );
				}
				return $css;
			}
		}

		/**
		 * Process the HTML to add inline styles based on palette classes.
		 *
		 * For non-heading elements, each class matching either:
		 *   - /^has-([a-z0-9-]+)-background-color$/i  → sets background-color
		 *   - /^has-([a-z0-9-]+)-color$/i             → sets color
		 *
		 * For heading elements (h1–h6), if a color mapping is detected that is not generic (i.e. not "text" or "link"),
		 * then that mapping is used; otherwise no inline color is applied so the heading will inherit its parent’s color.
		 */
		if ( ! function_exists( 'extract_styles_from_class_names' ) ) {
			function extract_styles_from_class_names( $html, $variables ) {
				// Define fallback mappings if needed.
				$fallbacks = [
					'text' => 'base',
					'link' => 'base',
				];

				libxml_use_internal_errors( true );
				$dom = new DOMDocument();
				$loaded = $dom->loadHTML( mb_convert_encoding( $html, 'HTML-ENTITIES', 'UTF-8' ) );
				if ( ! $loaded ) {
					error_log( "DOMDocument failed to load HTML." );
					return $html;
				}
				libxml_clear_errors();

				$xpath = new DOMXPath( $dom );
				$elements = $xpath->query( '//*[@class]' );
				foreach ( $elements as $element ) {
					$nodeName = strtolower( $element->nodeName );
					$isHeading = in_array( $nodeName, ['h1','h2','h3','h4','h5','h6'] );
					$classAttr = $element->getAttribute( 'class' );
					$classes = preg_split( '/\s+/', $classAttr );
					// For headings, collect color mappings in an array.
					$colorMappings = [];
					// For all elements, collect non-color declarations (e.g. background-color).
					$otherStyles = [];
					foreach ( $classes as $class ) {
						$slug = '';
						$styleType = '';
						// Match background color classes.
						if ( preg_match( '/^has-([a-z0-9-]+)-background-color$/i', $class, $matches ) ) {
							$slug = strtolower( $matches[1] );
							$styleType = 'background-color';
						} 
						// Match text color classes.
						elseif ( preg_match( '/^has-([a-z0-9-]+)-color$/i', $class, $matches ) ) {
							$slug = strtolower( $matches[1] );
							$styleType = 'color';
						} else {
							continue;
						}
						// Skip "has-link-color" if element is not an anchor.
						if ( strtolower( $class ) === 'has-link-color' && $nodeName !== 'a' ) {
							continue;
						}
						$variable_key = '--wp--preset--color--' . $slug;
						if ( ! isset( $variables[ $variable_key ] ) ) {
							if ( isset( $fallbacks[ $slug ] ) ) {
								$fallback_key = '--wp--preset--color--' . $fallbacks[ $slug ];
								if ( isset( $variables[ $fallback_key ] ) ) {
									$colorValue = $variables[ $fallback_key ];
								} else {
									error_log( "Fallback variable '$fallback_key' not found for slug: $slug (class: $class)" );
									continue;
								}
							} else {
								if ( $slug === 'white' ) {
									$colorValue = '#fff';
								} else {
									error_log( "CSS variable not found for slug: $slug (class: $class)" );
									continue;
								}
							}
						} else {
							$colorValue = $variables[ $variable_key ];
						}
						error_log( "Mapping class '$class' to $styleType: $colorValue" );
						$decl = "$styleType: $colorValue;";
						if ( $styleType === 'color' ) {
							// For headings, collect all text color mappings.
							$colorMappings[$slug] = $decl;
						} else {
							$otherStyles[] = $decl;
						}
					}
					// Decide on which color mapping to apply.
					if ( $isHeading ) {
						// If there is any color mapping whose slug is not "text" or "link", use that.
						$selectedColor = '';
						foreach ( $colorMappings as $slug => $decl ) {
							if ( $slug !== 'text' && $slug !== 'link' ) {
								$selectedColor = $decl;
								break;
							}
						}
						// If no explicit custom heading color was set (i.e. only generic "text" or "link" mappings exist),
						// then do not add any inline color for the heading so it will inherit the parent's text color.
						$finalColorStyle = $selectedColor;
					} else {
						// For non-heading elements, simply concatenate all text color declarations.
						$finalColorStyle = implode(' ', $colorMappings);
					}
					$allStyles = trim( $finalColorStyle . ' ' . implode(' ', $otherStyles) );
					if ( ! empty( $allStyles ) ) {
						$existingStyle = $element->getAttribute( 'style' );
						if ( ! empty( $existingStyle ) && substr( trim( $existingStyle ), -1 ) !== ';' ) {
							$existingStyle .= ';';
						}
						$mergedStyle = trim( $existingStyle . ' ' . $allStyles );
						$element->setAttribute( 'style', $mergedStyle );
					}
				}
				$body = $dom->getElementsByTagName( 'body' )->item( 0 );
				$innerHTML = '';
				foreach ( $body->childNodes as $child ) {
					$innerHTML .= $dom->saveHTML( $child );
				}
				return $innerHTML;
			}
		}

		/**
		 * Final cleanup for headings.
		 *
		 * As an extra safety step, remove an inline "color:" declaration if it equals the default blue (#287aa3),
		 * but only if no other explicit heading color mapping was applied.
		 */
		if ( ! function_exists( 'final_cleanup_headings' ) ) {
			function final_cleanup_headings( $html ) {
				libxml_use_internal_errors( true );
				$dom = new DOMDocument();
				$dom->loadHTML( mb_convert_encoding( $html, 'HTML-ENTITIES', 'UTF-8' ) );
				libxml_clear_errors();
				$defaultHeadingColor = '#287aa3';
				for ( $i = 1; $i <= 6; $i++ ) {
					$headings = $dom->getElementsByTagName( "h$i" );
					foreach ( $headings as $heading ) {
						$style = $heading->getAttribute( "style" );
						// Remove a "color:" declaration if it equals the default blue.
						$newStyle = preg_replace( '/\s*color\s*:\s*' . preg_quote($defaultHeadingColor, '/') . '\s*;?/i', '', $style );
						$heading->setAttribute( "style", trim( $newStyle ) );
					}
				}
				$body = $dom->getElementsByTagName( "body" )->item( 0 );
				$finalHTML = "";
				foreach ( $body->childNodes as $child ) {
					$finalHTML .= $dom->saveHTML( $child );
				}
				return $finalHTML;
			}
		}

		// Get CSS variables and replace CSS variable references.
		$css_variables = get_css_variables_from_theme_json();
		$combined_css  = replace_css_variables_in_css( $combined_css, $css_variables );

		// Process the content to add inline styles based on palette classes.
		$content = extract_styles_from_class_names( $content, $css_variables );

		// Inline the combined CSS using Emogrifier.
		try {
			$inlined_content = \Pelago\Emogrifier\CssInliner::fromHtml( $content )
				->inlineCss( $combined_css )
				->render();
		} catch ( Exception $e ) {
			error_log( 'Emogrifier error: ' . $e->getMessage() );
			$inlined_content = $content;
		}

		// Final cleanup of headings.
		$final_html = final_cleanup_headings( $inlined_content );

		echo do_shortcode( $final_html );
		return;
	}
}

// Fallback output if no custom template is assigned.
echo '<p>' . esc_html__( 'No content available for this email.', 'email-designer-for-woocommerce' ) . '</p>';
