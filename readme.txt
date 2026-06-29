=== CryptoStack Donations - Bitcoin, Ethereum & Solana Donate Button (WalletConnect) ===
Contributors: finland93
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

The donor connects any WalletConnect-compatible wallet (via Reown AppKit), chooses a network and amount, and confirms in their wallet. Depending on chain and wallet support, the donation and the platform fee are sent either in a single batched transaction or as two clearly labelled transactions.

= Platform fee (please read) =

This plugin includes a **1% platform fee** that helps fund ongoing development. It is sent on-chain, in the same flow as the donation, to the plugin maintainer's wallets. You choose how it is applied:

* **Inclusive** — the fee is taken from the donation amount (the recipient receives 99%).
* **On top** — the donor pays an extra 1% so the recipient receives 100%.

The fee is always visible to the donor in their wallet before they sign, because they can see exactly which addresses receive funds. Nothing is hidden. This fee and its destination are disclosed here and in the plugin settings.

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

= Can I remove the 1% fee? =

The fee funds development of the free plugin. You can choose whether it comes out of the donation (inclusive) or is added on top so your cause still receives 100%. A separate Pro version with a 0% fee and additional features may be offered outside the WordPress.org directory.

== Screenshots ==

1. The inline donation widget in light and dark themes.

== Changelog ==

= 0.1.0 =
* Initial release: Bitcoin, EVM (Ethereum, Polygon, Base, BNB) and Solana donations via WalletConnect / Reown AppKit. Block, shortcode and widget. Curated stablecoin allow-list, address locking, light/dark themes, and configurable platform fee mode.

== Upgrade Notice ==

= 0.1.0 =
First public release.
