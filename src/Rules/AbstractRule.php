<?php

namespace Northrook\Stylesheets\Rules;

use Northrook\Stylesheets\DynamicRules;
use Northrook\Stylesheets\DynamicStylesheetRules;

abstract class AbstractRule {

	public readonly array $parsed;
	protected array $rules = [];

	abstract protected function __construct( array $parse );

	public static function build( array $classes = [] ): array {
		$build = new static( $classes );
		// DynamicRules::updatedParsedRules( $build->parsed ?? [] );

		return $build->rules;
	}

}