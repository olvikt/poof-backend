# Android TWA wrapper (POOF)

Этот каталог хранит минимальный wrapper-контракт для сборки Android APK/AAB через **Trusted Web Activity (Bubblewrap)** поверх production PWA `https://app.poof.com.ua`.

## Что внутри

- `twa-manifest.template.json` — шаблон Bubblewrap-конфига без секретов.
- `.gitignore` — исключения для Android build-артефактов и локальных секретов.
- `assetlinks.template.json` — шаблон для публикации в `https://app.poof.com.ua/.well-known/assetlinks.json`.

## Быстрый старт

1. Установите Java 17+, Android SDK и Bubblewrap CLI.
2. Скопируйте шаблон и заполните параметры:
   ```bash
   cp android-twa/twa-manifest.template.json android-twa/twa-manifest.json
   ```
3. Сгенерируйте Android-проект:
   ```bash
   bubblewrap init --manifest android-twa/twa-manifest.json
   ```
4. Соберите debug APK:
   ```bash
   cd ua.com.poof.app
   ./gradlew assembleDebug
   ```

Детальный релизный процесс описан в `docs/android-release.md`.
