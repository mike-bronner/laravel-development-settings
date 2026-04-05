# Controllers

## No Business Logic

- Controllers should only control the flow of requests and responses. All business logic should be extracted to Form Request classes and Response classes, leaving the controller with only a few lines of code within each method.
- Controllers should be either restful or invokable, no custom actions. If you are reaching for custom actions, that is a code smell that the controller or related model has not been named or extracted granularly enough.

## Route Model Binding

- Controllers should auto-resolve models through route-model-binding, by adding the model parameter to the method, even if it is not used in the method, as simply adding it triggers the binding, and it is then available to be accessed in the Form Request class.
- Route-model binding can be customized in the RouteServiceProvider as needed.
