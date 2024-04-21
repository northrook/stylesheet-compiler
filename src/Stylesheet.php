<?php

namespace Northrook\Stylesheets;

use Northrook\Core\Service\CoreServiceTrait;
use Northrook\Core\Service\ServiceStatusInterface;
use Northrook\Core\Service\Status;
use Northrook\Logger\Log;
use Northrook\Support\Arr;
use Northrook\Support\Convert;
use Northrook\Support\File;
use Northrook\Support\Regex;
use Northrook\Support\Sort;
use Northrook\Support\Str;
use Northrook\Types\Path;
use Symfony\Component\Filesystem\Exception\FileNotFoundException;
use function array_reverse;
use function str_starts_with;

class Stylesheet implements ServiceStatusInterface

{
    use CoreServiceTrait;

    private array $options = [
        'forceUpdate'  => false,
        'colorPalette' => true,
        'dynamicRules' => true,
        'StyleReset'   => true,
    ];

    private array $enqueued;
    private array $stylesheets = [];

    private array $root      = [];
    private array $theme     = [];
    private array $selectors = [];
    private array $media     = [];
    private array $keyframes = [];

    private array                   $webkit = [
        'backdrop-filter',
        'tap-highlight-color',
        'user-select',
        'text-size-adjust',
    ];
    private Path                    $savePath;
    protected readonly Path         $rootDir;
    protected readonly DynamicRules $dynamicRules;
    /**
     * @var string|null The resulting CSS, or null on failure.
     */
    public readonly ?string $styles;
    public bool             $force = false;
    /**
     * @var bool True if the Stylesheet was saved successfully.
     */
    public readonly bool         $updated;
    public string                $defaultTheme = 'light';
    public readonly ColorPalette $palette;

    public function __construct(
        Path | string $rootDir,
        ?ColorPalette $palette = null,
        array         $templateDirectories = [],
        array         $options = [],
    ) {
        $this->status = new Status(
            [
                "success" => "New stylesheet created", // TODO : Add in $this->filename
                'notice'  => "Stylesheet regenerated",
                'error'   => "Error creating stylesheet",
            ],
        );

        $this->rootDir = $rootDir instanceof Path ? $rootDir : new Path( $rootDir );
        $this->options = array_merge( $this->options, $options );

        $this->palette      = $this->options[ 'colorPalette' ] ? $palette ?? new ColorPalette() : null;
        $this->dynamicRules = $this->options[ 'dynamicRules' ] ? new DynamicRules(
            $this->rootDir,
            $templateDirectories,
        ) : null;

        if ( $this->options[ 'StyleReset' ] ) {
            $this->resetRules();
        }
    }

    public function __toString() : string {
        return $this->styles ?? '';
    }

    private function resetRules() : void {

        $reset = ( true === $this->options[ 'StyleReset' ] ) ? 'baseline' : $this->options[ 'StyleReset' ];

        $reset = new Path(
            str_ends_with( $reset, '.css' ) ? $reset : __DIR__ . '/Resets/' . $reset . '.css',
        );

        if ( !$reset->exists ) {
            throw new FileNotFoundException( "Stylesheet::$reset does not exist." );
        }

        $this->options[ 'StyleReset' ] = $reset->value;
        $this->stylesheets[ 'reset' ]  = $reset->value;
    }

    public function addTemplatePaths( string ...$path ) : void {

        if ( !$this->options[ 'dynamicRules' ] ) {
            Log::warning( 'Stylesheet::addTemplateDirectories() requires dynamicRules to be enabled.' );
            return;
        }

        $this->dynamicRules->addTemplateDirectories( ...$path );

    }

    public function addStylesheets( string...$paths ) : void {

        foreach ( $paths as $path ) {

            $path = new Path( $path );
            $type = ( $path->isDir ? 'dir' : 'file' ) . ".$path->filename:" . crc32( $path->value );

            $this->stylesheets[ $type ] = $path->value;
        }
    }

    public function addStyleString( string $string ) : void {
        $this->stylesheets[ 'string:' . crc32( $string ) ] = $string;
    }

    public function save( string $filename ) : bool {
        $this->savePath = new Path( $filename );

        if ( $this->build() ) {
            $this->updated = File::save( $this->savePath, $this->styles );
        }
        else {
            $this->updated = false;
        }

        return $this->updated;
    }

    public function build() : bool {

        $this->status->action( __METHOD__ );

        if ( isset( $this->styles ) ) {
            $this->status->action( __METHOD__, 'skipped' );
            return true;
        }

        $this->scanEnqueuedStyles();

        if ( !$this->force() && !$this->updateSavedFile() ) {
            return false;
        }

        $this->enqueued = $this->getEnqueuedStyles();

        // Parse the enqueued styles
        foreach ( array_keys( $this->enqueued ) as $stylesheet ) {
            $this->matchMedia( $stylesheet );
            $this->matchRootStyles( $stylesheet );
            $this->matchThemeStyles( $stylesheet );
            $this->matchMediaElement( $stylesheet );
            $this->matchKeyframes( $stylesheet );
            $this->matchRules( $stylesheet );
        }

        // Clean up the enqueued styles, it should result in an empty array
        $this->enqueued = array_filter(
            $this->enqueued,
            static fn ( $content ) => trim( $content ),
        );

        if ( $this->enqueued ) {
            Log::Error(
                'The enqueued styles are not empty. Some styles were not parsed. See $this->enqueued:' . var_export(
                    $this->enqueued, true,
                ),
            );
        }

        // Combine selectors and properties
        $this->combineSelectorRules();

        $this->styles = Arr::implode(
            [
                // $this->buildTheme(),
                $this->buildRoot(),
                $this->buildElements(),
                $this->buildMedia(),
                $this->buildKeyframes(),
            ],
        );

        return true;
    }

    /**
     * Check if any enqueued asset is newer than the saved file
     *
     * @return bool True if at least one asset is newer, false otherwise
     */
    private function updateSavedFile() : bool {

        if ( !isset( $this->savePath ) ) {

            Log::Error( 'Stylesheet::savePath is not set.' );

            return false;
        }

        // Clear the
        clearstatcache( true, $this->savePath );

        // Loop through each provided asset and get modified times
        $assets = array_map(
            static function ( $enqueued ) {
                if ( file_exists( $enqueued ) ) {
                    clearstatcache( true, $enqueued );
                    return filemtime( filename : $enqueued );
                }
                return 0;
            },
            $this->enqueued,
        );

        $enqueued = $this->savePath->exists ? filemtime( $this->savePath ) : 0;

        // If any of the assets are newer than the saved file, return true
        return !empty( $assets ) && max( $assets ) >= $enqueued;
    }

    private function combineSelectorRules() : void {
        $elements = [];

        foreach ( $this->selectors as $selector => $rules ) {
            $merge = array_search( $rules, $elements, true );

            if ( $merge ) {
                $combined = "$merge, $selector";
                $elements = Arr::replaceKey( $elements, $merge, $combined );

                unset( $elements[ $selector ] ); // ! unset current key
            }
            else {
                $elements[ $selector ] = $rules;
            }

        }

        $this->selectors = $elements;

        if ( isset( $this->dynamicRules ) ) {
            $this->selectors = array_merge( $this->selectors, $this->dynamicRules->parse()->variables );
        }

        $this->root[ 'dynamic' ] = $this->dynamicRules->root;
    }

    private function matchMedia( string $parse ) : void {

        if ( !$styles = $this->enqueued[ $parse ] ?? null ) {
            return;
        }

        foreach ( Regex::matchNamedGroups(
            pattern : '/(?<screen>@media.*?\((?<size>.+?)\).*?{)(?<elements>.*?}\s*?)(?<end>})/ms',
            subject : $styles,
        ) as $media ) {
            $this->updateEnqueuedStylesheet( $parse, $media->matched );
            $size = $this->resolveMediaSize( $media->size );

            foreach ( Regex::matchNamedGroups(
                pattern : "/(.*?(?<rule>\S.+?){(?<declaration>.+?)})/ms",
                subject : $media->elements,
            ) as $screen ) {
                $rule = trim( $screen->rule );

                // Debug::dump( $size,$media->elements,$media, $screen );
                foreach ( $this->explodeDeclaration( $screen->declaration ) as $selector ) {
                    $declaration = $this->declaration( $selector );

                    if ( in_array( $declaration->property, $this->webkit, true ) ) {
                        $this->media[ $size ][ $rule ][ "-webkit-$declaration->property" ] = $declaration->value;
                    }
                    $this->media[ $size ][ $rule ][ $declaration->property ] = $declaration->value;

                }

            }

            // Debug::dump( $this->screens );
        }

    }

    private function matchRootStyles( string $parse ) : void {

        if ( !$styles = $this->enqueued[ $parse ] ?? null ) {
            return;
        }

        foreach ( Regex::matchNamedGroups(
            pattern : '/((?<rule>:root.+?){(?<declaration>.+?)})/ms',
            subject : $styles,
        ) as $root ) {

            $this->updateEnqueuedStylesheet( $parse, $root->matched );

            foreach ( $this->explodeDeclaration( $root->declaration ) as $declaration ) {

                $variable = $this->declaration( $declaration );

                $this->root[ $parse ][ $variable->property ] = $variable->value;
            }

        }

    }

    private function matchThemeStyles( string $parse ) : void {

        if ( !$styles = $this->enqueued[ $parse ] ?? null ) {
            return;
        }


        foreach ( $this->palette->getVariables() as $name => $palette ) {

            $theme = "[theme=\"$name\"]";

            foreach ( $palette as $variable => $value ) {

                if ( $this->defaultTheme === $name ) {
                    $this->selectors[ 'html' ][ $variable ] = $value;
                }

                $this->selectors[ $theme ][ $variable ] = $value;
            }
        }


        foreach ( Regex::matchNamedGroups(
            pattern : '/((?<rule>\[theme.+?){(?<declaration>.+?)})/ms',
            subject : $styles,
        ) as $theme ) {
            // Debug::print( $match );
            $this->updateEnqueuedStylesheet( $parse, $theme->matched );

            foreach ( $this->explodeRule( $theme->rule ) as $element ) {

                foreach ( $this->explodeDeclaration( $theme->declaration ) as $declaration ) {

                    $declaration = $this->declaration( $declaration );

                    if ( in_array( $declaration->property, $this->webkit, true ) ) {
                        $this->selectors[ $element ][ "-webkit-$declaration->property" ] = $declaration->value;
                    }

                    $this->selectors[ $element ][ $declaration->property ] = $declaration->value;
                }

            }

        }

    }

    private function matchMediaElement( string $parse ) : void {

        if ( !$styles = $this->enqueued[ $parse ] ?? null ) {
            return;
        }

        // $sizes = $this->setting()::screens( 'all' );
        $sizes = [
            'full'   => 1420,
            'large'  => 1020,
            'medium' => 640,
            'small'  => 420,
        ];

        foreach ( Regex::matchNamedGroups(
            pattern : "/(^@(?<type>.+?)\b(?<rule>.+?){(?<declaration>.+?)})/ms",
            subject : $styles,
        ) as $match ) {
            if ( $match->type === 'each' ) {
                $this->updateEnqueuedStylesheet( $parse, $match->matched );

                foreach ( $this->explodeRule( $match->rule ) as $element ) {
                    foreach ( $this->explodeDeclaration( $match->declaration ) as $declaration ) {

                        $declaration = $this->declaration( $declaration );

                        if ( in_array( $declaration->property, $this->webkit, true ) ) {
                            $this->selectors[ $element ][ "-webkit-$declaration->property" ] = $declaration->value;
                        }

                        $this->selectors[ $element ][ $declaration->property ] = $declaration->value;
                    }

                }

            }
            else {
                if ( array_key_exists( $match->type, $sizes ) ) {
                    $this->updateEnqueuedStylesheet( $parse, $match->matched );

                    foreach ( $this->explodeDeclaration( $match->declaration ) as $selector ) {
                        $declaration = $this->declaration( $selector );

                        $this->media[ "max:$match->type" ][ $match->rule ][ $declaration->property ] =
                            $declaration->value;
                    }

                }
            }

        }

        foreach ( Regex::matchNamedGroups(
            pattern : "/(^@each.*?(?<rule>\w.+?){(?<declaration>.+?)})/ms",
            subject : $styles,
        ) as $match ) {
            // Debug::print( $match );
            $this->updateEnqueuedStylesheet( $parse, $match->matched );

            foreach ( $this->explodeRule( $match->rule ) as $element ) {

                foreach ( $this->explodeDeclaration( $match->declaration ) as $declaration ) {

                    $declaration = $this->declaration( $declaration );

                    if ( in_array( $declaration->property, $this->webkit, true ) ) {
                        $this->selectors[ $element ][ "-webkit-$declaration->property" ] = $declaration->value;
                    }

                    $this->selectors[ $element ][ $declaration->property ] = $declaration->value;
                }

            }

        }

    }

    private function matchKeyframes( string $parse ) : void {

        if ( !$styles = $this->enqueued[ $parse ] ?? null ) {
            return;
        }

        foreach ( Regex::matchNamedGroups(
            pattern : "/@keyframes\s+?(?<key>\w.+?)\s.*?{(?<animation>.*?{.+?})\s*}/ms",
            subject : $styles,
        ) as $keyframe ) {

            $this->updateEnqueuedStylesheet( $parse, $keyframe->matched );

            $this->keyframes[ trim( $keyframe->key ) ] = $keyframe->animation;
        }
    }

    private function matchRules( string $parse ) : void {

        if ( !$styles = $this->enqueued[ $parse ] ?? null ) {
            return;
        }

        foreach ( Regex::matchNamedGroups(
            pattern : "/((?<rule>.*?){(?<declaration>.+?)})/ms",
            subject : $styles,
        ) as $match ) {

            $this->updateEnqueuedStylesheet( $parse, $match->matched );

            foreach ( $this->explodeRule( $match->rule ) as $element ) {

                foreach ( $this->explodeDeclaration( $match->declaration ) as $declaration ) {

                    $declaration = $this->declaration( $declaration );

                    if ( in_array( $declaration->property, $this->webkit, true ) ) {
                        $this->selectors[ $element ][ "-webkit-$declaration->property" ] = $declaration->value;
                    }
                    $this->selectors[ $element ][ $declaration->property ] = $declaration->value;
                }

            }

        }

    }

    private function buildRoot() : ?string {

        if ( !$this->root ) {
            return null;
        }

        $shadow = $this->root[ 'colors' ][ '--baseline-100' ] ?? null;

        if ( $shadow ) {
            $this->root[ 'colors' ][ '--shadow' ] = $this->root[ 'colors' ][ '--baseline-100' ];
        }

        $root = [ ':root {' ];

        foreach (
            array_merge( ...array_values( $this->root ) ) as $variable => $value
        ) {
            $root[] = "\t$variable: $value;";

        }

        $root[] = '}';

        unset( $this->root );

        return PHP_EOL . Arr::implode(
                $root,
                "\n\t",
            );
    }

    private function buildElements() : ?string {
        $elements = [];

        foreach (
            array_filter( $this->selectors ) as $element => $declarationBlock
        ) {
            $declaration = [];
            krsort( $declarationBlock );
            $declarationBlock = array_reverse( $declarationBlock );
            uksort( $declarationBlock, [ Sort::class, 'stylesheetDeclarations' ] );

            foreach (
                $declarationBlock as $variable => $value ) {
                $declaration[] = "$variable: $value;";
            }

            $declaration = Arr::implode(
                $declaration,
                "\n\t",
            );

            if ( str_starts_with( $element, '*' ) ) {
                array_unshift( $elements, "$element {\n\t$declaration\n} " );
            }
            else {
                $elements[] = "$element {\n\t$declaration\n} ";
            }

        }

        unset( $this->selectors );

        return $this->elementGroup( $elements );
    }

    private function elementGroup( array $elements ) : string {
        return "\n" . Arr::implode(
                $elements,
                "\n\n",
            );
    }

    private function buildMedia() : ?string {

        $sizes    = [
            'full'   => 1420,
            'large'  => 1020,
            'medium' => 640,
            'small'  => 420,
        ];
        $elements = [];

        foreach (
            array_filter( $this->media ) as $mediaSize => $screens
        ) {

            $set  = Str::split( $mediaSize, ':' );
            $size = Convert::pxToRem( $sizes[ $set[ 1 ] ] ?? null );

            if ( $size ) {
                $type   = trim( $set[ 0 ], ' :' );
                $screen = "@media ($type-width : $size)";
            }
            else {
                $screen = "@media ($mediaSize)";
            }

            $elements[] = "$screen {";

            foreach ( $screens as $element => $declarationBlock ) {
                $declaration = [];
                uksort( $declarationBlock, [ Sort::class, 'stylesheetDeclarations' ] );

                foreach (
                    $declarationBlock as $variable => $value ) {
                    $declaration[] = "$variable: $value;";
                }

                $declaration = Arr::implode(
                    $declaration,
                    "\n\t\t",
                );
                $elements[]  = "\t$element {\n\t\t$declaration\n\t} ";
            }

            $elements[] = "}";
        }

        unset( $this->media );

        return PHP_EOL . Arr::implode(
                $elements,
                PHP_EOL . PHP_EOL,
            );
    }

    private function buildKeyframes() : ?string {

        $keyframes = [];

        foreach (
            array_filter( $this->keyframes ) as $animation => $animationFrame
        ) {
            $animation   = "@keyframes $animation";
            $keyframes[] = "$animation {\n$animationFrame\n} ";
        }

        unset( $this->keyframes );

        return PHP_EOL . Arr::implode(
                $keyframes,
                PHP_EOL . PHP_EOL,
            );
    }

    private function scanEnqueuedStyles() : void {
        $this->enqueued = File::scanDirectories( path : $this->stylesheets, addUnexpectedValue : true );
    }

    private function getEnqueuedStyles() : array {

        foreach ( $this->enqueued as $index => $stylesheet ) {

            $key = trim(
                str_replace(
                    [ '.css', DIRECTORY_SEPARATOR ],
                    [ '', ':' ],
                    $stylesheet,
                ), ':',
            );

            // TODO: [low] Sanity check : even number of quotes, brackets, etc.
            if ( Str::contains( $stylesheet, [ '{', '}' ] ) ) {
                $this->enqueued[ $key ] = Str::squish( $stylesheet );
                unset( $this->enqueued[ $index ] );
                continue;
            }

            if ( $style = File::getContents( $stylesheet ) ) {
                $this->enqueued[ $key ] = Str::squish( $style );
                unset( $this->enqueued[ $index ] );
                continue;
            }

            Log::Error( "Stylesheet $stylesheet not found." );
        }

        return $this->enqueued;
    }

    // TODO: [low] Fix media queries.
    private function resolveMediaSize( string $media ) : ?string {

        return $media;
        //
        // if ( !Str::contains( $media, [ 'px', 'em', 'rem' ] ) ) {
        //     return $media;
        // }
        //
        // $sizes  = [
        //     'small'  => '420', // px
        //     'medium' => '640', // px
        //     'large'  => '1024', // px
        //     'full'   => '1420', // px | get data from full width
        // ];
        // $screen = substr( trim( $media ), 0, 3 ) . ':';
        // $px     = (int) Num::extract( $media );
        // $screen .= Num::closest( $px, $sizes, true );
        //
        //
        // return $screen ?? $media;
    }

    private function declaration( ?string $string, ?string $trim = ' :;' ) : object {

        [ $property, $value ] = Str::split( Str::squish( $string ) );
        $property = trim( strtolower( $property ), $trim );

        /**
         * TODO: [mid] Bug where a missing space between the property and value causes an error. Example: `margin : 0px` passes, `margin: 0px` passes, `margin :0px` fails
         */

        if ( !$property || !$value ) {
            dd(
                $string,
                $property,
                $value,
            );
        }

        $value = trim( str_replace( [ ' 0px', ' 0em', ' 0rem' ], ' 0', $value ), $trim );

        return (object) [
            'property' => $property,
            'value'    => $value,
        ];
    }

    private function explodeDeclaration( ?string $string ) : array {
        $declarations = explode( ';', trim( $string ) );

        foreach ( array_filter( $declarations ) as $key => &$part ) {
            $part = trim( $part );

            if ( !$part ) {
                unset( $declarations[ $key ] );
            }

        }

        return array_filter( $declarations );
    }

    private function explodeRule( ?string $string ) : array {
        $rule = array_filter( explode( ',', $string ) );

        foreach ( $rule as &$value ) {
            $value = strtolower( trim( $value ) );
        }

        return $rule;
    }

    /**
     * Remove the parsed partial $string from `$this->enqueued[$stylesheet]`
     *
     * @param string       $stylesheet  The stylesheet to parse
     * @param string|null  $string      The string to remove
     *
     * @return void
     */
    private function updateEnqueuedStylesheet( string $stylesheet, ?string $string ) : void {
        $this->enqueued[ $stylesheet ] = str_replace( $string, '', $this->enqueued[ $stylesheet ] );
    }


    private function force() : bool {
        return $this->force ?? $this->options[ 'forceUpdate' ] ?? false;
    }
}