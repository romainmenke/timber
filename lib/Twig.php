<?php

namespace Timber;

use Twig\TwigFunction;
use Twig\TwigFilter;

/**
 * Class Twig
 */
class Twig {

	public static $dir_name;

	/**
	 * @codeCoverageIgnore
	 */
	public static function init() {
		new self();
	}

	/**
	 * @codeCoverageIgnore
	 */
	public function __construct() {
		add_action('timber/twig/filters', array($this, 'add_timber_filters'));
		add_action('timber/twig/functions', array($this, 'add_timber_functions'));
		add_action('timber/twig/escapers', array($this, 'add_timber_escapers'));
	}

	/**
	 * Adds Timber-specific functions to Twig.
	 *
	 * @param \Twig\Environment $twig The Twig Environment.
	 *
	 * @return \Twig\Environment
	 */
	public function add_timber_functions( $twig ) {
		/* actions and filters */
		$twig->addFunction( new TwigFunction( 'action', function() {
			call_user_func_array( 'do_action', func_get_args() );
		} ) );

		$twig->addFunction(new TwigFunction('function', array(&$this, 'exec_function')));
		$twig->addFunction(new TwigFunction('fn', array(&$this, 'exec_function')));

		$twig->addFunction(new TwigFunction('shortcode', 'do_shortcode'));

		/**
		 * Timber object functions.
		 */

		$twig->addFunction(new TwigFunction('Post', function( $post_id, $PostClass = 'Timber\Post' ) {
			return self::maybe_convert_array( $post_id, $PostClass );
		} ) );

		$twig->addFunction( new TwigFunction( 'PostQuery', function( $args ) {
			return new PostQuery( $args );
		} ) );

		$twig->addFunction(new TwigFunction('Image', function( $post_id, $ImageClass = 'Timber\Image' ) {
			return self::maybe_convert_array( $post_id, $ImageClass );
		} ) );
		$twig->addFunction(new TwigFunction('Term', array($this, 'handle_term_object')));
		$twig->addFunction(new TwigFunction('User', function( $post_id, $UserClass = 'Timber\User' ) {
			return self::maybe_convert_array( $post_id, $UserClass );
		} ) );
		$twig->addFunction( new TwigFunction( 'Attachment', function( $post_id, $AttachmentClass = 'Timber\Attachment' ) {
			return self::maybe_convert_array( $post_id, $AttachmentClass );
		} ) );

		/**
		 * Deprecated Timber object functions.
		 */
		$twig->addFunction( new TwigFunction(
			'TimberPost',
			function( $post_id, $PostClass = 'Timber\Post' ) {
				Helper::deprecated( '{{ TimberPost() }}', '{{ Post() }}', '2.0.0' );
				return self::maybe_convert_array( $post_id, $PostClass );
			}
		) );

		$twig->addFunction( new TwigFunction(
			'TimberImage',
			function( $post_id = false, $ImageClass = 'Timber\Image' ) {
				Helper::deprecated( '{{ TimberImage() }}', '{{ Image() }}', '2.0.0' );
				return self::maybe_convert_array( $post_id, $ImageClass );
			}
		) );

		$twig->addFunction( new TwigFunction(
			'TimberTerm',
			function( $term_id, $taxonomy = '', $TermClass = 'Timber\Term' ) {
				Helper::deprecated( '{{ TimberTerm() }}', '{{ Term() }}', '2.0.0' );
				return self::handle_term_object($term_id, $taxonomy, $TermClass);
			}
		) );

		$twig->addFunction( new TwigFunction(
			'TimberUser',
			function( $user_id, $UserClass = 'Timber\User' ) {
				Helper::deprecated( '{{ TimberUser() }}', '{{ User() }}', '2.0.0' );
				return self::maybe_convert_array($user_id, $UserClass);
			}
		) );

		/* bloginfo and translate */
		$twig->addFunction(new TwigFunction('bloginfo', 'bloginfo'));
		$twig->addFunction(new TwigFunction('__', '__'));
		$twig->addFunction(new TwigFunction('translate', 'translate'));
		$twig->addFunction(new TwigFunction('_e', '_e'));
		$twig->addFunction(new TwigFunction('_n', '_n'));
		$twig->addFunction(new TwigFunction('_x', '_x'));
		$twig->addFunction(new TwigFunction('_ex', '_ex'));
		$twig->addFunction(new TwigFunction('_nx', '_nx'));
		$twig->addFunction(new TwigFunction('_n_noop', '_n_noop'));
		$twig->addFunction(new TwigFunction('_nx_noop', '_nx_noop'));
		$twig->addFunction(new TwigFunction('translate_nooped_plural', 'translate_nooped_plural'));

		return $twig;
	}

	/**
	 * Converts input to Timber object(s)
	 *
	 * @internal
	 * @since 2.0.0
	 *
	 * @param mixed  $post_id A post ID, object or something else that the Timber object class
	 *                        constructor an read.
	 * @param string $class   The class to use to convert the input.
	 *
	 * @return mixed An object or array of objects.
	 */
	public static function maybe_convert_array( $post_id, $class ) {
		if ( is_array( $post_id ) && ! Helper::is_array_assoc( $post_id ) ) {
			foreach ( $post_id as &$id ) {
				$id = new $class( $id );
			}

			return $post_id;
		}

		return new $class( $post_id );
	}

	/**
	 * Function for Term or Timber\Term() within Twig
	 * @since 1.5.1
	 * @author @jarednova
	 * @param int|array $term_id the term ID to search for
	 * @param string        $taxonomy the taxonomy to search inside of. If sent a class name, it will use that class to support backwards compatibility
	 * @param string        $TermClass the class to use for processing the term
	 * @return Term|array
	 */
	static function handle_term_object( $term_id, $taxonomy = '', $TermClass = 'Timber\Term' ) {
		if ( $taxonomy != $TermClass ) {
			// user has sent any additonal parameters, process
			$processed_args = self::process_term_args($taxonomy, $TermClass);
			$taxonomy = $processed_args['taxonomy'];
			$TermClass = $processed_args['TermClass'];
		}

		if ( is_array($term_id) && !Helper::is_array_assoc($term_id) ) {
			foreach ( $term_id as &$p ) {
				$p = new $TermClass($p, $taxonomy);
			}
			return $term_id;
		}

		return new $TermClass($term_id, $taxonomy);
	}

	/**
	 * Process the arguments for handle_term_object to determine what arguments the user is sending
	 * @since 1.5.1
	 * @author @jarednova
	 * @param string $maybe_taxonomy probably a taxonomy, but it could be a Timber\Term subclass
	 * @param string $TermClass a string for the Timber\Term subclass
	 * @return array of processed arguments
	 */
	protected static function process_term_args( $maybe_taxonomy, $TermClass ) {
		// A user could be sending a TermClass in the first arg, let's test for that ...
		if ( class_exists($maybe_taxonomy) ) {
			$tc = new $maybe_taxonomy;
			if ( is_subclass_of($tc, 'Timber\Term') ) {
				return array('taxonomy' => '', 'TermClass' => $maybe_taxonomy);
			}
		}
		return array('taxonomy' => $maybe_taxonomy, 'TermClass' => $TermClass);
	}

	/**
	 * Adds filters to Twig.
	 *
	 * @param \Twig\Environment $twig The Twig Environment.
	 * @return \Twig\Environment
	 */
	public function add_timber_filters( $twig ) {
		/* image filters */
		$twig->addFilter(new TwigFilter('resize', array('Timber\ImageHelper', 'resize')));
		$twig->addFilter(new TwigFilter('retina', array('Timber\ImageHelper', 'retina_resize')));
		$twig->addFilter(new TwigFilter('letterbox', array('Timber\ImageHelper', 'letterbox')));
		$twig->addFilter(new TwigFilter('tojpg', array('Timber\ImageHelper', 'img_to_jpg')));
		$twig->addFilter(new TwigFilter('towebp', array('Timber\ImageHelper', 'img_to_webp')));

		/* debugging filters */
		$twig->addFilter(new TwigFilter('get_class', function( $obj ) {
			Helper::deprecated( '{{ my_object | get_class }}', "{{ function('get_class', my_object) }}", '2.0.0' );
			return get_class( $obj );
		} ));
		$twig->addFilter(new TwigFilter('print_r', function( $arr ) {
			Helper::deprecated( '{{ my_object | print_r }}', '{{ dump(my_object) }}', '2.0.0' );
			return print_r($arr, true);
		} ));

		/* other filters */
		$twig->addFilter(new TwigFilter('stripshortcodes', 'strip_shortcodes'));
		$twig->addFilter(new TwigFilter('array', array($this, 'to_array')));
		$twig->addFilter(new TwigFilter('excerpt', 'wp_trim_words'));
		$twig->addFilter(new TwigFilter('excerpt_chars', array('Timber\TextHelper', 'trim_characters')));
		$twig->addFilter(new TwigFilter('function', array($this, 'exec_function')));
		$twig->addFilter(new TwigFilter('pretags', array($this, 'twig_pretags')));
		$twig->addFilter(new TwigFilter('sanitize', 'sanitize_title'));
		$twig->addFilter(new TwigFilter('shortcodes', 'do_shortcode'));
		$twig->addFilter(new TwigFilter('time_ago', array($this, 'time_ago')));
		$twig->addFilter(new TwigFilter('wpautop', 'wpautop'));
		$twig->addFilter(new TwigFilter('list', array($this, 'add_list_separators')));

		$twig->addFilter(new TwigFilter('pluck', array('Timber\Helper', 'pluck')));
		$twig->addFilter(new TwigFilter('filter', array('Timber\Helper', 'filter_array')));

		$twig->addFilter(new TwigFilter('relative', function( $link ) {
					return URLHelper::get_rel_url($link, true);
				} ));

		$twig->addFilter(new TwigFilter('date', array($this, 'intl_date')));

		$twig->addFilter(new TwigFilter('truncate', function( $text, $len ) {
					return TextHelper::trim_words($text, $len);
				} ));

		/* actions and filters */
		$twig->addFilter(new TwigFilter('apply_filters', function() {
					$args = func_get_args();
					$tag = current(array_splice($args, 1, 1));

					return apply_filters_ref_array($tag, $args);
				} ));

		/**
		 * Filters the Twig environment used in the global context.
		 *
		 * You can use this filter if you want to add additional functionality to Twig, like global
		 * variables, filters or functions.
		 *
		 * @since 0.21.9
		 * @example
		 * ```php
		 * /**
		 *  * Adds Twig functionality.
		 *  *
		 *  * @param \Twig\Environment $twig The Twig Environment to which you can add additional functionality.
		 *  *\/
		 * add_filter( 'timber/twig', function( $twig ) {
		 *     // Make get_theme_file_uri() usable as {{ theme_file() }} in Twig.
		 *     $twig->addFunction( new Twig\TwigFunction( 'theme_file', 'get_theme_file_uri' ) );
		 *
		 *     return $twig;
		 * } );
		 * ```
		 *
		 * ```twig
		 * <a class="navbar-brand" href="{{ site.url }}">
		 *     <img src="{{ theme_file( 'build/img/logo-example.svg' ) }}" alt="Logo {{ site.title }}">
		 * </a>
		 * ```
		 *
		 * @param \Twig\Environment $twig The Twig environment.
		 */
		$twig = apply_filters('timber/twig', $twig);

		/**
		 * Filters the Twig environment used in the global context.
		 *
		 * @deprecated 2.0.0
		 */
		$twig = apply_filters_deprecated( 'get_twig', array( $twig ), '2.0.0', 'timber/twig' );
		return $twig;
	}

	/**
	 * Adds escapers.
	 *
	 * @param \Twig\Environment $twig The Twig Environment.
	 * @return \Twig\Environment
	 */
	public function add_timber_escapers( $twig ) {
		$esc_url = function( \Twig\Environment $env, $string ) {
			return esc_url( $string );
		};

		$wp_kses_post = function( \Twig\Environment $env, $string ) {
			return wp_kses_post( $string );
		};

		$esc_html = function( \Twig\Environment $env, $string ) {
			return esc_html( $string );
		};

		$esc_js = function( \Twig\Environment $env, $string ) {
			return esc_js( $string );
		};

		if ( class_exists( 'Twig\Extension\EscaperExtension' ) ) {
			$escaper_extension = $twig->getExtension('Twig\Extension\EscaperExtension');
			$escaper_extension->setEscaper('esc_url', $esc_url);
			$escaper_extension->setEscaper('wp_kses_post', $wp_kses_post);
			$escaper_extension->setEscaper('esc_html', $esc_html);
			$escaper_extension->setEscaper('esc_js', $esc_js);
		} else {
			$escaper_extension = $twig->getExtension('Twig\Extension\CoreExtension');
			$escaper_extension->setEscaper('esc_url', $esc_url);
			$escaper_extension->setEscaper('wp_kses_post', $wp_kses_post);
			$escaper_extension->setEscaper('esc_html', $esc_html);
			$escaper_extension->setEscaper('esc_js', $esc_js);
		}

		return $twig;

	}

	/**
	 *
	 *
	 * @param mixed   $arr
	 * @return array
	 */
	public function to_array( $arr ) {
		if ( is_array($arr) ) {
			return $arr;
		}
		$arr = array($arr);
		return $arr;
	}

	/**
	 *
	 *
	 * @param string  $function_name
	 * @return mixed
	 */
	public function exec_function( $function_name ) {
		$args = func_get_args();
		array_shift($args);
		if ( is_string($function_name) ) {
			$function_name = trim($function_name);
		}
		return call_user_func_array($function_name, ($args));
	}

	/**
	 *
	 *
	 * @param string  $content
	 * @return string
	 */
	public function twig_pretags( $content ) {
		return preg_replace_callback('|<pre.*>(.*)</pre|isU', array(&$this, 'convert_pre_entities'), $content);
	}

	/**
	 *
	 *
	 * @param array   $matches
	 * @return string
	 */
	public function convert_pre_entities( $matches ) {
		return str_replace($matches[1], htmlentities($matches[1]), $matches[0]);
	}

	/**
	 *
	 *
	 * @param string|\DateTime  $date
	 * @param string            $format (optional)
	 * @return string
	 */
	public function intl_date( $date, $format = null ) {
		if ( $format === null ) {
			$format = get_option('date_format');
		}

		if ( $date instanceof \DateTime ) {
			$timestamp = $date->getTimestamp() + $date->getOffset();
		} else if ( is_numeric($date) && (strtotime($date) === false || strlen($date) !== 8) ) {
			$timestamp = intval($date);
		} else {
			$timestamp = strtotime($date);
		}

		return date_i18n($format, $timestamp);
	}

	/**
	 * @param int|string $from
	 * @param int|string $to
	 * @param string $format_past
	 * @param string $format_future
	 * @return string
	 */
	public static function time_ago( $from, $to = null, $format_past = '%s ago', $format_future = '%s from now' ) {
		$to = $to === null ? time() : $to;
		$to = is_int($to) ? $to : strtotime($to);
		$from = is_int($from) ? $from : strtotime($from);

		if ( $from < $to ) {
			return sprintf($format_past, human_time_diff($from, $to));
		} else {
			return sprintf($format_future, human_time_diff($to, $from));
		}
	}

	/**
	 * @param array $arr
	 * @param string $first_delimiter
	 * @param string $second_delimiter
	 * @return string
	 */
	public function add_list_separators( $arr, $first_delimiter = ',', $second_delimiter = ' and' ) {
		$length = count($arr);
		$list = '';
		foreach ( $arr as $index => $item ) {
			if ( $index < $length - 2 ) {
				$delimiter = $first_delimiter.' ';
			} elseif ( $index == $length - 2 ) {
				$delimiter = $second_delimiter.' ';
			} else {
				$delimiter = '';
			}
			$list = $list.$item.$delimiter;
		}
		return $list;
	}

}
