# WordPress.org store assets

These images are uploaded to the plugin's WordPress.org listing (not shipped inside
the plugin). The `deploy.yml` workflow points `ASSETS_DIR` here.

| File | Size | Purpose |
| ---- | ---- | ------- |
| `icon-256x256.png` | 256×256 | Plugin icon in search and the listing header |
| `banner-772x250.png` | 772×250 | Listing banner (standard) |
| `banner-1544x500.png` | 1544×500 | Listing banner (retina) |
| `screenshot-1.png` | — | Maps to screenshot #1 in `readme.txt` |

The PNGs here were generated from the SVG sources in `../.github/assets/`
(`icon.svg`, `wporg-banner.svg`, `preview.svg`). To regenerate after editing a
source SVG:

```bash
python3 -m pip install cairosvg
python3 - <<'PY'
import cairosvg
cairosvg.svg2png(url='../.github/assets/icon.svg', write_to='icon-256x256.png', output_width=256, output_height=256)
cairosvg.svg2png(url='../.github/assets/wporg-banner.svg', write_to='banner-1544x500.png', output_width=1544, output_height=500)
cairosvg.svg2png(url='../.github/assets/wporg-banner.svg', write_to='banner-772x250.png', output_width=772, output_height=250)
PY
```

To add more screenshots, drop `screenshot-2.png`, `screenshot-3.png`, … here and
describe each (in order) under `== Screenshots ==` in `readme.txt`. A real capture
of the **Settings → CryptoStack Donations** page makes a good screenshot #2.
