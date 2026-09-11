# Changelog

## 6.0.0

- Require `romanfedorskij/message-bus` `^6.0`.
- Replace removed `DomainHandler` discovery with `QueryHandler(contextAware: false)`.
- Use core `Interceptor\Pipeline` instead of the removed legacy middleware pipeline.
- Align examples with v6 command/query semantics: commands return `void`, queries return results.

## 5.2.0

- Require `romanfedorskij/message-bus` `^5.2`.
- Discover `DomainHandler` classes through Spiral Tokenizer.
- Register `default` and `domain_capability` sync flows when no flows are configured.
- Pass optional `MessageRegistryCompilerOptions` from Spiral config to the core compiler.
- Execute contextless domain handlers through the existing runtime plan.
