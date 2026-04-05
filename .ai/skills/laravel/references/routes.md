# Routes

## Do

- Always use resource routes that point to restful controllers.
- For special action routes, use invokable controllers, this should be very rare.
- Only associate routes with a single model.
- Name the controller and route after the model they act on.

## Do Not

- Use closures in routes, as they cannot be cached in `php artisan route:cache`.
- Create routes that do not relate to models.

## Types

### API

- API routes should be within an `API` route namespace.

### View

- Should have no namespace/prefix (as the API routes do).
- Controllers should only be responsible for a single model, but responsible for all views that pertain to that model, for example:

```
/resources/views/reports/index.blade.php
/resources/views/reports/create.blade.php
/resources/views/reports/show.blade.php
```
