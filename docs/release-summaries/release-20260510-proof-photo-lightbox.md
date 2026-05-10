# Release 2026-05-10 — Client proof photo lightbox

## What changed
- Replaced direct proof photo links in client order completion surfaces with in-app lightbox behavior in:
  - client active/current orders card (`OrdersList`)
  - subscription history awaiting client confirmation UI (`SubscriptionsPage`, incl. PR #587 path)
- Thumbnails now open modal/gallery overlay instead of navigating to raw file URLs.
- Added gallery title, photo counter (`Фото N з M`), close control (`Закрити ×`), and previous/next controls for multiple photos.
- Added keyboard support (Escape to close, Arrow Left/Right to switch photos), backdrop close, and accessible alt/aria attributes.
- Kept current proof URLs and payload usage unchanged (including signed/private URL behavior).

## QA checklist
1. Open client order with two proof photos.
2. Tap first thumbnail — verify modal opens inside app.
3. Navigate to second photo via next control.
4. Close modal — verify same order screen stays open.
5. Verify browser back button is not needed and URL does not change to image URL.
6. Verify confirm/dispute buttons still work after closing modal.
