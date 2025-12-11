# Content Type API Demo

This is a prototype implementation of the **[Declarative Content Modeling RFC](https://docs.google.com/document/d/1Z8ei9vOsj_TvyApq1EKet9BhzA4zdm8uFPd9cXJWpJs/edit?tab=t.0)** that introduces `register_content_type()` - a higher-level API for registering content types in WordPress.

## Try it out

[![Open in WordPress Playground](https://img.shields.io/badge/Open%20in-WordPress%20Playground-3858e9?logo=wordpress)](https://playground.wordpress.net/?blueprint-url=https://raw.githubusercontent.com/whyisjake/content-type-api-demo/main/blueprint.json)

[Try the demo in WordPress Playground](https://playground.wordpress.net/?blueprint-url=https://raw.githubusercontent.com/whyisjake/content-type-api-demo/main/blueprint.json) - includes a "Books" content type with sample data.

## Overview

The Content Type API provides a higher-level abstraction that combines `register_post_type()` and `register_post_meta()` into a single, declarative registration call.

## Key Features

- **Declarative Field Definitions** - Define all your custom fields in one place
- **Automatic Meta Registration** - Fields are automatically registered as post meta
- **REST API Integration** - Fields are automatically exposed in the REST API with proper schemas
- **Type Validation** - Built-in support for string, integer, number, boolean, array, and object types
- **Enum Support** - Constrain field values to a predefined list
- **UI Hints** - Provide hints for editor panel organization

## Example Usage

```php
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
```

## Installation

1. Download or clone this repository
2. Upload the `content-type-api-demo` folder to `/wp-content/plugins/`
3. Activate the plugin through the 'Plugins' menu in WordPress
4. Navigate to 'Books' in the admin menu to start creating books

## API Reference

### Functions

| Function | Description |
|----------|-------------|
| `register_content_type( $name, $args )` | Register a new content type |
| `unregister_content_type( $name )` | Unregister a content type |
| `get_content_type_object( $name )` | Get a content type object |
| `get_content_types( $args, $output )` | Get all registered content types |
| `get_content_type_fields( $name )` | Get field definitions for a content type |

### Field Properties

| Property | Description |
|----------|-------------|
| `type` | Field type: string, integer, number, boolean, array, object |
| `label` | Human-readable label |
| `description` | Field description |
| `required` | Whether the field is required |
| `default` | Default value |
| `enum` | Array of allowed values |
| `single` | Whether to store as single value (default: true) |
| `show_in_rest` | Whether to expose in REST API (default: true) |

## Related Links

- [RFC Pull Request](https://github.com/WordPress/wordpress-develop/pull/10617)
- [WordPress Core Trac](https://core.trac.wordpress.org/)

## License

GPL-2.0-or-later
