<?php
/**
 * Plugin Name: Content Type API Demo
 * Plugin URI: https://github.com/WordPress/wordpress-develop/pull/10617
 * Description: Demonstrates the Declarative Content Modeling RFC - register_content_type() API for WordPress 7.0
 * Version: 1.0.0
 * Author: WordPress Core Contributors
 * License: GPL-2.0-or-later
 *
 * This plugin bundles the Content Type API proposed in the RFC for demonstration purposes.
 * In WordPress 7.0, this API will be part of core.
 */

// Prevent direct access.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/*
 * =============================================================================
 * CONTENT TYPE API (Proposed for WordPress 7.0 Core)
 * =============================================================================
 */

/**
 * Core class representing a content type with declarative field definitions.
 */
final class WP_Content_Type {

	/**
	 * Content type name (slug).
	 *
	 * @var string
	 */
	public $name;

	/**
	 * Normalized field definitions.
	 *
	 * @var array
	 */
	private $fields = array();

	/**
	 * UI configuration.
	 *
	 * @var array
	 */
	private $ui = array();

	/**
	 * Original arguments passed during registration.
	 *
	 * @var array
	 */
	private $args = array();

	/**
	 * Valid field types.
	 *
	 * @var array
	 */
	private static $valid_types = array( 'string', 'integer', 'number', 'boolean', 'array', 'object' );

	/**
	 * Constructor.
	 *
	 * @param string $name Content type name.
	 * @param array  $args Content type arguments.
	 */
	public function __construct( $name, $args = array() ) {
		$this->name = $name;
		$this->args = $args;

		$fields = isset( $args['fields'] ) ? $args['fields'] : array();
		$this->fields = $this->normalize_fields( $fields );
		$this->ui = isset( $args['ui'] ) ? $args['ui'] : array();
	}

	/**
	 * Validates the field definitions.
	 *
	 * @return true|WP_Error True on success, WP_Error on failure.
	 */
	public function validate_fields() {
		foreach ( $this->fields as $key => $field ) {
			if ( ! in_array( $field['type'], self::$valid_types, true ) ) {
				return new WP_Error(
					'invalid_field_type',
					sprintf(
						/* translators: 1: Field key, 2: Invalid type, 3: Valid types */
						__( 'Invalid field type "%2$s" for field "%1$s". Valid types are: %3$s.' ),
						$key,
						$field['type'],
						implode( ', ', self::$valid_types )
					)
				);
			}
		}
		return true;
	}

	/**
	 * Registers all meta fields for this content type.
	 */
	public function register_meta_fields() {
		foreach ( $this->fields as $key => $field ) {
			$meta_args = array(
				'type'              => $field['type'],
				'single'            => $field['single'],
				'show_in_rest'      => $this->build_rest_schema( $field ),
				'default'           => $field['default'],
				'sanitize_callback' => $field['sanitize_callback'] ? $field['sanitize_callback'] : $this->get_sanitize_callback( $field ),
				'auth_callback'     => $field['auth_callback'],
				'revisions_enabled' => $field['revisions_enabled'],
			);

			if ( ! empty( $field['description'] ) ) {
				$meta_args['description'] = $field['description'];
			}

			register_post_meta( $this->name, $key, $meta_args );
		}
	}

	/**
	 * Gets all field definitions.
	 *
	 * @return array Field definitions.
	 */
	public function get_fields() {
		return $this->fields;
	}

	/**
	 * Gets a single field definition.
	 *
	 * @param string $key Field key.
	 * @return array|null Field definition or null if not found.
	 */
	public function get_field( $key ) {
		return isset( $this->fields[ $key ] ) ? $this->fields[ $key ] : null;
	}

	/**
	 * Gets the UI configuration.
	 *
	 * @return array UI configuration.
	 */
	public function get_ui() {
		return $this->ui;
	}

	/**
	 * Gets required field names.
	 *
	 * @return array List of required field keys.
	 */
	public function get_required_fields() {
		$required = array();
		foreach ( $this->fields as $key => $field ) {
			if ( ! empty( $field['required'] ) ) {
				$required[] = $key;
			}
		}
		return $required;
	}

	/**
	 * Converts the content type to an array representation.
	 *
	 * @return array Content type data.
	 */
	public function to_array() {
		return array(
			'name'   => $this->name,
			'fields' => $this->fields,
			'ui'     => $this->ui,
		);
	}

	/**
	 * Sanitizes a value based on its type.
	 *
	 * @param mixed  $value Value to sanitize.
	 * @param string $type  Field type.
	 * @return mixed Sanitized value.
	 */
	public static function sanitize_by_type( $value, $type ) {
		switch ( $type ) {
			case 'string':
				return sanitize_text_field( $value );
			case 'integer':
				return intval( $value );
			case 'number':
				return floatval( $value );
			case 'boolean':
				return rest_sanitize_boolean( $value );
			case 'array':
				return is_array( $value ) ? $value : array();
			case 'object':
				return is_array( $value ) ? $value : array();
			default:
				return $value;
		}
	}

	/**
	 * Normalizes field definitions with defaults.
	 *
	 * @param array $fields Raw field definitions.
	 * @return array Normalized field definitions.
	 */
	private function normalize_fields( $fields ) {
		$normalized = array();

		foreach ( $fields as $key => $field ) {
			if ( is_string( $field ) ) {
				$key   = $field;
				$field = array();
			}

			$type = isset( $field['type'] ) ? $field['type'] : 'string';
			$enum = isset( $field['enum'] ) ? $field['enum'] : array();

			$default = $this->get_default_for_type( $type );
			if ( ! empty( $enum ) && ! isset( $field['default'] ) ) {
				$default = $enum[0];
			}

			$normalized[ $key ] = wp_parse_args(
				$field,
				array(
					'type'              => 'string',
					'single'            => true,
					'show_in_rest'      => true,
					'label'             => $this->generate_label( $key ),
					'description'       => '',
					'required'          => false,
					'default'           => $default,
					'sanitize_callback' => null,
					'auth_callback'     => null,
					'control'           => $this->get_default_control( $type ),
					'enum'              => array(),
					'revisions_enabled' => false,
				)
			);
		}

		return $normalized;
	}

	/**
	 * Gets the default value for a field type.
	 *
	 * @param string $type Field type.
	 * @return mixed Default value appropriate for the type.
	 */
	private function get_default_for_type( $type ) {
		$defaults = array(
			'string'  => '',
			'integer' => 0,
			'number'  => 0,
			'boolean' => false,
			'array'   => array(),
			'object'  => array(),
		);

		return isset( $defaults[ $type ] ) ? $defaults[ $type ] : '';
	}

	/**
	 * Generates a human-readable label from a field key.
	 *
	 * @param string $key Field key.
	 * @return string Generated label.
	 */
	private function generate_label( $key ) {
		return ucwords( str_replace( array( '_', '-' ), ' ', $key ) );
	}

	/**
	 * Gets the default control type for a field type.
	 *
	 * @param string $type Field type.
	 * @return string Control type.
	 */
	private function get_default_control( $type ) {
		$controls = array(
			'string'  => 'text',
			'integer' => 'number',
			'number'  => 'number',
			'boolean' => 'checkbox',
			'array'   => 'textarea',
			'object'  => 'textarea',
		);

		return isset( $controls[ $type ] ) ? $controls[ $type ] : 'text';
	}

	/**
	 * Builds REST API schema for a field.
	 *
	 * @param array $field Field definition.
	 * @return array REST schema configuration.
	 */
	private function build_rest_schema( $field ) {
		if ( false === $field['show_in_rest'] ) {
			return false;
		}

		$schema = array(
			'type' => $field['type'],
		);

		if ( ! empty( $field['description'] ) ) {
			$schema['description'] = $field['description'];
		}

		if ( array_key_exists( 'default', $field ) ) {
			$type_default = $this->get_default_for_type( $field['type'] );
			if ( $field['default'] !== $type_default ) {
				$schema['default'] = $field['default'];
			}
		}

		if ( ! empty( $field['enum'] ) ) {
			$schema['enum'] = $field['enum'];
		}

		if ( ! empty( $field['required'] ) ) {
			$schema['required'] = true;
		}

		if ( 'array' === $field['type'] ) {
			$schema['items'] = isset( $field['items'] ) ? $field['items'] : array( 'type' => 'string' );
		}

		if ( 'object' === $field['type'] ) {
			$schema['additionalProperties'] = true;
		}

		return array(
			'schema' => $schema,
		);
	}

	/**
	 * Gets a sanitize callback based on field type.
	 *
	 * @param array $field Field definition.
	 * @return callable Sanitize callback.
	 */
	private function get_sanitize_callback( $field ) {
		$type = $field['type'];
		$enum = $field['enum'];

		return function ( $value ) use ( $type, $enum ) {
			$sanitized = WP_Content_Type::sanitize_by_type( $value, $type );

			if ( ! empty( $enum ) && ! in_array( $sanitized, $enum, true ) ) {
				return $enum[0];
			}

			return $sanitized;
		};
	}
}

// Global storage for content types.
global $wp_content_types;
$wp_content_types = array();

/**
 * Registers a content type with declarative field definitions.
 *
 * @param string $content_type Content type name (1-20 characters, lowercase alphanumeric and underscores).
 * @param array  $args         Content type arguments.
 * @return WP_Content_Type|WP_Error Content type object on success, WP_Error on failure.
 */
function register_content_type( $content_type, $args = array() ) {
	global $wp_content_types;

	// Validate content type name length.
	if ( empty( $content_type ) || strlen( $content_type ) > 20 ) {
		_doing_it_wrong(
			__FUNCTION__,
			__( 'Content type names must be between 1 and 20 characters.' ),
			'7.0.0'
		);
		return new WP_Error(
			'content_type_length_invalid',
			__( 'Content type names must be between 1 and 20 characters.' )
		);
	}

	// Check for duplicates.
	if ( isset( $wp_content_types[ $content_type ] ) ) {
		_doing_it_wrong(
			__FUNCTION__,
			sprintf(
				/* translators: %s: Content type name */
				__( 'Content type "%s" is already registered.' ),
				$content_type
			),
			'7.0.0'
		);
		return new WP_Error(
			'content_type_exists',
			sprintf(
				/* translators: %s: Content type name */
				__( 'Content type "%s" is already registered.' ),
				$content_type
			)
		);
	}

	/**
	 * Filters the arguments for registering a content type.
	 *
	 * @param array  $args         Content type arguments.
	 * @param string $content_type Content type name.
	 */
	$args = apply_filters( 'register_content_type_args', $args, $content_type );

	// Create content type object.
	$content_type_object = new WP_Content_Type( $content_type, $args );

	// Validate fields.
	$validation = $content_type_object->validate_fields();
	if ( is_wp_error( $validation ) ) {
		return $validation;
	}

	// Ensure show_in_rest is true by default.
	if ( ! isset( $args['show_in_rest'] ) ) {
		$args['show_in_rest'] = true;
	}

	// Register the underlying post type.
	$post_type_args = $args;
	unset( $post_type_args['fields'], $post_type_args['ui'] );

	$post_type = register_post_type( $content_type, $post_type_args );

	if ( is_wp_error( $post_type ) ) {
		return $post_type;
	}

	// Register meta fields.
	$content_type_object->register_meta_fields();

	// Store content type.
	$wp_content_types[ $content_type ] = $content_type_object;

	/**
	 * Fires after a content type is registered.
	 *
	 * @param string          $content_type        Content type name.
	 * @param WP_Content_Type $content_type_object Content type object.
	 * @param array           $args                Original arguments.
	 */
	do_action( 'registered_content_type', $content_type, $content_type_object, $args );

	return $content_type_object;
}

/**
 * Unregisters a content type.
 *
 * @param string $content_type Content type name.
 * @return true|WP_Error True on success, WP_Error on failure.
 */
function unregister_content_type( $content_type ) {
	global $wp_content_types;

	if ( ! isset( $wp_content_types[ $content_type ] ) ) {
		return new WP_Error(
			'content_type_not_exists',
			__( 'Content type does not exist.' )
		);
	}

	$content_type_object = $wp_content_types[ $content_type ];

	// Unregister meta fields.
	foreach ( $content_type_object->get_fields() as $key => $field ) {
		unregister_post_meta( $content_type, $key );
	}

	// Unregister post type.
	unregister_post_type( $content_type );

	// Remove from storage.
	unset( $wp_content_types[ $content_type ] );

	/**
	 * Fires after a content type is unregistered.
	 *
	 * @param string          $content_type        Content type name.
	 * @param WP_Content_Type $content_type_object Content type object.
	 */
	do_action( 'unregistered_content_type', $content_type, $content_type_object );

	return true;
}

/**
 * Retrieves a content type object.
 *
 * @param string $content_type Content type name.
 * @return WP_Content_Type|null Content type object or null if not found.
 */
function get_content_type_object( $content_type ) {
	global $wp_content_types;
	return isset( $wp_content_types[ $content_type ] ) ? $wp_content_types[ $content_type ] : null;
}

/**
 * Checks if a content type exists.
 *
 * @param string $content_type Content type name.
 * @return bool True if exists, false otherwise.
 */
function content_type_exists( $content_type ) {
	global $wp_content_types;
	return isset( $wp_content_types[ $content_type ] );
}

/**
 * Retrieves all registered content types.
 *
 * @param array  $args   Optional. Query arguments.
 * @param string $output Optional. 'names' or 'objects'. Default 'names'.
 * @return array Content type names or objects.
 */
function get_content_types( $args = array(), $output = 'names' ) {
	global $wp_content_types;

	if ( 'objects' === $output ) {
		return $wp_content_types;
	}

	return array_keys( $wp_content_types );
}

/**
 * Retrieves field definitions for a content type.
 *
 * @param string $content_type Content type name.
 * @return array|null Field definitions or null if content type not found.
 */
function get_content_type_fields( $content_type ) {
	$content_type_object = get_content_type_object( $content_type );
	return $content_type_object ? $content_type_object->get_fields() : null;
}

/**
 * Retrieves a single field definition.
 *
 * @param string $content_type Content type name.
 * @param string $field_key    Field key.
 * @return array|null Field definition or null if not found.
 */
function get_content_type_field( $content_type, $field_key ) {
	$content_type_object = get_content_type_object( $content_type );
	return $content_type_object ? $content_type_object->get_field( $field_key ) : null;
}

/**
 * Retrieves UI configuration for a content type.
 *
 * @param string $content_type Content type name.
 * @return array|null UI configuration or null if content type not found.
 */
function get_content_type_ui( $content_type ) {
	$content_type_object = get_content_type_object( $content_type );
	return $content_type_object ? $content_type_object->get_ui() : null;
}

/**
 * Validates values against a content type's field definitions.
 *
 * @param string $content_type Content type name.
 * @param array  $values       Values to validate.
 * @return true|WP_Error True on success, WP_Error on failure.
 */
function validate_content_type_values( $content_type, $values ) {
	$content_type_object = get_content_type_object( $content_type );

	if ( ! $content_type_object ) {
		return new WP_Error(
			'content_type_not_exists',
			__( 'Content type does not exist.' )
		);
	}

	$fields = $content_type_object->get_fields();

	// Check required fields.
	foreach ( $fields as $key => $field ) {
		if ( ! empty( $field['required'] ) && ( ! isset( $values[ $key ] ) || '' === $values[ $key ] ) ) {
			return new WP_Error(
				'missing_required_field',
				sprintf(
					/* translators: %s: Field key */
					__( 'Required field "%s" is missing.' ),
					$key
				)
			);
		}
	}

	// Check enum constraints.
	foreach ( $values as $key => $value ) {
		if ( isset( $fields[ $key ] ) && ! empty( $fields[ $key ]['enum'] ) ) {
			if ( ! in_array( $value, $fields[ $key ]['enum'], true ) ) {
				return new WP_Error(
					'invalid_enum_value',
					sprintf(
						/* translators: 1: Field key, 2: Valid values */
						__( 'Invalid value for field "%1$s". Valid values are: %2$s.' ),
						$key,
						implode( ', ', $fields[ $key ]['enum'] )
					)
				);
			}
		}
	}

	return true;
}

/**
 * Generates REST API schema for a content type.
 *
 * @param string $content_type Content type name.
 * @return array|null REST schema or null if content type not found.
 */
function get_content_type_rest_schema( $content_type ) {
	$content_type_object = get_content_type_object( $content_type );

	if ( ! $content_type_object ) {
		return null;
	}

	$fields     = $content_type_object->get_fields();
	$properties = array();
	$required   = array();

	foreach ( $fields as $key => $field ) {
		$properties[ $key ] = array(
			'type' => $field['type'],
		);

		if ( ! empty( $field['description'] ) ) {
			$properties[ $key ]['description'] = $field['description'];
		}

		if ( ! empty( $field['enum'] ) ) {
			$properties[ $key ]['enum'] = $field['enum'];
		}

		if ( ! empty( $field['required'] ) ) {
			$required[] = $key;
		}
	}

	$schema = array(
		'type'       => 'object',
		'properties' => $properties,
	);

	if ( ! empty( $required ) ) {
		$schema['required'] = $required;
	}

	return $schema;
}

/*
 * =============================================================================
 * BOOKS CONTENT TYPE DEMO
 * =============================================================================
 */

/**
 * Register the 'book' content type using the new API.
 */
function books_demo_register_content_type() {
	register_content_type(
		'book',
		array(
			'labels'       => array(
				'name'                  => _x( 'Books', 'post type general name', 'content-type-demo' ),
				'singular_name'         => _x( 'Book', 'post type singular name', 'content-type-demo' ),
				'menu_name'             => _x( 'Books', 'admin menu', 'content-type-demo' ),
				'name_admin_bar'        => _x( 'Book', 'add new on admin bar', 'content-type-demo' ),
				'add_new'               => _x( 'Add New', 'book', 'content-type-demo' ),
				'add_new_item'          => __( 'Add New Book', 'content-type-demo' ),
				'new_item'              => __( 'New Book', 'content-type-demo' ),
				'edit_item'             => __( 'Edit Book', 'content-type-demo' ),
				'view_item'             => __( 'View Book', 'content-type-demo' ),
				'all_items'             => __( 'All Books', 'content-type-demo' ),
				'search_items'          => __( 'Search Books', 'content-type-demo' ),
				'parent_item_colon'     => __( 'Parent Books:', 'content-type-demo' ),
				'not_found'             => __( 'No books found.', 'content-type-demo' ),
				'not_found_in_trash'    => __( 'No books found in Trash.', 'content-type-demo' ),
				'featured_image'        => _x( 'Book Cover Image', 'Overrides the "Featured Image"', 'content-type-demo' ),
				'set_featured_image'    => _x( 'Set cover image', 'Overrides "Set featured image"', 'content-type-demo' ),
				'remove_featured_image' => _x( 'Remove cover image', 'Overrides "Remove featured image"', 'content-type-demo' ),
				'use_featured_image'    => _x( 'Use as cover image', 'Overrides "Use as featured image"', 'content-type-demo' ),
				'archives'              => _x( 'Book archives', 'The post type archive label', 'content-type-demo' ),
				'insert_into_item'      => _x( 'Insert into book', 'Overrides "Insert into post"', 'content-type-demo' ),
				'uploaded_to_this_item' => _x( 'Uploaded to this book', 'Overrides "Uploaded to this post"', 'content-type-demo' ),
				'filter_items_list'     => _x( 'Filter books list', 'Screen reader text', 'content-type-demo' ),
				'items_list_navigation' => _x( 'Books list navigation', 'Screen reader text', 'content-type-demo' ),
				'items_list'            => _x( 'Books list', 'Screen reader text', 'content-type-demo' ),
			),
			'public'       => true,
			'has_archive'  => true,
			'show_in_rest' => true,
			'rest_base'    => 'books',
			'supports'     => array( 'title', 'editor', 'thumbnail', 'excerpt', 'custom-fields' ),
			'menu_icon'    => 'dashicons-book-alt',
			'rewrite'      => array( 'slug' => 'books' ),

			// Declarative field definitions - the key feature of the RFC!
			'fields'       => array(
				'isbn'           => array(
					'type'        => 'string',
					'label'       => __( 'ISBN', 'content-type-demo' ),
					'description' => __( 'International Standard Book Number', 'content-type-demo' ),
					'required'    => true,
					'control'     => 'text',
				),
				'published_year' => array(
					'type'        => 'integer',
					'label'       => __( 'Published Year', 'content-type-demo' ),
					'description' => __( 'Year the book was published', 'content-type-demo' ),
					'control'     => 'number',
				),
				'author_name'    => array(
					'type'        => 'string',
					'label'       => __( 'Author Name', 'content-type-demo' ),
					'description' => __( 'Name of the book author', 'content-type-demo' ),
					'control'     => 'text',
				),
				'genre'          => array(
					'type'        => 'string',
					'label'       => __( 'Genre', 'content-type-demo' ),
					'description' => __( 'Book genre category', 'content-type-demo' ),
					'enum'        => array( 'fiction', 'non-fiction', 'mystery', 'romance', 'sci-fi', 'fantasy', 'biography', 'history' ),
					'control'     => 'select',
				),
				'page_count'     => array(
					'type'        => 'integer',
					'label'       => __( 'Page Count', 'content-type-demo' ),
					'description' => __( 'Total number of pages', 'content-type-demo' ),
					'control'     => 'number',
				),
				'in_print'       => array(
					'type'        => 'boolean',
					'label'       => __( 'Currently In Print', 'content-type-demo' ),
					'description' => __( 'Whether the book is currently available in print', 'content-type-demo' ),
					'default'     => true,
					'control'     => 'checkbox',
				),
			),

			// UI configuration hints for the editor.
			'ui'           => array(
				'editor_panel' => array(
					'title'  => __( 'Book Details', 'content-type-demo' ),
					'fields' => array( 'isbn', 'published_year', 'author_name', 'genre', 'page_count', 'in_print' ),
				),
			),
		)
	);
}
add_action( 'init', 'books_demo_register_content_type' );

/**
 * Add meta box for book fields in the classic editor.
 */
function books_demo_add_meta_box() {
	add_meta_box(
		'book_details',
		__( 'Book Details', 'content-type-demo' ),
		'books_demo_render_meta_box',
		'book',
		'normal',
		'high'
	);
}
add_action( 'add_meta_boxes', 'books_demo_add_meta_box' );

/**
 * Render the book details meta box.
 *
 * @param WP_Post $post Current post object.
 */
function books_demo_render_meta_box( $post ) {
	wp_nonce_field( 'books_demo_save_meta', 'books_demo_meta_nonce' );

	$content_type = get_content_type_object( 'book' );
	if ( ! $content_type ) {
		return;
	}

	$fields = $content_type->get_fields();

	echo '<table class="form-table"><tbody>';

	foreach ( $fields as $field_key => $field ) {
		$value = get_post_meta( $post->ID, $field_key, true );

		if ( '' === $value && isset( $field['default'] ) ) {
			$value = $field['default'];
		}

		echo '<tr>';
		echo '<th scope="row"><label for="' . esc_attr( $field_key ) . '">' . esc_html( $field['label'] ) . '</label></th>';
		echo '<td>';

		switch ( $field['control'] ) {
			case 'select':
				echo '<select name="' . esc_attr( $field_key ) . '" id="' . esc_attr( $field_key ) . '" class="regular-text">';
				echo '<option value="">' . esc_html__( '-- Select --', 'content-type-demo' ) . '</option>';
				foreach ( $field['enum'] as $option ) {
					printf(
						'<option value="%s" %s>%s</option>',
						esc_attr( $option ),
						selected( $value, $option, false ),
						esc_html( ucfirst( $option ) )
					);
				}
				echo '</select>';
				break;

			case 'checkbox':
				printf(
					'<input type="checkbox" name="%s" id="%s" value="1" %s />',
					esc_attr( $field_key ),
					esc_attr( $field_key ),
					checked( $value, true, false )
				);
				break;

			case 'number':
				printf(
					'<input type="number" name="%s" id="%s" value="%s" class="regular-text" />',
					esc_attr( $field_key ),
					esc_attr( $field_key ),
					esc_attr( $value )
				);
				break;

			case 'textarea':
				printf(
					'<textarea name="%s" id="%s" class="large-text" rows="4">%s</textarea>',
					esc_attr( $field_key ),
					esc_attr( $field_key ),
					esc_textarea( $value )
				);
				break;

			default:
				printf(
					'<input type="text" name="%s" id="%s" value="%s" class="regular-text" />',
					esc_attr( $field_key ),
					esc_attr( $field_key ),
					esc_attr( $value )
				);
		}

		if ( ! empty( $field['description'] ) ) {
			echo '<p class="description">' . esc_html( $field['description'] ) . '</p>';
		}

		echo '</td></tr>';
	}

	echo '</tbody></table>';
}

/**
 * Save meta box data.
 *
 * @param int $post_id Post ID.
 */
function books_demo_save_meta_box( $post_id ) {
	if ( ! isset( $_POST['books_demo_meta_nonce'] ) ) {
		return;
	}

	if ( ! wp_verify_nonce( $_POST['books_demo_meta_nonce'], 'books_demo_save_meta' ) ) {
		return;
	}

	if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) {
		return;
	}

	if ( ! current_user_can( 'edit_post', $post_id ) ) {
		return;
	}

	$content_type = get_content_type_object( 'book' );
	if ( ! $content_type ) {
		return;
	}

	$fields = $content_type->get_fields();

	foreach ( $fields as $field_key => $field ) {
		if ( 'boolean' === $field['type'] ) {
			$value = isset( $_POST[ $field_key ] ) ? true : false;
			update_post_meta( $post_id, $field_key, $value );
		} elseif ( isset( $_POST[ $field_key ] ) ) {
			$value = WP_Content_Type::sanitize_by_type( $_POST[ $field_key ], $field['type'] );
			update_post_meta( $post_id, $field_key, $value );
		}
	}
}
add_action( 'save_post_book', 'books_demo_save_meta_box' );

/**
 * Append book details to the content on single book pages.
 *
 * @param string $content The post content.
 * @return string Modified content with book details.
 */
function books_demo_filter_content( $content ) {
	if ( ! is_singular( 'book' ) || is_admin() ) {
		return $content;
	}

	$post_id = get_the_ID();

	$content_type = get_content_type_object( 'book' );
	if ( ! $content_type ) {
		return $content;
	}

	$fields = $content_type->get_fields();

	$details  = '<div class="book-details">';
	$details .= '<h3>' . esc_html__( 'Book Details', 'content-type-demo' ) . '</h3>';
	$details .= '<dl class="book-details-list">';

	foreach ( $fields as $field_key => $field ) {
		$value = get_post_meta( $post_id, $field_key, true );

		if ( '' === $value && 'boolean' !== $field['type'] ) {
			continue;
		}

		$label = isset( $field['label'] ) ? $field['label'] : ucfirst( $field_key );

		switch ( $field['type'] ) {
			case 'boolean':
				$display_value = $value ? __( 'Yes', 'content-type-demo' ) : __( 'No', 'content-type-demo' );
				break;

			case 'integer':
			case 'number':
				$display_value = number_format_i18n( $value );
				break;

			default:
				if ( ! empty( $field['enum'] ) && in_array( $value, $field['enum'], true ) ) {
					$display_value = ucfirst( $value );
				} else {
					$display_value = $value;
				}
				break;
		}

		$details .= sprintf(
			'<dt class="book-detail-label">%s</dt><dd class="book-detail-value">%s</dd>',
			esc_html( $label ),
			esc_html( $display_value )
		);
	}

	$details .= '</dl>';
	$details .= '</div>';

	$styles = '<style>
		.book-details {
			margin: 2em 0;
			padding: 1.5em;
			background: #f9f9f9;
			border: 1px solid #e0e0e0;
			border-radius: 4px;
		}
		.book-details h3 {
			margin: 0 0 1em;
			padding-bottom: 0.5em;
			border-bottom: 1px solid #e0e0e0;
		}
		.book-details-list {
			display: grid;
			grid-template-columns: auto 1fr;
			gap: 0.5em 1em;
			margin: 0;
		}
		.book-detail-label {
			font-weight: 600;
			color: #333;
		}
		.book-detail-value {
			margin: 0;
			color: #666;
		}
	</style>';

	return $content . $styles . $details;
}
add_filter( 'the_content', 'books_demo_filter_content' );

/**
 * Flush rewrite rules on plugin activation.
 */
function books_demo_activate() {
	books_demo_register_content_type();
	flush_rewrite_rules();
}
register_activation_hook( __FILE__, 'books_demo_activate' );

/**
 * Add admin notice about the demo.
 */
function books_demo_admin_notice() {
	$screen = get_current_screen();
	if ( 'book' !== $screen->post_type ) {
		return;
	}
	?>
	<div class="notice notice-info">
		<p>
			<strong><?php esc_html_e( 'Content Type API Demo', 'content-type-demo' ); ?></strong> -
			<?php esc_html_e( 'This "Books" content type was registered using the new register_content_type() API proposed for WordPress 7.0.', 'content-type-demo' ); ?>
			<a href="https://github.com/WordPress/wordpress-develop/pull/10617" target="_blank"><?php esc_html_e( 'View the RFC/PR', 'content-type-demo' ); ?></a> |
			<a href="https://github.com/whyisjake/content-type-api-demo" target="_blank"><?php esc_html_e( 'View Plugin Source', 'content-type-demo' ); ?></a>
		</p>
	</div>
	<?php
}
add_action( 'admin_notices', 'books_demo_admin_notice' );
