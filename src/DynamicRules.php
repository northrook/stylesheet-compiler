<?php

namespace Northrook\Stylesheets;

use Northrook\Support\Arr;
use Northrook\Support\File;
use Northrook\Support\Regex;
use Northrook\Support\Str;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use UnexpectedValueException;

final class DynamicRules {

	private const SIZE = [
		'auto'   => 'auto',
		'null'   => '0',
		'tiny'   => 'tiny',
		'small'  => 'small',
		'medium' => 'medium',
		'large'  => 'large',
	];

	/** Classes run their own logic, arrays are parsed as `.class { key => value }` */
	private const RULES = [
		'flow' => ['.flow > * + *' => ['margin-top' => 'var(--flow-gap, 1em)']],
		'gap'  => ['.gap' => ['gap' => 'var(--gap, 1rem)']], // TODO: Gap::class - gap, gap-x, gap-y
		'flex' => Rules\Flex::RULES,
	];

	private const BUILD = [
		'palette' => [
			'bg'    => 'background-color',
			'color' => 'color',
		],
		'space'   => [
			'h' => ['margin-top'],
			'v' => ['margin-left'],
		],
		'gap'     => [
			''  => ['gap'],
			'x' => ['column-gap'],
			'y' => ['row-gap'],
		],
		'margin'  => [
			''  => ['margin'],
			'x' => ['margin-left', 'margin-right'],
			'y' => ['margin-top', 'margin-bottom'],
			't' => ['margin-top'],
			'r' => ['margin-right'],
			'b' => ['margin-bottom'],
			'l' => ['margin-left'],
		],
		'padding' => [
			''  => ['padding'],
			'x' => ['padding-left', 'padding-right'],
			'y' => ['padding-top', 'padding-bottom'],
			't' => ['padding-top'],
			'r' => ['padding-right'],
			'b' => ['padding-bottom'],
			'l' => ['padding-left'],
		],
	];

	private static array $directoriesToScan = [];

	/** @var array The dynamic variables. */
	public array $variables = [];

	
	private array $templateRules    = [];

	private array $dynamicVariables = [];
	private array $filterVariables  = [];

	public function __construct(
		private readonly string $rootDir,
		array $directories = [],
		array $variables = [],
	) {
		$this->scanTemplateFiles( $directories );
		// $this->makeRules();

		\var_dump( self::$directoriesToScan );
	}

	private function scanTemplateFiles( array $directories ): void {

		$this::scanDirectories( $directories );

		$templates = [];
		$files     = [];

		foreach ( $this::$directoriesToScan as $directory ) {

			$path = Str::filepath( $directory, $this->rootDir );

			try {
				$iterator = new RecursiveIteratorIterator( new RecursiveDirectoryIterator( $path ) );
			} catch ( UnexpectedValueException ) {
				continue;
			}

			foreach ( $iterator as $file ) {
				if ( $file->isDir() ) {
					continue;
				}
				$files[] = Str::squish( File::getContents( $file->getPathname() ) );
			}
		}

		foreach ( $files as $template ) {
			$classes = Regex::matchNamedGroups(
				'/class="(?<class>.*?)"/s',
				$template
			);
			foreach ( $classes as $get ) {
				$string = \preg_replace( '/\'.*?\'/s', ' ', $get->class );

				// $selectors = array_filter( explode( ' ', $string ) );
				$selectors             = Arr::explode( ' ', $string, true );
				$this->templateRules[] = $selectors;
				// if ( \count( $selectors ) > 1 ) {
				// 	// \dump( $selectors );
				// }
				/// TODO Combined classes such as flex.reverse does not trigger generation
				//!      Is this an issue?
				array_push( $templates, ...explode( ' ', $string ) );
			}
		}

		$templates = array_flip( $templates );

		// dd( $this::$scanDirectories, $templates, $this->templateRules );
		foreach ( $templates as $key => $void ) {
			unset( $templates[$key] );
			if ( ! $key ) {
				continue;
			}
			$templates[".$key"] = $key;
		}
		// dd( $this->templateRules );
		$this->filterVariables = $templates;

	}

	/** Add directories to scan for template files.
	 * * The scanned directories will be searched recursively.
	 *
	 * @param array $directories
	 */
	public static function addTemplateDirectories( array $directories ): void {
		self::$directoriesToScan = array_merge(
			self::$directoriesToScan,
			$directories
		);
	}
}