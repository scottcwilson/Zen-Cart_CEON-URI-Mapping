<?php
/**
 * Ceon URI Mapping URI Admin Base Class.
 *
 * @package     ceon_uri_mapping
 * @author      Conor Kerr <zen-cart.uri-mapping@ceon.net>
 * @copyright   Copyright 2008-2019 Ceon
 * @copyright   Copyright 2003-2019 Zen Cart Development Team
 * @copyright   Portions Copyright 2003 osCommerce
 * @link        http://ceon.net/software/business/zen-cart/uri-mapping
 * @license     http://www.zen-cart.com/license/2_0.txt GNU Public License V2.0
 * @version     $Id: class.CeonURIMappingAdmin.php 1027 2012-07-17 20:31:10Z conor $
 */

if (!defined('IS_ADMIN_FLAG')) {
	die('Illegal Access');
}

/**
 * Load in the parent class if not already loaded
 */
require_once(DIR_FS_CATALOG . DIR_WS_CLASSES . 'class.CeonURIMappingDBLookup.php');


// {{{ constants

define('CEON_URI_MAPPING_SINGLE_UNDERSCORE', 1);
define('CEON_URI_MAPPING_SINGLE_DASH', 2);
define('CEON_URI_MAPPING_SINGLE_FULL_STOP', 3);
define('CEON_URI_MAPPING_REMOVE', 4);

define('CEON_URI_MAPPING_CAPITALISATION_LOWERCASE', 1);
define('CEON_URI_MAPPING_CAPITALISATION_AS_IS', 2);
define('CEON_URI_MAPPING_CAPITALISATION_UCFIRST', 3);

define('CEON_URI_MAPPING_ADD_MAPPING_SUCCESS', 1);
define('CEON_URI_MAPPING_ADD_MAPPING_ERROR_MAPPING_EXISTS', -1);
define('CEON_URI_MAPPING_ADD_MAPPING_ERROR_DATA_ERROR', -2);
define('CEON_URI_MAPPING_ADD_MAPPING_ERROR_DB_ERROR', -3);

define('CEON_URI_MAPPING_MAKE_MAPPING_HISTORICAL_SUCCESS', 1);
define('CEON_URI_MAPPING_MAKE_MAPPING_HISTORICAL_ERROR_DATA_ERROR', -1);

// }}}


// {{{ CeonURIMappingAdmin

/**
 * Provides shared functionality for the Ceon URI Mapping admin functionality.
 *
 * @package     ceon_uri_mapping
 * @author      Conor Kerr <zen-cart.uri-mapping@ceon.net>
 * @copyright   Copyright 2008-2019 Ceon
 * @copyright   Copyright 2003-2007 Zen Cart Development Team
 * @copyright   Portions Copyright 2003 osCommerce
 * @link        http://ceon.net/software/business/zen-cart/uri-mapping
 * @license     http://www.zen-cart.com/license/2_0.txt GNU Public License V2.0
 */
class CeonURIMappingAdmin extends CeonURIMappingDBLookup
{
	// {{{ properties
	
	/**
	 * Whether autogeneration of URI mappings is enabled or not.
	 *
	 * @var     boolean
	 * @access  protected
	 */
	protected $_autogen_new = null;
	
	/**
	 * The site's setting for the whitesapce replacement character.
	 *
	 * @var     integer
	 * @access  protected
	 */
	protected $_whitespace_replacement = null;
	
	/**
	 * The site's setting for the capitalisation of words in URI mappings.
	 *
	 * @var     integer
	 * @access  protected
	 */
	protected $_capitalisation = null;
	
	/**
	 * The list of words which should be removed from URI mappings being autogenerated. This property is not parsed
	 * from its encoded string into a array until used for the first time, to save processing time.
	 *
	 * @var     array
	 * @access  protected
	 */
	protected $_remove_words = null;
	
	/**
	 * The list of character to string replacements which should be applied to URI mappings being autogenerated.
	 * This is not parsed from its encoded string into a usable array until used for the first time, to save
	 * processing time.
	 *
	 * @var     array
	 * @access  protected
	 */
	protected $_char_str_replacements = null;
	
	/** 
	 * The add language code identifier to URI setting for the store. 
	 * 
	 * @var     integer 
	 * @access  protected 
	 */ 
	protected $_language_code_add = null; 


	/**
	 * The site's setting for the handling of a URI mapping which clashes with an existing mapping.
	 *
	 * @var     string
	 * @access  protected
	 */
	protected $_mapping_clash_action = null;
	
	// }}}
	
	
	// {{{ Class Constructor
	
	/**
	 * Creates a new instance of the CeonURIMappingAdmin class. Loads the autogeneration settings for the store and
	 * sets the class properties' values.
	 * 
	 * @param   boolean   Whether or not the autogeneration configuration should be loaded when instantiating the
	 *                    class.
	 * @access  public
	 */
	public function __construct($load_config = true)
	{
		parent::__construct();
		
		if ($load_config) {
			$this->_loadAutogenerationSettings();
		}
	}
	
	// }}}
	
	
	// {{{ _loadAutogenerationSettings()
	
	/**
	 * Loads the autogeneration settings for the store and sets the class properties' values.
	 *
	 * @access  protected
	 * @return  none
	 */
	protected function _loadAutogenerationSettings()
	{
		global $db;
		
		// Only one config currently supported so the ID is hard-coded in the following SQL
		$load_autogen_settings_sql = "
			SELECT
				autogen_new,
				whitespace_replacement,
				capitalisation,
				remove_words,
				char_str_replacements,
				language_code_add,
				mapping_clash_action
			FROM
				" . TABLE_CEON_URI_MAPPING_CONFIGS . "
			WHERE
				id ='1';";
		
		$load_autogen_settings_result = $db->Execute($load_autogen_settings_sql);
		
		if (!$load_autogen_settings_result->EOF) {
			$this->_autogen_new = ($load_autogen_settings_result->fields['autogen_new'] == 1 ? true : false);
			
			$this->_whitespace_replacement = $load_autogen_settings_result->fields['whitespace_replacement'];
			
			$this->_capitalisation = $load_autogen_settings_result->fields['capitalisation'];
			
			$this->_remove_words = $load_autogen_settings_result->fields['remove_words'];
			
			$this->_char_str_replacements = $load_autogen_settings_result->fields['char_str_replacements'];
			
			$this->_language_code_add = $load_autogen_settings_result->fields['language_code_add'];
			
			$this->_mapping_clash_action = $load_autogen_settings_result->fields['mapping_clash_action'];
		}
	}
	
	// }}}
	
	
	// {{{ addURIMapping()
	
	/**
	 * Adds a URI mapping to the database. Use of this method abstracts the calling code from the actual
	 * implementation of the database's structure.
	 *
	 * @access  public
	 * @param   string    $uri   The URI for the mapping.
	 * @param   integer   $language_id   The Zen Cart language ID for the mapping.
	 * @param   string    $main_page   The Zen Cart page type the mapping is for.
	 * @param   string    $query_string_parameters   The query string parameters which are to be passed on to Zen
	 *                                               Cart as if they were in the original URI. ZC often uses this
	 *                                               information to "define" a page type or a particular instance
	 *                                               of a page type.
	 * @param   integer   $associated_db_id   The ID of a database record to be passed on to ZC. ZC uses this to
	 *                                        identify a particular instance of a page type.
	 * @param   string    $alternate_uri   An alternative URI to redirect to, instead of mapping to a Zen Cart
	 *                                     page.
	 * @param   integer   $redirection_type_code   The redirection type code to be used when redirecting to an
	 *                                             alternate URI.
	 * @param   boolean   $avoid_duplicates   Whether or not to prevent the creation of a duplicate mapping. Should
	 *                                        only ever be disabled if checks have already been made and should
	 *                                        therefore be skipped here for efficiency.
	 * @return  integer   A positive value if the mapping was added, a negative integer (constant value) if a
	 *                    mapping already exists for the specified URI or an error was encountered.
	 */
	public function addURIMapping($uri, $language_id = null, $main_page = null, $query_string_parameters = null,
		$associated_db_id = null, $alternate_uri = null, $redirection_type_code = null, $avoid_duplicates = true)
	{
		global $db;
		
		if (is_null($uri) || strlen($uri) == 0) {
			// Cannot be null!
			return CEON_URI_MAPPING_ADD_MAPPING_ERROR_DATA_ERROR;
		} else {
			$uri_quoted = "'" . zen_db_input(zen_db_prepare_input($uri)) . "'";
		}
		
		if (is_null($language_id) || (int) $language_id <= 0) {
			$language_id_quoted = 'NULL';
		} else {
			$language_id_quoted = "'" . (int) $language_id . "'";
		}
		
		$current_uri_quoted = "'1'";
		
		if (is_null($main_page) || strlen($main_page) == 0) {
			$main_page_quoted = 'NULL';
		} else {
			$main_page_quoted = "'" . zen_db_input(zen_db_prepare_input($main_page)) . "'";
		}
		
		if (is_null($query_string_parameters) || strlen($query_string_parameters) == 0) {
			$query_string_parameters_quoted = 'NULL';
		} else {
			$query_string_parameters_quoted =
				"'" . zen_db_input(zen_db_prepare_input($query_string_parameters)) . "'";
		}
		
		if (is_null($associated_db_id) || (int) $associated_db_id <= 0) {
			$associated_db_id_quoted = 'NULL';
		} else {
			$associated_db_id_quoted = "'" . (int) $associated_db_id . "'";
		}
		
		if (is_null($alternate_uri) || strlen($alternate_uri) == 0) {
			$alternate_uri_quoted = 'NULL';
		} else {
			$alternate_uri_quoted = "'" . zen_db_input(zen_db_prepare_input($alternate_uri)) . "'";
		}
		
		if (is_null($redirection_type_code) || (int) $redirection_type_code <= 0) {
			$redirection_type_code_quoted = 'NULL';
		} else {
			$redirection_type_code_quoted = "'" . (int) $redirection_type_code . "'";
		}
		
		// Language ID can only be null when the mapping is a redirect to an alternate URI
		if ($language_id_quoted == 'NULL' && $alternate_uri_quoted == 'NULL') {
			return CEON_URI_MAPPING_ADD_MAPPING_ERROR_DATA_ERROR;
		}
		
		if ($avoid_duplicates && $alternate_uri_quoted == 'NULL') {
			// Make sure no current mapping already exists for the specified URI (don't bother with checks for
			// mappings to alternate URIs)
			$check_exists_sql = "
				SELECT
					language_id
				FROM
					" . TABLE_CEON_URI_MAPPINGS . "
				WHERE
					uri = " . $uri_quoted . "
				AND
					language_id = " . $language_id_quoted . "
				AND
					current_uri = '1';";
			
			$check_exists_result = $db->Execute($check_exists_sql);
			
			if (!$check_exists_result->EOF) {
				return CEON_URI_MAPPING_ADD_MAPPING_ERROR_MAPPING_EXISTS;
			}
		}
		
		$date_added_quoted = "'" . date('Y-m-d H:i:s') . "'";
		
		$sql = "
			INSERT INTO
				" . TABLE_CEON_URI_MAPPINGS . "
				(
				uri,
				language_id,
				current_uri,
				main_page,
				query_string_parameters,
				associated_db_id,
				alternate_uri,
				redirection_type_code,
				date_added
				)
			VALUES
				(
				" . $uri_quoted . ",
				" . $language_id_quoted . ",
				" . $current_uri_quoted . ",
				" . $main_page_quoted . ",
				" . $query_string_parameters_quoted . ",
				" . $associated_db_id_quoted . ",
				" . $alternate_uri_quoted . ",
				" . $redirection_type_code_quoted . ",
				" . $date_added_quoted . "
				);";
		
		$db->Execute($sql);
		
		return CEON_URI_MAPPING_ADD_MAPPING_SUCCESS;
	}
	
	// }}}
	
	
	// {{{ makeURIMappingHistorical()
	
	/**
	 * Makes the URI mapping a historical mapping. Use of this method abstracts the calling code from the actual
	 * implementation of the database's structure.
	 *
	 * @access  public
	 * @param   string    $uri   The URI of the mapping.
	 * @param   integer   $language_id   The Zen Cart language ID for the mapping.
	 * @param   boolean   $avoid_duplicates   Whether or not to prevent the creation of duplicate historical
	 *                                        mappings. Should only ever be disabled if checks have already been
	 *                                        made and should therefore be skipped here for efficiency.
	 * @return  integer   A positive value if the mapping was made historical, a negative integer (constant value)
	 *                    if an error was encountered.
	 */
	public function makeURIMappingHistorical($uri, $language_id, $avoid_duplicates = true)
	{
		global $db;
		
		if (is_null($uri) || strlen($uri) == 0) {
			// Cannot be null!
			return CEON_URI_MAPPING_MAKE_MAPPING_HISTORICAL_ERROR_DATA_ERROR;
		} else {
			$uri_quoted = "'" . zen_db_input(zen_db_prepare_input($uri)) . "'";
		}
		
		if (is_null($language_id) || (int) $language_id <= 0) {
			// Cannot be null!
			return CEON_URI_MAPPING_MAKE_MAPPING_HISTORICAL_ERROR_DATA_ERROR;
		} else {
			$language_id_quoted = "'" . (int) $language_id . "'";
		}
		
		if ($avoid_duplicates) {
			// Make sure no historical mapping already exists for the specified URI
			$check_exists_sql = "
				SELECT
					language_id
				FROM
					" . TABLE_CEON_URI_MAPPINGS . "
				WHERE
					uri = " . $uri_quoted . "
				AND
					language_id = " . $language_id_quoted . "
				AND
					current_uri = '0';";
			
			$check_exists_result = $db->Execute($check_exists_sql);
			
			if (!$check_exists_result->EOF) {
				$selections = array(
					'uri' => $uri,
					'language_id' => $language_id,
					'current_uri' => 0
					);
				
				$this->deleteURIMappings($selections);
			}
		}
		
		$sql = "
			UPDATE
				" . TABLE_CEON_URI_MAPPINGS . "
			SET
				current_uri = '0'
			WHERE
				uri = " . $uri_quoted . "
			AND
				language_id = " . $language_id_quoted . ";";
		
		$db->Execute($sql);
		
		return CEON_URI_MAPPING_MAKE_MAPPING_HISTORICAL_SUCCESS;
	}
	
	// }}}
	
	
	// {{{ deleteURIMappings()
	
	/**
	 * Deletes any URI mappings matching the specified criteria. Use of this method abstracts the calling code from
	 * the actual implementation of the database's structure.
	 *
	 * @access  public                  Made public because of previous uses in functions type files.  Can stay
	 *                                    protected when call is in an observer provided the observer class
	 *                                    ultimately extends this class.
	 * @param   array     $selections   An associative array of column names and values to match for these columns.
	 *                                  A set of values can be grouped with OR by specifying an array of values for
	 *                                  the value.
	 * @return  none
	 */
	public function deleteURIMappings($selections)
	{
		global $db;
		
		$selection_string = '';
		
		$num_selection_columns = count($selections);
		
		$column_name_i = 0;
		
		foreach ($selections as $column_name => $column_value) {
			if (is_array($column_value)) {
				// The value is an array of values so create an ORed group
				$num_column_values = count($column_value);
				
				$selection_string .= '(' . "\n";
				
				for ($column_value_i = 0; $column_value_i < $num_column_values; $column_value_i++) {
					$selection_string .= "\t" . $column_name;
					
					$value = $column_value[$column_value_i];
					
					if (is_null($value) || strtolower($value) == 'null') {
						$selection_string .= " IS NULL\n";
					} else if (strtolower($value) == 'not null') {
						$selection_string .= " IS NOT NULL\n";
					} else {
						if (substr($value, -1) == '%') {
							$selection_string .= ' LIKE ';
						} else {
							$selection_string .= ' = ';
						}
						
						$selection_string .= "'" . zen_db_input($value) . "'\n";
					}
					
					if ($column_value_i < ($num_column_values - 1)) {
						$selection_string .= "OR\n";
					}
				}
				
				$selection_string .= ')' . "\n";
			} else {
				$selection_string .= "\t" . $column_name;
				
				if (is_null($column_value) || strtolower($column_value) == 'null') {
					$selection_string .= " IS NULL\n";
				} else if (strtolower($column_value) == 'not null') {
					$selection_string .= " IS NOT NULL\n";
				} else {
					if (substr($column_value, -1) == '%') {
						$selection_string .= ' LIKE ';
					} else {
						$selection_string .= ' = ';
					}
					
					$selection_string .= "'" . zen_db_input($column_value) . "'\n";
				}
			}
			
			if ($column_name_i < ($num_selection_columns - 1)) {
				$selection_string .= "AND\n";
			}
			
			$column_name_i++;
		}
		
		$sql = "
			DELETE FROM
				" . TABLE_CEON_URI_MAPPINGS . "
			WHERE
				" . $selection_string . ";";
		
		$db->Execute($sql);
	}
	
	// }}}
	
	
	// {{{ _autogenEnabled()
	
	/**
	 * Checks whether auto-generation of URIs is enabled or disabled.
	 *
	 * @access  protected
	 * @return  boolean   Whether auto-generation of URIs is enabled or disabled.
	 */
	protected function _autogenEnabled()
	{
		return $this->_autogen_new;
	}
	
	// }}}
	
	
	// {{{ _autoLanguageCodeAdd()
	
	/**
	 * Checks whether or not the site's settings dictate to prepend the language code to the URI.
	 *
	 * @access  protected
	 * @return  boolean   Whether or not a unique URI Mapping should be autogenerated by appending an integer to a
	 *                    clashing mapping.
	 */
	protected function _autoLanguageCodeAdd()
	{
		return $this->_language_code_add;
	}
	
	// }}}
	
	
	// {{{ _mappingClashAutoAppendInteger()
	
	/**
	 * Checks whether or not the site's settings dictate that when a mapping clashes, an attempt should be made to
	 * autogenerate a new, unique URI mapping by appending an integer to the clashing mapping.
	 *
	 * @access  protected
	 * @return  boolean   Whether or not a unique URI Mapping should be autogenerated by appending an integer to a
	 *                    clashing mapping.
	 */
	protected function _mappingClashAutoAppendInteger()
	{
		if ($this->_mapping_clash_action == 'auto-append') {
			return true;
		}
		
		return false;
	}
	
	// }}}
	
	
	// {{{ _convertStringForURI()
	
	/**
	 * Converts a string to a URI format, capitalising the words within as specified in the configuration, removing
	 * any unwanted words and replacing any specific character strings, transliterating it from a foreign language
	 * if necessary, then applying the whitespace replacement preference for the site.
	 *
	 * @access  protected
	 * @param   string    $text            The string to be converted to a URI part.
	 * @param   string    $language_code   The language code of the string to be converted.
	 * @param   boolean   $strip_slashes   Whether or not to strip foward slashes from the string.
	 * @return  string    The string as converted for use in a URI.
	 */
	protected function _convertStringForURI($text, $language_code, $strip_slashes = true)
	{
		//global $db;
		
		/**
		 * Load in the CeonString class to handle multibyte characters and transliteration.
		 */
		require_once(DIR_FS_CATALOG . DIR_WS_CLASSES . 'class.CeonString.php');
		
		
		// If the remove words and character string replacement settings have not yet been parsed,
		// parse them now
		if (!is_array($this->_remove_words)) {
			// Remove any spaces from between the words
			$this->_remove_words = CeonString::regexpReplace($this->_remove_words, '/\s/', '');
			
			// Escape any special characters in the selections of words
			$this->_remove_words = CeonString::regexpReplace($this->_remove_words,
				'/([\/\-\\\!\?\$\^\[\]\|\*\.\(\)\{\}\=\<\>\:\+])/U', '\\\$1');
			
			// Get the list of escaped words - @TODO is this multibyte safe?
			$this->_remove_words = explode(',', $this->_remove_words);
			
			if (count($this->_remove_words) == 1 && (is_null($this->_remove_words[0]) ||
					strlen($this->_remove_words[0]) == 0)) {
				$this->_remove_words = array();
			}
			
			$char_str_replacement_pairs = explode(',', $this->_char_str_replacements);
			
			$this->_char_str_replacements = array();
			
			for ($i = 0, $n = count($char_str_replacement_pairs); $i < $n; $i++) {
				$current_char_str_replacement = explode('=>', $char_str_replacement_pairs[$i]);
				
				if (count($current_char_str_replacement) == 2) {
					// Escape any special characters in the string to be replaced
					$current_char_str_replacement[0] = CeonString::regexpReplace($current_char_str_replacement[0],
						'/([\/\-\\\!\?\$\^\[\]\|\*\.\(\)\{\}\=\<\>\:\+])/U', '\\\$1');
					
					// Remove any spaces surrounding the string to be replaced
					$current_char_str_replacement[0] = trim($current_char_str_replacement[0]);
					
					$this->_char_str_replacements[] = array(
						'char_str' => $current_char_str_replacement[0],
						'replacement' => $current_char_str_replacement[1]
						);
				}
			}
		}
		
		// Convert the case of the string according to the configuration setting
		switch ($this->_capitalisation) {
			case CEON_URI_MAPPING_CAPITALISATION_LOWERCASE:
				$text = CeonString::toLowercase($text);
				break;
			case CEON_URI_MAPPING_CAPITALISATION_UCFIRST:
				$text = CeonString::toUCWords($text);
				break;
		}
		
		// Remove unwanted words
		if (count($this->_remove_words) > 0) {
			// Build a pattern to remove words surrounded by spaces
			$remove_words_pattern = '';
			
			foreach ($this->_remove_words as $remove_word) {
				// Set word to be removed in the pattern by wrapping it with spaces
				$remove_words_pattern .= '\s' . $remove_word . '\s|';
			}
			
			$remove_words_pattern =
				CeonString::substr($remove_words_pattern, 0, CeonString::length($remove_words_pattern) - 1);
			
			// Remove the words surrounded by spaces, replacing them with a single space
			$text = CeonString::regexpReplace($text, '/' . $remove_words_pattern . '/i', ' ');
			
			
			// Build a pattern to remove words at the start of the string
			$remove_words_pattern = '';
			
			foreach ($this->_remove_words as $remove_word) {
				// Set word to be removed in the pattern by wrapping it with spaces
				$remove_words_pattern .= '^' . $remove_word . '\s|';
			}
			
			$remove_words_pattern =
				CeonString::substr($remove_words_pattern, 0, CeonString::length($remove_words_pattern) - 1);
			
			// Remove any word which is at the start of the string
			$text = CeonString::regexpReplace($text, '/' . $remove_words_pattern . '/i', '');
			
			
			// Build a pattern to remove any word which at the end of the string
			$remove_words_pattern = '';
			
			foreach ($this->_remove_words as $remove_word) {
				// Set word to be removed in the pattern by wrapping it with spaces
				$remove_words_pattern .= '\s' . $remove_word . '$|';
			}
			
			$remove_words_pattern =
				CeonString::substr($remove_words_pattern, 0, CeonString::length($remove_words_pattern) - 1);
			
			// Remove any word which is at the end of the string
			$text = CeonString::regexpReplace($text, '/' . $remove_words_pattern . '/i', '');
		}
		
		// Replace specified characters/strings
		if (count($this->_char_str_replacements) > 0) {
			foreach ($this->_char_str_replacements as $char_str_replacement) {
				// Remove the words surrounded by spaces, replacing them with a single space
				$text = CeonString::regexpReplace($text, '/' . $char_str_replacement['char_str'] . '/i',
					$char_str_replacement['replacement']);
			}
		}
		
		// Convert certain characters in the name to spaces, rather than having the words separated by these
		// characters being joined together when they are removed later
		if ($strip_slashes) {
			$pattern = '/[\|\/\+_:;\(\)\[\]\<\>,]/';
		} else {
			$pattern = '/[\|\+_:;\(\)\[\]\<\>,]/';
		}
		
		$text = preg_replace($pattern, ' ', $text);
		
		// Convert the string to English ASCII as that's all that's permitted in URIs
		$text = CeonString::transliterate($text, CHARSET, $language_code);
		
		// Remove illegal characters
		if ($strip_slashes) {
			$pattern = '/[^a-zA-Z0-9\.\-_\s]/';
		} else {
			$pattern = '/[^a-zA-Z0-9\.\-_\s\/]/';
		}
		
		$text = preg_replace($pattern, '', $text);
		
		// Convert double whitespace/tabs etc. to single space
		$text = preg_replace('/\s+/', ' ', $text);
		
		// Remove any starting or trailing whitespace
		$text = trim($text);
		
		// Once again convert the case of the string according to the configuration setting. 
		// This must be repeated as the transliteration could have left some letters in place which may need to be
		// converted. As the string is now ASCII, a simple conversion can be used.
		switch ($this->_capitalisation) {
			case CEON_URI_MAPPING_CAPITALISATION_LOWERCASE:
				$text = strtolower($text);
				break;
			case CEON_URI_MAPPING_CAPITALISATION_UCFIRST:
				$text = ucwords($text);
				break;
		}
		
		// Convert whitespace to configured character (or remove it)
		switch ($this->_whitespace_replacement) {
			case CEON_URI_MAPPING_SINGLE_UNDERSCORE:
				$whitespace_replacement_char = '_';
				break;
			case CEON_URI_MAPPING_SINGLE_DASH:
				$whitespace_replacement_char = '-';
				break;
			case CEON_URI_MAPPING_SINGLE_FULL_STOP:
				$whitespace_replacement_char = '.';
				break;
			case CEON_URI_MAPPING_REMOVE:
				$whitespace_replacement_char = '';
				break;
		}
		
		$text = preg_replace('/\s/', $whitespace_replacement_char, $text);
		
		return $text;
	}
	
	// }}}
	
	
	// {{{ _cleanUpURIMapping()
	
	/**
	 * Ensures that a URI matches the format used by the URI mapping functionality.
	 *
	 * @access  protected
	 * @param   string    $uri   The URI to be checked/cleaned.
	 * @return  string    The checked/cleaned URI.
	 */
	protected function _cleanUpURIMapping($uri)
	{
		// Remove any starting or trailing whitespace
		$uri = trim($uri);
		
		// Replace any backslashes with forward slashes
		$uri = str_replace('\\', '/', $uri);
		
		// Remove illegal characters
		$uri = preg_replace('|[^a-zA-Z0-9\.\-_\/]|', '', $uri);
		
		// Remove any domain specification as all URIs must be relative to the site's root
		$uri = preg_replace('|^http:\/\/[^\/]+|iU', '', $uri);
		
		// Get rid of any double slashes
		while (strpos($uri, '//') !== false) {
			$uri = str_replace('//', '/', $uri);
		}
		
		if (strlen($uri) == 0) {
			return '';
		}
		
		// Prepend the URI with a root slash
		while (substr($uri, 0, 1) == '/') {
			$uri = substr($uri, 1, strlen($uri) - 1);
		}
		
		$uri = '/' . $uri;
		
		// Remove any trailing slashes
		while (substr($uri, -1) == '/') {
			$uri = substr($uri, 0, strlen($uri) - 1);
		}
		
		return $uri;
	}
	
	// }}}
}

// }}}
