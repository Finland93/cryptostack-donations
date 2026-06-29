# Changelog

All notable changes to this project are documented here. The format is based on
[Keep a Changelog](https://keepachangelog.com/en/1.1.0/), and this project adheres
to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [Unreleased]

## [0.1.0] - 2026-06-29
### Added
- Initial release.
- Multi-chain donations: Bitcoin, EVM (Ethereum, Polygon, Base, BNB) and Solana via WalletConnect / Reown AppKit.
- Inline donation widget (no popup) available as a Gutenberg block, `[crypto_donate]` shortcode, and classic sidebar widget.
- Transparent 1% platform fee split with no smart contract (inclusive or on-top modes).
- Curated stablecoin allow-list (USDC/USDT); never calls token `approve`.
- Automatic wallet-address locking on save, with a confirmed unlock action.
- Light / dark / auto themes, a custom accent color, and documented CSS variables.
- Data cleanup on uninstall (single site and multisite).

[Unreleased]: https://github.com/Finland93/cryptostack-donations/compare/v0.1.0...HEAD
[0.1.0]: https://github.com/Finland93/cryptostack-donations/releases/tag/v0.1.0
