# Classes

## Use Statements

### Sort Alphabetically

- Should be ordered alphabetically.

**Why:** Easier to parse, especially with many entries (mental debt).

### No Unused Entries

- Should not include unused entries.

**Why:**

- Less code is the best code (mental debt).
- Unused code is useless (no dead code).

## Contracts (Interfaces)

Contracts aim to loosen coupling of objects in an application. It is important to remember that coupling is shifted from concrete implementations to abstract contracts (which have no logic, but only specify method interfaces).

However, contracts are not always needed, and should be used only where actually useful, like instantiation of objects through dependency injection, or code used by others, especially packages. This lets us easily switch out implementations, especially in third-party-packages that might require some customization.

**Why:** Provides loose coupling (SOLID).

## No Statics

Avoid static classes. Classes are intended to be instantiated and be identifiable. Static classes do not have an identity and are not true objects, thus are a stow-away from the procedural era.

Further, static classes and methods are little more than modern equivalents of `GOTO` statements, procedural in nature. OOP goes beyond that, the object is the defining principle, not the code. Static classes and methods are a crutch used to think procedurally under the guise of seeming object-oriented.

**Why:**

- There is virtually no overhead in instantiating a class (pointless optimization).
- Classes can be expected to behave in the same manner (mental debt).
- Objects are inherently easier to test and inspect than static classes (testability).

## Class Naming

All classes should be suffixed based on the base folder within the app folder, with exception of the following:

- `app/Models`: no suffix
- `app/Http`: file suffix is based on the folder within `app/Http`
- `app/Livewire`: no suffix
- `app/Livewire/Forms`: Form suffix, only Livewire Form classes here

## Constructors

### Primary + Named Constructors

Use one primary constructor, along with multiple secondary (named) constructors that all make use of the primary constructor.

**Why:** Accommodate different scenarios without repeating code (DRY).

### No Logic

Constructors should not include any functionality or logic, but merely assign values to object properties. If logic needs to be performed, this is an indication that the information passed in should actually be another object. The reason behind this is that any code in the constructor will be parsed every time an object is created, regardless if it is necessary or not. That can't be optimized. Instead if only assignments are handled in constructors, optimization can be controlled, and only the necessary code performed.

**Why:** Prevents optimization (technical debt).

### Property Promotion

Use [property promotion](https://wiki.php.net/rfc/constructor_promotion) in constructors, avoid defining class properties outside of the constructor.

**Why:**

- Reduces lines of code (mental debt).
- Defines the parameters where they are introduced, makes it easier to parse code (mental debt).

## Properties

### Are Required

Avoid classes that do not encapsulate any data. Classes without properties have no state and no identity, and are analogous to procedural, non-object-oriented code.

**Why:** Classes represent concepts, all concepts have properties, this is an expectation that we have of things (mental debt).

## Methods

### Naming

From Robert Martin's Clean Code:

> "Methods should have a verb or verb-phrase names like postPayment, deletePage, or save."

- Name methods according to what they do or return. Their names should be self-documenting.
- Methods that perform an action should be a verb, and not return anything.
- Methods that return objects should be nouns and named after the object they return (they can be prefixed with adjectives that help better describe the object being returned). In Models this should always be attributes.
- Methods should read as an action being taken on the class.

**Why:**

- Set expectations as to what they do (mental debt, naming).
- Methods transform a class instance, which is indicated by the action taken, which is expressed using verbs or verb-phrases (naming).

### Declared Parameters

Methods should have a declared parameter list, and not use a dynamic one (exceptions are magic methods).

**Why:** Helps maintainability and readability (mental debt).

### Type Hints

Methods should have type-hinted parameters as well as a return type.

**Why:**

- Helps maintainability and readability, as well as is self-documenting (mental debt).
- Traps logic errors close to the source (technical debt).

### No Null Arguments

When calling methods with optional parameters, don't pass null into the methods, use named parameters instead.

**Why:**

- Helps maintainability and readability (mental debt).
- Reduces code (no dead code).

## Dependency Injection

Where possible, classes should be injected via the constructor, allowing resolution through Inversion of Control (IoC) and avoiding tight coupling between classes.

**Why:**

- Provides loose coupling between classes (SOLID).
- Creates easier-to-maintain code as we can decide which instance to provide when the class is instantiated (tech debt).
- Allows automatic resolution through Inversion of Control if we don't provide an instance (SOLID).

## Introspection / Type Casting

Avoid introspection (checking the type of the class to determine the outcome of a condition), for example using methods like `instanceof`. This creates tight coupling, and introduces technical debt, as the object type should already be defined in the method parameter or class property. If you have loosely coupled code, but use introspection, you are introducing another point of failure: what happens when the item you are inspecting is not of the expected type, but adheres to the correct interface?

If you find that you are reaching for introspection, it probably means that logic should be encapsulated or refactored, likely resulting in rearranging or creating classes.

**Why:** Causes brittle code (tech debt).
