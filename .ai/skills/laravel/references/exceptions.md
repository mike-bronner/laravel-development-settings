# Exceptions

- Catch errors and exceptions as soon as possible. Use type hinting and return types as one aspect toward achieving this.
- Create custom exceptions as much as possible, especially when catching exceptions for special handling.
- Always use `Throwable` for type-hinting exceptions, most often when catching.
- If the `$exception` is not used in the try-catch block, simply type hint like so:

```php
try {
    //
} catch (Throwable) {
    //
}
```
