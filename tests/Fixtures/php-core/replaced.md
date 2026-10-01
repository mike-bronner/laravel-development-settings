# PHP

@php
/** @var \Laravel\Boost\Install\GuidelineAssist $assist */
@endphp
- Always use curly braces for control structures, even for single-line bodies.
@if(empty($assist->enums()))
- Use TitleCase for Enum keys: `FavoritePerson`, `BestLake`, `Monthly`.
@else
- Follow existing application Enum naming conventions.
@endif
- No comments or docblocks unless explicitly requested. Exception: test section markers.
