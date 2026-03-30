# Versioned Releases Contract

Этот документ переводит production deploy-процесс от branch-based `origin/main` discipline к минимальной **versioned release** модели без большого platform rewrite.

## 1. Current model audit

До этой задачи production model была такой:

- **Deploy source:** `origin/main`.
- **Deploy method:** server-side git sync + `composer install` + `npm ci` + `npm run build` + migrations + cache rebuild + health-check.
- **Rollback method:** `scripts/rollback.sh <git-ref>` как точечная операция по произвольному ref.
- **Release identity:** implicit commit on `main`, а не явный release identifier.

Что уже хорошо:

- deploy и rollback уже выполняются через канонические repo scripts;
- build / migrate / cache / health steps уже дисциплинированы;
- rollback уже допускает explicit git ref;
- release gate уже описывает, что обязано пройти в CI и на production host.

Чего не хватало до versioned releases:

- **explicit release ID** — оператору не хватало канонического release ref/tag;
- **release selection** — deploy был привязан к `origin/main`, а не к выбранному release ref;
- **rollback target discipline** — rollback можно было сделать на любой commit, но не было явного правила “откатываемся к предыдущему release ref”;
- **operator traceability** — не было простого способа ответить, какой release ref и commit сейчас в production.

## 2. Minimal release model

Базовая модель после этой задачи:

- **Release ref** = явный git ref, предпочтительно immutable git tag.
- Рекомендуемый naming:
  - `release-YYYYMMDD-HHMM`, например `release-20260319-1200`; или
  - semver/date hybrid вроде `v2026.03.19.1`, если команде так удобнее.
- **Deploy contract:** `scripts/deploy.sh <release-ref>` или `DEPLOY_REF=<release-ref> bash scripts/deploy.sh`.
- **Rollback contract:** `scripts/rollback.sh <release-ref>` или `ROLLBACK_REF=<release-ref> bash scripts/rollback.sh`.
- **Traceability contract:** после успешного deploy/rollback production host записывает:
  - текущий known-good release ref;
  - previous known-good release ref и commit;
  - requested ref и resolved ref;
  - был ли использован explicit path или legacy fallback path;
  - resolved commit SHA;
  - UTC time deploy/rollback;
  - путь к локальному metadata snapshot и append-only release history ledger.

Для минимального operational safety рекомендуется использовать именно **annotated tags** как release refs:

```bash
git tag -a release-20260319-1200 -m "Production release 2026-03-19 12:00 UTC"
git push origin release-20260319-1200
```

## 3. Prepare a release

1. Убедиться, что `main` содержит нужный код и все blocking gates зелёные.
2. Создать явный release tag:

```bash
git checkout main
git pull --ff-only origin main
git tag -a release-YYYYMMDD-HHMM -m "Production release YYYY-MM-DD HH:MM UTC"
git push origin release-YYYYMMDD-HHMM
```

3. Зафиксировать этот tag как release candidate для production.
4. Добавить короткий operator-facing summary для этого тега:

```bash
bash scripts/create-release-summary.sh release-YYYYMMDD-HHMM "Short operator summary"
git add docs/release-summaries/release-YYYYMMDD-HHMM.md
git commit -m "docs(release): add summary for release-YYYYMMDD-HHMM"
```

Release summary convention:

- location: `docs/release-summaries/<release-tag>.md`;
- summary signal: первая непустая строка после заголовка;
- expected size: 1-3 коротких строки, без длинного narrative changelog.

## 4. Canonical production deploy path

### Canonical path: explicit release ref/tag

Нормальный production path теперь формулируется однозначно: оператор **должен** передавать explicit release ref/tag. Во всех runbooks, примерах и ручных командах первым показывается именно этот путь.

Перед самим deploy теперь обязателен pre-deploy gate:

```bash
cd /var/www/poof
RELEASE_REF=release-YYYYMMDD-HHMM \
GATE_OPERATOR=\"ops-oncall\" \
BROWSER_SMOKE_EVIDENCE=\"JIRA-1234\" \
SMOKE_HOME_OK=yes \
SMOKE_CLIENT_ORDER_CREATE_OK=yes \
SMOKE_PROFILE_ADDRESS_AVATAR_EDIT_OK=yes \
SMOKE_COURIER_AVAILABLE_MY_ORDERS_OK=yes \
SMOKE_CRITICAL_POPUPS_CAROUSELS_OK=yes \
bash scripts/prepare-release-gate.sh release-YYYYMMDD-HHMM
```

`scripts/deploy.sh` fail-closed проверяет gate artifact для выбранного release ref. Если runtime contract или browser smoke не подтверждены, deploy блокируется.

```bash
cd /var/www/poof
bash scripts/deploy.sh release-YYYYMMDD-HHMM
```

или:

```bash
cd /var/www/poof
DEPLOY_REF=release-YYYYMMDD-HHMM bash scripts/deploy.sh
```

### Legacy continuity path: fallback to `origin/main`

Backward-compatible fallback на `origin/main` остаётся доступным **только** как legacy/emergency continuity path.

```bash
cd /var/www/poof
bash scripts/deploy.sh
```

Важно:

- это больше не считается нормальным operator path;
- fallback оставлен ради backward compatibility со старыми вызовами и для emergency/manual continuity;
- при no-ref deploy скрипт теперь печатает явное warning-сообщение и записывает в release state, что был использован fallback.

Во время deploy скрипт теперь:

- делает `git fetch --prune --tags origin`;
- проверяет, что указанный ref resolvится в commit;
- логирует requested ref, fallback ref, resolved deploy ref, resolved release ref и commit SHA;
- явно помечает, был ли использован legacy fallback path;
- делает `git reset --hard <resolved-commit>` вместо жёсткого `origin/main`;
- записывает release state в `storage/app/current-release.json`;
- добавляет append-only запись в `storage/app/release-history.jsonl`;
- кладёт metadata snapshot в `storage/logs/deploy/`.
- для explicit tag release требует наличие `docs/release-summaries/<release-tag>.md`, извлекает short summary и сохраняет его в release state (`release_summary_file`, `release_summary`).

## 5. Rollback a previous release

Rollback теперь должен опираться не на “вспомнить хороший commit”, а на **previous known-good release ref/tag**.

Recommended path:

1. Посмотреть текущий production release:

```bash
cd /var/www/poof
cat storage/app/current-release.json
```

2. Выбрать предыдущий release tag из истории релизов:

```bash
git tag --list 'release-*' --sort=-creatordate
```

3. Выполнить rollback на выбранный explicit release ref:

```bash
cd /var/www/poof
bash scripts/rollback.sh release-YYYYMMDD-HHMM
```

Rollback script теперь:

- fetches tags/refs перед reset;
- валидирует rollback ref;
- логирует requested ref, resolved ref, commit и previous known-good release;
- явно фиксирует, что fallback path не использовался;
- переустанавливает production dependencies и заново собирает frontend assets (`npm ci` + `npm run build` + проверка `public/build/manifest.json`), чтобы host-side rollback не оставлял артефакты от более нового release;
- пересобирает Laravel caches (`config:clear` + `optimize:clear` + `optimize`);
- обновляет `storage/app/current-release.json` только после успешного health-check;
- добавляет append-only запись в `storage/app/release-history.jsonl`;
- пишет rollback metadata snapshot в `storage/logs/deploy/`;
- делает blocking health-check после rollback.

## 6. How to confirm what is in production

Минимальная traceability теперь строится на трёх местах:

1. **Current state file / operator summary**

Предпочтительный operator UX теперь начинается с одной команды:

```bash
cd /var/www/poof
bash scripts/show-release.sh
```

Она печатает human-readable summary для current release, previous known-good release и последних transition entries из history ledger. При необходимости raw state всё ещё можно читать напрямую:

```bash
cd /var/www/poof
cat storage/app/current-release.json
```

Ожидаемые поля:

- `release_ref`;
- `release_ref_kind`;
- `requested_ref`;
- `resolved_ref`;
- `fallback_ref`;
- `fallback_used`;
- `selection_mode`;
- `commit`;
- `deployed_at_utc`;
- `previous_release_ref`;
- `previous_commit`;
- `deploy_log`;
- `release_history`;
- `release_summary_required`;
- `release_summary_present`;
- `release_summary_file`;
- `release_summary`.
- `deployment_type`.

2. **Append-only release history ledger**

```bash
cd /var/www/poof
tail -n 5 storage/app/release-history.jsonl
```

Каждая строка — это JSON snapshot успешного deploy/rollback. Ledger удобен для быстрого ответа на вопросы:

- какой release был до текущего;
- когда production переключили на новый release;
- был ли выбран explicit ref или legacy fallback path;
- какой rollback уже выполнялся на этом хосте.

3. **Deploy log directory**

```bash
cd /var/www/poof
ls -1 storage/logs/deploy
```

Этого достаточно, чтобы ответить на вопросы: **“Что сейчас в проде?”, “какой previous known-good release был до него?” и “был ли это explicit release deploy или legacy fallback path?”**
А благодаря `release_summary` в state/`show-release` можно быстро ответить и на вопрос: **“Что вошло в этот release?”**

## 7. Operator contract summary

- **Canonical normal path:** production deploy выполняется с **explicit release ref/tag**.
- **Legacy continuity path:** default `origin/main` оставлен только ради backward compatibility и emergency/manual continuity.
- Если deploy был выполнен без explicit ref, это должно рассматриваться как осознанное исключение, а не как обычная практика.
- Rollback должен выполняться на **previous release tag**, а не на произвольный remembered commit.
- `storage/app/current-release.json` теперь отражает только последний **successful / known-good** release; failed health-check не должен перетирать current state.
- После deploy/rollback оператор обязан:
  - запустить `bash scripts/show-release.sh`;
  - убедиться, что `requested_ref`, `resolved_ref`, `selection_mode`, `previous_release_ref` и `fallback_used` выглядят ожидаемо;
  - при необходимости открыть raw `storage/app/current-release.json` или `tail -n 5 storage/app/release-history.jsonl`;
  - выполнить `bash scripts/check-server.sh`;
  - убедиться, что health/smoke checks прошли.

## 8. Out of scope

Эта задача специально **не** включает:

- artifact registry / artifact-based releases;
- blue/green deployment;
- canary rollout;
- Docker/Kubernetes migration;
- полный rewrite CI/CD platform.
