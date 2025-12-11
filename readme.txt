=== Content Type API Demo ===
Contributors: whyisjake
Tags: custom-post-types, content-modeling, rest-api, developer, fields
Requires at least: 6.4
Tested up to: 6.7
Stable tag: 1.0.0
Requires PHP: 7.4
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Demonstrates the Declarative Content Modeling RFC - a new register_content_type() API proposed for WordPress 7.0.

== Description ==

This plugin provides a preview of the `register_content_type()` API proposed in the [Declarative Content Modeling RFC](https://github.com/WordPress/wordpress-develop/pull/10617) for WordPress 7.0.

The Content Type API provides a higher-level abstraction that combines `register_post_type()` and `register_post_meta()` into a single, declarative registration call.

= Key Features =

* **Declarative Field Definitions** - Define all your custom fields in one place
* **Automatic Meta Registration** - Fields are automatically registered as post meta
* **REST API Integration** - Fields are automatically exposed in the REST API with proper schemas
* **Type Validation** - Built-in support for string, integer, number, boolean, array, and object types
* **Enum Support** - Constrain field values to a predefined list
* **UI Hints** - Provide hints for editor panel organization

= Example Usage =

`
register_content_type( 'book', array(
    'labels' => array(
        'name' => 'Books',
        'singular_name' => 'Book',
    ),
    'public' => true,
    'show_in_rest' => true,
    'fields' => array(
        'isbn' => array(
            'type' => 'string',
            'label' => 'ISBN',
            'required' => true,
        ),
        'published_year' => array(
            'type' => 'integer',
            'label' => 'Published Year',
        ),
        'genre' => array(
            'type' => 'string',
            'label' => 'Genre',
            'enum' => array( 'fiction', 'non-fiction', 'mystery', 'sci-fi' ),
        ),
        'in_print' => array(
            'type' => 'boolean',
            'label' => 'Currently In Print',
            'default' => true,
        ),
    ),
) );
`

= Demo Included =

This plugin includes a complete "Books" content type demo that showcases:

* Custom fields with various types (string, integer, boolean)
* Enum fields with predefined options
* Classic editor meta box integration
* Front-end display of book details
* Full REST API support

= Try It in WordPress Playground =

You can try this plugin instantly in your browser using WordPress Playground (includes sample book data):

[Launch Demo in Playground](https://playground.wordpress.net/?blueprint-url=https://raw.githubusercontent.com/whyisjake/content-type-api-demo/main/blueprint.json)

= Related Links =

* [RFC Pull Request](https://github.com/WordPress/wordpress-develop/pull/10617)
* [WordPress Core Trac](https://core.trac.wordpress.org/)

== Installation ==

1. Upload the `content-type-api-demo` folder to the `/wp-content/plugins/` directory
2. Activate the plugin through the 'Plugins' menu in WordPress
3. Navigate to 'Books' in the admin menu to start creating books

== Frequently Asked Questions ==

= Is this API part of WordPress core? =

Not yet. This plugin demonstrates an API that is proposed for WordPress 7.0. The RFC is currently under discussion.

= Will my data be compatible with the core implementation? =

The plugin uses standard WordPress post meta, so your data will remain accessible regardless of how the final API is implemented.

= Can I use this in production? =

This is a demonstration plugin. While it's functional, we recommend waiting for the official core implementation for production use.

= How do I access book data via the REST API? =

Books are available at `/wp-json/wp/v2/books`. Each book includes its custom fields (isbn, published_year, author_name, genre, page_count, in_print) in the response.

= How do I create my own content types? =

Use the `register_content_type()` function in your theme or plugin, hooking into the `init` action:

`
add_action( 'init', function() {
    register_content_type( 'movie', array(
        'labels' => array( 'name' => 'Movies' ),
        'public' => true,
        'fields' => array(
            'director' => array( 'type' => 'string' ),
            'release_year' => array( 'type' => 'integer' ),
            'runtime_minutes' => array( 'type' => 'integer' ),
        ),
    ) );
} );
`

== Screenshots ==

1. Books list in the WordPress admin
2. Book editor with custom fields meta box
3. Single book display on the front-end
4. REST API response with custom fields

== Changelog ==

= 1.0.0 =
* Initial release
* Complete Content Type API implementation
* Books content type demo
* Classic editor meta box support
* Front-end content display
* REST API integration

== Upgrade Notice ==

= 1.0.0 =
Initial release of the Content Type API Demo plugin.

== API Reference ==

= Functions =

* `register_content_type( $name, $args )` - Register a new content type
* `unregister_content_type( $name )` - Unregister a content type
* `get_content_type_object( $name )` - Get a content type object
* `get_content_types( $args, $output )` - Get all registered content types
* `get_content_type_fields( $name )` - Get field definitions for a content type
* `get_content_type_field( $name, $field_key )` - Get a single field definition
* `validate_content_type_values( $name, $values )` - Validate values against field definitions
* `get_content_type_rest_schema( $name )` - Get REST API schema for a content type

= Hooks =

* `register_content_type_args` - Filter arguments before registration
* `registered_content_type` - Action fired after registration
* `unregistered_content_type` - Action fired after unregistration

= Field Properties =

* `type` - Field type: string, integer, number, boolean, array, object
* `label` - Human-readable label
* `description` - Field description
* `required` - Whether the field is required
* `default` - Default value
* `enum` - Array of allowed values
* `single` - Whether to store as single value (default: true)
* `show_in_rest` - Whether to expose in REST API (default: true)
* `control` - UI control hint: text, number, checkbox, select, textarea
* `sanitize_callback` - Custom sanitization function
* `auth_callback` - Custom authorization function
