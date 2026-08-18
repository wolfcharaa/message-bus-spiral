<?php

declare(strict_types=1);

namespace Wolfcharaa\MessageBus\Spiral\Discovery;

use ReflectionClass;
use Spiral\Core\Attribute\Singleton;
use Spiral\Tokenizer\Attribute\TargetAttribute;
use Spiral\Tokenizer\TokenizationListenerInterface;
use Wolfcharaa\MessageBus\Attribute\CommandHandler;
use Wolfcharaa\MessageBus\Attribute\EventSubscriber;
use Wolfcharaa\MessageBus\Attribute\MessageAlias;
use Wolfcharaa\MessageBus\Attribute\QueryHandler;
use Wolfcharaa\MessageBus\Discovery\ClassListProvider;
use Wolfcharaa\MessageBus\Dumper\SymfonyVarExporterRegistryDumper;
use Wolfcharaa\MessageBus\Registry\MessageRegistryCompiler;
use Wolfcharaa\MessageBus\Spiral\Application\Config\MessageBusConfig;

#[Singleton]
#[TargetAttribute(CommandHandler::class)]
#[TargetAttribute(QueryHandler::class)]
#[TargetAttribute(EventSubscriber::class)]
#[TargetAttribute(MessageAlias::class)]
final class MessageBusCompilerListener implements TokenizationListenerInterface
{
    /** @var array<class-string, class-string> */
    private array $classes = [];

    private bool $finalized = false;

    public function __construct(
        private readonly MessageBusConfig $config,
        private readonly MessageRegistryCompiler $compiler = new MessageRegistryCompiler(),
        private readonly SymfonyVarExporterRegistryDumper $dumper = new SymfonyVarExporterRegistryDumper(),
    ) {
    }

    #[\Override]
    public function listen(ReflectionClass $class): void
    {
        /** @var class-string $className */
        $className = $class->getName();
        $this->classes[$className] = $className;
    }

    #[\Override]
    public function finalize(): void
    {
        if ($this->finalized) {
            return;
        }

        $this->finalized = true;

        $file = $this->config->getRegistryFile();
        if ($file === null) {
            throw new \RuntimeException(
                'MessageBus registry file is not configured. Set `message_bus.registryFile` to compiled registry path.'
            );
        }

        $definition = $this->compiler->compile(
            new ClassListProvider(\array_values($this->classes)),
            $this->config->getFlowRegistry(),
        );

        $this->write($file, $this->dumper->dump($definition));
    }

    private function write(string $file, string $php): void
    {
        $directory = \dirname($file);
        if (!\is_dir($directory) && !\mkdir($directory, 0775, true) && !\is_dir($directory)) {
            throw new \RuntimeException(\sprintf('Unable to create MessageBus registry directory `%s`.', $directory));
        }

        $temporaryFile = $file . '.' . \bin2hex(\random_bytes(6)) . '.tmp';
        if (\file_put_contents($temporaryFile, $php, LOCK_EX) === false) {
            throw new \RuntimeException(\sprintf('Unable to write MessageBus registry file `%s`.', $temporaryFile));
        }

        if (!\rename($temporaryFile, $file)) {
            @\unlink($temporaryFile);

            throw new \RuntimeException(\sprintf('Unable to move MessageBus registry file `%s` to `%s`.', $temporaryFile, $file));
        }
    }
}
