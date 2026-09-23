---
name: a11y
description: >
  Accessibility testing and WCAG compliance for Laravel Livewire + Blade applications. Use when building
  or reviewing Blade templates, Livewire components, forms, navigation, modals, or any user-facing HTML.
  Also trigger when the user mentions WCAG, screen readers, keyboard navigation, a11y, or accessible UI.
  Invoke proactively when creating any new user-facing component — accessibility should be built in from
  the start, not bolted on after. If a Blade or Livewire file is being created or significantly modified,
  this skill applies.
---

# Accessibility Tester — Livewire + Blade

For the PHP portion of Livewire components, also invoke the `laravel` skill to ensure code style compliance.

WCAG 2.1 AA compliance for Laravel applications using Livewire and Blade templates.

## Livewire-Specific Concerns

Livewire's dynamic DOM updates can break accessibility if not handled correctly:

### Live Region Announcements
When Livewire updates content dynamically, screen readers don't know something changed. Use ARIA live
regions to announce updates:

```blade
{{-- Announce validation errors as they appear --}}
<div aria-live="polite" aria-atomic="true">
    @error("email")
        <span role="alert">{{ $message }}</span>
    @enderror
</div>
```

### Loading States
Livewire loading states must be announced to screen readers:

```blade
<button wire:click="save" wire:loading.attr="disabled">
    <span wire:loading.remove>Save</span>
    <span wire:loading aria-live="assertive">Saving...</span>
</button>
```

### Wire:navigate
When using `wire:navigate` for SPA-like navigation, ensure:
- Page title updates on navigation (`<title>` in layout)
- Focus moves to main content after navigation
- Browser back/forward works correctly

## Blade Template Patterns

### Forms
```blade
{{-- Always associate labels with inputs --}}
<label for="email">Email Address</label>
<input
    id="email"
    name="email"
    type="email"
    required
    aria-describedby="email-help email-error"
>
<p id="email-help">We'll never share your email.</p>

@error("email")
    <p id="email-error" role="alert">{{ $message }}</p>
@enderror
```

### Navigation
```blade
<nav aria-label="Main navigation">
    <ul role="list">
        <li><a href="{{ route("dashboard") }}" @if(request()->routeIs("dashboard")) aria-current="page" @endif>Dashboard</a></li>
    </ul>
</nav>
```

### Modals (Alpine.js)
```blade
<div
    x-data="{ open: false }"
    @keydown.escape="open = false"
>
    <button @click="open = true">Open Settings</button>

    <div
        x-show="open"
        x-trap.noscroll="open"
        role="dialog"
        aria-modal="true"
        aria-labelledby="modal-title"
    >
        <h2 id="modal-title">Settings</h2>
        {{-- Modal content --}}
        <button @click="open = false">Close</button>
    </div>
</div>
```

## WCAG 2.1 AA Quick Reference

### Perceivable
- **Color contrast**: 4.5:1 for normal text, 3:1 for large text
- **Text alternatives**: all `<img>` tags need `alt` attributes
- **No color-only indicators**: don't rely solely on color to convey meaning
- **Captions**: video content needs captions

### Operable
- **Keyboard accessible**: everything reachable via Tab/Shift+Tab/Enter/Space/Escape
- **Focus visible**: never `outline: none` without a visible replacement
- **Skip links**: provide "Skip to main content" link
- **No keyboard traps**: user can always Tab out of any component

### Understandable
- **Language**: `<html lang="en">` on every page
- **Error identification**: form errors identify the field and describe the error
- **Consistent navigation**: same nav order across pages

### Robust
- **Valid HTML**: close all tags, unique IDs
- **ARIA when needed**: use native HTML elements first (`<button>` not `<div onclick>`)
- **Name, Role, Value**: custom widgets expose their state to assistive tech

## Testing Approach

1. **Keyboard-only navigation**: Tab through every interactive element. Can you reach and activate everything?
2. **Screen reader spot-check**: test key flows with VoiceOver (macOS) — forms, navigation, dynamic content
3. **Contrast check**: use browser DevTools accessibility panel or axe extension
4. **Automated scan**: run axe-core or Lighthouse accessibility audit
5. **Focus order**: verify focus moves logically through the page

## Common Failures in Livewire Apps

| Issue | Fix |
|-------|-----|
| Clickable `<div>` without role | Use `<button>` or add `role="button" tabindex="0"` + keyboard handler |
| Form without labels | Add `<label for="id">` for every input |
| Alpine modal without focus trap | Add `x-trap` directive |
| Dynamic content update not announced | Add `aria-live="polite"` region |
| Missing skip navigation | Add hidden skip link before main nav |
| Icons without text alternative | Add `aria-label` or `<span class="sr-only">` |
