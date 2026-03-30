# Canonical release-candidate operator workflow

Этот runbook — один канонический operator flow для production release candidate: подготовка explicit release tag, deploy выбранного release ref, обязательный smoke, optional PWA smoke, rollback на previous known-good release и проверка post-rollback state.

Связанные source-of-truth документы:

- `docs/versioned-releases.md`
- `docs/release-gates.md`
- `docs/release-discipline-checklist.md`
- `docs/production-server-setup.md`
- `docs/pwa-subsystem.md`
- `scripts/deploy.sh`
- `scripts/rollback.sh`
- `scripts/show-release.sh`
- `scripts/check-server.sh`
- `scripts/check-pwa.sh`

## 1. Release preparation

1. Убедитесь, что нужный commit уже стабилизирован в `release/*` ветке и текущий blocking CI gate прошёл.
2. Создайте explicit annotated release tag **из release branch**, а не напрямую из unstable `main`.
3. Push release branch и tag в origin.

```bash
git checkout release/YYYYMMDD-HHMM
git pull --ff-only origin release/YYYYMMDD-HHMM
git tag -a release-YYYYMMDD-HHMM -m "Production release YYYY-MM-DD HH:MM UTC"
git push origin release-YYYYMMDD-HHMM
```

Operator contract:

- normal production path = deploy explicit release ref/tag;
- `scripts/deploy.sh` без ref остаётся только legacy/emergency continuity path и не считается нормальным release workflow.

## 2. Mandatory pre-deploy gate (blocking)

Перед обычным production deploy оператор обязан открыть pre-deploy gate для конкретного release ref.

```bash
cd /var/www/poof
git fetch --prune --tags origin
RELEASE_REF=release-YYYYMMDD-HHMM \
GATE_OPERATOR=\"ops-oncall\" \
BROWSER_SMOKE_EVIDENCE=\"JIRA-1234 / screenshot-pack-2026-03-30\" \
SMOKE_HOME_OK=yes \
SMOKE_CLIENT_ORDER_CREATE_OK=yes \
SMOKE_PROFILE_ADDRESS_AVATAR_EDIT_OK=yes \
SMOKE_COURIER_AVAILABLE_MY_ORDERS_OK=yes \
SMOKE_CRITICAL_POPUPS_CAROUSELS_OK=yes \
bash scripts/prepare-release-gate.sh release-YYYYMMDD-HHMM
```

Этот шаг блокирующий и fail-closed:

- `scripts/check-server.sh` запускается как runtime contract gate;
- deploy не сможет продолжиться без валидного gate artifact в `storage/app/release-gates/*.json`;
- browser smoke checklist обязателен и фиксируется как артефакт в gate JSON.

## 3. Canonical deploy workflow

На production host всегда используйте explicit release ref/tag:

```bash
cd /var/www/poof
git fetch --prune --tags origin
bash scripts/deploy.sh release-YYYYMMDD-HHMM
```

Эквивалентный вызов через env допустим, но не обязателен:

```bash
cd /var/www/poof
git fetch --prune --tags origin
DEPLOY_REF=release-YYYYMMDD-HHMM bash scripts/deploy.sh
```

Что обязан сделать deploy script в этом workflow:

- fetch tags/refs и resolve выбранный ref в commit;
- `git reset --hard` на resolved commit;
- `composer install --no-dev --optimize-autoloader`;
- `npm ci` + `npm run build`;
- verify `public/build/manifest.json`;
- `php artisan migrate --force`;
- Laravel cache rebuild;
- blocking health-check against `https://api.poof.com.ua/up`;
- записать successful known-good release в `storage/app/current-release.json`;
- append successful transition в `storage/app/release-history.jsonl`.

`scripts/deploy.sh` теперь блокирует релиз, если для выбранного release ref отсутствует/просрочен pre-deploy gate artifact или не отмечен mandatory browser smoke checklist.

## 4. Immediate release verification

Сразу после successful deploy оператор обязан выполнить:

```bash
cd /var/www/poof
bash scripts/show-release.sh
bash scripts/check-server.sh
```

Оператор должен подтвердить в `bash scripts/show-release.sh`:

- `release_ref` = release tag, который только что деплоили;
- `requested_ref` = переданный explicit ref;
- `resolved_ref` = ожидаемый resolved deploy ref;
- `selection_mode` = `explicit`;
- `fallback_used` = `false`;
- `previous_release_ref` = предыдущий known-good production release;
- recent release transitions показывают новую successful deploy entry.

`bash scripts/check-server.sh` — mandatory smoke для каждого release candidate. Release не закрыт, пока этот smoke-runner не завершился успешно.

## 5. Extra step for PWA-affecting releases

Если релиз затрагивает `public/sw.js`, `public/manifest.json`, landing install shell/UI, Vite asset wiring или production HTML/cache behavior, после обязательного smoke-run дополнительно выполните:

```bash
cd /var/www/poof
bash scripts/check-pwa.sh
```

Interpretation:

- **ordinary backend-only release:** `deploy.sh` → `show-release.sh` → `check-server.sh`;
- **PWA-affecting release:** `deploy.sh` → `show-release.sh` → `check-server.sh` → `check-pwa.sh`.

`bash scripts/check-pwa.sh` остаётся узким operator smoke и не расширяет blocking CI/release gate до browser/E2E automation.

## 6. Canonical rollback workflow

Если release candidate не прошёл post-deploy verification, rollback выполняется на **previous known-good release ref/tag**, а не на произвольный remembered commit.

### 5.1 Identify rollback target

Сначала посмотрите recorded release state:

```bash
cd /var/www/poof
bash scripts/show-release.sh
```

Используйте `previous_release_ref` из summary как канонический rollback target. При необходимости можно дополнительно посмотреть raw state/history:

```bash
cd /var/www/poof
cat storage/app/current-release.json
tail -n 5 storage/app/release-history.jsonl
```

### 5.2 Execute rollback

```bash
cd /var/www/poof
bash scripts/rollback.sh <previous_release_ref>
```

Rollback contract в текущих scripts:

- fetch tags/refs и resolve выбранный rollback ref;
- `git reset --hard` на resolved commit;
- `composer install --no-dev --optimize-autoloader`;
- `npm ci` + `npm run build`;
- verify `public/build/manifest.json`;
- Laravel cache rebuild;
- restart workers;
- blocking health-check;
- записать successful rollback как новый current known-good state;
- append rollback transition в release history.

## 7. Mandatory post-rollback verification

Сразу после successful rollback оператор снова обязан выполнить:

```bash
cd /var/www/poof
bash scripts/show-release.sh
bash scripts/check-server.sh
```

Если откатываемый release был PWA-affecting или rollback возвращает предыдущую PWA shell/cache behavior, дополнительно выполните:

```bash
cd /var/www/poof
bash scripts/check-pwa.sh
```

Оператор должен подтвердить после rollback:

- `release_ref` теперь равен rollback target;
- `deployment_type` = `rollback`;
- `requested_ref` указывает на rollback ref;
- `fallback_used` = `false`;
- `previous_release_ref` указывает на release, с которого только что откатились;
- mandatory smoke снова проходит успешно;
- для PWA-affecting rollback `check-pwa.sh` снова подтверждает manifest / service-worker / landing wiring.

## 8. One-page operator checklist

### Ordinary backend-only release

1. Prepare and push explicit release tag.
2. On host: `git fetch --prune --tags origin`.
3. Run blocking pre-deploy gate: `bash scripts/prepare-release-gate.sh <release-tag>` with mandatory browser smoke confirmations.
4. Deploy: `bash scripts/deploy.sh <release-tag>`.
5. Inspect state: `bash scripts/show-release.sh`.
6. Mandatory smoke: `bash scripts/check-server.sh`.
7. If release is bad: read `previous_release_ref` from `show-release.sh` and run `bash scripts/rollback.sh <previous_release_ref>`.
8. Verify rollback: `bash scripts/show-release.sh` + `bash scripts/check-server.sh`.

### PWA-affecting release

1. Prepare and push explicit release tag.
2. On host: `git fetch --prune --tags origin`.
3. Run blocking pre-deploy gate: `bash scripts/prepare-release-gate.sh <release-tag>` with mandatory browser smoke confirmations.
4. Deploy: `bash scripts/deploy.sh <release-tag>`.
5. Inspect state: `bash scripts/show-release.sh`.
6. Mandatory smoke: `bash scripts/check-server.sh`.
7. Additional PWA smoke: `bash scripts/check-pwa.sh`.
8. If release is bad: use `previous_release_ref` from `show-release.sh` and run `bash scripts/rollback.sh <previous_release_ref>`.
9. Verify rollback: `bash scripts/show-release.sh` + `bash scripts/check-server.sh`.
10. If rollback touches PWA-visible behavior: `bash scripts/check-pwa.sh`.
