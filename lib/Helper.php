<?php

namespace Timber;

/**
 * Class Helper
 *
 * As the name suggests these are helpers for Timber (and you!) when developing. You can find additional
 * (mainly internally-focused helpers) in Timber\URLHelper.
 */
class Helper {
	/**
	 * A utility for a one-stop shop for transients.
	 *
	 * @api
	 * @example
	 * ```php
	 * $context = Timber::context();
	 * $context['favorites'] = Timber\Helper::transient('user-' .$uid. '-favorites', function() use ($uid) {
	 *  	//some expensive query here that's doing something you want to store to a transient
	 *  	return $favorites;
	 * }, 600);
	 * Timber::render('single.twig', $context);
	 * ```
	 *
	 * @param string  	$slug           Unique identifier for transient
	 * @param callable 	$callback      Callback that generates the data that's to be cached
	 * @param int  	$transient_time (optional) Expiration of transients in seconds
	 * @param int 	$lock_timeout   (optional) How long (in seconds) to lock the transient to prevent race conditions
	 * @param bool 	$force          (optional) Force callback to be executed when transient is locked
	 * @return mixed
	 */
	public static function transient( string $slug, callable $callback, $transient_time = 0, $lock_timeout = 5, $force = false ) {
		/**
		 * Filters the transient slug.
		 *
		 * This might be useful if you are using a multilingual solution.
		 *
		 * @since 0.22.6
		 *
		 * @param string $slug The slug for the transient.
		 */
		$slug = apply_filters('timber/transient/slug', $slug);

		$enable_transients = ($transient_time === false || (defined('WP_DISABLE_TRANSIENTS') && WP_DISABLE_TRANSIENTS)) ? false : true;
		$data = $enable_transients ? get_transient($slug) : false;

		if ( false === $data ) {
			$data = self::handle_transient_locking($slug, $callback, $transient_time, $lock_timeout, $force, $enable_transients);
		}
		return $data;
	}

	/**
	 * Does the dirty work of locking the transient, running the callback and unlocking.
	 *
	 * @internal
	 *
	 * @param string 	$slug
	 * @param callable 	$callback
	 * @param int  	$transient_time Expiration of transients in seconds
	 * @param int 	$lock_timeout   How long (in seconds) to lock the transient to prevent race conditions
	 * @param bool 	$force          Force callback to be executed when transient is locked
	 * @param bool 	$enable_transients Force callback to be executed when transient is locked
	 */
	protected static function handle_transient_locking( string $slug, callable $callback, $transient_time, $lock_timeout, $force, $enable_transients ) {
		if ( $enable_transients && self::_is_transient_locked($slug) ) {

			/**
			 * Filters …
			 *
			 * @todo Add summary, add description, add description for $force param
			 *
			 * @since 2.0.0
			 * @param bool $force
			 */
			$force = apply_filters( 'timber/transient/force_transients', $force );

			/**
			 * Filters …
			 *
			 * @todo Add summary
			 *
			 * @deprecated 2.0.0, use `timber/transient/force_transients`
			 */
			$force = apply_filters_deprecated(
				'timber_force_transients',
				array( $force ),
				'2.0.0',
				'timber/transient/force_transients'
			);

			/**
			 * Filters …
			 *
			 * Here is a description about the filter.
			 * `$slug` The transient slug.
			 *
			 * @todo Add summary, add description, add description for $force param
			 *
			 * @since 2.0.0
			 *
			 * @param bool $force
			 */
			$force = apply_filters( "timber/transient/force_transient_{$slug}", $force );

			/**
			 * Filters …
			 *
			 * @todo Add summary
			 *
			 * @deprecated 2.0.0, use `timber/transient/force_transient_{$slug}`
			 */
			$force = apply_filters( "timber_force_transient_{$slug}", $force );

			if ( !$force ) {
				//the server is currently executing the process.
				//We're just gonna dump these users. Sorry!
				return false;
			}
			$enable_transients = false;
		}
		// lock timeout shouldn't be higher than 5 seconds, unless
		// remote calls with high timeouts are made here
		if ( $enable_transients ) {
			self::_lock_transient($slug, $lock_timeout);
		}
		$data = $callback();
		if ( $enable_transients ) {
			set_transient($slug, $data, $transient_time);
			self::_unlock_transient($slug);
		}
		return $data;
	}

	/**
	 * @internal
	 * @param string $slug
	 * @param int $lock_timeout
	 */
	public static function _lock_transient( $slug, $lock_timeout ) {
		set_transient($slug.'_lock', true, $lock_timeout);
	}

	/**
	 * @internal
	 * @param string $slug
	 */
	public static function _unlock_transient( $slug ) {
		delete_transient($slug.'_lock');
	}

	/**
	 * @internal
	 * @param string $slug
	 */
	public static function _is_transient_locked( $slug ) {
		return (bool) get_transient($slug.'_lock');
	}

	/* These are for measuring page render time */

	/**
	 * For measuring time, this will start a timer.
	 *
	 * @api
	 * @return float
	 */
	public static function start_timer() {
		$time = microtime();
		$time = explode(' ', $time);
		$time = $time[1] + $time[0];
		return $time;
	}

	/**
	 * For stopping time and getting the data.
	 *
	 * @api
	 * @example
	 * ```php
	 * $start = Timber\Helper::start_timer();
	 * // do some stuff that takes awhile
	 * echo Timber\Helper::stop_timer( $start );
	 * ```
	 *
	 * @param int     $start
	 * @return string
	 */
	public static function stop_timer( $start ) {
		$time = microtime();
		$time = explode(' ', $time);
		$time = $time[1] + $time[0];
		$finish = $time;
		$total_time = round(($finish - $start), 4);
		return $total_time.' seconds.';
	}

	/* Function Utilities
	======================== */

	/**
	 * Calls a function with an output buffer. This is useful if you have a function that outputs
	 * text that you want to capture and use within a twig template.
	 *
	 * @api
	 * @example
	 * ```php
	 * function the_form() {
	 *     echo '<form action="form.php"><input type="text" /><input type="submit /></form>';
	 * }
	 *
	 * $context = Timber::context();
	 * $context['my_form'] = Timber\Helper::ob_function('the_form');
	 * Timber::render('single-form.twig', $context);
	 * ```
	 * ```twig
	 * <h1>{{ post.title }}</h1>
	 * {{ my_form }}
	 * ```
	 * ```html
	 * <h1>Apply to my contest!</h1>
	 * <form action="form.php"><input type="text" /><input type="submit /></form>
	 * ```
	 *
	 * @param callback $function
	 * @param array   $args
	 *
	 * @return string
	 */
	public static function ob_function( $function, $args = array(null) ) {
		ob_start();
		call_user_func_array($function, $args);
		$data = ob_get_contents();
		ob_end_clean();
		return $data;
	}

	/**
	 * @api
	 *
	 * @param mixed $arg that you want to error_log
	 * @return void
	 */
	public static function error_log( $error ) {
		global $timber_disable_error_log;
		if ( !WP_DEBUG || $timber_disable_error_log ) {
			return;
		}
		if ( is_object($error) || is_array($error) ) {
			$error = print_r($error, true);
		}
		return error_log($error);
	}

	/**
	 * Trigger a warning.
	 *
	 * @api
	 *
	 * @param string $message The warning that you want to output.
	 *
	 * @return void
	 */
	public static function warn( $message ) {
		if ( ! WP_DEBUG ) {
			return;
		}

		trigger_error( $message, E_USER_WARNING );
	}

	/**
	 * Triggers a deprecation warning.
	 *
	 * @api
	 * @param string $function    The name of the deprecated function/method.
	 * @param string $replacement Function to use instead.
	 * @param string $version     When we deprecated this.
	 * @return void
	 */
	public static function deprecated( $function, $replacement, $version ) {
		if ( ! WP_DEBUG ) {
			return;
		}

		 do_action( 'deprecated_function_run', $function, $replacement, $version );

	    /**
	     * Filters whether to trigger an error for deprecated functions.
	     *
	     * @since 2.5.0
	     *
	     * @param bool $trigger Whether to trigger the error for deprecated functions. Default true.
	     */
	    if ( WP_DEBUG && apply_filters( 'deprecated_function_trigger_error', true ) ) {
	        if ( function_exists( '__' ) ) {
	            if ( ! is_null( $replacement ) ) {
	                /* translators: 1: PHP function name, 2: version number, 3: alternative function name */
	                trigger_error( sprintf( __('%1$s is <strong>deprecated</strong> since Timber version %2$s! Use %3$s instead.'), $function, $version, $replacement ) );
	            } else {
	                /* translators: 1: PHP function name, 2: version number */
	                trigger_error( sprintf( __('%1$s is <strong>deprecated</strong> since Timber version %2$s with no alternative available.'), $function, $version ) );
	            }
	        } else {
	            if ( ! is_null( $replacement ) ) {
	                trigger_error( sprintf( '%1$s is <strong>deprecated</strong> since Timber version %2$s! Use %3$s instead.', $function, $version, $replacement ) );
	            } else {
	                trigger_error( sprintf( '%1$s is <strong>deprecated</strong> since Timber version %2$s with no alternative available.', $function, $version ) );
	            }
	        }
	    }
	}


	/**
	 * @api
	 *
	 * @param string  $separator
	 * @param string  $seplocation
	 * @return string
	 */
	public static function get_wp_title( $separator = ' ', $seplocation = 'left' ) {
		/**
		 * Filters the separator used for the page title.
		 *
		 * @since 2.0.0
		 *
		 * @param string $separator The separator to use. Default `' '`.
		 */
		$separator = apply_filters( 'timber/helper/wp_title_separator', $separator );

		/**
		 * Filters the separator used for the page title.
		 *
		 * @deprecated 2.0.0, use `timber/helper/wp_title_separator`
		 */
		$separator = apply_filters_deprecated( 'timber_wp_title_seperator', array( $separator ), '2.0.0', 'timber/helper/wp_title_separator' );

		return trim(wp_title($separator, false, $seplocation));
	}

	/**
	 * Sorts object arrays by properties.
	 *
	 * @api
	 *
	 * @param array  $array The array of objects to sort.
	 * @param string $prop  The property to sort by.
	 *
	 * @return void
	 */
	public static function osort( &$array, $prop ) {
		usort($array, function( $a, $b ) use ($prop) {
			return $a->$prop > $b->$prop ? 1 : -1;
		} );
	}

	/**
	 * @api
	 *
	 * @param array   $arr
	 * @return bool
	 */
	public static function is_array_assoc( $arr ) {
		if ( !is_array($arr) ) {
			return false;
		}
		return (bool) count(array_filter(array_keys($arr), 'is_string'));
	}

	/**
	 * @api
	 *
	 * @param array   $array
	 * @return \stdClass
	 */
	public static function array_to_object( $array ) {
		$obj = new \stdClass;
		foreach ( $array as $k => $v ) {
			if ( is_array($v) ) {
				$obj->{$k} = self::array_to_object($v); //RECURSION
			} else {
				$obj->{$k} = $v;
			}
		}
		return $obj;
	}

	/**
	 * @api
	 *
	 * @param array   $array
	 * @param string  $key
	 * @param mixed   $value
	 * @return bool|int
	 */
	public static function get_object_index_by_property( $array, $key, $value ) {
		if ( is_array($array) ) {
			$i = 0;
			foreach ( $array as $arr ) {
				if ( is_array($arr) ) {
					if ( $arr[$key] == $value ) {
						return $i;
					}
				} else {
					if ( $arr->$key == $value ) {
						return $i;
					}
				}
				$i++;
			}
		}
		return false;
	}

	/**
	 * @api
	 *
	 * @param array   $array
	 * @param string  $key
	 * @param mixed   $value
	 * @return array|null
	 * @throws \Exception
	 */
	public static function get_object_by_property( $array, $key, $value ) {
		if ( is_array($array) ) {
			foreach ( $array as $arr ) {
				if ( $arr->$key == $value ) {
					return $arr;
				}
			}
			return false;
		}
		throw new \InvalidArgumentException('$array is not an array, got:');
	}

	/**
	 * @api
	 *
	 * @param array   $array
	 * @param int     $len
	 * @return array
	 */
	public static function array_truncate( $array, $len ) {
		if ( sizeof($array) > $len ) {
			$array = array_splice($array, 0, $len);
		}
		return $array;
	}

	/* Bool Utilities
	======================== */

	/**
	 * @api
	 *
	 * @param mixed   $value
	 * @return bool
	 */
	public static function is_true( $value ) {
		if ( isset($value) ) {
			if ( is_string($value) ) {
				$value = strtolower($value);
			}
			if ( ($value == 'true' || $value === 1 || $value === '1' || $value == true) && $value !== false && $value !== 'false' ) {
				return true;
			}
		}
		return false;
	}

	/**
	 * Is the number even? Let's find out.
	 *
	 * @api
	 *
	 * @param int $i number to test.
	 * @return bool
	 */
	public static function iseven( $i ) {
		return ( $i % 2 ) === 0;
	}

	/**
	 * Is the number odd? Let's find out.
	 *
	 * @api
	 *
	 * @param int $i number to test.
	 * @return bool
	 */
	public static function isodd( $i ) {
		return ( $i % 2 ) !== 0;
	}

	/**
	 * Plucks the values of a certain key from an array of objects
	 *
	 * @api
	 *
	 * @param array  $array
	 * @param string $key
	 *
	 * @return array
	 */
	public static function pluck( $array, $key ) {
		$return = array();
		foreach ( $array as $obj ) {
			if ( is_object($obj) && method_exists($obj, $key) ) {
				$return[] = $obj->$key();
			} elseif ( is_object($obj) && property_exists($obj, $key) ) {
				$return[] = $obj->$key;
			} elseif ( is_array($obj) && isset($obj[$key]) ) {
				$return[] = $obj[$key];
			}
		}
		return $return;
	}

	/**
	 * Filters a list of objects, based on a set of key => value arguments.
	 *
	 * @api
	 * @since 1.5.3
	 * @ticket #1594
	 *
	 * @param array        $list to filter.
	 * @param string|array $filter to search for.
	 * @param string       $operator to use (AND, NOT, OR).
	 * @return array
	 */
	public static function filter_array( $list, $args, $operator = 'AND' ) {
		if ( ! is_array($args) ) {
			$args = array( 'slug' => $args );
		}

		if ( ! is_array( $list ) && ! is_a( $list, 'Traversable' ) ) {
			return array();
		}

		$util = new \WP_List_Util( $list );
		return $util->filter( $args, $operator );
	}

	/**
	 * Converts a WP object (WP_Post, WP_Term) into his
	 * equivalent Timber class (Timber\Post, Timber\Term).
	 *
	 * If no match is found the function will return the inital argument.
	 *
	 * @param mixed $obj WP Object
	 * @return mixed Instance of equivalent Timber object, or the argument if no match is found
	 */
	public static function convert_wp_object( $obj ) {
		if ( $obj instanceof \WP_Post ) {
			$class = PostGetter::get_post_class($obj->post_type);
			return new $class($obj->ID);
		} elseif ( $obj instanceof \WP_Term ) {
			return new Term($obj->term_id);
		} elseif ( $obj instanceof \WP_User ) {
			return new User($obj->ID);
		}

		return $obj;
	}
}
