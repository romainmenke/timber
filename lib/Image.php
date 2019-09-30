<?php

namespace Timber;

/**
 * Class Image
 *
 * The `Timber\Image` class represents WordPress attachments that are images.
 *
 * @api
 * @example
 * ```php
 * $context         = Timber::context();
 *
 * // Lets say you have an alternate large 'cover image' for your post
 * // stored in a custom field which returns an image ID.
 * $cover_image_id = $context['post']->cover_image;
 * $context['cover_image'] = new Timber\Image($cover_image_id);
 * Timber::render('single.twig', $context);
 * ```
 *
 * ```twig
 * <article>
 *   <img src="{{cover_image.src}}" class="cover-image" />
 *   <h1 class="headline">{{post.title}}</h1>
 *   <div class="body">
 *     {{post.content}}
 *   </div>
 *
 *  <img
 *    src="{{ Image(post.custom_field_with_image_id).src }}"
 *    alt="Another way to initialize images as Timber\Image objects, but within Twig" />
 * </article>
 * ```
 *
 * ```html
 * <article>
 *   <img src="http://example.org/wp-content/uploads/2015/06/nevermind.jpg" class="cover-image" />
 *   <h1 class="headline">Now you've done it!</h1>
 *   <div class="body">
 *     Whatever whatever
 *   </div>
 *   <img
 *     src="http://example.org/wp-content/uploads/2015/06/kurt.jpg"
 *     alt="Another way to initialize images as Timber\Image objects, but within Twig" />
 * </article>
 * ```
 */
class Image extends Attachment {
	/**
	 * Object type.
	 *
	 * @api
	 * @var string What the object represents in WordPress terms.
	 */
	public $object_type = 'image';

	/**
	 * Representation.
	 *
	 * @api
	 * @var string What does this class represent in WordPress terms?
	 */
	public static $representation = 'image';

	/**
	 * Image sizes.
	 *
	 * @api
	 * @var array An array of available sizes for the image.
	 */
	public $sizes = array();

	/**
	 * Image dimensions.
	 *
	 * @internal
	 * @var array An index array of image dimensions, where the first is the width and the second
	 *            item is the height of the image in pixels.
	 */
	protected $dimensions;

	/**
	 * Creates a new Timber\Image object
	 * @example
	 * ```php
	 * // You can pass it an ID number
	 * $myImage = new Timber\Image(552);
	 *
	 * //Or send it a URL to an image
	 * $myImage = new Timber\Image('http://google.com/logo.jpg');
	 * ```
	 * @param bool|int|string $iid
	 */
	public function __construct( $iid ) {
		$this->init($iid);
	}

	/**
	 * @return string the src of the file
	 */
	public function __toString() {
		if ( $src = $this->src() ) {
			return $src;
		}
		return '';
	}

	/**
	 * Get a PHP array with pathinfo() info from the file
	 * @deprecated 2.0.0, functionality will no longer be supported in future releases.
	 * @return array
	 */
	public function get_pathinfo() {
		Helper::deprecated(
			"{{ image.get_pathinfo }}",
			"{{ function('pathinfo', image.file) }}",
			'2.0.0'
		);
		return pathinfo($this->file);
	}

	/**
	 * Processes an image's dimensions.
	 * @deprecated 2.0.0, use `{{ image.width }}` or `{{ image.height }}` in Twig
	 * @internal
	 * @param string $dim
	 * @return array|int
	 */
	protected function get_dimensions( $dim ) {
		Helper::deprecated(
			'Image::get_dimensions',
			'Image::get_dimension',
			'2.0.0'
		);
		if ( isset($this->_dimensions) ) {
			return $this->get_dimensions_loaded($dim);
		}
		if ( file_exists($this->file_loc) && filesize($this->file_loc) ) {
			list($width, $height) = getimagesize($this->file_loc);
			$this->_dimensions = array();
			$this->_dimensions[0] = $width;
			$this->_dimensions[1] = $height;
			return $this->get_dimensions_loaded($dim);
		}
	}

	/**
	 * @deprecated 2.0.0, use Image::get_dimension_loaded
	 * @internal
	 * @param string|null $dim
	 * @return array|int
	 */
	protected function get_dimensions_loaded( $dim ) {
		Helper::deprecated(
			'Image::get_dimensions',
			'Image::get_dimension',
			'2.0.0'
		);
		$dim = strtolower($dim);
		if ( $dim == 'h' || $dim == 'height' ) {
			return $this->_dimensions[1];
		}
		return $this->_dimensions[0];
	}

	/**
	 * @deprecated 2.0.0, use Image::meta to retrieve specific fields
	 * @return array
	 */
	protected function get_post_custom( $iid ) {
		Helper::deprecated(
			'{{ image.get_post_custom( image.id ) }}',
			"{{ image.meta('my_field') }}",
			'2.0.0'
		);
		$pc = get_post_custom($iid);
		if ( is_bool($pc) ) {
			return array();
		}
		return $pc;
	}

	/**
	 * Gets the source URL for the image.
	 *
	 * You can use WordPress image sizes (including the ones you registered with your theme or
	 * plugin) by passing the name of the size to this function (like `medium` or `large`). If the
	 * WordPress size has not been generated, it will return an empty string.
	 *
	 * @api
	 * @example
	 * ```twig
	 * <img src="{{ post.thumbnail.src }}">
	 * <img src="{{ post.thumbnail.src('medium') }}">
	 * ```
	 * ```html
	 * <img src="http://example.org/wp-content/uploads/2015/08/pic.jpg" />
	 * <img src="http://example.org/wp-content/uploads/2015/08/pic-800-600.jpg">
	 * ```
	 *
	 * @param string $size Optional. The requested image size. This can be a size that was in
	 *                     WordPress. Example: `medium` or `large`. Default `full`.
	 *
	 * @return bool|string The src URL for the image.
	 */
	public function src( string $size = 'full' ) {
		if ( isset( $this->abs_url ) ) {
			return $this->maybe_secure_url( $this->abs_url );
		}

		if ( ! $this->is_image() ) {
			return wp_get_attachment_url( $this->ID );
		}

		$src = wp_get_attachment_image_src( $this->ID, $size );
		$src = $src[0];

		/**
		 * Filters the src URL for a `Timber\Image`.
		 *
		 * @see \Timber\Image::src()
		 * @since 0.21.7
		 *
		 * @param string $src The image src.
		 * @param int    $id  The image ID.
		 */
		$src = apply_filters( 'timber/image/src', $src, $this->ID );

		/**
		 * Filters the src URL for a `Timber\Image`.
		 *
		 * @deprecated 2.0.0, use `timber/image/src`
		 */
		$src = apply_filters_deprecated(
			'timber_image_src',
			array( $src, $this->ID ),
			'2.0.0',
			'timber/image/src'
		);

		return $src;
	}

	/**
	 * Gets the width of the image in pixels.
	 *
	 * @api
	 * @example
	 * ```twig
	 * <img src="{{ image.src }}" width="{{ image.width }}" />
	 * ```
	 * ```html
	 * <img src="http://example.org/wp-content/uploads/2015/08/pic.jpg" width="1600" />
	 * ```
	 *
	 * @return int The width of the image in pixels.
	 */
	public function width() {
		return $this->get_dimension( 'width' );
	}

	/**
	 * Gets the height of the image in pixels.
	 *
	 * @api
	 * @example
	 * ```twig
	 * <img src="{{ image.src }}" height="{{ image.height }}" />
	 * ```
	 * ```html
	 * <img src="http://example.org/wp-content/uploads/2015/08/pic.jpg" height="900" />
	 * ```
	 *
	 * @return int The height of the image in pixels.
	 */
	public function height() {
		return $this->get_dimension( 'height' );
	}

	/**
	 * Gets the aspect ratio of the image.
	 *
	 * @api
	 * @example
	 * ```twig
	 * {% if post.thumbnail.aspect < 1 %}
	 *   {# handle vertical image #}
	 *   <img src="{{ post.thumbnail.src|resize(300, 500) }}" alt="A basketball player" />
	 * {% else %}
	 *   <img src="{{ post.thumbnail.src|resize(500) }}" alt="A sumo wrestler" />
	 * {% endif %}
	 * ```
	 *
	 * @return float The aspect ratio of the image.
	 */
	public function aspect() {
		$w = intval( $this->width() );
		$h = intval( $this->height() );

		return $w / $h;
	}

	/**
	 * Gets the alt text for an image.
	 *
	 * For better accessibility, you should always add an alt attribute to your images, even if it’s
	 * empty.
	 *
	 * @api
	 * @example
	 * ```twig
	 * <img src="{{ image.src }}" alt="{{ image.alt }}" />
	 * ```
	 * ```html
	 * <img src="http://example.org/wp-content/uploads/2015/08/pic.jpg"
	 *     alt="You should always add alt texts to your images for better accessibility" />
	 * ```
	 *
	 * @return string Alt text stored in WordPress.
	 */
	public function alt() {
		$alt = $this->meta( '_wp_attachment_image_alt' );
		return trim( wp_strip_all_tags( $alt ) );
	}

	/**
	 * Gets dimension for an image.
	 *
	 * @internal
	 *
	 * @param string $dimension The requested dimension. Either `width` or `height`.
	 * @return int|null The requested dimension. Null if image file couldn’t be found.
	 */
	protected function get_dimension( string $dimension ) {
		// Load from internal cache.
		if ( isset( $this->dimensions ) ) {
			return $this->get_dimension_loaded( $dimension );
		}

		// Load dimensions.
		if ( file_exists( $this->file_loc ) && filesize( $this->file_loc ) ) {
			list( $width, $height ) = getimagesize( $this->file_loc );

			$this->dimensions    = array();
			$this->dimensions[0] = $width;
			$this->dimensions[1] = $height;

			return $this->get_dimension_loaded( $dimension );
		}

		return null;
	}

	/**
	 * Gets already loaded dimension values.
	 *
	 * @internal
	 *
	 * @param string|null $dim Optional. The requested dimension. Either `width` or `height`.
	 * @return int The requested dimension in pixels.
	 */
	protected function get_dimension_loaded( $dim = null ) {
		$dim = strtolower( $dim );

		if ( 'h' === $dim || 'height' === $dim ) {
			return $this->dimensions[1];
		}

		return $this->dimensions[0];
	}

	/**
	 * @param string $size a size known to WordPress (like "medium")
	 * @api
	 * @example
	 * ```twig
	 * <h1>{{ post.title }}</h1>
	 * <img src="{{ post.thumbnail.src }}" srcset="{{ post.thumbnail.srcset }}" />
	 * ```
	 * ```html
	 * <img src="http://example.org/wp-content/uploads/2018/10/pic.jpg" srcset="http://example.org/wp-content/uploads/2018/10/pic.jpg 1024w, http://example.org/wp-content/uploads/2018/10/pic-600x338.jpg 600w, http://example.org/wp-content/uploads/2018/10/pic-300x169.jpg 300w" />
	 * ```
	 *	@return bool|string
	 */
	public function srcset( $size = "full" ) {
		if( $this->is_image() ){
			return wp_get_attachment_image_srcset($this->ID, $size);
		}
	}

	/**
	 * @param string $size a size known to WordPress (like "medium")
	 * @api
	 * @example
	 * ```twig
	 * <h1>{{ post.title }}</h1>
	 * <img src="{{ post.thumbnail.src }}" srcset="{{ post.thumbnail.srcset }}" sizes="{{ post.thumbnail.img_sizes }}" />
	 * ```
	 * ```html
	 * <img src="http://example.org/wp-content/uploads/2018/10/pic.jpg" srcset="http://example.org/wp-content/uploads/2018/10/pic.jpg 1024w, http://example.org/wp-content/uploads/2018/10/pic-600x338.jpg 600w, http://example.org/wp-content/uploads/2018/10/pic-300x169.jpg 300w sizes="(max-width: 1024px) 100vw, 102" />
	 * ```
	 *	@return bool|string
	 */
	public function img_sizes( $size = "full" ) {
		if( $this->is_image() ){
			return wp_get_attachment_image_sizes($this->ID, $size);
		}
	}

	/**
	 * Checks whether the image is really an image.
	 *
	 * @internal
	 * @return bool Whether the attachment is really an image.
	 */
	protected function is_image() {
		$src        = wp_get_attachment_url( $this->ID );
		$check      = wp_check_filetype( basename( $src ), null );
		$image_exts = array( 'jpg', 'jpeg', 'jpe', 'gif', 'png' );

		return in_array( $check['ext'], $image_exts, true );
	}

	/**
	 * Determines the ID.
	 *
	 * Tries to figure out the attachment ID or otherwise handles the case when a string or other
	 * data is sent (object, file path, etc.).
	 *
	 * @internal
	 * @deprecated since 2.0 Functionality will no longer be supported in future releases.
	 *
	 * @param mixed $iid A value to test against.
	 * @return int|null The numberic ID that should be used for this post object.
	 */
	protected function determine_id( $iid ) {
		$iid = parent::determine_id( $iid );
		if ( $iid instanceof \WP_Post ) {
			$ref          = new \ReflectionClass( $this );
			$post         = $ref->getParentClass()->newInstance( $iid->ID );
			$thumbnail_id = $post->thumbnail_id();

			// Check if it’s a post that has a featured image.
			if ( $thumbnail_id ) {
				return $this->init( (int) $thumbnail_id );
			}
			return $this->init( $iid->ID );
		} elseif ( $iid instanceof Post ) {
			/**
			 * This will catch TimberPost and any post classes that extend TimberPost,
			 * see http://php.net/manual/en/internals2.opcodes.instanceof.php#109108
			 * and https://timber.github.io/docs/guides/extending-timber/
			 */
			return (int) $iid->thumbnail_id();
		}
		return $iid;
	}
}
