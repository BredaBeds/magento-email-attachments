# REFACTOR.md — BredaBeds_EmailAttachments

## How to use this document

This is a **refactor brief** for the `BredaBeds_EmailAttachments` module. It tells you (a future
developer or AI assistant with none of the original context) **why** this module needs cleanup,
**what** to fix, and **how** to do it safely without changing behavior. It is **not** a line-by-line
spec.

It is a companion to the `REWRITE.md` briefs in the four rewrite-grade modules. **This module is in
the refactor tier, but it is security-sensitive (it overrides core mail) — the security fix comes
first.**

## Background: why this refactor exists

In mid-2026 we audited every `app/code/BredaBeds` module for "overhaul need." **EmailAttachments scored
5/10**, and a separate security review flagged its core-mail override.

Context: **much of this code was written by non-developers or junior developers new to PHP and
Magento 2, and ported from Magento 1.** The module's central feature is a fragile override of Magento's
mail `TransportBuilder`. Because it affects **all outbound email** (a global `<preference>`) and
handles files, get the security fix in first and keep behavior intact.

## What the module does today (behavior to preserve)

Overrides the core mail `TransportBuilder` (a global DI `<preference>`) to attach generated PDFs —
built via a Browserless headless-browser service — to outbound email (e.g. order confirmations). The
emails and their attachments must keep arriving the same way after the refactor.

## Security finding to fix FIRST (from the security review)

> The override does `file_get_contents()` on an attachment path taken from a mail template variable
> with **no allow-list or base-directory restriction** — a path-traversal / arbitrary-file-read
> pattern across *all* outbound mail. **Constrain attachment paths to an allow-listed directory**
> (e.g. resolve against a known `var/...` path, `realpath()`-check containment, reject anything else)
> as the first change.

## What's wrong with it today (the problems we're fixing)

1. **Fragile core override.** `Model/Mail/Template/TransportBuilder.php` subclasses core
   `TransportBuilder` and reaches into its **protected** `$this->templateVars` / `$this->message`, so
   it silently breaks on Magento upgrades. Prefer composition / a supported extension point over
   reaching into protected internals.
2. **Insecure/brittle Browserless call.** `Helper/Browserless.php` calls the service over **`http://`**
   (use `https`) and **swallows all exceptions, returning `false`** — failures vanish.
3. **Empty catch.** `Console/SendOrderConfirmation.php` has an empty `catch {}`.

## How we'll fix it

- **Add the attachment-path allow-list first** (security).
- **Rework the TransportBuilder integration** so it does not depend on core's protected state — use a
  supported extension mechanism, or compose around the builder, so a Magento upgrade can't quietly
  break email.
- **Use `https` for Browserless** and replace the swallow-all with real error handling that surfaces
  failures (a missing/failed PDF should be visible, not silent).
- **Replace the empty `catch {}`** in the console command with real handling.
- Apply the baseline where you touch code: `declare(strict_types=1)`, full types, short array syntax,
  PSR-12 (see the repo `CLAUDE.md`).

## How we'll do it safely

- **Security fix first**, in its own small change.
- **Test outbound mail end-to-end** after each step — order-confirmation email plus PDF attachment
  must still send, and a PDF failure must now surface rather than silently dropping the attachment.
- Because the override is global (affects all mail), be especially careful and verify nothing else
  regresses in email sending.
- Keep each change independently revertible; lean on the project's rebuild/backup tooling.

## Definition of done

- [ ] Attachment paths are restricted to an allow-listed directory; no arbitrary file read.
- [ ] The mail integration no longer reaches into core's protected state; it survives a Magento
      upgrade.
- [ ] Browserless is called over `https`; failures surface instead of being swallowed.
- [ ] No empty `catch {}` in the console command.
- [ ] `declare(strict_types=1)`, full types, short array syntax, PSR-12 on touched files.
- [ ] Outbound email and PDF attachments still send exactly as before.
