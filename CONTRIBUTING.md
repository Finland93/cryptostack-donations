# Contributing to CryptoStack Donations

Thanks for your interest in improving the plugin! This guide covers the project layout, how to build the wallet bundle, and how to test.

## Project layout

```
cryptostack-donations.php        Bootstrap, constants, activation/uninstall hooks
includes/
  config.php                     Hardcoded fee wallets + integrity check, chain list, token allow-list
  class-csd-settings.php         Admin settings, validation, lock-on-save logic
  class-csd-render.php           Shortcode + block + asset loading, builds the JS config
  class-csd-widget.php           Classic sidebar widget
assets/
  css/donation.css               Inline widget + admin styles (light/dark/auto, accent var)
  js/donation-engine.js          Core: fee split (BigInt), tx building, inline UI
  js/admin.js                    Settings lock UX + accent color picker init
  src/appkit-bundle.js           SOURCE for the Reown AppKit wrapper (window.CSDAppKit)
blocks/donation/                 Gutenberg block (block.json + editor script)
build/appkit-bundle.js           BUILT wallet bundle (committed; ships in releases)
languages/                       Translation template (.pot)
readme.txt                       WordPress.org readme
uninstall.php                    Removes options (wallets) on delete
```

## How the fee split works (no smart contract)

The 1% is a second transfer in the same user flow:

- **Solana** — one transaction with two transfer instructions (recipient + fee). One signature.
- **EVM** — EIP-5792 `wallet_sendCalls` batches both transfers into one approval when the wallet supports it; otherwise two `eth_sendTransaction` calls.
- **Bitcoin** — the Reown connector sends to one recipient per call, so the donation and the fee are two transactions.

The fee is never hidden: the donor sees every destination address in their wallet before signing. The fee wallets are hardcoded in `includes/config.php` and protected by a SHA-256 integrity check — tampering disables the fee (fail-safe) rather than redirecting it.

## Building the wallet bundle

The Reown AppKit runtime is bundled locally into `build/appkit-bundle.js` (WordPress.org does not allow loading executable JS from a CDN). The committed bundle is what ships in releases, so **end users never build anything**.

You only need this to change `assets/src/appkit-bundle.js`:

```bash
npm install        # installs the exact pinned versions from package.json
npm run build      # regenerates build/appkit-bundle.js
```

`vite.config.js` produces a single self-contained IIFE that assigns `window.CSDAppKit`, with Buffer/process/global polyfills injected by `vite-plugin-node-polyfills`.

The committed bundle was built with `@reown/appkit` 1.8.21 (+ matching ethers/solana/bitcoin adapters), `@solana/web3.js` 1.98.4, `@solana/spl-token` 0.4.14, `ethers` 6.17.0, Vite 5.4.21. Keep the four `@reown/appkit*` packages on the same version. If you bump the AppKit **major**, re-verify the calls listed below.

### AppKit API used (checked against 1.8.21)

- `appkit.getProvider('eip155' | 'solana' | 'bip122')`
- `appkit.getAccount(namespace) -> { isConnected, address }`
- `appkit.subscribeAccount(cb, namespace) -> unsubscribe`
- `appkit.switchNetwork(network)` / `appkit.disconnect(namespace)`
- Solana provider: `signAndSendTransaction(tx)`
- Bitcoin connector: `sendTransfer({ recipient, amount })` (single recipient, satoshis)

## Coding standards

PHP follows the WordPress Coding Standards. A `phpcs.xml.dist` is included:

```bash
composer global require wp-coding-standards/wpcs   # one-time
phpcs --standard=phpcs.xml.dist .
```

Also run the official **Plugin Check** plugin in a WordPress install before opening a PR that touches PHP.

- Prefix everything with `csd_` / `CSD_`.
- Escape on output (`esc_html`, `esc_attr`, `esc_url`), sanitize on input, verify nonces, check `manage_options`.
- Keep all user-facing strings translatable with the `cryptostack-donations` text domain.

## Testing checklist

Test on mainnet with **tiny amounts** first.

- [ ] Settings: enter one address per family; only those networks show to donors.
- [ ] Lock-on-save: saving locks the address fields; unlocking requires the confirmed button.
- [ ] Integrity fail-safe: change a fee wallet constant → fee disables (`feeBps` = 0) and an admin notice appears.
- [ ] EVM batch: a wallet supporting EIP-5792 sends both transfers in one approval.
- [ ] EVM fallback: a wallet without batching sends two transactions (fee first).
- [ ] EVM stablecoin: USDC/USDT builds a `transfer` (selector `0xa9059cbb`), never `approve`.
- [ ] Solana native: one transaction, two `SystemProgram.transfer` instructions.
- [ ] Solana SPL: USDC donation, ATA created if missing, `transferChecked` used.
- [ ] Bitcoin: donation and fee go out as two `sendTransfer` calls.
- [ ] Fee modes: inclusive (99%) and on-top (100%) compute correctly with BigInt.
- [ ] Display: block, shortcode, and widget all render the same inline form.
- [ ] Themes: light / dark / auto, plus a custom accent color.

## Pull requests

1. Fork and create a feature branch.
2. Keep changes focused; update `readme.txt` / `CHANGELOG.md` when relevant.
3. If you changed `assets/src/appkit-bundle.js`, rebuild and commit `build/appkit-bundle.js`.
4. Make sure PHP lints clean and Plugin Check passes.
5. Open the PR against `main` with a clear description.

## Releasing (maintainers)

1. Bump the version in `cryptostack-donations.php`, `readme.txt` (Stable tag), and `CHANGELOG.md`.
2. Tag `vX.Y.Z` and push — the release workflow builds the bundle and attaches the plugin zip.
3. The deploy workflow can push the tag to the WordPress.org SVN repository (see `.github/workflows/deploy.yml`).
