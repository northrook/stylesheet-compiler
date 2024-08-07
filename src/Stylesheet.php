<?php

declare( strict_types = 1 );

namespace Northrook\CSS;

use Northrook\Logger\Log;
use Northrook\Resource\Path;
use Psr\Log\LoggerInterface;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use SplFileInfo;
use UnexpectedValueException;
use function Northrook\hashKey;
use function Northrook\sourceKey;


/**
 * @author Martin Nielsen <mn@northrook.com>
 */
class Stylesheet
{
    private readonly Compiler $compiler;

    private Path  $savePath;
    private array $sources             = [];
    private array $templateDirectories = [];
    private int   $lastModified        = 0;

    protected bool $locked  = false;
    protected bool $updated = false;

    /**
     * @param string            $defaultSavePath      Where to save the generated stylesheet
     * @param array             $sourceDirectories    Will be scanned for .css files
     * @param array             $templateDirectories  .latte files will be scanned for dynamic styles
     * @param ?LoggerInterface  $logger               Optional PSR-3 logger
     */
    public function __construct(
        string                              $defaultSavePath,
        array                               $sourceDirectories = [],
        array                               $templateDirectories = [],
        protected readonly ?LoggerInterface $logger = null,
    ) {
        Log::notice( 'Stylesheet initialization' );
        $this->addSource( ... $sourceDirectories )
             ->addTemplateDirectory( ... $templateDirectories )
            ->savePath = new Path( $defaultSavePath );
        Log::notice( 'Stylesheet initialized' );
    }

    public function addBaseline() : Stylesheet {
        $precompiled   = [ 'baseline' => new Path( __DIR__ . '/Precompiled/baseline.css' ) ];
        $this->sources = [ ...$precompiled, ... $this->sources ];
        return $this;
    }

    public function addReset() : Stylesheet {
        $precompiled   = [ 'reset' => new Path( __DIR__ . '/Precompiled/reset.css' ) ];
        $this->sources = [ ...$precompiled, ... $this->sources ];
        return $this;
    }

    public function addDynamicRules() : Stylesheet {
        $precompiled   = [ 'dynamicRules' => new Path( __DIR__ . '/Precompiled/dynamicrules.css' ) ];
        $this->sources = [ ...$precompiled, ... $this->sources ];
        return $this;
    }

    public function addSource( string ...$add ) : Stylesheet {

        $this->throwIfLocked( "Unable to add new source; locked by the build proccess." );

        foreach ( $add as $source ) {

            // If the $source contains brackets, assume it is a raw CSS string
            if ( \str_contains( $source, '{' ) && \str_contains( $source, '}' ) ) {
                $this->sources[ "raw:" . hashKey( $source ) ] ??= $source;
                continue;
            }

            $path = new Path( $source );

            // If the source is a valid, readable path, add it
            if ( $path->isReadable ) {
                $this->sources[ "$path->extension:" . sourceKey( $path ) ] ??= $path;
            }
            else {
                $this->logger?->error(
                    "Unable to add new source {source}, the path is not readable.",
                    [ 'source' => $source, 'path' => $path ],
                );
            }

        }

        return $this;
    }

    public function addTemplateDirectory( string ...$add ) : Stylesheet {

        $this->throwIfLocked( "Unable to add new template; locked by the build proccess." );

        foreach ( $add as $directory ) {
            $path = new Path( $directory );

            if ( !$path->isReadable ) {
                $this->logger?->error(
                    "Unable to add new template directory '{directory}', as it cannot be read.",
                    [ 'directory' => $directory, 'path' => $path ],
                );

                continue;
            }

            $this->templateDirectories[ sourceKey( $path ) ] ??= $path;
        }

        return $this;
    }


    final public function save( ?string $savePath = null, bool $force = false ) : bool {
        Log::info( 'Start: ' . __METHOD__ );
        if ( $savePath ) {
            $savePath = new Path( $savePath );

            if ( $savePath->isWritable ) {
                $this->savePath = $savePath;
            }
            else {
                $this->logger?->error(
                    'Unable to update save path "{savePath}", as it is not writable. Using default save path.',
                    [ 'savePath' => $savePath ],
                );
                // if $this->strict { throw }
            }
        }

        if ( $this->build( $force ) ) {
            $this->logger->info(
                'Saving compiled stylesheet to (path).',
                [ 'path' => $this->savePath ],
            );
            $this->updated = $this->savePath->save( $this->compiler->css );
        }
        else {
            $this->updated = false;
        }

        Log::info( 'End: ' . __METHOD__ );
        return $this->updated;
    }

    final public function build( bool $force = false ) : bool {

        // Lock the $sources
        $this->locked = true;

        // Find all $sources
        $sources = $this->scanSourceDirectories();

        // Bail if we have no sources, or if generation is unnecessary
        if ( !$sources || ( !$force && !$this->updateSavedFile() ) ) {
            return false;
        }

        // Initialize the compiler from provided $sources
        $this->compiler ??= new Compiler(
            $this->enqueueSources( $sources ),
            $this->logger,
        );

        $this->compiler->parseEnqueued()
                       ->mergeRules()
                       ->generateStylesheet();
        
        $this->locked = false;
        return true;
    }

    final protected function compiler() : Compiler {
        return $this->compiler ??= new Compiler( $this->sources, $this->logger );
    }

    /**
     * @param ?string  $message  Optional message
     *
     * @return void
     */
    final protected function throwIfLocked( ?string $message = null ) : void {
        if ( $this->locked ) {
            throw new \LogicException(
                $message ?? $this::class . " has been locked by the build proccess.",
            );
        }
    }

    final protected function scanSourceDirectories() : array {

        $files       = [];
        $underscored = [];

        foreach ( $this->sources as $key => $source ) {

            try {
                // Recursively scan all provided sources
                $iterator = new RecursiveIteratorIterator(
                    new RecursiveDirectoryIterator( (string) $source ),
                    RecursiveIteratorIterator::CHILD_FIRST,
                );
            }
            catch ( UnexpectedValueException ) {
                // If we encounter an exception, check if it is a raw source
                if (
                    \str_starts_with( $key, 'raw' )
                    || $source instanceof Path
                ) {
                    $files[ $key ] = $source;
                }
                continue;
            }

            /** @var SplFileInfo $file */
            foreach ( $iterator as $file ) {

                // Skip directories
                if ( $file->isDir() ) {
                    continue;
                }

                $path         = new Path( $file->getPathname() );
                $lastModified = (int) $file->getMTime();

                if ( $lastModified > $this->lastModified ) {
                    $this->lastModified = $lastModified;
                }

                $key = 'css:' . sourceKey( $path );

                if ( \str_starts_with( \basename( (string) $path ), '_' ) ) {
                    $underscored[ $key ] = $path;
                }
                else {
                    $files[ $key ] = $path;
                }
            }
        }

        // \usort(
        //     $underscored, static fn ( $a, $b ) => \strlen( \strrchr( $a, '_' ) ) <=> \strlen( \strrchr( $b, '_' ) ),
        // );

        return \array_merge( $underscored, $files );
    }

    /**
     * Check if any enqueued asset is newer than the saved file
     *
     * @return bool True if at least one asset is newer, false otherwise
     */
    private function updateSavedFile() : bool {

        if ( !isset( $this->savePath ) ) {

            $this->logger?->error( 'Stylesheet::savePath is not set.' );

            return false;
        }

        // If any of the assets are newer than the saved file, return true
        return $this->lastModified >= ( $this->savePath->exists ? $this->savePath->lastModified : 0 );
    }

    private function enqueueSources( array $sources ) : array {

        foreach ( $sources as $index => $source ) {

            $value = $source instanceof Path ? $source->read : $source;

            if ( !$value ) {
                $this->logger?->critical(
                    $this::class . ' is unable to read source "{source}"',
                    [ 'source' => $source ],
                );
            }
            $sources[ $index ] = $value;

        }
        return $sources;
    }
}