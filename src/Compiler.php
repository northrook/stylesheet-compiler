<?php

declare( strict_types = 1 );

namespace Northrook\CSS;

use Northrook\CSS\Compiler\Assembler;
use Northrook\CSS\Compiler\Parser;
use Northrook\CSS\Compiler\Syntax\Block;
use Northrook\CSS\Compiler\Syntax\Rule;
use Northrook\CSS\Compiler\Syntax\Statement;
use Northrook\Minify;
use Psr\Log\LoggerInterface;
use function Northrook\hashKey;
use function Northrook\numberPercentDifference;

/**
 * @author Martin Nielsen <mn@northrook.com>
 */
class Compiler
{
    private array        $enqueued = [];
    private array        $ast      = [];
    private readonly int $initialSizeBytes;
    private int          $compiledSizeBytes;

    protected array $rules = [
        // '@charset' => null,
        // '@import'  => [],
        // ':root'    => [],
    ];

    protected int $lastModified = 0;

    public readonly string $css;

    public function __construct(
        string | array                    $source = [],
        private readonly ?LoggerInterface $logger = null,
        private readonly bool             $strict = false,
    ) {
        $this->ingestSources( (array) $source );
        //      ->parseEnqueued();
    }

    final public function generateStylesheet() : self {
        $stylesheet = new Assembler( $this->rules );

        $stylesheet->build();

        $this->css = $stylesheet->toString();

        $this->compiledSizeBytes = \strlen( $this->css );

        $this->compressionReport( 'Complete: Compiler' );
        return $this;
    }

    final public function parseEnqueued() : self {

        foreach ( $this->enqueued as $key => $css ) {
            $this->ast[ $key ] = ( new Parser( $css, $key ) )->rules();
        };

        return $this;
    }

    private function compileDeclaration( Rule | Block $declaration ) : array {
        $rules = [];

        foreach ( $declaration->declarations as $property => $value ) {
            if ( $value instanceof Rule || $value instanceof Block ) {
                $rules[ $property ] = $this->compileDeclaration( $value );
            }
            else {
                $rules[ $property ] = $value;
            }
        }
        return $rules;
    }

    private function handleStatement( Statement $statement ) : void {
        if ( $statement->identifier === '@charset' ) {

            if ( isset( $this->rules[ '@charset' ] ) && $this->strict ) {
                throw new \LogicException(
                    "CSS Compiler encountered conflicting $statement->identifier rules.",
                );
            }

            $this->rules[ '@charset' ] ??= \strtolower( $statement->rule );
        }

        if ( $statement->identifier === '@import' ) {
            $this->rules[ '@import' ][] = $statement->rule;
        }

    }

    final public function mergeRules() : self {

        // dd($this);
        // Loop through each provided sources' compiled AST
        foreach ( $this->ast as $source => $rules ) {
            foreach ( $rules as $selector => $declaration ) {

                // dump( $declarations );
                // foreach ( $declarations as $declaration ) {

                if ( $declaration instanceof Statement ) {
                    $this->handleStatement( $declaration );
                    continue;
                }

                // if ( !\is_string( $declaration ) ) {
                // }

                $this->rules[ $selector ] =
                    \array_merge(
                        $this->rules[ $selector ] ?? [],
                        $this->compileDeclaration( $declaration ),
                    );
                // }
            }
        }


        $this->sortRules();

        return $this;
    }

    final function sort( array $array ) : array {

        $variables = [];

        foreach ( $array as $key => $value ) {
            if ( \str_starts_with( $key, '--' ) ) {
                $variables[ $key ] = $value;
                unset( $array[ $key ] );
            }
        }

        return [ ...$variables, ...$array ];
    }

    final protected function sortRules() : void {

        $this->deduplicateDeclarations();

        $head = [
            '@charset' => null,
            '@import'  => null,
            ':root'    => null,
        ];

        $themes = [];

        $priority = [
            'html' => null,
            'body' => null,
        ];

        $body = [];
        $html = [];

        $raise = [];


        foreach ( $this->rules as $selector => $rule ) {

            if ( \in_array( $selector, \array_keys( $head ), true ) ) {
                $head[ $selector ] = $rule;
            }
            elseif ( \in_array( $selector, \array_keys( $priority ), true ) ) {
                $priority[ $selector ] = $rule;
            }
            elseif ( \preg_match( '#\[theme=.+?]#', $selector ) ) {
                $themes[ $selector ] = $rule;
            }
            elseif ( \preg_match( '#^[a-zA-Z][^.:>~]*$#m', $selector ) ) {

                if ( \str_starts_with( $selector, 'html' ) ) {
                    $html[ $selector ] = $rule;
                }
                elseif ( \str_starts_with( $selector, 'body' ) ) {
                    $body[ $selector ] = $rule;
                }
                else {
                    $raise[ $selector ] = $rule;
                }

            }
            else {
                continue;
            }

            unset( $this->rules[ $selector ] );
        }

        ksort( $raise );

        $this->rules = \array_filter(
            [
                ...$head,
                ...$themes,
                ...$priority,
                ...$html,
                ...$body,
                ...$raise,
                ...$this->rules,
            ],
        );

        foreach ( $this->rules as $selector => $rule ) {
            if ( !\is_array( $rule ) ) {
                continue;
            }
            $this->rules[ $selector ] = $this->sort( $rule );
        }
    }

    private function ingestSources( array $source ) : self {

        $initial  = 0;
        $minified = 0;
        foreach ( $source as $index => $string ) {
            $initial    += \strlen( $string );
            $stylesheet = Minify::CSS( $string, false )->toString();

            if ( !$stylesheet ) {
                $this->logger?->notice(
                    'The {key} stylesheet is empty after minification.',
                    [ 'key' => $index ],
                );
                continue;
            }

            $minified += \strlen( $stylesheet );

            $this->enqueued[ $index ] = $stylesheet;
        }

        $this->initialSizeBytes  = $initial;
        $this->compiledSizeBytes = $minified;

        $this->compressionReport( 'Construct: Sources' );

        return $this;
    }

    private function compressionReport( ?string $message = null ) : void {

        if ( !$this->logger ) {
            return;
        }

        $differenceKb = $this->initialSizeBytes - $this->compiledSizeBytes;

        $differenceKb      = $this->initialSizeBytes - $this->compiledSizeBytes;
        $differencePercent = numberPercentDifference( $this->initialSizeBytes, $this->compiledSizeBytes );
        $this->logger->info(
            message : ( $message ?? 'Stylesheet' ) . ' minified {percent}, from {from} to {to} saving {diff},',
            context : [
                          'from'    => "{$this->initialSizeBytes}KB",
                          'to'      => "{$this->compiledSizeBytes}KB",
                          'diff'    => "{$differenceKb}KB",
                          'percent' => "{$differencePercent}%",
                      ],
        );

    }


    /**
     * TODO Refactor this, find out exactly how PHP sorting algorithms function
     *    ? Is the returned int a weighted relative order, or boolean?
     *    ? Do we need to flip, I assume we do so to deduplicate the array?
     *
     * @param                $a
     * @param                $b
     *
     * @return int
     */
    private function sortDeclarations( $a, $b ) : int {

        $sortByList ??= [
            'content',
            'order',
            'position',
            'z-index',
            'inset',
            'top',
            'right',
            'bottom',
            'left',
            'float',
            'clear',

            // Display
            'display',
            'flex',
            'flex-flow',
            'flex-basis',
            'flex-direction',
            'flex-grow',
            'flex-shrink',
            'flex-wrap',
            'justify-content',
            'align-content',
            'align-items',
            'align-self',
            'gap',
            'column-gap',
            'row-gap',
            'grid-template-columns',

            // Box
            'height',
            'min-height',
            'max-height',
            'width',
            'min-width',
            'max-width',
            'max-inline-size',
            'margin',
            'margin-top',
            'margin-right',
            'margin-bottom',
            'margin-left',
            'padding',
            'padding-top',
            'padding-right',
            'padding-bottom',
            'padding-left',
            'box-sizing',
            'block-size',
            'overflow',
            'overflow-x',
            'overflow-y',
            'scroll-behavior',
            'scroll-padding-top',

            // Text
            'color',
            'font',
            'font-family',
            'font-size',
            'font-weight',
            'font-style',
            'font-variant',
            'font-size-adjust',
            'font-stretch',
            'text-align',
            'text-align-last',
            'text-justify',
            'vertical-align',
            'white-space',
            'text-decoration',
            'text-emphasis',
            'text-emphasis-color',
            'text-emphasis-style',
            'text-emphasis-position',
            'text-indent',
            'text-rendering',
            'line-height',
            'letter-spacing',
            'word-spacing',
            'text-outline',
            'text-transform',
            'text-wrap',
            'text-overflow',
            'text-overflow-ellipsis',
            'text-overflow-mode',
            'word-wrap',
            'word-break',
            'tab-size',
            'hyphens',
            'text-size-adjust',
            '-webkit-text-size-adjust',
            '-webkit-font-smoothing',
            '-webkit-tap-highlight-color',
            'border',
            'border-width',
            'border-style',
            'border-color',
            'border-top',
            'border-top-width',
            'border-top-style',
            'border-top-color',
            'border-right',
            'border-right-width',
            'border-right-style',
            'border-right-color',
            'border-bottom',
            'border-bottom-width',
            'border-bottom-style',
            'border-bottom-color',
            'border-left',
            'border-left-width',
            'border-left-style',
            'border-left-color',
            'border-radius',
            'border-top-left-radius',
            'border-top-right-radius',
            'border-bottom-right-radius',
            'border-bottom-left-radius',
            'border-image',
            'border-image-source',
            'border-image-slice',
            'border-image-width',
            'border-image-outset',
            'border-image-repeat',
            'outline',
            'outline-width',
            'outline-style',
            'outline-color',
            'outline-offset',
            'background',
            'background-color',
            'background-image',
            'background-repeat',
            'background-attachment',
            'background-position',
            'background-position-x',
            'background-position-y',
            'background-clip',
            'background-origin',
            'background-size',
            'box-decoration-break',
            'box-shadow',
            'text-shadow',

            // Appearance
            '-webkit-appearance',
            'appearance',
            '',
            '',
            'cursor',
            'user-select',
            'pointer-events',
            'table-layout',
            'empty-cells',
            'caption-side',
            'border-spacing',
            'border-collapse',
            'list-style',
            'list-style-position',
            'list-style-type',
            'list-style-image',
            'quotes',
            'counter-reset',
            'counter-increment',
            'resize',
            'nav-index',
            'nav-up',
            'nav-right',
            'nav-down',
            'nav-left',
            'transform',
            'transform-origin',
            'visibility',
            'opacity',
            'clip',
            'fill',
            'zoom',
            'transition',
            'transition-delay',
            'transition-timing-function',
            'transition-duration',
            'transition-property',
            'animation',
            'animation-name',
            'animation-duration',
            'animation-play-state',
            'animation-timing-function',
            'animation-delay',
            'animation-iteration-count',
            'animation-direction',
            'animation-fill-mode',
        ];

        $order = 0;

        if ( !$b ) {
            return $order;
        }


        $hierarchy = array_flip( $sortByList );
        $a         = trim( $a, ' -:' );
        $b         = trim( $b, ' -:' );
        if (
            array_key_exists( $a, $hierarchy )
            &&
            array_key_exists( $b, $hierarchy )
        ) {
            $order = $hierarchy[ $a ] <=> $hierarchy[ $b ];
        }
        // dump( $a, $order );


        return $order;
    }

    private function deduplicateDeclarations() : void {
        $duplicates = [];

        foreach ( $this->rules as $selector => $rule ) {


            // TODO : Deduplication
            $selectors = explode( ',', $selector );

            if ( \count( $selectors ) > 1 ) {

                sort( $selectors );
                $hashed = hashKey( $selectors );


                $duplicateSelector = $duplicates[ $hashed ] ?? false;

                if (
                    $duplicateSelector
                ) {

                    if ( $this->rules[ $duplicateSelector ] !== $rule ) {
                        // dump( $duplicateSelector, $selector );
                        $this->rules[ $duplicateSelector ] = \array_merge(
                            $this->rules[ $duplicateSelector ], $rule,
                        );
                    }

                    unset( $this->rules[ $selector ] );
                    continue;
                }

                $duplicates[ $hashed ] = $selector;

            }
        }

    }


}