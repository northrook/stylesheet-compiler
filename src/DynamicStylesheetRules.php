<?php

namespace Northrook\Stylesheets;

use Northrook\Support\File;
use Northrook\Support\Regex;
use Northrook\Support\Str;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use UnexpectedValueException;
final class DynamicStylesheetRules {
	
	private const ALIAS_RULE = [
		'bg'	=> 'background-color',
		'color'	=> 'color',
	];
	
	private const SIZE = [
		'auto'		=> 'auto',
		'null'		=> '0',
		'tiny'		=> 'tiny',
		'small'		=> 'small',
		'medium'	=> 'medium',
		'large'		=> 'large',
	];
	
	private const RULES = [
		'palette'	=> [
			'bg'	=> 'background-color',
			'color'	=> 'color',
		],
		'space'		=> [
			'h'	=> [ 'margin-top' ],
			'v'	=> [ 'margin-left' ],
		],
		'gap'       => [
			''	=> [ 'gap' ],
			'x'	=> [ 'column-gap', ],
			'y'	=> [ 'row-gap', ],
		],
		'margin'    => [
			''	=> [ 'margin' ],
			'x'	=> [ 'margin-left', 'margin-right', ],
			'y'	=> [ 'margin-top', 'margin-bottom', ],
			't'	=> [ 'margin-top' ],
			'r'	=> [ 'margin-right' ],
			'b'	=> [ 'margin-bottom' ],
			'l'	=> [ 'margin-left' ],
		],
		'padding'   => [
			''	=> [ 'padding' ],
			'x'	=> [ 'padding-left', 'padding-right', ],
			'y'	=> [ 'padding-top', 'padding-bottom', ],
			't'	=> [ 'padding-top' ],
			'r'	=> [ 'padding-right' ],
			'b'	=> [ 'padding-bottom' ],
			'l'	=> [ 'padding-left' ],
		],
	];
	
	private static array $scanDirectories = [];
	
	/** @var array The dynamic variables. */
	public readonly array $variables;
	
	private array	$dynamicVariables	= [];
	private array	$filterVariables	= [];
	
	private readonly string $rootDir;
	
	public function __construct(
		string $rootDir,
		array $scanDirectories = [],
		array $variables = [],
	) {
		$this->rootDir = $rootDir;
		
		$this->assignPresetVariables();
		$this->generateDynamicVariables( $variables );
		$this->createAutoCompleteCache();
		$this->scanTemplateFiles( $scanDirectories );
		$this->variables = $this->filterVariables();
	}
	
	public static function scanDirectories( array $scanDirectories ) : void {
		DynamicStylesheetRules::$scanDirectories = array_merge(
			DynamicStylesheetRules::$scanDirectories,
			$scanDirectories
		);
	}
	
	private function scanTemplateFiles( array $directories ) : void {
		
		$this::scanDirectories( $directories );
		
		$templates	= [];
		$files		= [];
		
		foreach ( $this::$scanDirectories as $directory ) {
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
				/// TODO Combined classes such as flex.reverse does not trigger generation
				//!      Is this an issue?
				array_push( $templates, ... explode( ' ', $get->class ) );
			}
		}
		
		$templates = array_flip( $templates );
		foreach ( $templates as $key => $void ) {
			unset( $templates[ $key ] );
			if ( ! $key || Str::contains( $key, [ '--', '{', '}', '|', '??' ] ) ) {
				continue;
			}
			$key					= str_replace( [ '(', ')', "'" ], '', $key );
			$templates[ ".$key" ]	= $key;
		}
		$this->filterVariables = $templates;
		
	}
	
	private function filterVariables() : array {
		$rules = [];
		// dd( $this->filterVariables );
		foreach ( $this->filterVariables as $rule => $variables ) {
			if ( array_key_exists( $rule, $this->dynamicVariables ) ) {
				$rules[ $rule ] = $this->dynamicVariables[ $rule ];
				continue;
			}
			foreach ( $this->dynamicVariables as $key => $value ) {
				if ( str_contains( $key, $rule ) ) {
					$rules[ $key ] = $this->dynamicVariables[ $key ];
				}
			}
		}
		return $rules;
	}
	
	private function createAutoCompleteCache() : void {
		$content = [];
		foreach ( $this->dynamicVariables as $selector => $rules ) {
			foreach ( $rules as $rule => $value ) {
                $content[ $selector ][] = $rule . ' : ' . $value . ';';
			}
            $content[ $selector ] = $selector . " {\n\t" . implode( "\n\t", $content[ $selector ] ) . "\n}\n";
		}
		
		File::putContents(
			implode( PHP_EOL, $content ),
			File::getPath( $this->rootDir . '/var/styles/dynamicRules.css', true )
		);
	}
	
	
	private function assignPresetVariables() : void {
		
        $this->dynamicVariables[ '.bottom' ][ 'bottom' ] = '0';
		
        $this->dynamicVariables[ '.nowrap' ][ 'white-space' ]				= 'nowrap';
        $this->dynamicVariables[ '.flex-row' ][ 'display' ]					= 'flex';
        $this->dynamicVariables[ '.flex-row.reverse' ][ 'flex-direction' ]	= 'row-reverse';
        $this->dynamicVariables[ '.flex-col' ]								= [
			'display'			=> 'flex',
			'flex-direction'	=> 'column',
		];
        $this->dynamicVariables[ '.flex-col.reverse' ][ 'flex-direction' ]	= 'column-reverse';
		
        $this->dynamicVariables[ '.flex-col.align-top' ][ 'justify-content' ]		= 'flex-start';
        $this->dynamicVariables[ '.flex-row.align-left' ][ 'justify-content' ]		= 'flex-start';
        $this->dynamicVariables[ '.flex-row.align-center' ][ 'align-items' ]		= 'center';
        $this->dynamicVariables[ '.flex-col.align-center' ][ 'justify-content' ]	= 'center';
        $this->dynamicVariables[ '.flex-col.align-left' ][ 'align-items' ]			= 'flex-start';
		
	}
	
	private function generateDynamicVariables( array $colorPalette = [] ) : void {
		
		$dynamicPalette = [];
		foreach ( $colorPalette as $key => $value ) {
            $dynamicPalette[ trim( str_replace( [ '--', 'baseline' ], '', $key ), '-' ) ] = $key;
		}
		
		foreach ( $this::RULES as $type => $ruleType ) {
			if ( $type === 'palette' ) {
				foreach ( $ruleType as $create => $rule ) {
					foreach ( $dynamicPalette as $label => $color ) {
						$key = implode( '-', [ $create, $label ] );
						// $rule									= $value;
						$style										= 'hsla(var(' . $color . '))';
                        $this->dynamicVariables[ ".$key" ][ $rule ]	= $style;
					}
				}
			}
			if ( $type === 'space' ) {
				foreach ( $ruleType as $create => $rule ) {
					// $key = "m$create";
					foreach ( $this::SIZE as $label => $size ) {
						$key = implode( '-', [ "space-$create", $label ] ) . ' > * + *';
						
						foreach ( $rule as $ruleKey ) {
							if ( $label === 'auto' ) {
								$set = 'auto';
							}
							else {
								$set = ( $label === 'null' ) ? '0' : "var(--$size)";
							}
							
                            $this->dynamicVariables[ ".$key" ][ $ruleKey ] = $set;
						}
					}
				}
			}
			if ( $type === 'gap' ) {
				foreach ( $ruleType as $create => $rule ) {
					
					foreach ( $this::SIZE as $label => $size ) {
						$key = implode( '-', array_filter( [ "gap", $create, $label ] ) );
						
						foreach ( $rule as $ruleKey ) {
							if ( $label === 'auto' ) {
								continue;
							}
							$set = ( $label === 'null' ) ? '0' : "var(--$size)";
							
                            $this->dynamicVariables[ ".$key" ][ $ruleKey ] = $set;
						}
					}
				}
			}
			if ( $type === 'margin' ) {
				foreach ( $ruleType as $create => $rule ) {
					// $key = "m$create";
					foreach ( $this::SIZE as $label => $size ) {
						$key = implode( '-', [ "m$create", $label ] );
						
						foreach ( $rule as $ruleKey ) {
							if ( $label === 'auto' ) {
								$set = 'auto';
							}
							else {
								$set = ( $label === 'null' ) ? '0' : "var(--$size)";
							}
							
							
                            $this->dynamicVariables[ ".$key" ][ $ruleKey ] = $set;
						}
					}
				}
			}
			if ( $type === 'padding' ) {
				foreach ( $ruleType as $create => $rule ) {
					// $key = "m$create";
					foreach ( $this::SIZE as $label => $size ) {
						$key = implode( '-', [ "p$create", $label ] );
						
						foreach ( $rule as $ruleKey ) {
							if ( $label === 'auto' ) continue;
							
							
							$set = ( $label === 'null' ) ? '0' : "var(--$size)";
							
							
                            $this->dynamicVariables[ ".$key" ][ $ruleKey ] = $set;
						}
					}
				}
			}
		}
		
	}
}