# Security

## Reporting a vulnerability

Please don't open a public issue. Report it privately on GitHub instead:
**Security > Report a vulnerability** on this repository, or
[this link](https://github.com/mahoudeau/universal-shipping-for-sylius/security/advisories/new).
Only the maintainer sees it.

Say what an attacker can do, on which version, and how to reproduce it. A
proof of concept against a local shop is welcome; please never test against a
shop you don't run.

You get an answer within a week. A confirmed issue is fixed in a patch release
first, then published as a GitHub security advisory crediting you, unless you'd
rather not be named.

## Supported versions

The plugin is still 0.x: only the latest release gets fixes. Upgrading within
0.x is described in the [CHANGELOG](CHANGELOG.md).

## What this plugin exposes

Worth knowing when you review a shop that runs it:

- **The Sendcloud webhook** (`/universal-shipping/webhooks/sendcloud`) is
  public by design. Every call must carry a valid signature made with your
  Sendcloud secret key, or it is refused.
- **The address suggestion route** (`/universal-shipping/address/suggest`) is
  public and asks the address provider on the server's behalf. Answers are
  cached, but put a rate limiter in front of it if you expect abuse.
- **Label actions** sit under the admin prefix, behind the admin firewall, and
  each one checks a CSRF token.
- **The pickup point** a customer sends back is only an id from the list the
  server built. An address posted by the browser is never trusted.
