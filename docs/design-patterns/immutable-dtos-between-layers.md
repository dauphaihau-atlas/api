# Immutable DTOs Between Layers

## What it means

The phrase **"Immutable objects transfer data between application layers without exposing domain entities"** means you should pass around **simple, read-only data objects** instead of handing one layer a real domain entity from another layer.

In a Clean Architecture application, different layers often need to exchange information, such as:

- Presentation
- Application
- Domain
- Infrastructure

A DTO (Data Transfer Object) is used to carry that information in a controlled way.

## Why not expose domain entities?

Domain entities represent core business concepts and often contain:

- business rules
- invariants
- behavior
- internal state that should stay protected

If you return or pass domain entities directly into controllers, API resources, or infrastructure code, those layers become tightly coupled to domain internals.

That can lead to problems such as:

- outer layers depending on business logic details
- accidental misuse of domain objects
- domain changes affecting unrelated layers
- formatting or serialization concerns leaking into business logic

Instead, each layer receives only the data it needs through a DTO.

## What "immutable" means

An immutable object cannot be changed after it is created.

In PHP 8.1+, DTOs are often made immutable by using `readonly` properties. That means once values are assigned in the constructor, they cannot be modified later.

This makes DTOs useful as stable snapshots of data.

Benefits of immutability include:

- safer data flow between layers
- fewer accidental mutations
- more predictable behavior
- easier debugging and testing

## Simple example

Instead of passing a domain `User` entity directly to a controller or response layer, the application layer can return a DTO such as:

- `id`
- `name`
- `email`

The controller or API resource reads that DTO and formats the response, while the real domain entity stays inside the business layer.

## Mental model

Think of it like this:

- **Domain entity** = the real business object, with rules and behavior
- **DTO** = a sealed envelope containing only the data another layer needs

You send the envelope, not the full internal object.

## Why this matters in Clean Architecture

Using immutable DTOs helps keep boundaries clear between layers.

This gives you:

- lower coupling
- clearer responsibilities
- better maintainability
- safer data transfer
- protection for domain logic

A common flow looks like this:

1. A controller receives input
2. The input is placed into a request DTO
3. A use case processes the request
4. The use case returns a response DTO
5. The controller or resource formats the output

At no point does the presentation layer need direct access to the domain entity itself.

## Summary

Immutable DTOs are read-only objects used to move data between layers. They help preserve clean architecture boundaries by ensuring that outer layers work with plain transferred data instead of depending directly on domain entities and their business behavior.