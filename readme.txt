=== CryptoStack Donations ===
Contributors: axndata
Tags: crypto donations, bitcoin, ethereum, solana, walletconnect
Requires at least: 6.4
Tested up to: 7.0
Requires PHP: 7.4
Stable tag: 0.1.0
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Accept Bitcoin, Ethereum/EVM and Solana donations with one WalletConnect button. Non-custodial, no smart contracts. Block, shortcode & widget.

== Description ==

CryptoStack Donations lets your visitors donate cryptocurrency directly to **your own wallets** with a single, modern "Connect wallet" button. It supports three ecosystems out of the box:

* **Bitcoin** (Native SegWit)
* **Ethereum and EVM chains** — Ethereum, Polygon, Base, BNB Smart Chain
* **Solana**

Donations go **straight on-chain to the addresses you configure**. The plugin is **non-custodial**: it never holds, forwards, or has access to funds. There is no account to create, no KYC, and no third-party payment processor.

= Why site owners choose it =

* **One button, many chains.** Donors pick the network and asset they already hold.
* **No smart contracts.** Nothing to deploy, audit, or pay gas to publish. Transfers use each chain's native capabilities.
* **You keep custody.** Funds move from the donor's wallet directly to yours.
* **Anti-scam by design.** The plugin only ever builds native transfers or transfers of a small, curated list of well-known stablecoins (USDC/USDT). It never calls token `approve`, never interacts with arbitrary contracts, and never accepts unknown/spam tokens. Donors always review every transaction in their own wallet before signing.
* **Lockable settings.** Once your wallet addresses are set, lock them to guard against accidental or unauthorized edits.
* **Display it anywhere.** Gutenberg block, `[crypto_donate]` shortcode, or classic sidebar widget.
* **Light/dark/auto theme** to match your site.

= How donors pay =

The donor connects any WalletConnect-compatible wallet (via Reown AppKit), chooses a network, enters an amount in that asset's own units (for example BTC, ETH, SOL or USDC), and confirms in their wallet. Depending on chain and wallet support, the donation and the platform fee are sent either in a single batched transaction or as two clearly labelled transactions.

= Platform fee (please read) =

This plugin is free and always will be. There is no paid version, no "Pro" edition, no license key, no trial period, and no account to register. Every feature the plugin has is available to every user, permanently.

It is funded by a **1% platform fee** on donations, in the same way a payment processor keeps a percentage of what it moves. The fee is sent on-chain, in the same flow as the donation, to the maintainer's wallets (they are listed in plain sight in `includes/config.php`). You choose how it applies:

* **Inclusive** — the fee is taken from the donation amount (the recipient receives 99%).
* **On top** — the donor pays an extra 1% so the recipient receives 100%.

Nothing is hidden and nothing is locked. The fee is disclosed here, on the plugin's settings screen, and — most importantly — the donor sees every single receiving address in their own wallet before they sign anything. If you would rather not have the fee, the plugin is GPL: you are free to fork it and change it. Nothing in the code stops you.

= WalletConnect / Reown Project ID =

Wallet connections use the WalletConnect protocol through Reown AppKit. You need a free Project ID from the Reown dashboard (dashboard.reown.com). Paste it into the plugin settings — it takes a minute and costs nothing.

= Important notices =

This plugin is a tool for sending cryptocurrency transactions. It is provided "as is", without warranty. Accepting cryptocurrency donations may carry tax, accounting, and regulatory obligations that differ by country and situation. You are responsible for complying with the laws that apply to you. This plugin is not financial, legal, or tax advice. Always test with a small amount first.

== Installation ==

1. Upload the plugin to `/wp-content/plugins/cryptostack-donations`, or install it from the Plugins screen in your WordPress dashboard.
2. Activate it through the **Plugins** menu.
3. Go to **Settings → CryptoStack Donations**.
4. Paste your free Reown **Project ID**.
5. Enter the wallet address(es) you want to receive donations. A chain only appears to donors if you provide an address for it.
6. (Optional) Enable the stablecoins you want to accept, choose a button label, theme, and fee mode.
7. Click **Save settings**. Your wallet addresses are then locked automatically to protect them. (To change them later, use the **Unlock wallet addresses** button, edit, and save again.)
8. Add the donation button to your site using the **Crypto Donation** block, the `[crypto_donate]` shortcode, or the **Crypto Donation** widget.

== Frequently Asked Questions ==

= Is this custodial? Do you touch the money? =

No. The plugin builds a transaction in the donor's browser and the donor signs it with their own wallet. Funds go directly to the addresses you configured. The maintainer's only involvement is the disclosed 1% fee, which is part of the same on-chain transfer.

= Do I need a smart contract? =

No. On Solana, a single transaction pays both the recipient and the fee natively. On EVM chains, the plugin batches both transfers into one wallet approval when the wallet supports it, and otherwise sends two clearly labelled transactions. On Bitcoin, the donation and the fee are sent as two transactions. No contract is ever deployed on any chain.

= Which wallets work? =

Any wallet compatible with WalletConnect / Reown AppKit for the relevant chain (for example MetaMask, Trust Wallet, Rainbow, Phantom, and others). Hardware wallets work too, though they may require the two-transaction flow on EVM instead of batching.

= Can donors send me random or scam tokens through this? =

No. The donation flow only offers each chain's native coin plus a short, curated allow-list of major stablecoins. It never requests token approvals and never interacts with arbitrary contracts, so it cannot be used to push spam or malicious tokens through your button.

= Can I accept only one chain? =

Yes. Fill in only the addresses you want. If you enter only a Bitcoin address, donors only see Bitcoin. Add more chains at any time.

= How do I stop the addresses from being changed? =

It happens automatically. Every time you click **Save settings**, your wallet addresses are locked. While locked, the address fields cannot be edited until you click **Unlock wallet addresses** (which asks you to confirm). Note that, as with any plugin, this cannot stop someone who already has full administrator or server access.

= What happens to my wallet addresses if I delete the plugin? =

They are removed. Deleting (uninstalling) the plugin clears its stored settings, including your wallet addresses, from the site. Simply deactivating the plugin keeps them so you can reactivate later.

= Is there a paid or Pro version? =

No, and there never will be. This is the whole plugin. Nothing is held back, nothing is locked, and no feature requires a payment or an upgrade to use. The 1% fee is simply how the free plugin is paid for.

= Can I remove the 1% fee? =

Not from the settings — it is how development is funded, and it applies to everyone equally. But the plugin is licensed GPLv2 or later, so you are entirely free to fork the code and modify it as you see fit. There is no license check, no phone-home, and no mechanism that disables the plugin if you do.

== External services ==

This plugin connects to third-party services to make wallet connections and on-chain transfers work. Nothing is sent until a visitor actively chooses to connect a wallet or send a donation. The plugin performs no background tracking and sends no data to the plugin developer.

**WalletConnect / Reown (Reown AppKit)**
Wallet connectivity is provided by the WalletConnect protocol through Reown AppKit. When a visitor clicks "Connect wallet", and while a wallet session is active, the bundled AppKit code contacts Reown / WalletConnect endpoints — including relay.walletconnect.org, pulse.walletconnect.org, api.web3modal.org, rpc.walletconnect.org, verify.walletconnect.com, verify.walletconnect.org, echo.walletconnect.com, secure.walletconnect.org and fonts.reown.com — to establish and maintain the encrypted wallet session, resolve wallet metadata, load the modal's interface assets, and route blockchain RPC requests. The data involved includes your WalletConnect Project ID, the selected chain, wallet/session metadata and, once connected, the visitor's public wallet address and the transaction they approve. This is required to connect wallets and cannot be performed locally by the plugin.
Terms of service: https://reown.com/terms-of-service
Privacy policy: https://reown.com/privacy-policy

Note: the bundled AppKit library also contains static connection metadata (RPC and block-explorer URLs) for many other blockchain networks. Only the networks listed in this readme are enabled by this plugin, so those other endpoints are never used or contacted.

**Coinbase Wallet SDK (only if a visitor selects Coinbase Wallet)**
If a visitor connects using Coinbase Wallet, the Coinbase Wallet SDK bundled inside Reown AppKit may contact Coinbase endpoints (for example *.cbhq.net, cca-lite.coinbase.com or keys.coinbase.com) as part of that wallet's own connection and analytics. This happens only when the visitor chooses Coinbase Wallet.
Terms of service: https://www.coinbase.com/legal/user_agreement
Privacy policy: https://www.coinbase.com/legal/privacy

**Solana RPC (Solana donations only)**
For donations on Solana, the bundled code talks to a public Solana JSON-RPC endpoint (by default api.mainnet-beta.solana.com, operated by the Solana Foundation) to read a recent blockhash and broadcast the signed transaction. The data sent is the transaction and related public on-chain information, and only when a visitor sends a Solana donation.
Terms of service: https://solana.com/tos
Privacy policy: https://solana.com/privacy-policy

**Public blockchain networks**
Completing any donation broadcasts a signed transaction to the relevant public blockchain (Bitcoin, an EVM network such as Ethereum, Polygon, Base or BNB Smart Chain, or Solana). On-chain transactions are public by nature. This is inherent to sending cryptocurrency and is not optional once a donor confirms a transfer.

== Screenshots ==

1. The inline donation widget in light and dark themes.

== Changelog ==

= 0.1.0 =
* Initial release: Bitcoin, EVM (Ethereum, Polygon, Base, BNB) and Solana donations via WalletConnect / Reown AppKit. Block, shortcode and widget. Curated stablecoin allow-list, address locking, light/dark themes. Donation amounts are entered in each asset's native units. Configurable platform fee mode (inclusive / on top).

== Upgrade Notice ==

= 0.1.0 =
First public release.
