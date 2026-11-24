<?php
/*
 +-------------------------------------------------------------------------+
 | Copyright (C) 2004-2026 The Cacti Group                                 |
 |                                                                         |
 | This program is free software; you can redistribute it and/or           |
 | modify it under the terms of the GNU General Public License             |
 | as published by the Free Software Foundation; either version 2          |
 | of the License, or (at your option) any later version.                  |
 +-------------------------------------------------------------------------+
 | Cacti: The Complete RRDtool-based Graphing Solution                     |
 +-------------------------------------------------------------------------+
 | http://www.cacti.net/                                                   |
 +-------------------------------------------------------------------------+

/*
 * Find translation helper calls in a plugin's own PHP files that do not pass
 * the plugin's gettext text domain.
 *
 * Cacti's helpers take the domain as an argument and fall back to the core
 * 'cacti' domain without it, so a missing or misspelled domain silently makes
 * the string untranslatable from the plugin's catalog. Where the domain goes
 * (include/global_languages.php in Cacti):
 *
 *   __(), __esc(), __x(), __esc_x()  last argument (any number of printf args)
 *   __n(), __esc_n()                 4th argument
 *   __xn(), __esc_xn()               5th argument
 *   __gettext()                      2nd argument
 *
 * The files are read with PHP's tokenizer, so strings and comments that merely
 * mention a helper are not calls. The domain must be a string literal.
 * vendor/, tests/, locales/ and hidden directories are not scanned.
 *
 * Usage: php tests/bin/check-i18n-domain.php <domain>
 *
 * Exits 1 and prints file:line for each offender, 0 when there are none.
 */

if (!function_exists('i18n_domain_offenders')) {
	/**
	 * @param string $root   Plugin root directory.
	 * @param string $domain Text domain every helper call must pass.
	 *
	 * @return array<int, array{file: string, line: int, call: string, reason: string}>
	 */
	function i18n_domain_offenders($root, $domain) {
		$root      = rtrim(realpath($root), '/');
		$offenders = [];

		foreach (i18n_domain_files($root) as $file) {
			$relative = substr($file, strlen($root) + 1);

			foreach (i18n_domain_check_source(file_get_contents($file), $domain) as $offender) {
				$offender['file'] = $relative;
				$offenders[]      = $offender;
			}
		}

		return $offenders;
	}

	/**
	 * @param string $root Plugin root directory.
	 *
	 * @return array<int, string> Absolute paths, sorted.
	 */
	function i18n_domain_files($root) {
		$skip  = ['vendor', 'tests', 'locales'];
		$files = [];

		$iterator = new RecursiveIteratorIterator(
			new RecursiveCallbackFilterIterator(
				new RecursiveDirectoryIterator($root, FilesystemIterator::SKIP_DOTS),
				function ($current) use ($root, $skip) {
					$relative = substr($current->getPathname(), strlen($root) + 1);

					if ($current->getFilename()[0] === '.') {
						return false;
					}

					if ($current->isDir()) {
						return !in_array($relative, $skip, true);
					}

					return $current->getExtension() === 'php';
				}
			)
		);

		foreach ($iterator as $file) {
			$files[] = $file->getPathname();
		}

		sort($files);

		return $files;
	}

	/**
	 * @param string $source PHP source code.
	 * @param string $domain Expected text domain.
	 *
	 * @return array<int, array{line: int, call: string, reason: string}>
	 */
	function i18n_domain_check_source($source, $domain) {
		// Position of the domain argument: an index from the start, or -1 for the last argument.
		$positions = [
			'__'        => -1, '__esc'   => -1, '__x'   => -1, '__esc_x'  => -1,
			'__n'       => 3,  '__esc_n' => 3,  '__xn'  => 4,  '__esc_xn' => 4,
			'__gettext' => 1,
		];

		$tokens    = array_values(array_filter(
			token_get_all($source),
			function ($token) {
				return !is_array($token) || !in_array($token[0], [T_WHITESPACE, T_COMMENT, T_DOC_COMMENT], true);
			}
		));
		$offenders = [];
		$count     = count($tokens);

		for ($i = 0; $i < $count; $i++) {
			if (!is_array($tokens[$i]) || !in_array($tokens[$i][0], [T_STRING, T_NAME_FULLY_QUALIFIED], true)) {
				continue;
			}

			$name = ltrim($tokens[$i][1], '\\');

			if (!isset($positions[$name]) || ($tokens[$i + 1] ?? null) !== '(') {
				continue;
			}

			// Skip method calls, static calls, declarations and instantiations.
			$previous = $tokens[$i - 1] ?? null;

			if (is_array($previous) && in_array($previous[0], [T_OBJECT_OPERATOR, T_NULLSAFE_OBJECT_OPERATOR, T_DOUBLE_COLON, T_FUNCTION, T_NEW], true)) {
				continue;
			}

			$arguments = i18n_domain_arguments($tokens, $i + 1);
			$wanted    = $positions[$name] === -1 ? count($arguments) - 1 : $positions[$name];
			$reason    = null;

			if (!isset($arguments[$wanted]) || ($positions[$name] === -1 && count($arguments) < 2)) {
				$reason = 'no text domain argument';
			} elseif (count($arguments[$wanted]) !== 1 || !is_array($arguments[$wanted][0]) || $arguments[$wanted][0][0] !== T_CONSTANT_ENCAPSED_STRING) {
				$reason = 'text domain is not a string literal';
			} elseif (substr($arguments[$wanted][0][1], 1, -1) !== $domain) {
				$reason = "text domain is " . $arguments[$wanted][0][1] . ", expected '" . $domain . "'";
			}

			if ($reason !== null) {
				$offenders[] = ['line' => $tokens[$i][2], 'call' => $name . '()', 'reason' => $reason];
			}
		}

		return $offenders;
	}

	/**
	 * Split the arguments of the call whose opening parenthesis is at $open.
	 *
	 * @param array<int, mixed> $tokens Significant tokens.
	 * @param int               $open   Index of the '(' token.
	 *
	 * @return array<int, array<int, mixed>> Tokens of each top-level argument.
	 */
	function i18n_domain_arguments($tokens, $open) {
		$arguments = [[]];
		$depth     = 0;
		$count     = count($tokens);

		for ($i = $open; $i < $count; $i++) {
			$token = $tokens[$i];
			$text  = is_array($token) ? $token[1] : $token;

			if ($text === '(' || $text === '[' || $text === '{' || (is_array($token) && in_array($token[0], [T_CURLY_OPEN, T_DOLLAR_OPEN_CURLY_BRACES], true))) {
				$depth++;

				if ($depth === 1) {
					continue;
				}
			} elseif ($text === ')' || $text === ']' || $text === '}') {
				$depth--;

				if ($depth === 0) {
					break;
				}
			} elseif ($text === ',' && $depth === 1) {
				$arguments[] = [];

				continue;
			}

			$arguments[count($arguments) - 1][] = $token;
		}

		// A trailing comma leaves an empty last argument.
		if (end($arguments) === []) {
			array_pop($arguments);
		}

		return $arguments;
	}
}

if (PHP_SAPI === 'cli' && isset($argv[0]) && realpath($argv[0]) === __FILE__) {
	$domain = $argv[1] ?? '';

	if ($domain === '') {
		fwrite(STDERR, "Usage: php tests/bin/check-i18n-domain.php <domain>\n");

		exit(2);
	}

	$offenders = i18n_domain_offenders(dirname(__DIR__, 2), $domain);

	foreach ($offenders as $offender) {
		print $offender['file'] . ':' . $offender['line'] . ' ' . $offender['call'] . ': ' . $offender['reason'] . "\n";
	}

	exit($offenders === [] ? 0 : 1);
}
