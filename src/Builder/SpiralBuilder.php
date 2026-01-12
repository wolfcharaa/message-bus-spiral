<?php

declare(strict_types=1);

namespace Wolfcharaa\MessageBus\Spiral\Builder;

use Closure;
use InvalidArgumentException;
use Psr\Container\ContainerExceptionInterface;
use Psr\Container\ContainerInterface;
use Psr\Container\NotFoundExceptionInterface;
use ReflectionException;
use ReflectionFunction;
use Spiral\Core\Attribute\Singleton;
use Spiral\Interceptors\Context\Target;
use Spiral\Interceptors\Handler\AutowireHandler;
use Spiral\Interceptors\InterceptorInterface;
use Spiral\Interceptors\PipelineBuilder;
use Wolfcharaa\MessageBus\Builder\HandlerBuilderInterface;
use Wolfcharaa\MessageBus\Handler\HandlerWithMiddleware;
use Wolfcharaa\MessageBus\Middleware\Middleware;
use Wolfcharaa\MessageBus\Spiral\Handler\SpiralHandler;
use Wolfcharaa\MessageBus\Handler\Handler;
use Wolfcharaa\MessageBus\Spiral\Middleware\SpiralMiddleware;

use function count;

#[Singleton]
class SpiralBuilder implements HandlerBuilderInterface
{
    /**
     * @var array<Middleware|InterceptorInterface> $middleware
     */
    private array $middleware = [];

    public function __construct(
        private readonly AutowireHandler $autowireHandler,
        private readonly PipelineBuilder $pipelineBuilder,
        private readonly ContainerInterface $container,
    ) {
    }

    /**
     * @throws ContainerExceptionInterface
     * @throws NotFoundExceptionInterface
     */
    public function withMiddleware(string ...$middleware): HandlerBuilderInterface
    {
        $clone = clone $this;

        foreach ($middleware as $class) {
            if (
                !is_a($class, Middleware::class, true)
                &&
                !is_a($class, InterceptorInterface::class, true)
            ) {
                throw new InvalidArgumentException(sprintf(
                    'Middleware `%s` is not supported.',
                    $class
                ));
            }

            $clone->middleware[] = $this->container->get($class);
        }

        return $clone;
    }

    /**
     * @param array{0: class-string|string, 1: ?string} $target
     * @throws ReflectionException
     * @throws ReflectionException
     * @throws ReflectionException
     */
    public function build(array $target): Handler
    {
        $target = match (true) {
            count($target) === 2 => Target::fromPair($target[0], $target[1]),
            $target[0] instanceof Closure => Target::fromClosure($target[0]),
            function_exists($target[0]) => Target::fromReflectionFunction(new ReflectionFunction($target[0])),
            default => throw new InvalidArgumentException(sprintf(
                'Target `%s` is not supported',
                gettype($target[0])
            )),
        };

        if (empty($this->middleware)) {
            return new SpiralHandler($this->pipelineBuilder->build($this->autowireHandler), $target);
        }

        return new HandlerWithMiddleware(
            new SpiralHandler($this->pipelineBuilder->build($this->autowireHandler), $target),
            array_map(function (Middleware|InterceptorInterface $middleware) use ($target): Middleware {
                if (is_a($middleware, InterceptorInterface::class, true)) {
                    return new SpiralMiddleware($middleware, $this->autowireHandler);
                }

                return $middleware;
            }, $this->middleware),
        );
    }
}
