# Security Policy

## Reporting a vulnerability

If you discover a security issue, **please do not open a public issue**. Instead,
report it privately:

- Use GitHub's [private security advisories](https://github.com/Finland93/cryptostack-donations/security/advisories/new), or
- Contact [@Finland93](https://github.com/Finland93) directly.

Please include steps to reproduce and the affected version. You'll get an
acknowledgement as soon as possible, and credit in the changelog if you'd like.

## Scope notes

- The plugin is **non-custodial**: it builds transactions the donor signs in their
  own wallet. It never has access to funds or private keys.
- The platform fee wallets are hardcoded and protected by an integrity check;
  tampering disables the fee rather than redirecting it.
- Recipient wallet addresses are stored in the site options table and printed to
  the page (required to build the transaction). Locking guards against edits below
  full-admin level, but — as with any plugin — cannot stop someone with full
  administrator or server access.

## Supported versions

Only the latest released version receives security updates.
