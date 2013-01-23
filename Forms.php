<?php

/**
 * Data-aware form generator
 */
class scbForms {

	const TOKEN = '%input%';

	/**
	 * @param array|scbFormField_I $args
	 * @param mixed                $value
	 *
	 * @return string
	 */
	static function input_with_value( $args, $value ) {
		$field = scbFormField::create( $args );

		return $field->render( $value );
	}

	/**
	 * @param array|scbFormField_I $args
	 * @param array                $formdata
	 *
	 * @return string
	 */
	static function input( $args, $formdata = null ) {
		$field = scbFormField::create( $args );

		return $field->render( scbForms::get_value( $args['name'], $formdata ) );
	}

	/**
	 * Generates a table wrapped in a form
	 *
	 * @param array $rows
	 * @param array $formdata
	 *
	 * @return string
	 */
	static function form_table( $rows, $formdata = null ) {
		$output = '';
		foreach ( $rows as $row )
			$output .= self::table_row( $row, $formdata );

		$output = self::form_table_wrap( $output );

		return $output;
	}

	/**
	 * Generates a form
	 *
	 * @param array  $inputs
	 * @param array  $formdata
	 * @param string $nonce
	 *
	 * @return string
	 */
	static function form( $inputs, $formdata = null, $nonce ) {
		$output = '';
		foreach ( $inputs as $input )
			$output .= self::input( $input, $formdata );

		$output = self::form_wrap( $output, $nonce );

		return $output;
	}

	/**
	 * Generates a table
	 *
	 * @param array $rows
	 * @param array $formdata
	 *
	 * @return string
	 */
	static function table( $rows, $formdata = null ) {
		$output = '';
		foreach ( $rows as $row )
			$output .= self::table_row( $row, $formdata );

		$output = self::table_wrap( $output );

		return $output;
	}

	/**
	 * Generates a table row
	 *
	 * @param array $args
	 * @param array $formdata
	 *
	 * @return string
	 */
	static function table_row( $args, $formdata = null ) {
		return self::row_wrap( $args['title'], self::input( $args, $formdata ) );
	}


// ____________WRAPPERS____________

	/**
	 * @param string $content
	 * @param string $nonce
	 *
	 * @return string
	 */
	static function form_table_wrap( $content, $nonce = 'update_options' ) {
		return self::form_wrap( self::table_wrap( $content ), $nonce );
	}

	/**
	 * @param string $content
	 * @param string $nonce
	 *
	 * @return string
	 */
	static function form_wrap( $content, $nonce = 'update_options' ) {
		return html( "form method='post' action=''",
			$content,
			wp_nonce_field( $nonce, '_wpnonce', $referer = true, $echo = false )
		);
	}

	/**
	 * @param string $content
	 *
	 * @return string
	 */
	static function table_wrap( $content ) {
		return html( "table class='form-table'", $content );
	}

	/**
	 * @param string $title
	 * @param string $content
	 *
	 * @return string
	 */
	static function row_wrap( $title, $content ) {
		return html( 'tr',
			html( "th scope='row'", $title ),
			html( 'td', $content )
		);
	}


// ____________PRIVATE METHODS____________


// Utilities


	/**
	 * Generates the proper string for a name attribute.
	 *
	 * @param array|string $name The raw name
	 *
	 * @return string
	 */
	static function get_name( $name ) {
		$name = (array) $name;

		$name_str = array_shift( $name );

		foreach ( $name as $key ) {
			$name_str .= '[' . esc_attr( $key ) . ']';
		}

		return $name_str;
	}

	/**
	 * Traverses the formdata and retrieves the correct value.
	 *
	 * @param string $name     The name of the value
	 * @param array  $value    The data that will be traversed
	 * @param mixed  $fallback The value returned when the key is not found
	 *
	 * @return mixed
	 */
	static function get_value( $name, $value, $fallback = null ) {
		foreach ( (array) $name as $key ) {
			if ( !isset( $value[ $key ] ) )
				return $fallback;

			$value = $value[$key];
		}

		return $value;
	}

	/**
	 * Given a list of fields, validate some data.
	 *
	 * @param array $fields    List of args that would be sent to scbForms::input()
	 * @param array $data      The data to validate. Defaults to $_POST
	 * @param array $to_update Existing data to populate. Necessary for nested values
	 *
	 * @return array
	 */
	static function validate_post_data( $fields, $data = null, $to_update = array() ) {
		if ( null === $data ) {
			$data = stripslashes_deep( $_POST );
		}

		foreach ( $fields as $field ) {
			$value = scbForms::get_value( $field['name'], $data );

			$fieldObj = scbFormField::create( $field );

			$value = $fieldObj->validate( $value );

			if ( null !== $value )
				self::set_value( $to_update, $field['name'], $value );
		}

		return $to_update;
	}

	/**
	 * For multiple-choice fields, we can never distinguish between "never been set" and "set to none".
	 * For single-choice fields, we can't distinguish either, because of how self::update_meta() works.
	 * Therefore, the 'default' parameter is always ignored.
	 *
	 * @param array  $args      Field arguments.
	 * @param int    $object_id The object ID the metadata is attached to
	 * @param string $meta_type
	 *
	 * @return string
	 */
	static function input_from_meta( $args, $object_id, $meta_type = 'post' ) {
		$single = ( 'checkbox' != $args['type'] );

		$key = (array) $args['name'];
		$key = end( $key );

		$value = get_metadata( $meta_type, $object_id, $key, $single );

		return self::input_with_value( $args, $value );
	}

	/**
	 * @param array  $fields
	 * @param array  $data
	 * @param int    $object_id
	 * @param string $meta_type
	 */
	static function update_meta( $fields, $data, $object_id, $meta_type = 'post' ) {
		foreach ( $fields as $field_args ) {
			$key = $field_args['name'];

			if ( 'checkbox' == $field_args['type'] ) {
				$new_values = isset( $data[$key] ) ? $data[$key] : array();

				$old_values = get_metadata( $meta_type, $object_id, $key );

				foreach ( array_diff( $new_values, $old_values ) as $value )
					add_metadata( $meta_type, $object_id, $key, $value );

				foreach ( array_diff( $old_values, $new_values ) as $value )
					delete_metadata( $meta_type, $object_id, $key, $value );
			} else {
				$value = isset( $data[$key] ) ? $data[$key] : '';

				if ( '' === $value )
					delete_metadata( $meta_type, $object_id, $key );
				else
					update_metadata( $meta_type, $object_id, $key, $value );
			}
		}
	}

	/**
	 * @param array  $arr
	 * @param string $name
	 * @param mixed  $value
	 */
	private static function set_value( &$arr, $name, $value ) {
		$name = (array) $name;

		$final_key = array_pop( $name );

		while ( !empty( $name ) ) {
			$key = array_shift( $name );

			if ( !isset( $arr[ $key ] ) )
				$arr[ $key ] = array();

			$arr =& $arr[ $key ];
		}

		$arr[ $final_key ] = $value;
	}
}


/**
 * A wrapper for scbForms, containing the formdata
 */
class scbForm {
	protected $data   = array();
	protected $prefix = array();

	/**
	 * @param array          $data
	 * @param string|boolean $prefix
	 */
	function __construct( $data, $prefix = false ) {
		if ( is_array( $data ) )
			$this->data = $data;

		if ( $prefix )
			$this->prefix = (array) $prefix;
	}

	/**
	 * @param string $path
	 *
	 * @return scbForm
	 */
	function traverse_to( $path ) {
		$data = scbForms::get_value( $path, $this->data );

		$prefix = array_merge( $this->prefix, (array) $path );

		return new scbForm( $data, $prefix );
	}

	/**
	 * @param array $args
	 *
	 * @return string
	 */
	function input( $args ) {
		$value = scbForms::get_value( $args['name'], $this->data );

		if ( !empty( $this->prefix ) ) {
			$args['name'] = array_merge( $this->prefix, (array) $args['name'] );
		}

		return scbForms::input_with_value( $args, $value );
	}
}

/**
 * Interface for form fields.
 */
interface scbFormField_I {

	/**
	 * Generate the corresponding HTML for a field
	 *
	 * @param mixed $value The value to use
	 *
	 * @return string
	 */
	function render( $value = null );

	/**
	 * Validates a value against a field.
	 *
	 * @param mixed $value The value to check
	 *
	 * @return mixed null if the validation failed, sanitized value otherwise.
	 */
	function validate( $value );
}

/**
 * Base class for form fields implementations.
 */
abstract class scbFormField implements scbFormField_I {

	protected $args;

	/**
	 * @param array|scbFormField_I $args
	 *
	 * @return mixed false on failure or instance of form class
	 */
	public static function create( $args ) {
		if ( is_a( $args, 'scbFormField_I' ) )
			return $args;

		if ( empty( $args['name'] ) ) {
			return trigger_error( 'Empty name', E_USER_WARNING );
		}

		if ( isset( $args['value'] ) && is_array( $args['value'] ) ) {
			$args['choices'] = $args['value'];
			unset( $args['value'] );
		}

		if ( isset( $args['values'] ) ) {
			$args['choices'] = $args['values'];
			unset( $args['values'] );
		}

		if ( isset( $args['extra'] ) && !is_array( $args['extra'] ) )
			$args['extra'] = shortcode_parse_atts( $args['extra'] );

		$args = wp_parse_args( $args, array(
			'desc'      => '',
			'desc_pos'  => 'after',
			'wrap'      => scbForms::TOKEN,
			'wrap_each' => scbForms::TOKEN,
		) );

		// depends on $args['desc']
		if ( isset( $args['choices'] ) )
			self::_expand_choices( $args );

		switch ( $args['type'] ) {
		case 'radio':
			return new scbRadiosField( $args );
		case 'select':
			return new scbSelectField( $args );
		case 'checkbox':
			if ( isset( $args['choices'] ) )
				return new scbMultipleChoiceField( $args );
			else
				return new scbSingleCheckboxField( $args );
		case 'custom':
			return new scbCustomField( $args );
		default:
			return new scbTextField( $args );
		}
	}

	/**
	 * @param array $args
	 */
	protected function __construct( $args ) {
		$this->args = $args;
	}

	/**
	 * @param string $key
	 *
	 * @return mixed
	 */
	public function __get( $key ) {
		return $this->args[ $key ];
	}

	/**
	 * @param string $key
	 *
	 * @return bool
	 */
	public function __isset( $key ) {
		return isset( $this->args[ $key ] );
	}

	/**
	 * @param mixed $value
	 *
	 * @return string
	 */
	public function render( $value = null ) {
		if ( null === $value && isset( $this->default ) )
			$value = $this->default;

		$args = $this->args;

		if ( null !== $value )
			$this->_set_value( $args, $value );

		$args['name'] = scbForms::get_name( $args['name'] );

		return str_replace( scbForms::TOKEN, $this->_render( $args ), $this->wrap );
	}

	/**
	 * Mutate the field arguments so that the value passed is rendered.
	 *
	 * @param array  $args
	 * @param mixed  $value
	 */
	abstract protected function _set_value( &$args, $value );

	/**
	 * The actual rendering
	 *
	 * @param array $args
	 */
	abstract protected function _render( $args );

	/**
	 * Handle args for a single checkbox or radio input
	 *
	 * @param array $args
	 *
	 * @return string
	 */
	protected static function _checkbox( $args ) {
		$args = wp_parse_args( $args, array(
			'value'   => true,
			'desc'    => null,
			'checked' => false,
			'extra'   => array(),
		) );

		foreach ( $args as $key => &$val )
			$$key = &$val;
		unset( $val );

		$extra['checked'] = $checked;

		if ( is_null( $desc ) && !is_bool( $value ) )
			$desc = str_replace( '[]', '', $value );

		return self::_input_gen( $args );
	}

	/**
	 * Generate html with the final args
	 *
	 * @param array $args
	 *
	 * @return string
	 */
	protected static function _input_gen( $args ) {
		extract( wp_parse_args( $args, array(
			'value' => null,
			'desc'  => null,
			'extra' => array(),
		) ) );

		$extra['name'] = $name;

		if ( 'textarea' == $type ) {
			$input = html( 'textarea', $extra, esc_textarea( $value ) );
		} else {
			$extra['value'] = $value;
			$extra['type']  = $type;
			$input = html( 'input', $extra );
		}

		return self::add_label( $input, $desc, $desc_pos );
	}

	/**
	 * @param string $input
	 * @param string $desc
	 * @param string $desc_pos
	 *
	 * @return string
	 */
	protected static function add_label( $input, $desc, $desc_pos ) {
		return html( 'label', self::add_desc( $input, $desc, $desc_pos ) ) . "\n";
	}

	/**
	 * @param string $input
	 * @param string $desc
	 * @param string $desc_pos
	 *
	 * @return string
	 */
	protected static function add_desc( $input, $desc, $desc_pos ) {
		if ( empty( $desc ) )
			return $input;

		if ( 'before' == $desc_pos )
			return $desc . ' ' . $input;
		else
			return $input . ' ' . $desc;
	}

	/**
	 * @param array $args
	 */
	private static function _expand_choices( &$args ) {
		$choices =& $args['choices'];

		if ( !empty( $choices ) && !self::is_associative( $choices ) ) {
			if ( is_array( $args['desc'] ) ) {
				$choices = array_combine( $choices, $args['desc'] );	// back-compat
				$args['desc'] = false;
			} elseif ( !isset( $args['numeric'] ) || !$args['numeric'] ) {
				$choices = array_combine( $choices, $choices );
			}
		}
	}

	/**
	 * @param array $array
	 *
	 * @return bool
	 */
	private static function is_associative( $array ) {
		$keys = array_keys( $array );
		return array_keys( $keys ) !== $keys;
	}
}

/**
 * Text form field.
 */
class scbTextField extends scbFormField {

	/**
	 * @param string $value
	 *
	 * @return string
	 */
	public function validate( $value ) {
		$sanitize = isset( $this->sanitize ) ? $this->sanitize : 'wp_filter_kses';

		return call_user_func( $sanitize, $value, $this );
	}

	/**
	 * @param array $args
	 *
	 * @return string
	 */
	protected function _render( $args ) {
		$args = wp_parse_args( $args, array(
			'value'    => '',
			'desc_pos' => 'after',
			'extra'    => array( 'class' => 'regular-text' ),
		) );

		foreach ( $args as $key => &$val )
			$$key = &$val;
		unset( $val );

		if ( !isset( $extra['id'] ) && !is_array( $name ) && false === strpos( $name, '[' ) )
			$extra['id'] = $name;

		return scbFormField::_input_gen( $args );
	}

	/**
	 * @param array  $args
	 * @param string $value
	 */
	protected function _set_value( &$args, $value ) {
		$args['value'] = $value;
	}
}

/**
 * Base class for form fields with single choice.
 */
abstract class scbSingleChoiceField extends scbFormField {

	/**
	 * @param mixed $value
	 *
	 * @return mixed|null
	 */
	public function validate( $value ) {
		if ( isset( $this->choices[ $value ] ) )
			return $value;

		return null;
	}

	/**
	 * @param array $args
	 *
	 * @return string
	 */
	protected function _render( $args ) {
		$args = wp_parse_args( $args, array(
			'numeric'  => false, // use numeric array instead of associative
			'selected' => array( 'foo' ), // hack to make default blank
		) );

		return $this->_render_specific( $args );
	}

	/**
	 * @param array $args
	 * @param mixed $value
	 */
	protected function _set_value( &$args, $value ) {
		$args['selected'] = $value;
	}

	/**
	 * @param array $args
	 *
	 * @return string
	 */
	abstract protected function _render_specific( $args );
}

/**
 * Dropdown field.
 */
class scbSelectField extends scbSingleChoiceField {

	protected function _render_specific( $args ) {
		extract( wp_parse_args( $args, array(
			'text'  => false,
			'extra' => array(),
		) ) );

		$options = array();

		if ( false !== $text ) {
			$options[] = array(
				'value'    => '',
				'selected' => ( $selected == array( 'foo' ) ),
				'title'    => $text,
			);
		}

		foreach ( $choices as $value => $title ) {
			$options[] = array(
				'value'    => $value,
				'selected' => ( $value == $selected ),
				'title'    => $title,
			);
		}

		$opts = '';
		foreach ( $options as $option ) {
			extract( $option );

			$opts .= html( 'option', compact( 'value', 'selected' ), $title );
		}

		$extra['name'] = $name;

		$input = html( 'select', $extra, $opts );

		return scbFormField::add_label( $input, $desc, $desc_pos );
	}
}

/**
 * Radio field.
 */
class scbRadiosField extends scbSelectField {

	/**
	 * @param array $args
	 *
	 * @return string
	 */
	protected function _render_specific( $args ) {
		extract( $args );

		if ( array( 'foo' ) == $selected ) {
			// radio buttons should always have one option selected
			$selected = key( $choices );
		}

		$opts = '';
		foreach ( $choices as $value => $title ) {
			$single_input = scbFormField::_checkbox( array(
				'name'     => $name,
				'type'     => 'radio',
				'value'    => $value,
				'checked'  => ( $value == $selected ),
				'desc'     => $title,
				'desc_pos' => 'after',
			) );

			$opts .= str_replace( scbForms::TOKEN, $single_input, $wrap_each );
		}

		return scbFormField::add_desc( $opts, $desc, $desc_pos );
	}
}

/**
 * Checkbox field with multiple choices.
 */
class scbMultipleChoiceField extends scbFormField {

	/**
	 * @param mixed $value
	 *
	 * @return array
	 */
	public function validate( $value ) {
		return array_intersect( array_keys( $this->choices ), (array) $value );
	}

	/**
	 * @param array $args
	 *
	 * @return string
	 */
	protected function _render( $args ) {
		$args = wp_parse_args( $args, array(
			'numeric' => false, // use numeric array instead of associative
			'checked' => null,
		) );

		extract( $args );

		if ( !is_array( $checked ) )
			$checked = array();

		$opts = '';
		foreach ( $choices as $value => $title ) {
			$single_input = scbFormField::_checkbox( array(
				'name'     => $name . '[]',
				'type'     => 'checkbox',
				'value'    => $value,
				'checked'  => in_array( $value, $checked ),
				'desc'     => $title,
				'desc_pos' => 'after',
			) );

			$opts .= str_replace( scbForms::TOKEN, $single_input, $wrap_each );
		}

		return scbFormField::add_desc( $opts, $desc, $desc_pos );
	}

	/**
	 * @param array $args
	 * @param mixed $value
	 */
	protected function _set_value( &$args, $value ) {
		$args['checked'] = (array) $value;
	}
}

/**
 * Checkbox field.
 */
class scbSingleCheckboxField extends scbFormField {

	/**
	 * @param mixed $value
	 *
	 * @return boolean
	 */
	public function validate( $value ) {
		return (bool) $value;
	}

	/**
	 * @param array $args
	 *
	 * @return string
	 */
	protected function _render( $args ) {
		$args = wp_parse_args( $args, array(
			'value'   => true,
			'desc'    => null,
			'checked' => false,
			'extra'   => array(),
		) );

		foreach ( $args as $key => &$val )
			$$key = &$val;
		unset( $val );

		$extra['checked'] = $checked;

		if ( is_null( $desc ) && !is_bool( $value ) )
			$desc = str_replace( '[]', '', $value );

		return scbFormField::_input_gen( $args );
	}

	/**
	 * @param array $args
	 * @param mixed $value
	 */
	protected function _set_value( &$args, $value ) {
		$args['checked'] = ( $value || ( isset( $args['value'] ) && $value == $args['value'] ) );
	}
}

/**
 * Wrapper field for custom callbacks.
 */
class scbCustomField implements scbFormField_I {

	protected $args;

	/**
	 * @param array $args
	 */
	function __construct( $args ) {
		$this->args = wp_parse_args( $args, array(
			'render'   => 'var_dump',
			'sanitize' => 'wp_filter_kses',
		) );
	}

	/**
	 * @param string $key
	 *
	 * @return mixed
	 */
	public function __get( $key ) {
		return $this->args[ $key ];
	}

	/**
	 * @param string $key
	 *
	 * @return boolean
	 */
	public function __isset( $key ) {
		return isset( $this->args[ $key ] );
	}

	/**
	 * @param mixed $value
	 *
	 * @return string
	 */
	public function render( $value = null ) {
		return call_user_func( $this->render, $value, $this );
	}

	/**
	 * @param mixed $value
	 *
	 * @return mixed
	 */
	public function validate( $value ) {
		return call_user_func( $this->sanitize, $value, $this );
	}
}

