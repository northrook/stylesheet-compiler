<?php

namespace Northrook\Stylesheets\Rules;

use Northrook\Support\Str;

class Padding extends AbstractRule {

	protected const TRIGGER = 'p';

	private ?string $padding = null;

	protected function construct() {

		$this->padding = match ( Str::before( $this->class, ':' ) ) {
			'p-x' => 'horizontal',
			'p-y' => 'vertical',
			default => 'padding',
		};

		if ( $this->padding === 'horizontal' ) {
			$this->rules( ".{$this->class}", [
				'padding-left'  => $this->value ?? 'var(--padding)',
				'padding-right' => $this->value ?? 'var(--padding)']
			);
		} else if ( $this->padding === 'vertical' ) {
			$this->rules( ".{$this->class}", [
				'padding-top'    => $this->value ?? 'var(--padding)',
				'padding-bottom' => $this->value ?? 'var(--padding)']
			);
		} else {
			$this->rules( ".{$this->class}", [$this->padding => $this->value ?? 'var(--padding)'] );
		}
	}

}