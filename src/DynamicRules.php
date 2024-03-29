<?php

namespace Northrook\Stylesheets;

use Northrook\Stylesheets\Rules\AbstractRule;
use Northrook\Support\Arr;
use Northrook\Support\File;
use Northrook\Support\Regex;
use Northrook\Support\Str;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use UnexpectedValueException;

final class DynamicRules {

	public const SIZE = [
		'auto'   => 'auto',
		'null'   => '0',
		'tiny'   => '.125rem',
		'small'  => '.25rem',
		'base'   => '1rem',
		'medium' => '1.5rem',
		'large'  => '2rem',
		'full'   => '100%',
	];

	/** Classes run their own logic, arrays are parsed as `.class { key => value }` */
	private const RULES = [
		'disabled' => ['.disabled' => ['pointer-events' => 'none']],
		'sr'       => Rules\Accessibility::class,
		'flow'     => Rules\Flow::class,
		'divide'   => Rules\Divide::class,
		'h'        => Rules\Height::class,
		'w'        => Rules\Width::class,
		'gap'      => Rules\Gap::class,
		'flex'     => Rules\Flex::class,
		// 'grid' => Rules\Grid::class,
		'm'        => Rules\Margin::class,
		'p'        => Rules\Padding::class,
		'r'        => Rules\Radius::class,
		'font'     => Rules\Font::class,
		'color'    => Rules\Color::class,
		'bg'       => Rules\Background::class,
	];

	private array $matchRules               = [];
	private static array $directoriesToScan = [];

	/** @var array The dynamic variables. */
	public readonly array $root;
	public readonly array $variables;

	/** @var array All rules found in every template file. */
	private array $templateRules = [];
	private array $rule          = [];

	public function __construct(
		private readonly string $rootDir,
		array $directories = [],
	) {
		$this->scanTemplateFiles( $directories );
		$this->parseTemplateRules();

		$root = [];
		foreach ( $this::SIZE as $key => $value ) {
			if ( 'null' === $key || 'auto' === $key ) {
				continue;
			}
			$root["--{$key}"] = $value;
		}

		$this->root      = [':root' => $root];
		$this->variables = $this->rule;

	}

	private function parseTemplateRules(): void {

		foreach ( $this->templateRules as $selectors ) {

			// $selectors should always be an array
			if ( ! is_array( $selectors ) ) {
				continue;
			}

			$match = array_map(
				callback: static fn( $selector ) => Str::before( $selector, [':', '-'] ),
				array:$selectors
			);

			$rules = Arr::searchKeys( $this::RULES, $match );

			if ( ! $rules ) {
				continue;
			}

			foreach ( $rules as $rule ) {
				if ( is_string( $rule ) && class_exists( $rule ) && is_subclass_of( $rule, AbstractRule::class ) ) {
					$rule = $rule::build( $selectors );
				}
				$this->rule = array_merge( $this->rule, $rule );
			}
		}

	}

	private function scanTemplateFiles( array $directories ): void {

		$this::addTemplateDirectories( $directories );

		$files = [];
		foreach ( $this::$directoriesToScan as $directory ) {

			$match = false;

			if ( \str_contains( $directory, '*' ) ) {
				$match     = trim( strrchr( $directory, '*' ), '*' );
				$directory = strstr( $directory, '*', true );
			}

			$path = Str::filepath( $directory, $this->rootDir );

			if ( is_file( $path ) ) {
				$files[] = File::getContents( $path );
				continue;
			}

			try {
				$iterator = new RecursiveIteratorIterator( new RecursiveDirectoryIterator( $path ) );
			} catch ( UnexpectedValueException ) {
				continue;
			}

			foreach ( $iterator as $file ) {
				if ( $file->isDir()
					|| str_ends_with( $file->getFilename(), '.lock' )
					|| ( $match && ! Str::contains( $file->getFilename(), $match ) ) ) {
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
				$string                = \preg_replace( '/\'.*?\'/s', ' ', $get->class );
				$selectors             = Arr::explode( ' ', $string, true );
				$this->templateRules[] = $selectors;
			}
		}
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