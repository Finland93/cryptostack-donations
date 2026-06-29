/**
 * Vite build config for CryptoStack Donations.
 *
 * Bundles assets/src/appkit-bundle.js (which imports Reown AppKit and the Solana
 * libraries) into a single self-contained IIFE at build/appkit-bundle.js. The
 * source assigns window.CSDAppKit, which the donation engine consumes.
 *
 * The Solana / WalletConnect dependency tree expects Node globals (Buffer,
 * process, global). vite-plugin-node-polyfills injects browser-safe shims so the
 * bundle runs in a plain browser with no further setup.
 *
 * WordPress.org requires plugins to load their own JS (no remote CDN), so this
 * built file is committed to the repo and shipped inside the plugin zip. The
 * human-readable source stays in assets/src/ for review.
 *
 * Usage:
 *   npm install
 *   npm run build
 */
import { defineConfig } from 'vite';
import { nodePolyfills } from 'vite-plugin-node-polyfills';

export default defineConfig( {
	define: {
		'process.env.NODE_ENV': '"production"',
	},
	plugins: [
		nodePolyfills( {
			// Make Buffer, process and global available as browser shims.
			globals: { Buffer: true, global: true, process: true },
			protocolImports: true,
		} ),
	],
	build: {
		outDir: 'build',
		emptyOutDir: true,
		target: 'es2020',
		minify: true,
		lib: {
			entry: 'assets/src/appkit-bundle.js',
			name: 'CSDAppKitBundle',
			formats: [ 'iife' ],
			fileName: () => 'appkit-bundle.js',
		},
		rollupOptions: {
			output: {
				inlineDynamicImports: true,
				entryFileNames: 'appkit-bundle.js',
			},
		},
	},
} );
