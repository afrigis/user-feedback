<?php
defined( 'WPINC' ) or die;

class User_Feedback_Plugin extends WP_Stack_Plugin2 {

	/**
	 * @var self
	 */
	protected static $instance;

	/**
	 * Plugin version.
	 */
	const VERSION = '1.0.0';

	/**
	 * Constructs the object, hooks in to `plugins_loaded`.
	 */
	protected function __construct() {
		$this->hook( 'plugins_loaded', 'add_hooks' );
	}

	/**
	 * Adds hooks.
	 */
	public function add_hooks() {
		$this->hook( 'init' );

		// Load the scripts & styles

		if ( apply_filters( 'user_feedback_load_on_frontend', true ) ) {
			$this->hook( 'wp_enqueue_scripts', 'enqueue_scripts' );
			$this->hook( 'wp_footer', 'print_templates' );
		}

		if ( apply_filters( 'user_feedback_load_on_backend', false ) ) {
			$this->hook( 'admin_enqueue_scripts', 'enqueue_scripts' );
			$this->hook( 'admin_footer', 'print_templates' );
		}

		// Ajax callbacks
		$this->hook( 'wp_ajax_user_feedback', 'ajax_callback' );
		$this->hook( 'wp_ajax_nopriv_user_feedback', 'ajax_callback' );

		// Send feedback emails
		$this->hook( 'user_feedback_received', 'process_feedback' );
	}

	/**
	 * Initializes the plugin, registers textdomain, etc.
	 */
	public function init() {
		$this->load_textdomain( 'user-feedback', '/languages' );
	}

	/**
	 * Ajax callback for user feedback.
	 */
	public function ajax_callback() {
		if ( ! isset( $_POST['data'] ) ) {
			die( 0 );
		}

		/**
		 * This action is run whenever there's new user feedback.
		 *
		 * The variable contains all the data received via the ajax request.
		 *
		 * @param array $feedback {
		 *
		 * @type array  $browser  Contains useful browser information like user agent, platform, and online status.
		 * @type string $url      The URL from where the user submitted the feedback.
		 * @type string $html     Contains the complete HTML output of $url.
		 * @type string $img      Base64 encoded screenshot of the page.
		 * @type string $note     Additional notes from the user.
		 * }
		 *
		 */
		do_action( 'user_feedback_received', $_POST['data'] );

		die( 1 );
	}

	/**
	 * Save the submitted image as media item.
	 *
	 * @param string $img Base64 encoded image.
	 *
	 * @return int|WP_Error
	 */
	public function save_image( $img ) {
		// Strip the "data:image/png;base64," part and decode the image
		$img = explode( ',', $img );
		$img = base64_decode( $img[1] );

		if ( ! $img ) {
			return false;
		}

		// Upload to tmp folder
		$filename = 'user-feedback-' . date( 'Y-m-d-H-i' ) . '.png';
		// todo: Use WP_Filesystem class
		$file = file_put_contents( '/tmp/' . $filename, $img );

		if ( ! $file ) {
			return false;
		}

		return '/tmp/' . $filename;
	}

	/**
	 * This function runs whenever new feedback is submitted.
	 *
	 * What it does:
	 * - uploading the image in the WordPress uploads folder
	 * - store the feedback as a custom post
	 * - send an email to the admin
	 *
	 * @param array $feedback {
	 *
	 * @type array  $browser  Contains useful browser information like user agent, platform, and online status.
	 * @type string $url      The URL from where the user submitted the feedback.
	 * @type string $html     Contains the complete HTML output of $url.
	 * @type string $img      Base64 encoded screenshot of the page.
	 * @type string $message  Additional notes from the user.
	 * }
	 */
	public function process_feedback( $feedback ) {

		$attachments = array();
		$img         = self::save_image( $feedback['img'] );
		if ( $img ) {
			$attachments[] = $img;
		}

		$user_name  = stripslashes( $feedback['user']['name'] );
		$user_email = stripslashes( $feedback['user']['email'] );

		if ( empty( $user_name ) ) {
			$user_name = __( 'Anonymous', 'user-feedback' );
		}

		if ( empty( $user_email ) ) {
			$user_email = __( '(not provided)', 'user-feedback' );
		}

		$message = __( 'Howdy,', 'user-feedback' ) . "\r\n\r\n";
		$message .= __( 'You just received a new user feedback regarding your website!', 'user-feedback' ) . "\r\n\r\n";
		$message .= sprintf( __( 'Name: %s', 'user-feedback' ), $user_name ) . "\r\n";
		$message .= sprintf( __( 'Email: %s', 'user-feedback' ), $user_email ) . "\r\n";
		$message .= sprintf( __( 'Browser: %s (%s)', 'user-feedback' ), $feedback['browser']['name'], $feedback['browser']['userAgent'] ) . "\r\n";
		$message .= sprintf( __( 'Visited URL: %s', 'user-feedback' ), $feedback['url'] ) . "\r\n";
		$message .= sprintf( __( 'Site Language: %s', 'user-feedback' ), $feedback['language'] ) . "\r\n";
		$message .= __( 'Additional Notes:', 'user-feedback' ) . "\r\n";
		$message .= stripslashes( $feedback['message'] ) . "\r\n\r\n";
		$message .= __( 'A screenshot of the visited page is attached.', 'user-feedback' ) . "\r\n";

		// Send email to the blog admin
		wp_mail(
			apply_filters( 'user_feedback_email_address', get_option( 'admin_email' ) ),
			apply_filters( 'user_feedback_email_subject',
				sprintf( __( '[%s] New User Feedback', 'user-feedback' ), get_option( 'blogname' ) )
			),
			apply_filters( 'user_feedback_email_message', $message ),
			'',
			$img
		);

		if ( ! is_email( $user_email ) ) {
			return;
		}

		$message = __( 'Howdy,', 'user-feedback' ) . "\r\n\r\n";
		$message .= __( 'We just received the following feedback from you and will get in touch shortly. Thank you.', 'user-feedback' ) . "\r\n\r\n";
		$message .= sprintf( __( 'Name: %s', 'user-feedback' ), $user_name ) . "\r\n";
		$message .= sprintf( __( 'Email: %s', 'user-feedback' ), $user_email ) . "\r\n";
		$message .= sprintf( __( 'Browser: %s', 'user-feedback' ), $feedback['browser']['name'] ) . "\r\n";
		$message .= sprintf( __( 'Visited URL: %s', 'user-feedback' ), $feedback['url'] ) . "\r\n";
		$message .= __( 'Additional Notes:', 'user-feedback' ) . "\r\n";
		$message .= stripslashes( $feedback['message'] ) . "\r\n\r\n";
		$message .= __( 'A screenshot of the visited page is attached.', 'user-feedback' ) . "\r\n";

		// Send email to the submitting user
		wp_mail(
			apply_filters( 'user_feedback_email_copy_address', $user_email ),
			apply_filters( 'user_feedback_email_copy_subject',
				sprintf( __( '[%s] Your Feedback', 'user-feedback' ), get_option( 'blogname' ) )
			),
			apply_filters( 'user_feedback_email_copy_message', $message ),
			'',
			$img
		);
	}

	/**
	 * Register JavaScript files
	 */
	public function enqueue_scripts() {
		// Use minified libraries if SCRIPT_DEBUG is turned off
		$suffix = ( defined( 'SCRIPT_DEBUG' ) && SCRIPT_DEBUG ) ? '' : '.min';

		/**
		 * Allow others to enable/disable the plugin's functionality at will.
		 *
		 * For example, you could also load the plugin for non-logged-in users.
		 *
		 * @param bool $load_user_feedback Whether the user feedback script should be loaded or not.
		 *                                 Defaults to true for logged in users.
		 */
		$load_user_feedback = apply_filters( 'user_feedback_load', true );

		/** @var bool $load_user_feedback */
		if ( ! $load_user_feedback ) {
			remove_action( 'wp_footer', array( __CLASS__, 'print_templates' ) );

			return;
		}

		wp_enqueue_style(
			'user-feedback',
			$this->get_url() . 'css/user-feedback' . $suffix . '.css',
			array(),
			'1.0.0'
		);

		wp_enqueue_script(
			'user-feedback',
			$this->get_url() . 'js/user-feedback' . $suffix . ' .js',
			array( 'underscore', 'backbone' ),
			'1.0.0',
			true
		);

		/**
		 * Get current user data.
		 *
		 * If the user isn't logged in, a fake object is created
		 */
		$userdata = get_userdata( get_current_user_id() );

		if ( ! $userdata ) {
			$userdata               = new stdClass();
			$userdata->display_name = __( 'Anonymous', 'user-feedback' );
			$userdata->user_email   = '';
		}

		/**
		 * Get theme data.
		 *
		 * Store the theme's name and the currently used template, e.g. index.php
		 *
		 * @todo: Maybe use {@link get_included_files()} if necessary
		 */
		$theme = wp_get_theme();
		global $template;
		$current_template = basename( str_replace( $theme->theme_root . '/' . $theme->stylesheet . '/', '', $template ) );

		/**
		 * Get the current language.
		 *
		 * Uses the WordPress locale setting, but also checks for WPML and Polylang.
		 */
		$language = get_bloginfo( 'language' );

		if ( defined( 'ICL_LANGUAGE_CODE' ) ) {
			$language = ICL_LANGUAGE_CODE;
		}

		if ( function_exists( 'pll_current_language' ) ) {
			$language = pll_current_language( 'slug' );
		}

		wp_localize_script( 'user-feedback', 'user_feedback', apply_filters( 'user_feedback_script_data', array(
			'ajax_url'  => admin_url( 'admin-ajax.php' ),
			'theme'     => array(
				'name'             => $theme->Name,
				'stylesheet'       => $theme->stylesheet,
				'current_template' => $current_template,
			),
			'user'      => array(
				'logged_in' => is_user_logged_in(),
				'name'      => $userdata->display_name,
				'email'     => $userdata->user_email,
			),
			'language'  => $language,
			'templates' => array(
				'button'                => array(
					'label' => __( 'Feedback', 'user-feedback' ),
				),
				'bottombar'             => array(
					'step'   => array(
						'one'   => _x( 'Feedback', 'step 1', 'user-feedback' ),
						'two'   => _x( 'Highlight area', 'step 3', 'user-feedback' ),
						'three' => _x( 'Leave a message', 'step 2', 'user-feedback' ),
					),
					'button' => array(
						'help'     => _x( '?', 'help button label', 'user-feedback' ),
						'helpAria' => _x( 'Submit Feedback', 'help button title text and aria label', 'user-feedback' ),
					),
				),
				'wizardStep1'           => array(
					'title'       => _x( 'Feedback', 'modal title', 'user-feedback' ),
					'salutation'  => __( 'Howdy stranger,', 'user-feedback' ),
					'intro'       => __( 'Please let us know who you are. This way we will get back to you as soon as the issue is resolved:', 'user-feedback' ),
					'placeholder' => array(
						'name'  => _x( 'Your name', 'input field placeholder', 'user-feedback' ),
						'email' => _x( 'Email address', 'input field placeholder', 'user-feedback' ),
					),
					'button'      => array(
						'primary'   => __( 'Next', 'user-feedback' ),
						'secondary' => __( 'Stay anonymous', 'user-feedback' ),
						'close'     => _x( '&times;', 'close button', 'user-feedback' ),
						'closeAria' => _x( 'Close', 'close button title text and aria label', 'user-feedback' )
					),
				),
				'wizardStep2'           => array(
					'title'      => _x( 'Feedback', 'modal title', 'user-feedback' ),
					'salutation' => __( 'Hello ', 'user-feedback' ),
					'intro'      => __( 'Please help us understand your feedback better!', 'user-feedback' ),
					'intro2'     => __( 'You can not only leave us a message but also highlight areas relevant to your feedback.', 'user-feedback' ),
					'inputLabel' => __( 'Don\'t show me this again', 'user-feedback' ),
					'button'     => array(
						'primary'   => __( 'Next', 'user-feedback' ),
						'close'     => _x( '&times;', 'close button', 'user-feedback' ),
						'closeAria' => _x( 'Close', 'close button title text and aria label', 'user-feedback' )
					),
				),
				'wizardStep3'           => array(
					'title'  => _x( 'Highlight area', 'modal title', 'user-feedback' ),
					'intro'  => __( 'Highlight the areas relevant to your feedback.', 'user-feedback' ),
					'button' => array(
						'primary'   => __( 'Take screenshot', 'user-feedback' ),
						'close'     => _x( '&times', 'close button', 'user-feedback' ),
						'closeAria' => _x( 'Close', 'close button title text and aria label', 'user-feedback' )
					),
				),
				'wizardStep3Annotation' => array(
					'close'     => _x( '&times', 'close button', 'user-feedback' ),
					'closeAria' => _x( 'Close', 'close button title text and aria label', 'user-feedback' )
				),
				'wizardStep4'           => array(
					'title'         => _x( 'Feedback', 'modal title', 'user-feedback' ),
					'screenshotAlt' => _x( 'Annotated Screenshot', 'alt text', 'user-feedback' ),
					'user'          => array(
						'by'          => _x( 'From ', 'by user xy', 'user-feedback' ),
						'gravatarAlt' => _x( 'Gravatar', 'alt text', 'user-feedback' )
					),
					'placeholder'   => array(
						'message' => _x( 'Tell us what we should improve or fix &hellip;', 'textarea placeholder', 'user-feedback' ),
					),
					'details'       => array(
						'theme'    => __( 'Theme: ', 'user-feedback' ),
						'template' => __( 'Page: ', 'user-feedback' ),
						'browser'  => __( 'Browser: ', 'user-feedback' ),
						'language' => __( 'Language: ', 'user-feedback' ),
					),
					'button'        => array(
						'primary'   => __( 'Send', 'user-feedback' ),
						'secondary' => __( 'Back', 'user-feedback' ),
						'close'     => _x( '&times', 'close button', 'user-feedback' ),
						'closeAria' => _x( 'Close', 'close button title text and aria label', 'user-feedback' )
					),
				),
				'wizardStep5'           => array(
					'title'  => _x( 'Feedback', 'modal title', 'user-feedback' ),
					'intro'  => __( 'Thank you for taking your time to give us feedback. We will examine it and get back to as quickly as possible.', 'user-feedback' ),
					'intro2' => __( '&ndash; Your required+ support team', 'user-feedback' ),
					'button' => array(
						'primary'   => __( 'Done', 'user-feedback' ),
						'secondary' => __( 'Leave another message', 'user-feedback' ),
					),
				)
			),
		) ) );
	}

	/**
	 * Prints the HTML templates used by the feedback JavaScript.
	 */
	public function print_templates() {
		// Our main container
		echo '<div id="user-feedback-container"></div>';
	}
}