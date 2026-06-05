# STATUS.md

## Current Status
- Replaced the button component for video titles with a native `<a>` tag to natively support `target="_blank"`.
- Wired `@click` and `@auxclick` with AlpineJS to hit the tracking endpoint without using `window.open`.
- Middle-click now tracks properly using `auxclick` event checking for `event.button === 1`.
- Rearranged header buttons: Moved "Newest" next to "Shortest".
- Added a 30px visual divider after sorting buttons.
- Updated duration format to use the ⏳ emoji instead of tilde.
- Added 30 and 45-minute duration jump options.
