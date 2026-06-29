# CryptoStack Donations (developer notes)

A multi-chain, non-custodial crypto donation plugin for WordPress. Donors connect
a wallet via WalletConnect / Reown AppKit and send a donation directly on-chain to
the site owner's wallets. The plugin takes a disclosed **1% platform fee** in the
same flow — **without any smart contract**.

This file is for developers building, testing, and shipping the plugin. End-user
docs live in `readme.txt`.

---

## How the 1% works without a smart contract

The fee is a second transfer in the same user flow. It is **not** cryptographically
enforced (it cannot be, without a contract). It is the honest default, protected by
convenience, the hardcoded treasury constants, and an integrity check — not by math.

| Chain | Mechanism | Signatures |
| ----- | --------- | ---------- |
| **Solana** | One `Transaction` with **two `SystemProgram.transfer` instructions** (recipient + treasury). SPL tokens use two `transferChecked` instructions. No program deployed. | 1 |
| **Bitcoin** | The Reown Bitcoin connector sends to one recipient per call, so the donation and the fee go out as **two transactions** (same two-step model as the EVM fallback). A single-signature two-output PSBT is possible via `signPSBT` but needs manual UTXO/fee handling. | 2 |
| **EVM** | If the wallet supports **EIP-5792** (`wallet_sendCalls`), both transfers are **batched into one approval**. Otherwise the engine falls back to **two separate `eth_sendTransaction` calls** (fee first, then donation). | 1 or 2 |

The EVM fallback is detected at runtime via `wallet_getCapabilities`. Hardware
wallets and wallets without atomic-batch support get the two-transaction path.

---

## Architecture

```
cryptostack-donations.php        Bootstrap, constants, activation/deactivation
includes/
  config.php                     Hardcoded treasury + integrity check, chain list,
                                 curated stablecoin allow-list
  class-csd-settings.php         Admin settings page, validation, lock logic, save
  class-csd-render.php           Shortcode + block + classic assets, builds JS config
  class-csd-widget.php           Classic WP_Widget
assets/
  css/donation.css               Frontend widget + modal + admin styles
  js/donation-engine.js          Core: fee split (BigInt), tx building, send routing
  js/admin.js                    Settings lock UX + non-blocking address hints
  src/appkit-bundle.js           SOURCE for the AppKit wrapper (window.CSDAppKit)
blocks/donation/
  block.json                     Block metadata
  index.js                       Block editor registration (no JSX, dynamic block)
build/
  appkit-bundle.js               BUILT bundle (generated — see below)
readme.txt                       WordPress.org listing
uninstall.php                    Removes the options row on delete
package.json / vite.config.js    Build tooling
```

The PHP layer only ever exposes a chain to the front end when the site owner has
configured a recipient address **and** the treasury integrity check passes. The JS
config (`window.CSD_CONFIG`) is printed inline by `class-csd-render.php`.

---

## The built bundle is already included

**You do not need Node, npm, or a build step to install and use this plugin.**
`build/appkit-bundle.js` is already built and committed (it bundles Reown AppKit +
the Solana libraries, with browser polyfills baked in). Just install the plugin zip
in WordPress and it works.

The section below is only for **rebuilding** the bundle (e.g. to bump versions).

### Rebuilding the AppKit bundle (optional)

WordPress.org does not allow loading JavaScript from a CDN, so the Reown AppKit
runtime is bundled locally into `build/appkit-bundle.js`. The human-readable source
is `assets/src/appkit-bundle.js`.

#### Prerequisites

* Node.js 18+ and npm.

#### Steps

```bash
npm install        # installs the exact pinned versions from package.json
npm run build      # regenerates build/appkit-bundle.js
```

`vite.config.js` builds a single self-contained IIFE that assigns
`window.CSDAppKit`, with Buffer/process/global polyfills injected by
`vite-plugin-node-polyfills` so it runs in a plain browser. The donation engine
(`assets/js/donation-engine.js`) is plain, unbundled JS and consumes that global
plus `window.CSD_CONFIG`.

The committed bundle was built with: `@reown/appkit` 1.8.21 (+ matching ethers/
solana/bitcoin adapters), `@solana/web3.js` 1.98.4, `@solana/spl-token` 0.4.14,
`ethers` 6.17.0, Vite 5.4.21. `package.json` pins these so a rebuild reproduces it.

---

## API status (checked against @reown/appkit 1.8.21)

These were confirmed against the installed type definitions and are wired in the
committed bundle:

1. **EVM provider** — `appkit.getProvider('eip155')` returns the EIP-1193 provider;
   the engine drives it with `eth_sendTransaction` / `wallet_sendCalls` /
   `wallet_getCapabilities`. ✔
2. **Account state** — `appkit.getAccount(namespace)` returns
   `{ isConnected, address, ... }`; `subscribeAccount(cb, namespace)` returns an
   unsubscribe function. ✔
3. **Solana** — `appkit.getProvider('solana').signAndSendTransaction(tx)` exists;
   native uses two `SystemProgram.transfer` instructions, SPL uses
   `transferChecked` with on-demand ATA creation. ✔
4. **Bitcoin** — the connector's `sendTransfer({ recipient, amount })` takes a
   **single** recipient (amount in satoshis, string) and returns a txid. The fee is
   therefore a **second** `sendTransfer`. ✔

Still genuinely runtime/wallet-dependent (handled with feature detection + fallback,
but worth watching during live testing):

* Whether a given EVM wallet supports atomic batching (EIP-5792). MetaMask needs the
  EOA→smart-account upgrade (EIP-7702); Ledger/Trezor fall back to two transactions.
* Exact confirmation UX per wallet.

If you bump `@reown/appkit` to a new **major**, re-check items 1–4.

---

## Testing checklist

Test on mainnet with **tiny amounts** first (and/or testnets where practical).

* [ ] Settings: enter one address per family, save, confirm only those chains show.
* [ ] Lock: lock addresses, confirm fields become read-only; unlock requires a save.
* [ ] Integrity: change a treasury constant in `config.php` and confirm the fee is
      disabled (`feeBps` becomes 0) and an admin notice appears — i.e. fail-safe works.
* [ ] EVM (batch): on a wallet supporting EIP-5792, confirm one approval covers both
      transfers and both land on-chain.
* [ ] EVM (fallback): on a wallet without batching, confirm two transactions (fee
      first), both succeed, and the UI reports two transactions.
* [ ] EVM stablecoin: USDC/USDT donation builds a `transfer` (selector `0xa9059cbb`),
      never `approve`.
* [ ] Solana native: one transaction, two `SystemProgram.transfer` instructions.
* [ ] Solana SPL: USDC donation, ATA created if missing, `transferChecked` used.
* [ ] Bitcoin: donation and fee go out as two `sendTransfer` calls (two txids).
* [ ] Fee modes: inclusive (recipient 99%) and on-top (recipient 100%) both compute
      correctly with BigInt (no floating-point drift).
* [ ] Display: block, shortcode, and widget all render and open the same modal.
* [ ] Theme: auto/light/dark render correctly.

---

## WordPress.org submission notes (read before you submit)

These are practical realities, not blockers:

* **GPL + open source.** All hosted code is GPL and fully functional; nothing can be
  premium-locked in the .org version. Anyone can fork the GPL code and delete the fee
  line. The fee is protected by convenience and license terms, **not** technically.
* **Crypto plugins get stricter review.** Expect closer scrutiny and possibly a
  request to make the fee more prominent or **opt-out**. Keep the disclosure in
  `readme.txt` and the settings page. If review requires it, add a clear fee
  acknowledgement on activation and/or a way to set the fee to 0.
* **No CDN JS.** Already handled — everything is bundled into `build/`. Ship the
  source in `assets/src/` for human review.
* **Sanitization/escaping/nonces.** Settings save uses capability checks, a nonce,
  and per-field validation; output is escaped. Re-run Plugin Check before submitting.
* **Trademarks.** "WalletConnect" and "Reown" are third-party marks — describe
  compatibility, don't imply endorsement.

### Recommended monetization (sustainable)

* **Free core** on WordPress.org: 1% default, fully disclosed, all three ecosystems.
* **Pro** sold off-site with real added value: analytics/dashboard, multiple
  campaigns/goals, custom themes, 0% fee, donation receipts / CSV export for taxes,
  more chains and tokens. This is what makes the project durable — the .org listing
  is the funnel, not the product you sell.

---

## Packaging for distribution

Ship a zip whose root is `cryptostack-donations/` containing the PHP, `assets/`
(including `assets/src/`), `blocks/`, **and the built `build/appkit-bundle.js`**, plus
`readme.txt` and `uninstall.php`. Exclude `node_modules/`, `package-lock.json`, and
any local tooling. The plugin must work on a fresh install with no build step on the
user's side, which is why `build/` is included.

---

## Security model (summary)

* **Treasury (the maintainer's 1%)** is hardcoded in `config.php` and protected by a
  SHA-256 fingerprint. Tampering disables the fee (fail-safe) rather than redirecting it.
* **Recipient addresses** live in the options table and are validated on save; they
  are necessarily printed to the front end (required to build the transaction).
* **Lock-on-save.** Every time the owner clicks *Save settings*, the wallet
  addresses are automatically locked (whenever at least one is set). Editing them
  again requires an explicit *Unlock wallet addresses* click (which asks for
  confirmation). This makes the safe state the default with zero extra effort.
* **Uninstall wipes wallets.** Deleting the plugin runs `uninstall.php`, which
  removes the options row (and therefore the stored wallet addresses) on the site —
  and on every site in a multisite network. Deactivation alone keeps the data.
* **Lock** prevents edits below full-admin level; it is not, and cannot be,
  protection against someone with full admin/server access — true of every plugin.
* **Anti-scam** is structural: only native transfers and `transfer` of curated
  stablecoins are ever built. No `approve`, no arbitrary contract calls, no unknown
  tokens. The donor reviews and signs everything.
