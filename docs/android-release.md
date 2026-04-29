# Android release guide for POOF (TWA/Bubblewrap)

## 1) Архитектурное решение

Для текущего стека Laravel + PWA используется **Trusted Web Activity (TWA)** через **Bubblewrap**:

- web app остаётся источником истины (`https://app.poof.com.ua`);
- Android shell только открывает origin в full-screen Chrome runtime;
- публикация в Google Play идёт через Android App Bundle (`.aab`);
- для сторонних маркетплейсов собирается release APK.

Рекомендуемая структура в этом репозитории:

- `android-twa/` — шаблоны и документация wrapper-контракта;
- generated Gradle-проект создаётся локально Bubblewrap и **не коммитится**.

## 2) PWA-контракт (что проверяем перед Android релизом)

Базовые точки:

- Manifest endpoint: `https://app.poof.com.ua/manifest.json`.
- Service Worker: `https://app.poof.com.ua/sw.js`.
- Иконки в manifest должны включать минимум:
  - `192x192` PNG;
  - `512x512` PNG;
  - maskable `512x512` PNG с `"purpose": "maskable"`.

Текущий контракт зафиксирован в:

- `docs/pwa-subsystem.md`;
- `scripts/check-pwa.sh`.

Перед сборкой Android выполните:

```bash
bash scripts/check-pwa.sh
```

> Примечание: скрипт может требовать `APP_BASE_URL=https://app.poof.com.ua` в окружении.

## 3) Установка tooling

### 3.1 Java и Android SDK

- JDK 17 (или совместимая версия, поддерживаемая текущим AGP);
- Android SDK + platform tools;
- `ANDROID_HOME`/`ANDROID_SDK_ROOT` должны быть доступны в окружении.

### 3.2 Node + Bubblewrap

```bash
npm i -g @bubblewrap/cli
bubblewrap --version
```

### 3.3 Google Chrome on device

Для runtime TWA на устройстве должен быть совместимый Chrome (или Chromium-based browser с поддержкой TWA).

## 4) Подготовка wrapper-конфига

1. Создайте локальный конфиг из шаблона:

```bash
cp android-twa/twa-manifest.template.json android-twa/twa-manifest.json
```

2. Проверьте ключевые значения:

- `packageId`: `ua.com.poof.app`
- `name` / `launcherName`: `POOF`
- `host`: `app.poof.com.ua`
- `startUrl`: `/`
- `themeColor` / `backgroundColor`: `#18191f` (синхронизировано с PWA manifest)

3. Заполните local signing values (не коммитить):

- `signingKey.path`
- `signingKey.alias`
- `storePassword`
- `keyPassword`

## 5) Генерация Android проекта

```bash
bubblewrap init --manifest android-twa/twa-manifest.json
```

После команды появится каталог с Android project (обычно по package name, например `ua.com.poof.app/`).

## 6) Сборка debug APK

```bash
cd ua.com.poof.app
./gradlew assembleDebug
```

Результат:

- `app/build/outputs/apk/debug/app-debug.apk`

## 7) Сборка release AAB (Google Play)

```bash
cd ua.com.poof.app
./gradlew bundleRelease
```

Результат:

- `app/build/outputs/bundle/release/app-release.aab`

Дальше в Play Console:

1. Create app / Production track.
2. Upload `.aab`.
3. Заполнить Data Safety, privacy policy, content rating.
4. Добавить store listing (скриншоты, иконка, feature graphic).

## 8) Сборка release APK (сторонние маркетплейсы)

```bash
cd ua.com.poof.app
./gradlew assembleRelease
```

Результат:

- `app/build/outputs/apk/release/app-release.apk`

Если маркетплейс требует подписанный universal APK, используйте тот же release keystore и проверьте требования конкретной площадки.

## 9) Digital Asset Links

TWA требует корректный `assetlinks.json` по адресу:

- `https://app.poof.com.ua/.well-known/assetlinks.json`

Шаги:

1. Сгенерируйте SHA-256 отпечаток сертификата (upload/release key):

```bash
keytool -list -v -keystore /path/to/release.keystore -alias <alias>
```

2. Подставьте fingerprint в `android-twa/assetlinks.template.json`.
3. Опубликуйте итоговый JSON в `public/.well-known/assetlinks.json`.
4. Проверьте доступность URL (HTTP 200).

## 10) Что нельзя коммитить

Никогда не коммитьте:

- `*.keystore`, `*.jks`, `*.p12`;
- реальные пароли подписи;
- локальные `local.properties` и env с секретами;
- generated Android build artifacts.

См. `android-twa/.gitignore`.

## 11) Go-live checklist

- [ ] `scripts/check-pwa.sh` проходит на production URL.
- [ ] `manifest.json` содержит 192/512/maskable icons.
- [ ] `assetlinks.json` опубликован с правильным package + SHA-256.
- [ ] Debug APK собирается локально.
- [ ] Release AAB собирается и загружается в Play Console.
- [ ] Release APK собирается для сторонних сторів.
- [ ] Подготовлены policy страницы (privacy policy / terms), скриншоты и listing assets.
