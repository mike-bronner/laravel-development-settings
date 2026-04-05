# Governing Principles

## Table of Contents

- [Clear Code](#clear-code) — hierarchical code organization (thought → idea → concept → method → class → domain)
- [Naming](#naming) — intention-revealing names, casing conventions
- [Boy Scout Rule](#boy-scout-rule) — leave code cleaner than you found it
- [Debt](#debt) — technical debt and mental debt
- [No Dead Code](#no-dead-code) — no unused or commented code
- [Don't Optimize Early](#dont-optimize-early) — follow standards first, optimize when needed
- [Patterns](#patterns) — MVC, DRY, SOLID, Repository

## Clear Code

### One Thought Per Line

Each line of code should convey one thought. In PHP this is easiest eliminated by ensuring that each line of code only has one access operator (`::` or `->`), or to the right of the assignment operator.

Chaining of access operators should be avoided through creation of attributes on models that encapsulate references to related objects. This keeps code more concise, optimizes maintainability by moving logic closer to its source.

### One Idea Per Statement

Combining multiple thoughts creates an idea, thus each code statement should encapsulate a single idea, possibly across multiple lines.

### Group Code By Concepts

Multiple ideas come together as a concept, by combining multiple statements into a group of statements separated by an empty line.

### Encapsulate Each Concept in a Method

This can be further improved by refactoring each concept into its own method, and through careful naming can result in wonderfully readable, clean code.

### Encapsulate Related Methods in a Class

In Laravel we often do not have this issue, as most business logic is contained within Models. However, sometimes we need to create classes to encapsulate concepts outside of models or other Laravel-prescribed functional classes. Action classes are optimal candidates for encapsulating isolated concepts.

### Encapsulate Related Classes in a Domain

Taking this one step further, we can group related classes into a domain that represents functional blocks in the real world.

Domain-driven design takes this to the extreme:

> "Domain-driven design (less often, domain-driven design, DDD) is a set of principles and schemes aimed at creating optimal systems of objects. The development process boils down to creating software abstractions called domain models. These models include business logic that links the actual conditions of a product's application to the code."

## Naming

From Robert Martin's Clean Code:

> "Use Intention-Revealing Names. [...] The name of a variable, function, or class, should answer all the big questions. It should tell you why it exists, what it does, and how it is used. If a name requires a comment, then the name does not reveal its intent."

> "Avoid Disinformation. [...] Spelling similar concepts similarly is information. Using inconsistent spellings is dis-information. With modern [programming] environments we enjoy automatic code completion. We write a few characters of anime and press some hotkey combination (if that) and we are rewarded with a list of possible completions for that name. It is very helpful if names for very similar things sort together alphabetically and if the differences are very obvious, because the developer is likely to pick an object by name without seeing your copious comments or even the list of methods supplied by that class."

> "Use Pronounceable Names. Humans are good at words. A significant part of our brains is dedicated to the concept of words. And words are, by definition, pronounceable. It would be a shame not to take advantage of that huge portion of our brains that has evolved to deal with spoken language. So make your names pronounceable."

> "Use Searchable Names. Single-letter names and numeric constants have a particular problem in that they are not easy to locate across a body of text."

> "Avoid Mental Mapping. Readers shouldn't have to mentally translate your names into other names they already know. This problem generally arises from a choice to use neither problem domain terms nor solution domain terms. [...] In general programmers are smart people. Smart people sometimes like to show off their smarts by demonstrating their mental juggling abilities. [...] One difference between a smart programmer and a professional programmer is that the professional understands that clarity is king. Professionals use their powers for good and write code that others can understand."

> "Pick One Word Per Concept. Pick one word for one abstract concept and stick with it. For instance, it's confusing to have fetch, retrieve, and get as equivalent methods of different classes. How do you remember with method name goes with which class? [...] Otherwise you spend an awful lot of time browsing through headers and previous code samples."

### Casing

- All variables, properties, and methods should be in camelCase.
- All SQL fields should be in snake_case.
- All SQL keywords should be in UPPERCASE.
- All class names should be in PascalCase.
- All urls, query strings, and config keys should be in `snake_case`.
- All env vars should be in UPPER_SNAKE_CASE.

**Takeaways:**

- Code should be self-documenting.
- Code should clarify, and not obscure.
- Code should have intention.
- Code should be consistent, and adhere to expectations.

## Boy Scout Rule

From Robert Martin's Clean Coder:

> "The Boy Scouts of America have a simple rule that we can apply to our profession: 'Leave the campground cleaner than you found it.' If we all checked in our code a little cleaner than when we checked it out, the code simply could not rot. The cleanup doesn't have to be something big. Change one variable name for the better, break up one function that's a little too large, eliminate one small bit of duplication, clean up one composite if-statement. Can you imagine working on a project where the code simply got a lot better as time passed?"

**Takeaways:**

- Improve each file you touch during the course of working your PR to continuously improve the project over time.

## Debt

### Technical Debt

From Wikipedia:

> "In software-intensive systems, technical debt is a collection of design or implementation constructs that are expedient in the short term, but set up a technical context that can make future changes more costly or impossible."

From scrum.org:

> "There is also a kind of technical debt that is passively created when the Scrum Team learns more about the problem it is trying to solve. Today, the Development Team might prefer a different solution by comparison to the one the team implemented just six months ago. Or, the Development Team upgrades the definition of 'Done,' thus introducing rework in former product Increments."

From Ward Cunningham:

> "Technical Debt is the deferment of good software design for the sake of expediency. Put simply, we've chosen to make some questionable design choices in order to get product delivered. This may be a conscious choice, but—more often than not—it's unconscious, and the result of a time-crunch."

**Takeaways:**

- Address technical debt as soon as possible after it has been recognized.
- Anyone on the team has the ability to identify technical debt.

### Mental Debt

Mental debt is the mental cost required to read code.

**Takeaways:**

- Write as few lines as possible: less code means having to parse less.
- Carefully name classes, properties, and methods: clearly named objects convey their intended use clearly, and avoid making assumptions, or having to spend time figuring out what the name means.
- Do not use abbreviations anywhere: abbreviations are not clear to everyone, do not assume others know the abbreviation, this helps keep code readable for new developers, or developers coming back to the code years from now, and does not assume knowledge of abbreviation conventions at the time the code was written.
- Keep lines of code to under 100 characters: exceeding that break to a new line.
- Unnecessary lines of code cause additional mental overhead in trying to figure out what they do, just to realize that they don't really do anything.

## No Dead Code

There should be no unused or commented code.

**Takeaways:**

- This creates unnecessary code bloat, making the file harder to parse.
- It raises questions as to why the code was commented, or included but not used. Code should answer questions, not raise them.

## Don't Optimize Early

Optimizing without a specific need (code standards are already met, there are no apparent issues with the code) is pointless, and can make code worse.

Save optimization for the last possible moment, as any changes will inform how the code should be optimized.

From CodingDrills.com:

> "Increased Complexity: Premature optimization can introduce unnecessary complexity into the codebase. As developers focus on optimizing performance prematurely, they may end up sacrificing code readability, maintainability, and even correctness. The result is convoluted code that is difficult to debug and extend."

> "Time and Resource Waste: Optimizing code prematurely demands additional time and resources. Developers may spend hours or even days working on optimization, only to realize that the initial implementation was already performant enough. This wasted effort can slow down the development process, reducing productivity."

> "Compromised Code Quality: When developers prioritize optimization over clean code design and architecture, it can lead to compromises in overall code quality. The focus shifts from producing well-structured and maintainable code to achieving maximum performance."

**Takeaways:**

- Follow the coding standards primarily.
- Only optimize code when the need arises, as following the coding standards will already create highly maintainable code.

## Patterns

### Model-View-Controller (MVC)

#### Model

- This usually represents the core business logic. Models should have attributes that handle most of that, and any processing of the incoming request will take the model into account, either to persist or retrieve data.

#### View

- This can be a model, response class, view, or value object.

#### Controller

- Controller should contain no logic, instead it is only responsible for handling the incoming request, passing it to the corresponding Request Form class, then passing those results to the corresponding Response class (or view), which result is then return from the controller as the outgoing response.
- Should always be a restful or `__invoke` for single action controllers.

### Don't Repeat Yourself (DRY)

- Code should be abstracted/refactored out to small, manageable parts.
- Unrelated code can then use common functionality that has been abstracted out of the other code path.
- You shouldn't abstract out prematurely, only start abstracting out when other code needs to perform the same logic.

### SOLID

#### Single Responsibility Principle

> "A class should have one and only one reason to change, meaning that a class should have only one job."

- Each class is responsible only for one thing:
    - Model defines its relationships, scopes, and attributes.
    - Controller handles how incoming request is handled (form request class) and how the outgoing response is prepared (model, view, response class, resource class).
    - Action is an invokable class with only one purpose.

#### Open-Closed Principle

> "Objects or entities should be open for extension but closed for modification."

- Classes need to be extendable without requiring modification of the class itself.

#### Liskov Substitution Principle

> "Let q(x) be a property provable about objects of x of type T. Then q(y) should be provable for objects y of type S where S is a subtype of T."

- Classes and their sub-classes should be able to be substituted without code breaking.

#### Interface Segregation Principle

> "A client should never be forced to implement an interface that it doesn't use, or clients shouldn't be forced to depend on methods they do not use."

- If you add signatures to interfaces for functionality that does not apply to all classes that implement it, then you need to create a new interface, which will be implemented by the respective class in addition to the original interface.

#### Dependency Inversion Principle

> "Entities must depend on abstractions, not on concretions. It states that the high-level module must not depend on the low-level module, but they should depend on abstractions."

- When using Dependency Inversion, specify the interface instead of the concrete class. A great example is how Action classes are instantiated.

### Repository

See [Models](models.md) reference.
