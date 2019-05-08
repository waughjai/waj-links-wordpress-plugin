<?php

const OBJECT = "";

global $posts;
$posts =
[
	[ 'url' => 'https://www.jaimeson-waugh.com', 'title' => 'Jaimeson Waugh', 'slug' => 'jaimeson-waugh', 'type' => 'not_google' ],
	[ 'url' => 'https://www.google.com',         'title' => 'Google'        , 'slug' => 'google'        , 'type' => 'google' ]
];

global $terms;
$terms =
[
	[
		'term_id' => '0',
		'slug' => 'google',
		'title' => 'Google',
		'url' => 'https://www.google.com'
	],
	[
		'term_id' => '1',
		'slug' => 'jaimeson',
		'title' => 'Jaimeson',
		'url' => 'https://www.jaimeson-waugh.com'
	]
];

class WP_Post
{
	public function __construct( $input )
	{
		global $posts;
		$this->ID = $input;
		$this->post_title = $posts[ $input ][ 'title' ];
		$this->title = $this->post_title;
	}

	public $ID;
	public $post_title;
	public $title;
}

function wp_get_attachment_image_src()
{
	
}

function get_category( $id )
{
	global $terms;
	return ( isset( $terms[ $id ] ) ) ? ( object )$terms[ $id ] : false;
}

function get_category_link( $cat )
{
	return get_tag_link( $cat );
}

function get_category_by_slug( $slug )
{
	return get_term_by( 'slug', $slug, null );
}

function get_term_by( $field, $value, $taxonomy )
{
	global $terms;
	foreach ( $terms as $term )
	{
		if ( $term[ 'slug' ] === $value )
		{
			return ( object )$term;
		}
	}
	return false;
}

function get_tag_link( $tag )
{
	global $terms;
	return ( isset( $terms[ $tag ] ) ) ? $terms[ $tag ][ 'url' ] : false;
}

function get_home_url()
{
	global $posts;
	return $posts[ 0 ][ 'url' ];
}

function get_option( $key )
{
	return 0;
}

function get_post( $input )
{
	return new WP_Post( $input );
}

function get_permalink( $input )
{
	if ( is_a( $input, WP_Post::class ) )
	{
		global $posts;
		$url = $posts[ $input->ID ][ 'url' ];
		return $url;
	}
	return null;
}

function get_page_by_path( $slug, $output, $types )
{
	global $posts;

	if ( !is_array( $types ) )
	{
		$types = [ $types ];
	}

	$number_of_posts = count( $posts );
	for ( $i = 0; $i < $number_of_posts; $i++ )
	{
		$post = $posts[ $i ];
		if ( $post[ 'slug' ] === $slug && in_array( $slug, $types ) )
		{
			return get_post( $i );
		}
	}
	return null;
}

function get_page_by_title( $title )
{
	global $posts;
	$number_of_posts = count( $posts );
	for ( $i = 0; $i <= $number_of_posts; $i++ )
	{
		$post = $posts[ $i ];
		if ( $post[ 'title' ] === $title )
		{
			return get_post( $i );
		}
	}
}

function get_post_types()
{
	return [ 'page', 'post', 'google', 'not-google' ];
}


function add_shortcode( $tag, $callback ) {
	global $shortcode_tags;

	if ( '' == trim( $tag ) ) {
		$message = __( 'Invalid shortcode name: Empty name given.' );
		_doing_it_wrong( __FUNCTION__, $message, '4.4.0' );
		return;
	}

	if ( 0 !== preg_match( '@[<>&/\[\]\x00-\x20=]@', $tag ) ) {
		/* translators: 1: shortcode name, 2: space separated list of reserved characters */
		$message = sprintf( __( 'Invalid shortcode name: %1$s. Do not use spaces or reserved characters: %2$s' ), $tag, '& / < > [ ] =' );
		_doing_it_wrong( __FUNCTION__, $message, '4.4.0' );
		return;
	}

	$shortcode_tags[ $tag ] = $callback;
}

function do_shortcode( $content, $ignore_html = false ) {
    global $shortcode_tags;

    if ( false === strpos( $content, '[' ) ) {
        return $content;
    }

    if ( empty( $shortcode_tags ) || ! is_array( $shortcode_tags ) ) {
        return $content;
    }

    // Find all registered tag names in $content.
    preg_match_all( '@\[([^<>&/\[\]\x00-\x20=]++)@', $content, $matches );
    $tagnames = array_intersect( array_keys( $shortcode_tags ), $matches[1] );

    if ( empty( $tagnames ) ) {
        return $content;
    }

    $content = do_shortcodes_in_html_tags( $content, $ignore_html, $tagnames );

    $pattern = get_shortcode_regex( $tagnames );
    $content = preg_replace_callback( "/$pattern/", 'do_shortcode_tag', $content );

    return $content;
}

function do_shortcodes_in_html_tags( $content, $ignore_html, $tagnames ) {
    // Normalize entities in unfiltered HTML before adding placeholders.
    $trans   = array(
        '&#91;' => '&#091;',
        '&#93;' => '&#093;',
    );
    $content = strtr( $content, $trans );
    $trans   = array(
        '[' => '&#91;',
        ']' => '&#93;',
    );

    $pattern = get_shortcode_regex( $tagnames );
    $textarr = wp_html_split( $content );

    foreach ( $textarr as &$element ) {
        if ( '' == $element || '<' !== $element[0] ) {
            continue;
        }

        $noopen  = false === strpos( $element, '[' );
        $noclose = false === strpos( $element, ']' );
        if ( $noopen || $noclose ) {
            // This element does not contain shortcodes.
            if ( $noopen xor $noclose ) {
                // Need to encode stray [ or ] chars.
                $element = strtr( $element, $trans );
            }
            continue;
        }

        if ( $ignore_html || '<!--' === substr( $element, 0, 4 ) || '<![CDATA[' === substr( $element, 0, 9 ) ) {
            // Encode all [ and ] chars.
            $element = strtr( $element, $trans );
            continue;
        }

        $attributes = wp_kses_attr_parse( $element );
        if ( false === $attributes ) {
            // Some plugins are doing things like [name] <[email]>.
            if ( 1 === preg_match( '%^<\s*\[\[?[^\[\]]+\]%', $element ) ) {
                $element = preg_replace_callback( "/$pattern/", 'do_shortcode_tag', $element );
            }

            // Looks like we found some crazy unfiltered HTML.  Skipping it for sanity.
            $element = strtr( $element, $trans );
            continue;
        }

        // Get element name
        $front   = array_shift( $attributes );
        $back    = array_pop( $attributes );
        $matches = array();
        preg_match( '%[a-zA-Z0-9]+%', $front, $matches );
        $elname = $matches[0];

        // Look for shortcodes in each attribute separately.
        foreach ( $attributes as &$attr ) {
            $open  = strpos( $attr, '[' );
            $close = strpos( $attr, ']' );
            if ( false === $open || false === $close ) {
                continue; // Go to next attribute.  Square braces will be escaped at end of loop.
            }
            $double = strpos( $attr, '"' );
            $single = strpos( $attr, "'" );
            if ( ( false === $single || $open < $single ) && ( false === $double || $open < $double ) ) {
                // $attr like '[shortcode]' or 'name = [shortcode]' implies unfiltered_html.
                // In this specific situation we assume KSES did not run because the input
                // was written by an administrator, so we should avoid changing the output
                // and we do not need to run KSES here.
                $attr = preg_replace_callback( "/$pattern/", 'do_shortcode_tag', $attr );
            } else {
                // $attr like 'name = "[shortcode]"' or "name = '[shortcode]'"
                // We do not know if $content was unfiltered. Assume KSES ran before shortcodes.
                $count    = 0;
                $new_attr = preg_replace_callback( "/$pattern/", 'do_shortcode_tag', $attr, -1, $count );
                if ( $count > 0 ) {
                    // Sanitize the shortcode output using KSES.
                    $new_attr = wp_kses_one_attr( $new_attr, $elname );
                    if ( '' !== trim( $new_attr ) ) {
                        // The shortcode is safe to use now.
                        $attr = $new_attr;
                    }
                }
            }
        }
        $element = $front . implode( '', $attributes ) . $back;

        // Now encode any remaining [ or ] chars.
        $element = strtr( $element, $trans );
    }

    $content = implode( '', $textarr );

    return $content;
}

function get_shortcode_regex( $tagnames = null ) {
	global $shortcode_tags;

	if ( empty( $tagnames ) ) {
		$tagnames = array_keys( $shortcode_tags );
	}
	$tagregexp = join( '|', array_map( 'preg_quote', $tagnames ) );

	// WARNING! Do not change this regex without changing do_shortcode_tag() and strip_shortcode_tag()
	// Also, see shortcode_unautop() and shortcode.js.

	// phpcs:disable Squiz.Strings.ConcatenationSpacing.PaddingFound -- don't remove regex indentation
	return
		'\\['                                // Opening bracket
		. '(\\[?)'                           // 1: Optional second opening bracket for escaping shortcodes: [[tag]]
		. "($tagregexp)"                     // 2: Shortcode name
		. '(?![\\w-])'                       // Not followed by word character or hyphen
		. '('                                // 3: Unroll the loop: Inside the opening shortcode tag
		.     '[^\\]\\/]*'                   // Not a closing bracket or forward slash
		.     '(?:'
		.         '\\/(?!\\])'               // A forward slash not followed by a closing bracket
		.         '[^\\]\\/]*'               // Not a closing bracket or forward slash
		.     ')*?'
		. ')'
		. '(?:'
		.     '(\\/)'                        // 4: Self closing tag ...
		.     '\\]'                          // ... and closing bracket
		. '|'
		.     '\\]'                          // Closing bracket
		.     '(?:'
		.         '('                        // 5: Unroll the loop: Optionally, anything between the opening and closing shortcode tags
		.             '[^\\[]*+'             // Not an opening bracket
		.             '(?:'
		.                 '\\[(?!\\/\\2\\])' // An opening bracket not followed by the closing shortcode tag
		.                 '[^\\[]*+'         // Not an opening bracket
		.             ')*+'
		.         ')'
		.         '\\[\\/\\2\\]'             // Closing shortcode tag
		.     ')?'
		. ')'
		. '(\\]?)';                          // 6: Optional second closing brocket for escaping shortcodes: [[tag]]
	// phpcs:enable
}

function wp_html_split( $input ) {
    return preg_split( get_html_split_regex(), $input, -1, PREG_SPLIT_DELIM_CAPTURE );
}

function get_html_split_regex() {
    static $regex;

    if ( ! isset( $regex ) ) {
        // phpcs:disable Squiz.Strings.ConcatenationSpacing.PaddingFound -- don't remove regex indentation
        $comments =
            '!'             // Start of comment, after the <.
            . '(?:'         // Unroll the loop: Consume everything until --> is found.
            .     '-(?!->)' // Dash not followed by end of comment.
            .     '[^\-]*+' // Consume non-dashes.
            . ')*+'         // Loop possessively.
            . '(?:-->)?';   // End of comment. If not found, match all input.

        $cdata =
            '!\[CDATA\['    // Start of comment, after the <.
            . '[^\]]*+'     // Consume non-].
            . '(?:'         // Unroll the loop: Consume everything until ]]> is found.
            .     '](?!]>)' // One ] not followed by end of comment.
            .     '[^\]]*+' // Consume non-].
            . ')*+'         // Loop possessively.
            . '(?:]]>)?';   // End of comment. If not found, match all input.

        $escaped =
            '(?='             // Is the element escaped?
            .    '!--'
            . '|'
            .    '!\[CDATA\['
            . ')'
            . '(?(?=!-)'      // If yes, which type?
            .     $comments
            . '|'
            .     $cdata
            . ')';

        $regex =
            '/('                // Capture the entire match.
            .     '<'           // Find start of element.
            .     '(?'          // Conditional expression follows.
            .         $escaped  // Find end of escaped element.
            .     '|'           // ... else ...
            .         '[^>]*>?' // Find end of normal element.
            .     ')'
            . ')/';
        // phpcs:enable
    }

    return $regex;
}

function do_shortcode_tag( $m ) {
	global $shortcode_tags;

	// allow [[foo]] syntax for escaping a tag
	if ( $m[1] == '[' && $m[6] == ']' ) {
		return substr( $m[0], 1, -1 );
	}

	$tag  = $m[2];
	$attr = shortcode_parse_atts( $m[3] );

	if ( ! is_callable( $shortcode_tags[ $tag ] ) ) {
		/* translators: %s: shortcode tag */
		$message = sprintf( __( 'Attempting to parse a shortcode without a valid callback: %s' ), $tag );
		_doing_it_wrong( __FUNCTION__, $message, '4.3.0' );
		return $m[0];
	}

	/**
	 * Filters whether to call a shortcode callback.
	 *
	 * Passing a truthy value to the filter will effectively short-circuit the
	 * shortcode generation process, returning that value instead.
	 *
	 * @since 4.7.0
	 *
	 * @param bool|string $return      Short-circuit return value. Either false or the value to replace the shortcode with.
	 * @param string       $tag         Shortcode name.
	 * @param array|string $attr        Shortcode attributes array or empty string.
	 * @param array        $m           Regular expression match array.
	 */
	$return = apply_filters( 'pre_do_shortcode_tag', false, $tag, $attr, $m );
	if ( false !== $return ) {
		return $return;
	}

	$content = isset( $m[5] ) ? $m[5] : null;

	$output = $m[1] . call_user_func( $shortcode_tags[ $tag ], $attr, $content, $tag ) . $m[6];

	/**
	 * Filters the output created by a shortcode callback.
	 *
	 * @since 4.7.0
	 *
	 * @param string       $output Shortcode output.
	 * @param string       $tag    Shortcode name.
	 * @param array|string $attr   Shortcode attributes array or empty string.
	 * @param array        $m      Regular expression match array.
	 */
	return apply_filters( 'do_shortcode_tag', $output, $tag, $attr, $m );
}

function shortcode_parse_atts( $text ) {
	$atts    = array();
	$pattern = get_shortcode_atts_regex();
	$text    = preg_replace( "/[\x{00a0}\x{200b}]+/u", ' ', $text );
	if ( preg_match_all( $pattern, $text, $match, PREG_SET_ORDER ) ) {
		foreach ( $match as $m ) {
			if ( ! empty( $m[1] ) ) {
				$atts[ strtolower( $m[1] ) ] = stripcslashes( $m[2] );
			} elseif ( ! empty( $m[3] ) ) {
				$atts[ strtolower( $m[3] ) ] = stripcslashes( $m[4] );
			} elseif ( ! empty( $m[5] ) ) {
				$atts[ strtolower( $m[5] ) ] = stripcslashes( $m[6] );
			} elseif ( isset( $m[7] ) && strlen( $m[7] ) ) {
				$atts[] = stripcslashes( $m[7] );
			} elseif ( isset( $m[8] ) && strlen( $m[8] ) ) {
				$atts[] = stripcslashes( $m[8] );
			} elseif ( isset( $m[9] ) ) {
				$atts[] = stripcslashes( $m[9] );
			}
		}

		// Reject any unclosed HTML elements
		foreach ( $atts as &$value ) {
			if ( false !== strpos( $value, '<' ) ) {
				if ( 1 !== preg_match( '/^[^<]*+(?:<[^>]*+>[^<]*+)*+$/', $value ) ) {
					$value = '';
				}
			}
		}
	} else {
		$atts = ltrim( $text );
	}
	return $atts;
}

function get_shortcode_atts_regex() {
	return '/([\w-]+)\s*=\s*"([^"]*)"(?:\s|$)|([\w-]+)\s*=\s*\'([^\']*)\'(?:\s|$)|([\w-]+)\s*=\s*([^\s\'"]+)(?:\s|$)|"([^"]*)"(?:\s|$)|\'([^\']*)\'(?:\s|$)|(\S+)(?:\s|$)/';
}

function apply_filters( $tag, $value ) {
    global $wp_filter, $wp_current_filter;

    $args = array();

    // Do 'all' actions first.
    if ( isset( $wp_filter['all'] ) ) {
        $wp_current_filter[] = $tag;
        $args                = func_get_args();
        _wp_call_all_hook( $args );
    }

    if ( ! isset( $wp_filter[ $tag ] ) ) {
        if ( isset( $wp_filter['all'] ) ) {
            array_pop( $wp_current_filter );
        }
        return $value;
    }

    if ( ! isset( $wp_filter['all'] ) ) {
        $wp_current_filter[] = $tag;
    }

    if ( empty( $args ) ) {
        $args = func_get_args();
    }

    // don't pass the tag name to WP_Hook
    array_shift( $args );

    $filtered = $wp_filter[ $tag ]->apply_filters( $value, $args );

    array_pop( $wp_current_filter );

    return $filtered;
}
