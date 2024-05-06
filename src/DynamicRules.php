<?php

namespace Northrook\Stylesheets;

use Northrook\Stylesheets\Rules\AbstractRule;
use Northrook\Support\Arr;
use Northrook\Support\File;
use Northrook\Support\Regex;
use Northrook\Support\Str;
use Northrook\Types\Path;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use UnexpectedValueException;

final class DynamicRules
{

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
        'disabled' => [ '.disabled' => [ 'pointer-events' => 'none' ] ],
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

    private array $directoriesToScan = [];
    /** @var array All rules found in every template file. */
    private array $templateRules = [];
    private array $rule          = [];
    /** @var array The dynamic variables. */
    public readonly array $root;
    public readonly array $variables;

    public function __construct(
        private readonly Path $rootDir,
        array                 $directories = [],
    ) {

        $this->addTemplateDirectories( ... $directories );

        $root = [];
        foreach ( $this::SIZE as $key => $value ) {
            if ( 'null' === $key || 'auto' === $key ) {
                continue;
            }
            $root[ "--$key" ] = $value;
        }

        $this->root = $root;
    }

    public function parse( ?string ...$templates ) : self {

        $this->addTemplateDirectories( ... $templates );

        $this->scanTemplateFiles()->parseTemplateRules();

        $this->variables = $this->rule;
        return $this;
    }

    private function scanTemplateFiles() : self {


        $files = [];
        foreach ( $this->directoriesToScan as $directory ) {

            $match = false;

            if ( str_contains( $directory, '*' ) ) {
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
            }
            catch ( UnexpectedValueException ) {
                continue;
            }

            foreach ( $iterator as $file ) {
                if ( $file->isDir()
                     || str_ends_with( $file->getFilename(), '.lock' )
                     || ( $match && !Str::contains( $file->getFilename(), $match ) ) ) {
                    continue;
                }
                $files[] = Str::squish( File::getContents( $file->getPathname() ) );
            }
        }

        foreach ( $files as $template ) {

            // Remove comments
            $template = preg_replace(
                [
                    '/\/\*\*.*?\*\//s', // PHP comments
                    '/\/\/.*?\v/m',     // Single line comments
                    '/<!--.*?-->/s',    // HTML comments
                    '/{\*.*?\*}/s',     // Latte comments
                    '/{#.*?#}/s',       // Twig comments
                    '/{{--.*?--}}/s',   // Blade comments
                ], '', $template,
            );

            // Find all class assignments
            $classes = Regex::matchNamedGroups(
                '/class="(?<class>.*?)"/s',
                $template,
            );
            foreach ( $classes as $get ) {
                $string = $get->class;

                // Remove single quited substrings
                $string = preg_replace( '/\'.*?\'/s', ' ', $string );

                $this->templateRules[] = Arr::explode( ' ', $string, true );
            }
        }

        return $this;
    }

    private function parseTemplateRules() : void {

        $inventory = [];
        foreach ( $this->templateRules as $selectors ) {

            // $selectors should always be an array
            if ( !is_array( $selectors ) ) {
                continue;
            }

            // Loop trough each selector provided
            foreach ( $selectors as $selector ) {
                $matchRule  = Str::before( $selector, [ ':', '-' ] );
                $foundRules = Arr::searchKeys( $this::RULES, $matchRule );
                foreach ( $foundRules as $rule ) {
                    if (
                        is_string( $rule ) &&
                        class_exists( $rule ) &&
                        is_subclass_of( $rule, AbstractRule::class )
                    ) {
                        $inventory += $rule::build( $selectors );
                    }

                }
            }
        }

        foreach ( $inventory as $key => $value ) {

            unset( $inventory[ $key ] );

            // Escape the colon used for variable assignment
            $key = str_replace( ':', '\:', $key );

            // Escape periods in numeric values, while preserving class dot notation
            $key = preg_replace( '/(\.)(?=\d)/', '\.', $key );

            $inventory[ ".$key" ] = $value;

        }

        $this->rule = $inventory;
    }


    /** Add directories to scan for template files.
     * * The scanned directories will be searched recursively.
     *
     * @param array  $path
     */
    public function addTemplateDirectories( string ...$path ) : void {

        foreach ( $path as $template ) {
            $this->directoriesToScan[] = Path::normalize( $template );
        }
    }
}