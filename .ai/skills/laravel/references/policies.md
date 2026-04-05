# Policies

## Secure Front- and Back-Ends

- Checks should be implemented on the frontend to prevent displaying of unwanted elements.
- Checks should be implemented on the backend to prevent execution of unwanted code, in the event
  front-end restrictions are being circumvented.

## Policy Authorization Pattern

Always authorize in both the controller and Blade/Livewire layer:

```php
// Controller — gate check via Form Request or Policy
public function update(UpdateLeadRequest $request, Lead $lead): JsonResponse
{
    $lead->updateFromRequest(request: $request);

    return response()->json(data: new LeadResource(resource: $lead));
}

// Form Request — authorize via Policy
public function authorize(): bool
{
    return $this->user()->can(abilities: "update", arguments: $this->route(param: "lead"));
}
```

```blade
{{-- Blade — hide elements the user can't act on --}}
@can("update", $lead)
    <button wire:click="edit">Edit</button>
@endcan
```

## Rules

- One Policy per model. Place in `app/Policies/`.
- Register policies explicitly in `AuthServiceProvider` if auto-discovery doesn't apply.
- Never rely solely on middleware for resource-level authorization — middleware checks roles,
  Policies check ownership and resource-level permissions.
- Use `Gate::authorize()` or `$this->authorize()` in controllers for non-CRUD actions.
