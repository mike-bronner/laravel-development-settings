# PHP

@php
/** @var \Laravel\Boost\Install\GuidelineAssist $assist */
@endphp
- Always use curly braces for control structures, even for single-line bodies.
- Use PHP 8 constructor property promotion: `public function __construct(public GitHub $github) { }`. Do not leave empty zero-parameter `__construct()` methods unless the constructor is private.
- Use explicit return type declarations and type hints for all method parameters: `function isAccessible(User $user, ?string $path = null): bool`
@if(empty($assist->enums()) || !preg_match('/^\s*case\s+[A-Z][A-Z0-9_]{2,}/m', $assist->enumContents()))
- Use TitleCase for Enum keys: `FavoritePerson`, `BestLake`, `Monthly`.
@else
- Follow existing application Enum naming conventions.
@endif
- No comments or docblocks unless explicitly requested. Exception: test section markers.
