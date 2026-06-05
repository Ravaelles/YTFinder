# STATUS.md

## Current Status
- Replaced the button component for video titles with a native `<a>` tag to natively support `target="_blank"`.
- Wired `@click` and `@auxclick` with AlpineJS to hit the tracking endpoint without using `window.open`.
- Middle-click now tracks properly using `auxclick` event checking for `event.button === 1`.
- Committed the changes related to the middle-click feature.
