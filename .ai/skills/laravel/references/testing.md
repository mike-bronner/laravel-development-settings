# Testing

## Guidelines

- Always run tests in parallel. Don't use the `--compact` option.
- When starting an app, start where you would start with writing code. The first test does not have to be elegant, or even correct. The most important thing is just to get started.
- Goal of tests is to get as quickly as possible to "Shameless Green", which means that no matter how ugly your code is, it satisfies all tests, thus is "green".
- One of the principles of Shameless Green is that code is written for understanding, rather than extreme adherence to any and all patterns. The human is the focus.
- Always write unit and integration tests, testing for success and failure for each scenario.
- Only test public methods of classes.
- Write tests so that they cover all protected and private methods of the class, accessed through the public methods. If there are non-public methods that are not covered, they are either inaccessible, or the tests are not comprehensive enough. If they are inaccessible, those methods should be removed.
- Tests should document the functionality of classes and their methods through careful naming of test methods.
- Mock any external interfaces you do not control, and test for both successes and failures. However, also create integration tests that test the external interface, so that the mocks do not become stale if the external interface changes.
- Do not mock classes that you control.

## Development Process

- Write unit tests before implementing classes (only implement classes, never procedural code).
- Always do Red/Green/Refactor TDD. This means writing tests for the code you would like to see in an optimal world. Then make the failing test pass using the minimum amount of code. Write another test to expand on the first test, which again makes the existing code fail. Refactor your code to make the second test pass. Rinse and repeat until you have the minimum necessary functionality for your MVP (minimum viable product).
- During the red/green/refactor process, keep in mind that you need to develop from two different perspectives:
    1. When writing tests, keep the larger picture of the application and business domain in mind.
    2. When writing code to satisfy tests, only think about the test that needs to be satisfied. DO NOT THINK ABOUT BUSINESS LOGIC, ONLY FOCUS ON MAKING TESTS GREEN.
- As your tests get more specific, your code should become more generic. Consider Robert Martin's Transformation Priority Premise (https://8thlight.com/blog/uncle-bob/2013/05/27/TheTransformationPriorityPremise.html) when writing functional code to keep code complexity at a minimum. Try to opt for the highest ranked option.
- What this means is that you start out satisfying the tests with minimal or no logic, and as your tests become more specific to certain use cases, the code gains more business logic and is more generically applicable.
- Never add code that won't be used.
- Remove any code that is not used.
- Use cyclomatic complexity as a guide for the number of tests needed to achieve full test coverage of your code (perhaps 1 test per complexity unit).
- Wait to DRY out duplicated code until after a few tests cover it. This way the correct abstraction might reveal itself, rather than prematurely abstracting it out incorrectly.

## Databases

Do not use SQLite for testing if:

- You are using JSON fields.
- Require exact float value calculations based on decimal fields.
- Have table alterations in your migrations.
- If you have raw queries which manipulate dates.

## Test Suites

### Unit Tests

Unit tests are tests that concern themselves only with the class under test. These are rare in Laravel, as most classes in Laravel have external concerns (controllers, models, listeners, events, jobs, etc.).

Even though, we should strive to write unit tests if at all possible, as they are the fastest.

### Feature Tests

Feature tests are any tests that use more than internal methods of a class, for instance use the database, other classes, HTTP requests, WebSockets, etc., but which do NOT traverse the internet to connect with external functionality.

Third-party APIs should be tested in feature tests through use of HTTP fakes. When doing so, an identical test should be written as an integration test that does not use fakes.

### Integration Tests

These are tests dedicated to requests that test external dependencies over the internet.
