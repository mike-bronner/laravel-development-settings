# Livewire

## Components

- Each Livewire component must have a single root element (usually a div) with no Livewire, Blade, or Alpine attributes.
- Add a unique `wire:key` to each component:

```blade
<mycomponent
    wire:key="my-key"
/>
```

- Livewire components that are in loops or when multiple components are adjacent, wrap each component in a `<template>` tag that has the same `wire:key` attribute as the component:

```blade
<template
    wire:key="my-unique-key"
>
    <mycomponent
        wire:key="my-key"
    />
</template>
```
