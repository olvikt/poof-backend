# Architecture Rules

## A. Purpose

Этот документ фиксирует **текущий архитектурный contract проекта** в короткой review-friendly форме.

Используйте его как PR checklist, а не как полное описание всей системы. Правила ниже опираются на уже существующие project patterns: address extraction, release gates, canonical order/courier lifecycle methods и auth/protected-route regressions.


- не оставляем ли мы business logic в UI-слое;
- есть ли явные boundaries между Livewire / DTO / services / actions;
- не ломаем ли runtime и browser-event contracts;
- добавили ли мы tests на extracted logic и user-visible поведение.

## B. Livewire rules

**Livewire-компонент — orchestration layer.**

Он может держать:

- state;
- listeners;
- validation;
- browser events;
- user-flow orchestration.

Он не должен становиться местом для:

- тяжёлой business logic;
- persistence orchestration, размазанной по компоненту;
- parsing/normalization внешних API payloads.

Если flow перестаёт быть тривиальным, выносим его в DTO + services/actions, а Livewire оставляем точкой входа и coordination-слоем. Это уже подтверждено текущим `AddressForm` extraction path, где geocode и save path вынесены из компонента.

**PR checklist:**

- Не превратился ли Livewire в “fat component”?
- Не живут ли save/geocode rules прямо в hook/method компонента?
- Сохраняется ли browser-facing contract, пока логика уходит наружу?

## C. Actions / Services / DTO rules

**DTO** нужны для явных входных/выходных контрактов flow.

**Services** содержат orchestration/integration logic:

- подготовка и нормализация payload;
- geocode/reverse-geocode resolution;
- fallback rules.

**Actions** применяют domain/app persistence side effects.

Граница должна оставаться практичной:

- не плодим абстракции “на будущее”;
- новая service/action/DTO появляется, когда у flow уже есть самостоятельный contract, отдельная ответственность или отдельный test surface;
- если нет второго реального consumer’а или отдельной причины жить отдельно, не дробим код механически.

**PR checklist:**

- Есть ли у non-trivial flow явный DTO/service boundary?
- Не смешаны ли integration, normalization и persistence в одном месте?
- Не добавили ли мы лишний слой без новой ответственности?

## D. External API / geocode rules

Внешний API не парсится прямо в Livewire/controller/UI layer.

Обязательные правила:

- HTTP integration инкапсулируется в service;
- response parsing и normalization живут рядом с integration logic, а не в UI;
- fallback behavior и malformed-response handling тестируются отдельно;
- bad/partial responses не должны silently портить уже существующий UI state.

`null`/no-op при плохом ответе допустим, если user-visible contract остаётся предсказуемым и текущее состояние не ломается “между делом”.

**PR checklist:**

- Не разбирается ли внешний payload прямо в компоненте или контроллере?
- Есть ли отдельный test на fallback / malformed response?
- Защищён ли существующий state от silent corruption?

## E. Logging rules

Логируем failures на critical-path transitions, но делаем это намеренно.

Минимальные правила:

- логируем ошибки там, где user flow может завершиться неуспешно;
- log context должен быть минимально полезным и безопасным;
- не размазываем ad-hoc debug logging без понятной operational purpose;
- production logging вокруг maps/geocode должен быть осознанным, легко понижаемым или удаляемым после диагностики.

**PR checklist:**

- Есть ли лог на реальный failure path?
- Не утекают ли лишние payload/details в logs?
- Не оставили ли мы временный noisy debug logging без явной причины?

## F. Testing rules

Каждый critical-path refactor должен защищать и extraction logic, и user-visible contract.

Ожидаемый минимум:

- **unit tests** на extracted services/actions/normalization rules;
- **feature/livewire regression tests** на observable поведение;
- **structural regression tests** допустимы там, где нужно зафиксировать архитектурное ограничение или canonical entry point.

Route/auth contracts и lifecycle canonical methods тоже считаются важными regression surfaces, если они уже оформлены тестами. Это включает protected-route regressions и structural tests на canonical lifecycle entry points.

Blocking CI gate и non-blocking wider suites определяются отдельно в [`docs/release-gates.md`](./release-gates.md) и не дублируются здесь.

**PR checklist:**

- Добавлены ли unit tests на вынесенную логику?
- Есть ли regression test на user-visible contract?
- Нужен ли structural test, чтобы не расползлась архитектура снова?

## G. Browser-event / runtime contract rules

Browser events считаются публичным UI contract.

Поэтому:

- event names/payloads нельзя менять “по пути” без coordinated refactor;
- runtime-state transitions для courier/order должны идти через canonical domain methods;
- нельзя подменять canonical lifecycle scattered writes по route/controller/Livewire/form слоям.

Если existing flow уже защищён regression test’ом как contract, изменение такого flow требует осознанного обновления и кода, и теста, и связанной документации.

**PR checklist:**

- Не ломаем ли browser-event name/payload без coordinated change?
- Идут ли runtime transitions через canonical domain methods?
- Не появились ли прямые status/session/flag writes там, где уже есть canonical method?

## H. Release gate reference

Эти architecture rules работают вместе с [`docs/release-gates.md`](./release-gates.md):

- `architecture-rules.md` фиксирует **что считать корректной структурой change**;
- `release-gates.md` фиксирует **что должно пройти перед merge/deploy/release**.

Если PR меняет critical flow, проверяем оба документа, но не дублируем один в другом.


## I. Route-layer responsibility rules

Route layer = transport boundary.

Допустимо в route closure только transport glue:

- тривиальный `view(...)` / `redirect(...)`;
- health/readiness probes без доменной логики;
- временный compatibility shim, если он явно documented и покрыт regression test.

Нужно обязательно выносить в controller/action, если в route появляется хоть что-то из списка:

- lifecycle переходы (`accept/start/complete/cancel/pay`);
- runtime/business state mutations;
- нетривиальная валидация/ветвление user-flow;
- повторяемая orchestration logic, которая уже имеет canonical entrypoint.

Практическое правило для PR:

- если код route трогает model method, который меняет состояние, — route должен делегировать в controller/action, а не выполнять transition inline;
- route оставляем thin: URL + middleware + auth boundary + delegation;
- source of truth для business transition — canonical domain/application entrypoint.


## J. Naming & responsibility glossary

Canonical naming and boundary rules for Form/Manager/Action/Policy/Controller roles are documented in [`docs/naming-responsibility-boundaries.md`](./naming-responsibility-boundaries.md).

Use it as a quick audit checklist when introducing new UI orchestration, write-actions, or controller entry points.
