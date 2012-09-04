<?php

/**
 * PHP2JS
 * 
 * 	A PHP to JavaScript code translator.
 *
 * Features:
 *  - Free and open source
 *  - Plug and play, just include and GO!
 *  - Support translation of static contents (string, file) or dynamic
 *    objects (function, method, closure)
 *  - Translation of special operations (array, concatenation, ...) 
 *  - No dependency, no other library requirement
 *  - PHP 4 & 5 code compatible
 *
 * Permission is hereby granted, free of charge, to any person obtaining a
 * copy of this software and associated documentation files (the "Software"),
 * to deal in the Software without restriction, including without limitation
 * the rights to use, copy, modify, merge, publish, distribute, sublicense,
 * and/or sell copies of the Software, and to permit persons to whom the
 * Software is furnished to do so, subject to the following conditions:
 *
 * The above copyright notice and this permission notice shall be included
 * in all copies or substantial portions of the Software.
 *
 * THE SOFTWARE IS PROVIDED "AS IS", WITHOUT WARRANTY OF ANY KIND, EXPRESS
 * OR IMPLIED, INCLUDING BUT NOT LIMITED TO THE WARRANTIES OF MERCHANTABILITY,
 * FITNESS FOR A PARTICULAR PURPOSE AND NONINFRINGEMENT. IN NO EVENT SHALL THE
 * AUTHORS OR COPYRIGHT HOLDERS BE LIABLE FOR ANY CLAIM, DAMAGES OR OTHER
 * LIABILITY, WHETHER IN AN ACTION OF CONTRACT, TORT OR OTHERWISE, ARISING FROM,
 * OUT OF OR IN CONNECTION WITH THE SOFTWARE OR THE USE OR OTHER DEALINGS IN
 * THE SOFTWARE.
 *
 * @package    evolya.php2js
 * @author     ted <contact@evolya.fr>
 * @copyright  Copyright (c) 2013 Evolya.fr
 * @version    1.0
 * @license    http://www.opensource.org/licenses/MIT MIT License
 * @link       http://blog.evolya.fr/labo/fr/php2js/
 */
final class PHP2JS {

	/**
	 * Function replacements.
	 * @var string[]
	 */
	protected static $code_replace = array(
		'urlencode'		=> 'encodeURIComponent',
		'rawurlencode'	=> 'encodeURIComponent',
		'abs'			=> 'Math.abs',
		'ceil'			=> 'Math.ceil',
		'floor'			=> 'Math.floor',
		'round'			=> 'Math.round',
		'max'			=> 'Math.max',
		'min'			=> 'Math.min',
		'pow'			=> 'Math.pow',
		'sqrt'			=> 'Math.sqr',
		'is_array'		=> '"[object Array]" == Object.prototype.toString.call',
		'is_object'		=> '"object" == typeof',
		'is_null'		=> 'null === ',
		'is_bool'		=> '',
		'isset'			=> '',
		'gettype'		=> 'typeof'
	);

	/**
	 * Tokens to remove.
	 * @var int[]
	 */
	protected static $token_remove = array(
		T_OPEN_TAG,
		T_OPEN_TAG_WITH_ECHO,
		T_CLOSE_TAG,
		T_ARRAY_CAST,
		T_BOOL_CAST,
		T_COMMENT,
		T_DOC_COMMENT,
		T_DOUBLE_CAST,
		T_END_HEREDOC,
		T_INT_CAST,
		//T_ML_COMMENT,
		T_OBJECT_CAST,
		T_STRING_CAST
	);

	/**
	 * Tokens to replace.
	 * @var int[]
	 */
	protected static $token_replace = array(
		T_OBJECT_OPERATOR		=> '.',		// Replace -> operator
		T_PAAMAYIM_NEKUDOTAYIM	=> '.',		// Replace :: operator
		T_CONCAT_EQUAL			=> '+=',	// Replace .= operator
		T_IS_NOT_EQUAL			=> '!=',	// Avoid <> operator
		T_LOGICAL_AND			=> '&&',	// Avoid AND operator
		T_LOGICAL_OR			=> '||',	// Avoid OR operator
		T_DOUBLE_ARROW			=> ':',		// Avoid => in arrays
		T_UNSET					=> 'delete'	// Replace UNSET special token
	);
	
	/**
	 * Token to avoid.
	 * @var int[]
	 */
	protected static $token_forbidden = array(
		T_ABSTRACT,
		T_AND_EQUAL,
		T_CLASS,
		T_CLASS_C,
		T_CLONE,
		T_CONST,
		T_CURLY_OPEN,
		T_DECLARE,
		T_DIR,
		T_DOLLAR_OPEN_CURLY_BRACES,
		T_ENDDECLARE,
		T_ENDFOR,
		T_ENDFOREACH,
		T_ENDIF,
		T_ENDSWITCH,
		T_ENDWHILE,
		T_EXIT,
		T_EXTENDS,
		T_FILE,
		T_FINAL,
		T_FUNC_C,
		T_GOTO,
		T_HALT_COMPILER,
		T_IMPLEMENTS,
		T_INCLUDE,
		T_INCLUDE_ONCE,
		T_INLINE_HTML,
		//T_INSTEADOF,
		T_INTERFACE,
		T_LINE,
		T_METHOD_C,
		T_NAMESPACE,
		T_NS_C,
		T_NS_SEPARATOR,
		T_NUM_STRING,
		//T_OLD_FUNCTION,
		T_PRIVATE,
		T_PUBLIC,
		T_PROTECTED,
		T_REQUIRE,
		T_REQUIRE_ONCE,
		T_START_HEREDOC,
		T_STATIC,
		T_STRING_VARNAME,
		//T_TRAIT,
		//T_TRAIT_C,
		T_UNSET_CAST,
		T_USE,
		T_ENCAPSED_AND_WHITESPACE,
		T_VAR
	);

	/**
	 * Translate something in javascript code.
	 * 
	 * Argument can be:
	 * 	- A function name
	 *  - A closure instance
	 *  - An array, with an object and a method name
	 *  - A string containing a piece of PHP code
	 *  - A path to a file
	 *
	 * @param	mixed $something
	 * @param	boolean $pack Pack source code.
	 * @return	string
	 * @throws	ReflectionException
	 * @throws	TranslationException
	 * @throws	InvalidArgumentException
	 */
	public static function translate($something, $pack = false) {
		
		// The target is an array with the object and the method name
		if (is_array($something) && sizeof($something) === 2 && is_object($something[0]) && is_string($something[1])) {
			return self::translateMethod($something[0], $something[1], $pack);
		}
		
		// The target is a closure
		if ($something instanceof Closure) {
			return self::translateClosure($something, $pack);
		}
		
		// The target is a function name
		if (is_string($something) && function_exists($something)) {
			return self::translateFunction($something, $pack);
		}
		
		// The target is a file path 
		if (is_string($something) && is_file($something)) {
			return self::translateFile($something, $pack);
		}
		
		// The target is a piece of PHP code
		if (is_string($something)) {
			return self::translateString($something, $pack);
		}
		
		// Invalid argument
		throw new InvalidArgumentException('Invalid target to translate: ' . gettype($something));
		
	} 
	
	/**
	 * Translate a closure to javascript code.
	 *
	 * @param	Closure $closure The closure.
	 * @param	boolean $pack Pack source code.
	 * @return	string
	 * @throws	ReflectionException
	 * @throws	TranslationException
	 * @see		http://php.net/manual/en/class.closure.php
	 */
	public static function translateClosure(Closure $closure, $pack = false) {
		return self::translateString(
			self::getInnerCode(
				new ReflectionFunction($closure),
				true,
				false
			),
			$pack
		);
	}

	/**
	 * Translate a function to javascript code.
	 *
	 * @param	string $name The function name.
	 * @param	boolean $pack Pack source code.
	 * @return	string
	 * @throws	ReflectionException
	 * @throws	TranslationException
	 */
	public static function translateFunction($name, $pack = false) {
		return self::translateString(
			self::getInnerCode(
				new ReflectionFunction($name),
				true,
				false
			),
			$pack
		);
	}
	
	/**
	 * Translate a class method to javascript code.
	 *
	 * @param	object|string $object An object, or a class name.
	 * @param	string $method The method name.
	 * @param	boolean $pack Pack source code.
	 * @return	string
	 * @throws	ReflectionException
	 * @throws	TranslationException
	 */
	public static function translateMethod($object, $method, $pack = false) {
		return self::translateString(
			self::getInnerCode(
				new ReflectionMethod(is_object($object) ? get_class($object) : "$object", $method),
				true,
				false
			),
			$pack
		);
	}

	/**
	 * Translate a file to javascript code.
	 *
	 * @param	string $filename Path to the file.
	 * @param	boolean $pack Pack source code.
	 * @return	string
	 * @throws	ReflectionException
	 * @throws	TranslationException
	 */
	public static function translateFile($filename, $pack = false) {
		return self::translateString(
			file_get_contents($filename),
			$pack
		);
	}
	
	/**
	 * Translate a string to javascript code.
	 *
	 * @param	string $str
	 * @param	boolean $pack
	 * @return	string
	 * @throws	TranslationException
	 */
	public static function translateString($str, $pack = false) {

		// String to return
		$out = '';

		// Explode string in tokens
		$str = token_get_all("<?php $str ?>");

		// Fetch tokens
		for ($key = 0, $length = sizeof($str); $key < $length; $key++) {

			// Get token value
			$token = $str[$key];

			// Token is a string
			if (!is_array($token)) {
				
				// Replace concatenation operator
				if ($token === '.') {
					$out .= '+';
				}
				
				// Remove reference sign
				else if ($token === '&') {
					continue;
				}
				
				// Append the original token
				else {
					$out .= $token;
				}
				
				// Next token
				continue;
				
			}
	
			// Token is an array, with a token id
			else {

				// Token id
				$tokenid = $token[0];
				
				// Forbidden token
				if (in_array($tokenid, self::$token_forbidden)) {
					throw new TranslationException('Forbidden token: ' . token_name($tokenid));
				}
				
				// Token to ignore 
				if (in_array($tokenid, self::$token_remove)) {
					continue;
				}
				
				// Token to replace
				if (array_key_exists($tokenid, self::$token_replace)) {
					$out .= self::$token_replace[$tokenid];
					continue;
				}
				
				// Switch token id
				switch ($tokenid) {
					
					// Transform echo & print special functions
					case T_ECHO :
					case T_PRINT :
						// The function name is followed by an opening parenthesis, so the
						// name can just be replaced
						if (self::next_is($str, '(', $key + 1)) {
							$out .= ($tokenid == T_ECHO ? 'alert' : 'console.log');
						}
						// The function doesn't use parenthesis around the value, so
						// let's add them.
						else {
							$out .= ($tokenid == T_ECHO ? 'alert' : 'console.log') . '(';
							$str[self::next_pos($str, ';', $key + 1)] = ');';
						}
						break;
					
					// Transform array
					case T_ARRAY :
						self::handleArray($key, $str);
						$out .= is_array($str[$key]) ? $str[$key][1] : $str[$key];
						break;
					
					// Transform foreach
					case T_FOREACH :
						self::handleForeach($key, $str);
						$out .= is_array($str[$key]) ? $str[$key][1] : $str[$key];
						break;
					
					// Handle white spaces
					case T_WHITESPACE :
						if ($pack) {
							if (substr($out, -1) != ' ') {
								$out .= ' ';
							}
						}
						else {
							$out .= $token[1];
						}
						break;

					// TODO To implements
					// Et gêrer des fonctions courantes: urlencode, htmlentities, htmlspecialchars, pop, push, shift,
					// isset, empty, array_find, array_key_exists, ...
					case T_EMPTY :
					case T_GLOBAL :
					case T_ISSET :
					case T_LIST :
						throw new TranslationException('Not implemented yet: ' . token_name($tokenid));
						break;
					
					// Other tokens
					default :
						// Variable name
						if (substr($token[1], 0, 1) === '$') {
							$out .= substr($token[1], 1);
						}
						// Existing code segment to replace
						else if (array_key_exists($token[1], self::$code_replace)) {
							$out .= self::$code_replace[$token[1]];
						}
						// Append the original token
						else {
							$out .= $token[1];
						}
						break;

				}
				
			}

		}

		// Return a string
		return $out;
	
	}
	
	/**
	 * Handle array statement.
	 *
	 * @param 	string $key Key of the 'array' token in the list
	 * @param	mixed[] &$tokens List of tokens
	 * @return	void
	 */
	protected static function handleArray($key, &$tokens) {
	
		// Indicate if the array uses key/value mapping
		$useMap = false;
	
		// Opened parenthesis counter
		$parenthesis = 0;
			
		// Fetch tokens inside the array
		for ($index = $key, $length = sizeof($tokens); $index < $length; $index++) {
	
			// Get value of the token
			$value = $tokens[$index];
	
			// Opening parenthesis
			if ($value === '(') {
				// Increment counter
				$parenthesis++;
			}
	
			// Closing parenthesis
			else if ($value === ')') {
	
				// Decrement counter
				$parenthesis--;
	
				// Closing array
				if ($parenthesis < 1) {

					// Rewrite
					$tokens[$key] = '';
					$tokens[$key + 1] = $useMap ? '{' : '[';
					$tokens[$index] = $useMap ? '}' : ']';

					// Break loop
					return;

				}
	
			}
	
			// Detect key/value mapping
			else if (is_array($value) && $value[0] === T_DOUBLE_ARROW && $parenthesis < 2) {
				$useMap = true;
			}
	
		}
	
	}
	
	/**
	 * Handle foreach statement. 
	 *
	 * @param 	string $key Key of the 'foreach' token in the list
	 * @param	mixed[] &$tokens List of tokens
	 * @return	void
	 */
	protected static function handleForeach($key, &$tokens) {
	
		// Position of the opening parenthesis
		$position_open = null;
	
		// Position of the AS keyword
		$position_as = null;
	
		// Position of the => operator
		$position_arrow = -1;
	
		// Position of the closing parenthesis
		$position_close = null;
	
		// Opened parenthesis counter
		$parenthesis = 0;
	
		// Fetch tokens inside the array
		for ($index = $key + 1, $length = sizeof($tokens); $index < $length; $index++) {
	
			// Get value of the token
			$value = $tokens[$index];
	
			// Detect AS operator
			if (is_array($value) && $value[0] === T_AS) {
				// Debug
				//echo "AS: $index\n";
				// Save index
				$position_as = $index;
				// Next token
				continue;
			}
	
			// Detect => (key/value mapping) operator
			else if (is_array($value) && $value[0] === T_DOUBLE_ARROW) {
				// Debug
				//echo "Arrow: $index\n";
				// Save index
				$position_arrow = $index;
				// Next token
				continue;
			}
	
			// Opening parenthesis
			else if ($value == '(') {
	
				// Foreach open position
				if ($parenthesis === 0) {
					//echo "\nOpen: $index\n";
					$position_open = $index;
				}
	
				// Increment counter
				$parenthesis++;
				
				continue;
	
			}
	
			// Closing parenthesis
			else if ($value == ')') {
					
				// Decrease counter
				$parenthesis--;
					
				// Closing array
				if ($parenthesis < 1) {
	
					// Debug
					//echo "Close: $index\n";
					
					// Closing parenthesis position
					$position_close = $index;

					// Position of the last element of the space between the last bracket and the rest of the code
					$position_space = null;
					
					// Search for next bracket position
					for ($i = $position_close + 1; $i < $length; $i++) {
						$c = $tokens[$i];
						if ($position_space !== null) {
							// Append whitespaces
							if (is_array($c) && $c[0] === T_WHITESPACE) {
								$position_space = $i;
								continue;
							}
							// Debug
							//echo "Space position: $position_space\n";
							// Stop looping
							break;
						}
						if (is_array($c)) {
							if ($c[0] === T_WHITESPACE) {
								continue;
							}
						}
						else if ($c === '{') {
							// Save position
							$position_bracket = $i;
							// Create space array
							$position_space = $i;
							// Debug
							//echo "Next bracket: $position_bracket\n";
							// Next token
							continue;
						}
						throw new TranslationException('Unexpected token: ' . print_r($c, true));
					}
					
					// Get declaration code
					$code = self::tokens2string($tokens, $position_open + 1, $position_close - $position_open - 1);
					$append = '';
					
					// Debug
					//echo "Declaration code: $code\n";
					
					// Prepare matching array
					$matches = array();
					
					// Syntax: foreach ($array as $key => $value)
					if (preg_match('/(.*) as (.*)=>(.*)/si', $code, $matches)) {
						
						// Rewrite inner declaration code
						$code = trim($matches[2]) . ' in ' . trim($matches[1]);
						
						// Append value
						// TODO Support $pack
						$append =
							  self::tokens2string($tokens, $position_bracket + 1, $position_space - $position_bracket)
							. 'var ' . self::translateString(trim($matches[3]) . ' = ' . trim($matches[1]) . '[' . trim($matches[2]) . '];', false);
						
					}
					
					// Syntax: foreach ($array as $value)
					else if (preg_match('/(.*) as (.*)/si', $code, $matches)) {
						
						// Rewrite inner declaration code
						$code = '$key in ' . trim($matches[1]);
						
						// Append value
						// TODO Support $pack
						$append =
							  self::tokens2string($tokens, $position_bracket + 1, $position_space - $position_bracket)
							. 'var ' . self::translateString(trim($matches[2]) . ' = ' . trim($matches[1]) . '[$key];', false);
						
					}
					
					// Invalid syntax
					else {
						throw new TranslationException("Invalid declaration: $code");
					}
					
					// Debug
					//echo "Rewrited declaration code: $code\nCode to append: $append\n";
					
					// Rewrite
					$tokens[$key] = 'for';
					$tokens[$position_open + 1] = trim(self::translateString($code, false));
					for ($i = $position_open + 2; $i < $position_close; $i++) $tokens[$i] = ''; // Clean
					$tokens[$position_bracket] = '{' . $append;

					// Stop working
					return;
	
				}
					
			}
	
		}
	
	}
	
	/**
	 * Returns the PHP code inside a function.
	 *
	 * @param	ReflectionFunctionAbstract $reflection
	 * @param	boolean $surround
	 * @param	boolean $fullParameters
	 * @return	string
	 * @see		http://www.php.net/manual/en/book.reflection.php
	 * @see		http://www.php.net/manual/en/class.splfileobject.php
	 */
	public static function getInnerCode(ReflectionFunctionAbstract $reflection, $surround = true, $fullParameters = true) {

		// Inner code
		$file = new SplFileObject($reflection->getFileName());
		$file->seek($reflection->getStartLine() - 1);
		$code = '';
		while ($file->key() < $reflection->getEndLine()) {
			$code .= $file->current();
			$file->next();
		}
		$start = strpos($code, '{') + 1;
		$end = strrpos($code, '}');
		$code = substr($code, $start, $end - $start);

		// Don't surround function code: return only inner php
		if (!$surround) {
			return $code;
		}

		// Get function parameters
		$parameters = array();
		foreach ($reflection->getParameters() as $param) {
			if ($fullParameters) {
				$str = '';
				if ($param->isArray()) {
					$str .= 'array ';
				}
				else {
					try {
						$class = $param->getClass();
						if ($class) {
							$str = $class->getName() . ' ';
						}
					}
					catch (ReflectionException $ex) {
						$matches = array();
						if (preg_match('/Class (.*?) does not exist/i', $ex->getMessage(), $matches)) {
							$str .= $matches[1] . ' ';
						}
					}
				}
				if ($param->isPassedByReference()) {
					$str .= '&';
				}
				$str .= '$' . $param->getName();
				if ($param->isDefaultValueAvailable()) {
					$str .= ' = ' . var_export($param->getDefaultValue(), true);
				}
				$parameters[] = $str;
			}
			else {
				$parameters[] = '$' . $param->getName();
			}
		}
		
		// Cleanup
		unset($file, $start, $end, $param, $str, $class, $matches);

		// Return as string
		return 'function (' . implode(', ', $parameters) . ') {' . $code . '}';

	}
	
	/**
	 * Return a part of an array provided by token_get_all() as a string.
	 * 
	 * @param	mixed[] $tokens
	 * @param	int $start
	 * @param	int $end
	 * @return	string
	 */
	protected static function tokens2string(array $tokens, $start, $length = 0) {
		$tokens = array_slice($tokens, $start, $length);
		foreach ($tokens as &$token) {
			if (is_array($token)) {
				$token = $token[1];
			}
		}
		return implode('', $tokens);
	}
	
	/**
	 * Search the position of the next matching token.
	 * 
	 * This function ignore white spaces.
	 * 
	 * @param	mixed[] $tokens
	 * @param	string $token
	 * @param	int $index
	 * @return	int|false
	 */
	protected static function next_pos(array &$tokens, $token, $index = 0) {
		for ($length = sizeof($tokens); $index < $length; $index++) {
			$tok = $tokens[$index];
			if (is_array($tok) && $tok[0] === T_WHITESPACE) {
				continue;
			}
			if ($tok === $token) {
				return $index;
			}
		}
		return false;
	}

	/**
	 * Return TRUE is the next token matches.
	 *
	 * This function ignore white spaces.
	 *
	 * @param	mixed[] $tokens
	 * @param	string $token
	 * @param	int $index
	 * @return	boolean
	 */
	protected static function next_is(array &$tokens, $token, $index = 0) {
		for ($length = sizeof($tokens); $index < $length; $index++) {
			$tok = $tokens[$index];
			if (is_array($tok) && $tok[0] === T_WHITESPACE) {
				continue;
			}
			return $tok === $token;
		}
		return false;
	}

	/**
	 * Return TRUE is the next token matches.
	 *
	 * This function ignore white spaces.
	 *
	 * @param	mixed[] $tokens
	 * @param	string $token
	 * @param	int $index
	 * @return	int|false
	 * @throws	InvalidArgumentException
	 */
	protected static function next_close(array &$tokens, $token, $index = 0) {
		
		// Check token
		if ($token === '(') {
			$close = ')';
		}
		else if ($token === '[') {
			$close = ']';
		}
		else if ($token === '{') {
			$close = '}';
		}
		else {
			throw new InvalidArgumentException("Invalid open character: $token");
		}
		
		// Check argument match with token list
		if ($tokens[$index] !== $token) {
			throw new InvalidArgumentException("Token at index $index is not: $token");
		}
		
		// Counts the number of opened tokens
		$counter = 0;
		
		// Fetch tokens
		for ($length = sizeof($tokens); $index < $length; $index++) {
			
			// Current token
			$tok = $tokens[$index];
			
			// Open
			if ($tok === $token) {
				$counter++;
				continue;
			}
			
			// Close
			if ($tok === $close) {
				
				$counter--;
				
				// Closing token found
				if ($counter < 1) {
					return $index;
				}
				
			}
			
		}
		
		// Closing token not found
		return false;
		
	}

}

/**
 * Basic exception for PHP2JS library.
 *
 * @package    evolya.php2js
 * @author     ted <contact@evolya.fr>
 * @copyright  Copyright (c) 2013 Evolya.fr
 */
class TranslationException extends Exception { }

?>