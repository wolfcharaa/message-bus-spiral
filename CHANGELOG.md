# Changelog

## 5.2.0

- Require `romanfedorskij/message-bus` `^5.2`.
- Discover `DomainHandler` classes through Spiral Tokenizer.
- Register `default` and `domain_capability` sync flows when no flows are configured.
- Pass optional `MessageRegistryCompilerOptions` from Spiral config to the core compiler.
- Execute contextless domain handlers through the existing runtime plan.
