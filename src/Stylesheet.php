<?php

declare(strict_types=1);

namespace Northrook\CSS;

use JetBrains\PhpStorm\Deprecated;
use LogicException;
use Northrook\{Clerk, Get};
use Northrook\Resource\Path;
use Psr\Log\LoggerInterface;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use SplFileInfo;
use UnexpectedValueException;
use function String\{hashKey, sourceKey};

/**
 * @author Martin Nielsen <mn@northrook.com>
 */
class Stylesheet
{
    private readonly Compiler $compiler;

    private ?Path $savePath;

    private array $sources = [];

    private array $templateDirectories = [];

    private int $lastModified = 0;

    protected bool $locked = false;

    protected bool $updated = false;

    /**
     * @param ?string          $defaultSavePath
     * @param string[]         $sources         Will be scanned for .css files
     * @param ?LoggerInterface $logger          Optional PSR-3 logger
     */
    public function __construct(
        ?string                             $defaultSavePath = null,
        array                               $sources = [],
        // array                               $templateDirectories = [], // TODO : [low]
        protected readonly ?LoggerInterface $logger = null,
    ) {
        Clerk::event( $this::class, 'document' );
        $this->addSource( ...$sources );

        $this->savePath = $defaultSavePath ? new Path( $defaultSavePath ) : null;

        Clerk::event( $this::class.'::initialized', 'document' );
    }

    /**
     * Add one or more stylesheets to this generator.
     *
     * Accepts raw CSS, or a path to a CSS file.
     *
     * @param string ...$add
     *
     * @return $this
     */
    final public function addSource( string ...$add ) : Stylesheet
    {
        // TODO : [low] Support URL

        $this->throwIfLocked( 'Unable to add new source; locked by the build proccess.' );

        foreach ( $add as $source ) {

            if ( ! $source ) {
                $this->logger?->warning( $this::class.' was provided an empty source string. It was not enqueued.', ['sources' => $add] );

                continue;
            }

            // If the $source contains brackets, assume it is a raw CSS string
            if ( \str_contains( $source, '{' ) && \str_contains( $source, '}' ) ) {
                $this->sources['raw:'.hashKey( $source )] ??= $source;

                continue;
            }

            $path = new Path( $source );

            // If the source is a valid, readable path, add it
            if ( 'css' === $path->extension && $path->isReadable ) {
                $this->sources["{$path->extension}:".sourceKey( $path )] ??= $path;

                continue;
            }

            $this->logger?->error(
                'Unable to add new source {source}, the path is not valid.',
                ['source' => $source, 'path' => $path],
            );
        }

        return $this;
    }

    // TODO : [low]
    // final public function addTemplateDirectory( string ...$add ) : Stylesheet
    // {
    //     $this->throwIfLocked( 'Unable to add new template; locked by the build proccess.' );
    //
    //     foreach ( $add as $directory ) {
    //         $path = Get::path( $directory, true );
    //
    //         if ( ! $path->isReadable ) {
    //             $this->logger?->error(
    //                 "Unable to add new template directory '{directory}', as it cannot be read.",
    //                 ['directory' => $directory, 'path' => $path],
    //             );
    //
    //             continue;
    //         }
    //
    //         $this->templateDirectories[sourceKey( $path )] ??= $path;
    //     }
    //
    //     return $this;
    // }

    final public function compile() : string
    {
        Clerk::event( __METHOD__ );

        // Lock the $sources
        $this->locked = true;

        // Initialize the compiler from provided $sources
        $this->compiler ??= new Compiler(
            $this->enqueueSources( $this->sources ),
            $this->logger,
        );
        $this->compiler->parseEnqueued()
            ->mergeRules()
            ->generateStylesheet();

        $this->locked = false;

        Clerk::event( __METHOD__ )->stop();
        return $this->compiler->css;
    }

    #[Deprecated]
    final public function build( bool $force = false ) : bool
    {
        Clerk::event( __METHOD__, 'document' );
        // Lock the $sources
        $this->locked = true;

        // Find all $sources
        $sources = $this->scanSourceDirectories();

        // Bail if we have no sources, or if generation is unnecessary
        if ( ! $sources || ( ! $force && ! $this->updateSavedFile() ) ) {
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

    #[Deprecated]
    final public function save( ?string $savePath = null, bool $force = false ) : bool
    {
        Clerk::event( __METHOD__, 'document' );
        if ( $savePath ) {
            $savePath = Get::path( $savePath, true );

            if ( $savePath->isWritable ) {
                $this->savePath = $savePath;
            }
            else {
                $this->logger?->error(
                    'Unable to update save path "{savePath}", as it is not writable. Using default save path.',
                    ['savePath' => $savePath],
                );
                // if $this->strict { throw }
            }
        }

        if ( $this->build( $force ) ) {
            $this->logger?->info(
                'Saving compiled stylesheet to (path).',
                ['path' => $this->savePath],
            );
            $this->updated = $this->savePath->save( $this->compiler->css );
        }
        else {
            $this->updated = false;
        }

        Clerk::event( __METHOD__ )->stop();
        return $this->updated;
    }

    /**
     * @param ?string $message Optional message
     *
     * @return void
     */
    final protected function throwIfLocked( ?string $message = null ) : void
    {
        if ( $this->locked ) {
            throw new LogicException( $message ?? $this::class.' has been locked by the compile proccess.' );
        }
    }

    #[Deprecated]
    final protected function scanSourceDirectories() : array
    {
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
                    $files[$key] = $source;
                }

                continue;
            }

            /** @var SplFileInfo $file */
            foreach ( $iterator as $file ) {
                // Skip directories
                if ( $file->isDir() || $file->getExtension() !== 'css' ) {
                    continue;
                }

                $path         = new Path( $file->getPathname() );
                $lastModified = (int) $file->getMTime();

                if ( $lastModified > $this->lastModified ) {
                    $this->lastModified = $lastModified;
                }

                $key = 'css:'.sourceKey( $path );

                if ( \str_starts_with( \basename( (string) $path ), '_' ) ) {
                    $underscored[$key] = $path;
                }
                else {
                    $files[$key] = $path;
                }
            }
        }

        // \usort(
        //     $underscored, static fn ( $a, $b ) => \strlen( \strrchr( $a, '_' ) ) <=> \strlen( \strrchr( $b, '_' ) ),
        // );

        return \array_merge( $underscored, $files );
    }

    /**
     * Check if any enqueued asset is newer than the saved file.
     *
     * @return bool True if at least one asset is newer, false otherwise
     */
    private function updateSavedFile() : bool
    {
        if ( ! isset( $this->savePath ) ) {
            $this->logger?->error( 'Stylesheet::savePath is not set.' );

            return false;
        }

        // If any of the assets are newer than the saved file, return true
        return $this->lastModified >= ( $this->savePath->exists ? $this->savePath->lastModified : 0 );
    }

    private function enqueueSources( array $sources ) : array
    {
        foreach ( $sources as $index => $source ) {
            $value = $source instanceof Path ? $source->read : $source;

            if ( ! $value ) {
                $this->logger?->critical(
                    $this::class.' is unable to read source "{source}"',
                    ['source' => $source],
                );
            }
            $sources[$index] = $value;
        }
        return $sources;
    }
}
