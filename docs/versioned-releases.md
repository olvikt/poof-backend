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
- **Traceability contract:** после deploy/rollback production host записывает:
  - текущий release ref;
  - resolved commit SHA;
  - UTC time deploy/rollback;
  - путь к локальному deploy log/state record.

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

## 4. Deploy a specific release

### Backward-compatible default

Если ref не указан, `scripts/deploy.sh` по-прежнему использует `origin/main` ради обратной совместимости.

### Recommended explicit deploy

```bash
cd /var/www/poof
bash scripts/deploy.sh release-YYYYMMDD-HHMM
```

или:

```bash
cd /var/www/poof
DEPLOY_REF=release-YYYYMMDD-HHMM bash scripts/deploy.sh
```

Во время deploy скрипт теперь:

- делает `git fetch --prune --tags origin`;
- проверяет, что указанный ref resolvится в commit;
- логирует requested ref, resolved release ref и commit SHA;
- делает `git reset --hard <resolved-commit>` вместо жёсткого `origin/main`;
- записывает release state в `storage/app/current-release.json`;
- кладёт deploy metadata snapshot в `storage/logs/deploy/`.

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
- логирует requested ref, resolved ref и commit;
- обновляет `storage/app/current-release.json`;
- пишет rollback record в `storage/logs/deploy/`;
- делает blocking health-check после rollback.

## 6. How to confirm what is in production

Минимальная traceability теперь строится на двух местах:

1. **Current state file**

```bash
cd /var/www/poof
cat storage/app/current-release.json
```

Ожидаемые поля:

- `release_ref`;
- `requested_ref`;
- `commit`;
- `deployed_at_utc`;
- `deploy_log`;
- `deployment_type` — присутствует для rollback.

2. **Deploy log directory**

```bash
cd /var/www/poof
ls -1 storage/logs/deploy
```

Этого достаточно, чтобы ответить на вопрос: **“Что сейчас в проде, какой commit behind it и когда это было выкачено?”**

## 7. Operator contract summary

- Production deploy должен использовать **explicit release ref/tag** whenever possible.
- Default `origin/main` оставлен только ради backward compatibility и emergency/manual continuity.
- Rollback должен выполняться на **previous release tag**, а не на произвольный remembered commit.
- После deploy/rollback оператор обязан:
  - проверить `storage/app/current-release.json`;
  - выполнить `bash scripts/check-server.sh`;
  - убедиться, что health/smoke checks прошли.

## 8. Out of scope

Эта задача специально **не** включает:

- artifact registry / artifact-based releases;
- blue/green deployment;
- canary rollout;
- Docker/Kubernetes migration;
- полный rewrite CI/CD platform.
