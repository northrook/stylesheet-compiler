<?php

namespace Northrook\Stylesheets;

use Northrook\Support\Arr;
use Northrook\Support\Convert;
use Northrook\Support\Debug;
use Northrook\Support\File;
use Northrook\Support\Num;
use Northrook\Support\Regex;
use Northrook\Support\Sort;
use Northrook\Support\Str;

/**
 *  Parses several stylesheets into one string
 *  String can be saved to a stylesheet.css file.
 *  String can be injected into a <style> tag in HTML
 */
class Stylesheet {

	public string $defaultTheme = 'light';

	private array $root      = [];
	private array $theme     = [];
	private array $selectors = [];
	private array $screens   = [];
	private array $keyframes = [];

	protected array $stylesheets = [];

	private array $enqueued;

	private array $webkit = [
		'backdrop-filter',
		'tap-highlight-color',
		'user-select',
		'text-size-adjust',
	];

	/**
	 * @var string|null The resulting CSS, or null on failure.
	 */
	public readonly ?string $styles;

	/**
	 * @var bool True if the Stylesheet was saved successfully.
	 */
	public readonly bool $updated;
	private ?string $savePath = null;

	private readonly string $rootDir;

	public function __toString(): string {
		return $this->styles ?? '';
	}

	public function __construct(
		string $rootDir,
		public ?ColorPalette $palette = null,
		public bool $force = false,
		public bool | string $reset = true
	) {
		$this->rootDir = Str::filepath( $rootDir );
		$this->resetRules();
	}

	private function resetRules(): void {

		if ( ! $this->reset ) {
			return;
		}
		
		$this->reset = ( true === $this->reset ) ? 'baseline' : $this->reset;

		if ( str_ends_with( $this->reset, '.css' ) ) {
			$this->reset = File::getPath( $this->reset );
		}

		if ( File::exists( __DIR__ . '/resets/' . $this->reset . '.css' ) ) {
			$this->reset = File::getPath( __DIR__ . '/resets/' . $this->reset . '.css' );
		}

		$this->stylesheets['reset'] = $this->reset;
	}

	public function addStylesheets( string...$paths ): void {

		foreach ( $paths as $path ) {
			$this->stylesheets['file:' . File::getFileName( $path ) . ':' . crc32( $path )] = Str::filepath( $path );
		}

	}

	public function addStyleString( string $string ): void {
		$this->stylesheets['string:' . crc32( $string )] = $string;
	}

	public function save( string $filename ): void {
		$this->savePath = File::getPath( $filename, true );

		if ( $this->build() ) {
			File::putContents( $this->styles, $this->savePath, );
			$this->updated = true;
		} else {
			$this->updated = false;
		}

	}

	public function build(): bool {

		$this->scanEnqueuedStyles();

		if ( ! $this->force && ! $this->updateSavedFile() ) {
			return false;
		}

		$this->enqueued = $this->getEnqueuedStyles();

// Parse the enqueued styles
		foreach ( array_keys( $this->enqueued ) as $stylesheet ) {
			$this->matchScreens( $stylesheet );
			$this->matchRootStyles( $stylesheet );
			$this->matchThemeStyles( $stylesheet );
			$this->matchScreenElement( $stylesheet );
			$this->matchKeyframes( $stylesheet );
			$this->matchRules( $stylesheet );
		}

		// Clean up the enqueued styles, it should result in an empty array
		$this->enqueued = array_filter(
			$this->enqueued,
			static fn( $content ) => trim( $content )
		);

		if ( $this->enqueued ) {
			Debug::log( 'The enqueued styles are not empty. Some styles were not parsed. See $this->enqueued:' . var_export( $this->enqueued, true ) );
		}

		// Combine selectors and properties
		$this->combineSelectorRules();

		$this->styles = Arr::implode( [
			// $this->buildTheme(),
			$this->buildRoot(),
			$this->buildElements(),
			$this->buildScreens(),
			$this->buildKeyframes(),
		] );

		return true;
	}

	/**
	 * Check if any enqueued asset is newer than the saved file
	 *
	 * @return bool True if at least one asset is newer, false otherwise
	 */
	private function updateSavedFile(): bool {

		if ( $this->savePath === null ) {
			return true;
		}

		// Loop through each supplied asset and get modified times
		$assets = array_map(
			static fn( $enqueued ) => file_exists( $enqueued ) ? filemtime(
				filename : $enqueued
			): 0,
			$this->enqueued
		);

		$enqueued = file_exists( $this->savePath ) ? filemtime( $this->savePath ) : 0;

		// If any of the assets are newer than the saved file, return true

		// var_dump( $this->enqueued );

		return ! empty( $assets ) && max( $assets ) >= $enqueued;
	}

	private function combineSelectorRules(): void {
		$elements = [];

		foreach ( $this->selectors as $selector => $rules ) {
			$merge = array_search( $rules, $elements, true );

			if ( $merge ) {
				$combined = "$merge, $selector";
				$elements = Arr::replaceKey( $elements, $merge, $combined );

				unset( $elements[$selector] ); // ! unset current key
			} else {
				$elements[$selector] = $rules;
			}

		}

		$this->selectors = $elements;
		$dynamic         = new DynamicStylesheetRules(
			$this->rootDir,
			[
				'var/cache/latte/',
			]
		);
		$this->selectors = array_merge( $this->selectors, $dynamic->variables );
		// dd( $this->elements );
	}

	private function matchScreens( string $parse ): void {

		if ( ! $styles = $this->enqueued[$parse] ?? null ) {
			return;
		}

		foreach ( Regex::matchNamedGroups(
			pattern: '/(?<screen>@media.*?\((?<size>.+?)\).*?{)(?<elements>.*?}\s*?)(?<end>})/ms',
			subject: $styles,
		) as $media ) {
			$this->updateEnqueuedStylesheet( $parse, $media->matched );
			$size = $this->resolveMediaSize( $media->size );

			foreach ( Regex::matchNamedGroups(
				pattern: "/(.*?(?<rule>\w.+?){(?<declaration>.+?)})/ms",
				subject: $media->elements,
			) as $screen ) {
				$rule = trim( $screen->rule );

				foreach ( $this->explodeDeclaration( $screen->declaration ) as $selector ) {
					$declaration = $this->declaration( $selector );

					if ( in_array( $declaration->property, $this->webkit ) ) {
						$this->screens[$size][$rule]["-webkit-$declaration->property"] = $declaration->value;
					}
					$this->screens[$size][$rule][$declaration->property] = $declaration->value;
				}

			}

		}

	}

	private function matchRootStyles( string $parse ): void {

		if ( ! $styles = $this->enqueued[$parse] ?? null ) {
			return;
		}

		foreach ( Regex::matchNamedGroups(
			pattern: '/((?<rule>:root.+?){(?<declaration>.+?)})/ms',
			subject: $styles,
		) as $root ) {

			$this->updateEnqueuedStylesheet( $parse, $root->matched );

			foreach ( $this->explodeDeclaration( $root->declaration ) as $declaration ) {

				$variable = $this->declaration( $declaration );

				$this->root[$parse][$variable->property] = $variable->value;
			}

		}

	}

	private function matchThemeStyles( string $parse ): void {

		if ( ! $styles = $this->enqueued[$parse] ?? null ) {
			return;
		}

		if ( $this->palette !== null ) {

			foreach ( $this->palette->getVariables() as $name => $palette ) {

				$theme = "[theme=\"$name\"]";

				foreach ( $palette as $variable => $value ) {

					if ( $this->defaultTheme === $name ) {
						$this->selectors['html'][$variable] = $value;
					}

					$this->selectors[$theme][$variable] = $value;
				}
			}
		}

		foreach ( Regex::matchNamedGroups(
			pattern: '/((?<rule>\[theme.+?){(?<declaration>.+?)})/ms',
			subject: $styles,
		) as $theme ) {
			// Debug::print( $match );
			$this->updateEnqueuedStylesheet( $parse, $theme->matched );

			foreach ( $this->explodeRule( $theme->rule ) as $element ) {

				foreach ( $this->explodeDeclaration( $theme->declaration ) as $declaration ) {

					$declaration = $this->declaration( $declaration );

					if ( in_array( $declaration->property, $this->webkit ) ) {
						$this->selectors[$element]["-webkit-$declaration->property"] = $declaration->value;
					}

					$this->selectors[$element][$declaration->property] = $declaration->value;
				}

			}

		}

	}

	private function matchScreenElement( string $parse ): void {

		if ( ! $styles = $this->enqueued[$parse] ?? null ) {
			return;
		}

		// $sizes = $this->setting()::screens( 'all' );
		$sizes = [
			'full'   => 1420,
			'large'  => 1020,
			'medium' => 640,
			'small'  => 420,
		];

// var_dump( $parse, $this->elements );

		foreach ( Regex::matchNamedGroups(
			pattern: "/(^@(?<type>.+?)\b(?<rule>.+?){(?<declaration>.+?)})/ms",
			subject: $styles,
		) as $match ) {
			if ( $match->type === 'each' ) {
				$this->updateEnqueuedStylesheet( $parse, $match->matched );

				foreach ( $this->explodeRule( $match->rule ) as $element ) {
					foreach ( $this->explodeDeclaration( $match->declaration ) as $declaration ) {

						$declaration = $this->declaration( $declaration );

						if ( in_array( $declaration->property, $this->webkit ) ) {
							$this->selectors[$element]["-webkit-$declaration->property"] = $declaration->value;
						}

						$this->selectors[$element][$declaration->property] = $declaration->value;
					}

				}

			} elseif ( array_key_exists( $match->type, $sizes ) ) {
				$this->updateEnqueuedStylesheet( $parse, $match->matched );

				foreach ( $this->explodeDeclaration( $match->declaration ) as $selector ) {
					$declaration = $this->declaration( $selector );

					$this->screens["max:$match->type"][$match->rule][$declaration->property] = $declaration->value;
				}

			}

		}

		foreach ( Regex::matchNamedGroups(
			pattern: "/(^@each.*?(?<rule>\w.+?){(?<declaration>.+?)})/ms",
			subject: $styles,
		) as $match ) {
			// Debug::print( $match );
			$this->updateEnqueuedStylesheet( $parse, $match->matched );

			foreach ( $this->explodeRule( $match->rule ) as $element ) {

				foreach ( $this->explodeDeclaration( $match->declaration ) as $declaration ) {

					$declaration = $this->declaration( $declaration );

					if ( in_array( $declaration->property, $this->webkit ) ) {
						$this->selectors[$element]["-webkit-$declaration->property"] = $declaration->value;
					}

					$this->selectors[$element][$declaration->property] = $declaration->value;
				}

			}

		}

	}

	private function matchKeyframes( string $parse ): void {

		if ( ! $styles = $this->enqueued[$parse] ?? null ) {
			return;
		}

		// var_dump($styles);
		foreach ( Regex::matchNamedGroups(
			pattern: "/@keyframes\s+?(?<key>\w.+?)\s.*?{(?<animation>.*?{.+?})\s*}/ms",
			// pattern: "/(?<keyframes>@keyframes.*?(?<key>.+?){.*?)(?<animation>.*?}.*?)(?<end>})/ms",
			subject: $styles,
		) as $keyframe ) {

			// var_dump($keyframe);

			$this->updateEnqueuedStylesheet( $parse, $keyframe->matched );

			$this->keyframes[trim( $keyframe->key )] = $keyframe->animation;

			// foreach ( Regex::matchNamedGroups(
			//     pattern: "/(^.*?(?<rule>\w.+?){(?<declaration>.+?)})/ms",
			//     string: $keyframe->animation,
			//     ) as $animation ) {

			//         var_dump($animation);
			//         foreach ( $this->explodeDeclaration( $animation->declaration ) as $declaration ) {

			//             $declaration = $this->declaration( $declaration );

			//             if ( in_array( $declaration->property, $this->webkit ) ) {
			//                 $this->keyframes[trim( $keyframe->key )][trim( $animation->rule )]["-webkit-$declaration->property"] = $declaration->value;
			//             }
			//             $this->keyframes[trim( $keyframe->key )][trim( $animation->rule )][$declaration->property] = $declaration->value;
			//         }

			//     }

		}

		// var_dump($this->keyframes);
	}

	private function matchRules( string $parse ): void {

		if ( ! $styles = $this->enqueued[$parse] ?? null ) {
			return;
		}

		foreach ( Regex::matchNamedGroups(
			pattern: "/((?<rule>.*?){(?<declaration>.+?)})/ms",
			subject: $styles,
		) as $match ) {

			$this->updateEnqueuedStylesheet( $parse, $match->matched );

			foreach ( $this->explodeRule( $match->rule ) as $element ) {

				foreach ( $this->explodeDeclaration( $match->declaration ) as $declaration ) {

					$declaration = $this->declaration( $declaration );

					if ( in_array( $declaration->property, $this->webkit ) ) {
						$this->selectors[$element]["-webkit-$declaration->property"] = $declaration->value;
					}
					$this->selectors[$element][$declaration->property] = $declaration->value;
				}

			}

		}

	}

	private function buildRoot(): ?string {

		if ( ! $this->root ) {
			return null;
		}

		// dd( $this->root );

		$shadow = $this->root['colors']['--baseline-100'] ?? null;

		if ( $shadow ) {
			$this->root['colors']['--shadow'] = $this->root['colors']['--baseline-100'];
		}

		// $this->root[]    = $this->palette->variables;
		$root = [':root {'];

		foreach (
			array_merge( ...array_values( $this->root ) ) as $variable => $value
		) {
			// var_dump( $variable, $value );
			$root[] = "\t$variable: $value;";

		}

		$root[] = '}';

		unset( $this->root );

		return PHP_EOL . Arr::implode(
			$root,
			"\n\t"
		);
	}

	private function buildElements(): ?string {
		$elements = [];

		foreach (
			array_filter( $this->selectors ) as $element => $declarationBlock
		) {
			$declaration = [];
			uksort( $declarationBlock, [Sort::class, 'stylesheetDeclarations'] );

			foreach (
				$declarationBlock as $variable => $value ) {
				$declaration[] = "$variable: $value;";
			}

			$declaration = Arr::implode(
				$declaration,
				"\n\t"
			);

			if ( \str_starts_with( $element, '*' ) ) {
				// \var_dump( $element, $declaration );
				array_unshift( $elements, "$element {\n\t$declaration\n} " );
			} else {
				$elements[] = "$element {\n\t$declaration\n} ";
			}

		}

		unset( $this->selectors );

		// \var_dump( $elements );

		return PHP_EOL . Arr::implode(
			$elements,
			"\n\n"
		);
	}

	private function buildScreens(): ?string {

		// $sizes    = $this->setting()::screens( 'all' );
		$sizes = [
			'full'   => 1420,
			'large'  => 1020,
			'medium' => 640,
			'small'  => 420,
		];
		$elements = [];

// var_dump( $sizes );
		foreach (
			array_filter( $this->screens ) as $mediaSize => $screens
		) {

			$set  = Str::split( $mediaSize, ':' );
			$type = trim( $set[0], ' :' );
			/// TODO [low] Handle theoretical null value, or missing array value
			$size   = Convert::pxToRem( $sizes[$set[1]] ?? null );
			$screen = "@media ($type-width : $size)";

			$elements[] = "$screen {";

			foreach (
				$screens as $element => $declarationBlock
			) {
				$declaration = [];
				uksort( $declarationBlock, [Sort::class, 'stylesheetDeclarations'] );

				foreach (
					$declarationBlock as $variable => $value ) {
					$declaration[] = "$variable: $value;";
				}

				$declaration = Arr::implode(
					$declaration,
					"\n\t\t"
				);
				$elements[] = "\t$element {\n\t\t$declaration\n\t} ";
			}

			$elements[] = "}";
		}

		unset( $this->screens );

		return PHP_EOL . Arr::implode(
			$elements,
			PHP_EOL . PHP_EOL
		);
	}

	private function buildKeyframes(): ?string {

		$keyframes = [];

		foreach (
			array_filter( $this->keyframes ) as $animation => $animationFrame
		) {
			$animation = "@keyframes $animation";
			// $declaration = [];

			// var_dump( $animation,$animationFrame );
			// foreach (
			//     $animationFrame as $frame => $rules ) {
			//     $declaration[] = "$frame {";
			//     uksort( $rules, [Sort::class, 'stylesheetDeclarations'] );

			//     foreach (
			//         $rules as $variable => $value ) {
			//         $declaration[] = "\t$variable: $value;";
			//     }

			//     $declaration[] = "}";
			// }

			// $declaration = Arr::implode(
			//     $declaration,
			//     "\n\t"
			// );
			// var_dump($animationFrame);
			$keyframes[] = "$animation {\n$animationFrame\n} ";
		}

		// var_dump($keyframes);
		unset( $this->keyframes );

		return PHP_EOL . Arr::implode(
			$keyframes,
			PHP_EOL . PHP_EOL
		);
	}

	private function scanEnqueuedStyles(): void {
		$this->enqueued = File::scanDirectories( path: $this->stylesheets, addUnexpectedValue: true );
	}

	private function getEnqueuedStyles(): array {

		foreach ( $this->enqueued as $index => $stylesheet ) {

			$key = trim( str_replace(
				[$this->rootDir, '.css', DIRECTORY_SEPARATOR],
				['', '', ':'],
				$stylesheet
			), ':' );

			if ( Str::containsAll( $stylesheet, ['{', '}'] ) ) {
				$this->enqueued[$key] = Str::squish( $stylesheet );
				unset( $this->enqueued[$index] );
				continue;
			}

			if ( $style = File::getContents( $stylesheet ) ) {
				$this->enqueued[$key] = Str::squish( $style );
				unset( $this->enqueued[$index] );
				continue;
			}

			Debug::log( "Stylesheet $stylesheet not found." );
		}

		return $this->enqueued;
	}

	private function resolveMediaSize( string $media ): ?string {

		// $sizes  = Core::settings()::screens( 'all' );
		$sizes = [
			'small' => '420', // px
			'medium' => '640', // px
			'large' => '1024', // px
			'full' => '1420', // px | get data from full width
		];
		$screen = substr( trim( $media ), 0, 3 ) . ':';
		$px     = (int) Num::extract( $media );
		$screen .= Num::closest( $px, $sizes, true );

		return $screen;
	}

	private function declaration( ?string $string, ?string $trim = ' :;' ): object {

		$set      = Str::split( Str::squish( $string ), ':' );
		$property = trim( strtolower( $set[0] ), $trim );
		$value    = trim( str_replace( [' 0px', ' 0em', ' 0rem'], ' 0', $set[1] ), $trim );

		return (object) [
			'property' => $property,
			'value'    => $value,
		];
	}

	private function explodeDeclaration( ?string $string ): array {
		$declarations = explode( ';', trim( $string ) );

		foreach ( array_filter( $declarations ) as $key => &$part ) {
			$part = trim( $part );

			if ( ! $part ) {
				unset( $declarations[$key] );
			}

		}

		return array_filter( $declarations );
	}

	private function explodeRule( ?string $string ): array {
		$rule = array_filter( explode( ',', $string ) );

		foreach ( $rule as &$value ) {
			$value = trim( strtolower( $value ) );
		}

		return $rule;
	}

	/**
	 * Remove the parsed partial $string from `$this->enqueued[$stylesheet]`
	 *
	 * @param  string      $stylesheet The stylesheet to parse
	 * @param  string|null $string     The string to remove
	 * @return void
	 */
	private function updateEnqueuedStylesheet( string $stylesheet, ?string $string ): void {
		$this->enqueued[$stylesheet] = str_replace( $string, '', $this->enqueued[$stylesheet] );
	}

}
