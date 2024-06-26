<?php

namespace Northrook\Stylesheets\Rules;

class Flex extends AbstractRule
{

    protected const TRIGGER = 'flex';

    protected function rules( ?string $class = null ) : array {

        if ( $this->has( 'flex' ) ) {
            $this->rule( 'flex', [ 'display' => 'flex' ] );
        }

        if ( $this->has( 'col' ) ) {
            $this->rule( 'col', [ 'flex-direction' => 'column' ] );
        }


        // Is this element using a flow layout?
        $flow = $this->has( 'flow' );
        if ( $flow ) {
            if ( $this->has( 'reverse' ) ) {
                $this->rules[ "flex.$flow.reverse" ] = [ 'flex-direction' => 'column-reverse' ];
            }
            else {
                $this->rules[ "flex.$flow" ] = [ 'flex-direction' => 'column' ];
            }
        }
        else {
            if ( $this->has( 'center' ) ) {
                if ( $this->has( 'reverse' ) ) {
                    $this->rules[ 'flex.reverse.center' ] = [
                        'align-items'     => 'center',
                        'justify-content' => 'center',
                        'flex-direction'  => 'row-reverse',
                    ];
                }
                else {
                    $this->rules[ 'flex.center' ] = [
                        'align-items'     => 'center',
                        'justify-content' => 'center',
                    ];
                }

            }
            else {
                if ( $this->has( 'reverse' ) ) {
                    $this->rules[ 'flex.col.reverse' ] = [ 'flex-direction' => 'column-reverse' ];
                }
                {
                    $this->rules[ 'flex.col' ] = [ 'flex-direction' => 'column' ];
                }
            }
        }

        if ( $this->has( 'align-top' ) ) {
            $this->rules[ 'flex.align-top' ] = [ 'justify-content' => 'flex-start' ];
        }
        if ( $this->has( 'align-center' ) ) {
            $this->rules[ 'flex.align-center' ] = [ 'justify-content' => 'center' ];
        }
        if ( $this->has( 'align-baseline' ) ) {
            $this->rules[ 'flex.align-center' ] = [ 'align-items' => 'baseline' ];
        }
        if ( $this->has( 'align-left' ) ) {
            $this->rules[ 'flex.align-left' ] = [ 'align-items' => 'flex-start' ];
        }
        if ( $this->has( 'align-right' ) ) {
            $this->rules[ 'flex.align-right' ] = [ 'align-items' => 'flex-end' ];
        }
        if ( $this->has( 'align-bottom' ) ) {
            $this->rules[ 'flex.align-bottom' ] = [ 'align-items' => 'flex-end' ];
        }

        if ( $this->has( 'justify-between' ) ) {
            $this->rules[ 'flex.justify-between' ] = [ 'justify-content' => 'space-between' ];
        }

        if ( $this->has( 'grow' ) ) {
            $this->rules[ 'flex.grow' ] = [ 'flex-grow' => '1' ];
        }

        if ( $this->has( 'shrink' ) ) {
            $this->rules[ 'flex.shrink' ] = [ 'flex-shrink' => '1' ];
        }

        if ( $this->has( 'nowrap' ) ) {
            $this->rules[ 'flex.nowrap' ] = [ 'flex-wrap' => 'nowrap' ];
        }

        if ( $this->has( 'wrap' ) ) {
            $this->rules[ 'flex.wrap' ] = [ 'flex-wrap' => 'wrap' ];
        }

        return [];
    }

    // protected function construct() : void {
    //
    //     if ( $this->has( 'flex' ) ) {
    //         $this->rule( 'flex', [ 'display' => 'flex' ] );
    //     }
    //
    //     $flow = $this->has( 'flow' );
    //     if ( $flow ) {
    //         if ( $this->has( 'reverse' ) ) {
    //             $this->rules[ "flex.$flow.reverse" ] = [ 'flex-direction' => 'column-reverse' ];
    //         }
    //         else {
    //             $this->rules[ "flex.$flow" ] = [ 'flex-direction' => 'column' ];
    //         }
    //     }
    //     else {
    //         if ( $this->has( 'center' ) ) {
    //             if ( $this->has( 'reverse' ) ) {
    //                 $this->rules[ 'flex.reverse.center' ] = [
    //                     'align-items'     => 'center',
    //                     'justify-content' => 'center',
    //                     'flex-direction'  => 'row-reverse',
    //                 ];
    //             }
    //             else {
    //                 $this->rules[ 'flex.center' ] = [
    //                     'align-items'     => 'center',
    //                     'justify-content' => 'center',
    //                 ];
    //             }
    //
    //         }
    //         else {
    //             if ( $this->has( 'reverse' ) ) {
    //                 $this->rules[ 'flex.col.reverse' ] = [ 'flex-direction' => 'column-reverse' ];
    //             }
    //             {
    //                 $this->rules[ 'flex.col' ] = [ 'flex-direction' => 'column' ];
    //             }
    //         }
    //     }
    //
    //     if ( $this->has( 'align-top' ) ) {
    //         $this->rules[ 'flex.align-top' ] = [ 'justify-content' => 'flex-start' ];
    //     }
    //     if ( $this->has( 'align-center' ) ) {
    //         $this->rules[ 'flex.align-center' ] = [ 'justify-content' => 'center' ];
    //     }
    //     if ( $this->has( 'align-baseline' ) ) {
    //         $this->rules[ 'flex.align-center' ] = [ 'align-items' => 'baseline' ];
    //     }
    //     if ( $this->has( 'align-left' ) ) {
    //         $this->rules[ 'flex.align-left' ] = [ 'align-items' => 'flex-start' ];
    //     }
    //     if ( $this->has( 'align-right' ) ) {
    //         $this->rules[ 'flex.align-right' ] = [ 'align-items' => 'flex-end' ];
    //     }
    //     if ( $this->has( 'align-bottom' ) ) {
    //         $this->rules[ 'flex.align-bottom' ] = [ 'align-items' => 'flex-end' ];
    //     }
    //
    //     if ( $this->has( 'justify-between' ) ) {
    //         $this->rules[ 'flex.justify-between' ] = [ 'justify-content' => 'space-between' ];
    //     }
    //
    //     if ( $this->has( 'grow' ) ) {
    //         $this->rules[ 'flex.grow' ] = [ 'flex-grow' => '1' ];
    //     }
    //
    //     if ( $this->has( 'shrink' ) ) {
    //         $this->rules[ 'flex.shrink' ] = [ 'flex-shrink' => '1' ];
    //     }
    //
    //     if ( $this->has( 'nowrap' ) ) {
    //         $this->rules[ 'flex.nowrap' ] = [ 'flex-wrap' => 'nowrap' ];
    //     }
    //
    //     if ( $this->has( 'wrap' ) ) {
    //         $this->rules[ 'flex.wrap' ] = [ 'flex-wrap' => 'wrap' ];
    //     }
    // }

}