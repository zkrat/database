<?php

/**
 * This file is part of the Nette Framework (http://nette.org)
 * Copyright (c) 2004 David Grudl (http://davidgrudl.com)
 */

namespace Nette\Database;

use Nette;


/**
 * SQL preprocessor compatibility checker.
 *
 * nette.database.default:
 *     setup:
 *          - Nette\Database\CompatibilityChecker22::install(@self)
 *
 * @author     David Grudl
 */
class CompatibilityChecker22 extends Nette\Object
{

	public static function install(Connection $connection)
	{
		$connection->onQuery[] = array(__CLASS__, 'check');
	}


	public static function check($connection, $result)
	{
		$trace = debug_backtrace(FALSE);
		$args = is_array($trace[6]['args'][0]) ? $trace[6]['args'][0] : $trace[6]['args']; // calling query()
		if (count($args) < 2) {
			return;
		}

		foreach ($trace as $item) {
			if (isset($item['function'], $item['class'])) {
				try {
					$reflection = new \ReflectionMethod($item['class'], $item['function']);
					if (preg_match('#\s@skipDatabaseCompatibility\s#', $reflection->getDocComment())) {
						return;
					}
				} catch (\ReflectionException $e) {}
			}
		}

		$args[0] = str_replace(array('?values', '?set'), '?', $args[0]); // added by Selection
		$preprocessor = new SqlPreprocessor22($connection);
		list($statement, $params) = $preprocessor->process($args);

		$src = "\nin " . $trace[7]['file'] . ':' . $trace[7]['line'];
		if (trim($result->getQueryString()) !== trim($statement)) {
			trigger_error("Detected incompatibility in SqlPreprocessor:\nStatement: $args[0]\nNew: '{$result->getQueryString()}'\nOld: '$statement'$src", E_USER_WARNING);

		} elseif ($result instanceof Nette\Database\ResultSet && $result->getParameters() !== $params) {
			trigger_error('Detected incompatibility in SqlPreprocessor: parameters was changed.' . $src, E_USER_WARNING);
		}
	}

}
