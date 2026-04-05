# Models

## Eager Loading

- Avoid eager loading relationships in the `protected $with = [];` variable as this could lead to data bloat.
- Try to explicitly load relationships at the point they are used using the `with()` method on the eloquent query, instead of using the `load()` method later in the code.

## Organization

1. List traits, in alphabetical order, only one trait per line.
2. List the public, protected, and private properties, each group in alphabetical order.
3. List relationship methods in alphabetical order.
4. List attribute methods in alphabetical order.
5. List any other methods in alphabetical order.

## Persistence Methods (Repository Pattern)

- Laravel models are the de-facto persistence repository, especially in eloquent. Do not create repository classes.
- Do not use the generic eloquent CRUD methods `save()`, `update()`, `create()`, `delete()`, etc. outside of the model. Instead create descriptive methods that explain exactly what is happening. This decouples the business domain from the persistence domain of the app, as well as makes code so much more humanly readable. For instance: instead of `$user->save()` create a method that handles a specific situation, like `$agent->addListingInfo($listingInfo);` and then handle all the data parsing and assignment in the method, at the end of which `$this->save()` is called to persist the changes.
- This pattern is not a Repository pattern, but instead an adaptation thereof for Laravel models. Laravel models already implement the repository pattern in how they are built on top of Eloquent, so splitting out each model into multiple single-use-traits is an effort to maintain organization, while the other rules enforce the repository pattern of the model throughout the code-base.
- The benefits of this guide go deep beneath the surface, and at first glance may not be apparent:
    - Centralized place of maintenance in the attribute and query traits.
    - Deep performance optimization via centralized caching in the traits. This automatically ensures that relationship references are cached as well, for example when looping over a relationship collection.
    - Reduced technical debt, as queries and custom attributes are all contained in known centralized traits. This makes maintenance much easier when fixing or optimizing queries, as it is no longer necessary to inspect your entire codebase.
    - Reduced visual debt in models, as the custom parts are separated out into traits, keeping the model itself lean and to the point.
    - Adoption of better patterns: separating and centralizing queries helps DRY up your code, as well as force you to think more about how each query works and discover areas it can be optimized, by pulling it out of context, and looking at it on its own, without being distracted by the domain logic surrounding it.

## Structure

- All attribute methods should be extracted to an Attributes trait.
- All query methods should be extracted to traits:

```
App
|-Concerns
| |-Attributes
| | \-Book
| |
| \-Queries
|   \-Book
|
|-BaseModel
\-Book
```

```php
<?php

declare(strict_types=1);

namespace App;

use Illuminate\Database\Eloquent\Model;
use Laravel\Scout\Searchable;

abstract class BaseModel extends Model
{
    use Searchable;
}
```

```php
<?php

declare(strict_types=1);

namespace App;

use App\Concerns\Attributes\Contact as Attributes;
use App\Concerns\Queries\Contact as Queries;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class Contact extends BaseModel
{
    use Attributes;
    use Queries;

    protected $appends = [
        "searchKey",
        "searchUrl",
    ];
    protected $fillable = [
        "mobile_phone",
        "name",
        "private_email",
        "title",
        "work_email",
        "work_phone",
    ];

    public function contactTypes(): BelongsToMany
    {
        return $this->belongsToMany(related: ContactType::class);
    }
}
```

```php
<?php

declare(strict_types=1);

namespace App\Concerns\Attributes;

use Illuminate\Database\Eloquent\Casts\Attribute;

trait Contact
{
    protected function gravatar(): Attribute
    {
        return Attribute::make(
            get: function (): string {
                $defaultAvatar = "https://www.gravatar.com/avatar/?d=mm";
                $workHash = md5(value: strtolower(string: trim(string: data_get(target: $this, key: "work_email", default: ""))));
                $privateHash = md5(value: strtolower(string: trim(string: data_get(target: $this, key: "private_email", default: ""))));
                $workAvatar = "https://www.gravatar.com/avatar/{$workHash}?d=mm";
                $privateAvatar = "https://www.gravatar.com/avatar/{$privateHash}?d=mm";

                if (md5(value: file_get_contents(filename: $workAvatar)) !== md5(value: file_get_contents(filename: $defaultAvatar))) {
                    return $workAvatar;
                }

                if (md5(value: file_get_contents(filename: $privateAvatar)) !== md5(value: file_get_contents(filename: $defaultAvatar))) {
                    return $privateAvatar;
                }

                return $defaultAvatar;
            },
        );
    }

    protected function searchKey(): Attribute
    {
        return Attribute::make(
            get: fn (): string => data_get(target: $this, key: "name", default: ""),
        );
    }

    protected function searchUrl(): Attribute
    {
        return Attribute::make(
            get: fn (): string => route(name: "contacts.show", parameters: $this->getKey()),
        );
    }
}
```

```php
<?php

declare(strict_types=1);

namespace App\Concerns\Queries;

use Illuminate\Support\Collection;

trait Contact
{
    public function getAll(): Collection
    {
        return cache()->tags(tags: [data_get(target: $this, key: "classSlug")])
            ->rememberForever(key: "contact-{$this->id}-getAll", callback: function (): Collection {
                return $this->orderBy(column: "name")->get();
            });
    }

    public function getByTypes(array $types): Collection
    {
        $key = implode(separator: "-", array: $types);

        return cache()->tags(tags: [data_get(target: $this, key: "classSlug"), "contact-type"])
            ->rememberForever(key: "contact-{$this->id}-getByTypes-{$key}", callback: function () use ($types): Collection {
                return $this->with(relations: ["contactTypes" => function ($query) use ($types) {
                        $query->whereIn(column: "title", values: $types);
                    }])
                    ->orderBy(column: "name")
                    ->get();
            });
    }
}
```

## Relationship Properties

- Do not query relationship properties on models directly. Instead expose the relationship property as an attribute of the model itself. This allows you to provide a default if the relationship does not exist, as well as limits interdependence of models to only the models themselves, and not through your code.

For example, instead of `$book->author->name`, create a model attribute called `authorName`, then call that as `$book->authorName` in your code:

```php
<?php

declare(strict_types=1);

namespace App;

use App\Concerns\Attributes\Book as Attributes;
use Illuminate\Database\Eloquent\Relations\HasOne;

class Book extends Model
{
    use Attributes;

    public function author(): HasOne
    {
        return $this->hasOne(related: Author::class);
    }
}
```

```php
<?php

declare(strict_types=1);

namespace App\Concerns\Attributes;

use Illuminate\Database\Eloquent\Casts\Attribute;

trait Book
{
    protected function authorName(): Attribute
    {
        return Attribute::make(
            get: fn (): string => data_get(target: $this->author, key: "name", default: ""),
        );
    }
}
```

## Naming Conventions

### Properties or Methods with Certain Return Types

- Boolean properties should start with `is`, `has`, `should`, etc., indicating a question that can be answered as yes or no.
- Boolean properties or methods that check if a certain condition is true should be named `has<Condition in past tense>`.

### Query Methods

- Methods returning a single instance should be prefixed with `find` followed by the name of the model instance it returns, for example `->findUserByName(string $name)`.
- Methods returning a collection of instances should be prefixed with `get` followed by the name of the model instances it returns, for example `->getUsersByType(string $type)`.

### Attributes

- Use the "new" attribute implementation: https://laravel.com/docs/11.x/eloquent-mutators#defining-an-accessor
- Create attributes to expose properties of related models.
