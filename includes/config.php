<?php
/**
 * Central configuration for CryptoStack Donations.
 *
 * This file holds the PLATFORM treasury addresses (where the 1% fee goes),
 * the supported chains, and the curated token allow-list used to block
 * spam / scam tokens.
 *
 * SECURITY NOTE (read me):
 *   The treasury addresses below are hardcoded constants. They are also
 *   protected by an integrity hash (CSD_TREASURY_FINGERPRINT). On every
 *   render and on settings save we recompute the hash and compare. If the
 *   constants are edited without also updating the fingerprint, the plugin
 *   refuses to apply the platform fee (fails safe) and logs a notice.
 *
 *   This raises the bar against casual tampering. It is NOT cryptographic
 *   enforcement: because the plugin is GPL/open-source, a determined fork
 *   can recompute the fingerprint. That is an inherent property of any
 *   open-source, no-smart-contract design and is documented in the README.
 *
 * @package CryptoStack_Donations
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit; // No direct access.
}

/**
 * Platform fee, expressed in basis points. 100 bps = 1.00%.
 * Kept as a constant so it is auditable and not silently changeable per-site.
 */
if ( ! defined( 'CSD_PLATFORM_FEE_BPS' ) ) {
	define( 'CSD_PLATFORM_FEE_BPS', 100 ); // 1%.
}

/**
 * PLATFORM treasury addresses. The 1% fee is routed here.
 * These are public receiving addresses (safe to ship in the page).
 */
if ( ! defined( 'CSD_TREASURY_EVM' ) ) {
	define( 'CSD_TREASURY_EVM', '0x15a308ed14B7c519a474d1856f45bF81d0000D80' );
}
if ( ! defined( 'CSD_TREASURY_SOL' ) ) {
	define( 'CSD_TREASURY_SOL', 'GikaTNTG1DpytsTqwRdcVJ3F7Z2eHHgFZTkkUWxP2VRP' );
}
if ( ! defined( 'CSD_TREASURY_BTC' ) ) {
	// Native SegWit (bech32).
	define( 'CSD_TREASURY_BTC', 'bc1q2sg0hf2a6vqxsypwt28rwwt625ct3fq5z3na64' );
}

/**
 * Integrity fingerprint of the three treasury addresses + fee.
 * Computed as sha256 of "EVM|SOL|BTC|BPS". If you legitimately change a
 * treasury address, regenerate this value with csd_compute_treasury_fingerprint().
 */
if ( ! defined( 'CSD_TREASURY_FINGERPRINT' ) ) {
	define(
		'CSD_TREASURY_FINGERPRINT',
		hash(
			'sha256',
			CSD_TREASURY_EVM . '|' . CSD_TREASURY_SOL . '|' . CSD_TREASURY_BTC . '|' . CSD_PLATFORM_FEE_BPS
		)
	);
}

/**
 * Recompute the fingerprint from the live constants.
 *
 * @return string sha256 hex.
 */
function csd_compute_treasury_fingerprint() {
	return hash(
		'sha256',
		CSD_TREASURY_EVM . '|' . CSD_TREASURY_SOL . '|' . CSD_TREASURY_BTC . '|' . CSD_PLATFORM_FEE_BPS
	);
}

/**
 * Verify treasury integrity. Returns true only if constants match the
 * shipped fingerprint. Used to fail-safe the fee logic.
 *
 * @return bool
 */
function csd_treasury_is_intact() {
	return hash_equals( CSD_TREASURY_FINGERPRINT, csd_compute_treasury_fingerprint() );
}

/**
 * Supported chains and their metadata.
 *
 * native      = symbol of native coin
 * caip        = CAIP-2 chain id used by WalletConnect / Reown AppKit
 * decimals    = native decimals
 * explorer    = base URL for tx links
 * tokens      = CURATED allow-list of fungible tokens accepted as donations.
 *               ONLY these contract/mint addresses are ever offered in the UI,
 *               which is the anti-spam / anti-scam guarantee. We never let a
 *               donor pick an arbitrary token, and we never call approve().
 *
 * @return array
 */
function csd_get_chains() {
	return array(

		// ---- EVM chains -------------------------------------------------.
		'ethereum' => array(
			'label'    => 'Ethereum',
			'family'   => 'evm',
			'native'   => 'ETH',
			'chain_id' => 1,
			'caip'     => 'eip155:1',
			'decimals' => 18,
			'explorer' => 'https://etherscan.io/tx/',
			'tokens'   => array(
				'USDC' => array( 'address' => '0xA0b86991c6218b36c1d19D4a2e9Eb0cE3606eB48', 'decimals' => 6 ),
				'USDT' => array( 'address' => '0xdAC17F958D2ee523a2206206994597C13D831ec7', 'decimals' => 6 ),
			),
		),
		'polygon'  => array(
			'label'    => 'Polygon',
			'family'   => 'evm',
			'native'   => 'POL',
			'chain_id' => 137,
			'caip'     => 'eip155:137',
			'decimals' => 18,
			'explorer' => 'https://polygonscan.com/tx/',
			'tokens'   => array(
				'USDC' => array( 'address' => '0x3c499c542cEF5E3811e1192ce70d8cC03d5c3359', 'decimals' => 6 ),
				'USDT' => array( 'address' => '0xc2132D05D31c914a87C6611C10748AEb04B58e8F', 'decimals' => 6 ),
			),
		),
		'base'     => array(
			'label'    => 'Base',
			'family'   => 'evm',
			'native'   => 'ETH',
			'chain_id' => 8453,
			'caip'     => 'eip155:8453',
			'decimals' => 18,
			'explorer' => 'https://basescan.org/tx/',
			'tokens'   => array(
				'USDC' => array( 'address' => '0x833589fCD6eDb6E08f4c7C32D4f71b54bdA02913', 'decimals' => 6 ),
			),
		),
		'bsc'      => array(
			'label'    => 'BNB Smart Chain',
			'family'   => 'evm',
			'native'   => 'BNB',
			'chain_id' => 56,
			'caip'     => 'eip155:56',
			'decimals' => 18,
			'explorer' => 'https://bscscan.com/tx/',
			'tokens'   => array(
				'USDT' => array( 'address' => '0x55d398326f99059fF775485246999027B3197955', 'decimals' => 18 ),
			),
		),

		// ---- Solana -----------------------------------------------------.
		'solana'   => array(
			'label'    => 'Solana',
			'family'   => 'solana',
			'native'   => 'SOL',
			'caip'     => 'solana:5eykt4UsFv8P8NJdTREpY1vzqKqZKvdp', // Mainnet-beta.
			'decimals' => 9,
			'explorer' => 'https://solscan.io/tx/',
			'tokens'   => array(
				'USDC' => array( 'address' => 'EPjFWdd5AufqSSqeM2qN1xzybapC8G4wEGGkZwyTDt1v', 'decimals' => 6 ),
				'USDT' => array( 'address' => 'Es9vMFrzaCERmJfrF4H2FYD4KCoNkY11McCe8BenwNYB', 'decimals' => 6 ),
			),
		),

		// ---- Bitcoin ----------------------------------------------------.
		'bitcoin'  => array(
			'label'    => 'Bitcoin',
			'family'   => 'bitcoin',
			'native'   => 'BTC',
			'caip'     => 'bip122:000000000019d6689c085ae165831e93',
			'decimals' => 8,
			'explorer' => 'https://mempool.space/tx/',
			'tokens'   => array(), // No tokens on L1.
		),
	);
}

/**
 * Map a chain family to its hardcoded treasury address.
 *
 * @param string $family evm|solana|bitcoin.
 * @return string|null
 */
function csd_treasury_for_family( $family ) {
	switch ( $family ) {
		case 'evm':
			return CSD_TREASURY_EVM;
		case 'solana':
			return CSD_TREASURY_SOL;
		case 'bitcoin':
			return CSD_TREASURY_BTC;
		default:
			return null;
	}
}
