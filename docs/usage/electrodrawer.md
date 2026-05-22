# Electrodrawer — WLED LED Cabinet Highlighting

Electrodrawer integrates a WLED-controlled LED matrix with PartDB storage locations. When a part is viewed or highlighted, the drawer containing it lights up automatically.

## Architecture

```
PartDB (parts.fablabjoinville.com.br)
  └─ Symfony Messenger worker
        └─ WledHighlightHandler
              └─ HTTP POST → WLED device (192.168.1.37/json/state)
```

WLED is controlled via its **JSON HTTP API** directly from the VPS over the local LAN. No MQTT broker is involved in the highlight path (the Mosquitto broker and Home Assistant remain available for other automation projects).

## Physical Cabinet

The current cabinet has 6 rows (A–F) arranged on a 2D LED matrix:

| Row | LED y-index | Drawers per LED column | Notes            |
|-----|-------------|------------------------|------------------|
| F   | 0           | 1                      | Top row          |
| E   | 1           | 1                      |                  |
| D   | 2           | 1                      |                  |
| C   | 3           | 1                      |                  |
| B   | 4           | 2                      | Two drawers tall |
| A   | 5           | 5                      | Five drawers tall |

WLED panel: 60×6 matrix. Segment 0 is a frozen black base layer; segment 1 ("ELECTRODRAWER") is the active highlight segment.

## Naming Convention

Storage locations named `[Letter][Number]` (e.g. `E35`, `B12`, `A03`) are **automatically highlighted** with no additional configuration needed. The handler parses the name to derive 2D segment coordinates:

- Letter → row lookup in Row Configuration
- Number → column (1-based)
- Example: `E35` → row E (y=1), column 35 → `start=34, stop=35, startY=1, stopY=2`

Locations that do not follow this pattern will not highlight unless **Manual LED Override** is configured (see below).

## Configuration

### System Settings → Misc → WLED

| Setting | Description |
|---------|-------------|
| Highlight Color | RGB hex color for highlighting (default `#FF6600`) |
| Highlight Duration | Duration in minutes (default `2`) |
| Effect ID | WLED effect index (0 = solid, 65 = Fireworks). See [WLED effects list](https://kno.wled.ge/features/effects/) |
| Default WLED Host | Fallback IP/hostname when no host is set on the storage location (default `192.168.1.37`) |
| Row Configuration (JSON) | Maps row letters to WLED matrix parameters (see below) |

### Row Configuration JSON format

```json
{
  "F": {"y": 0, "perDrawer": 1},
  "E": {"y": 1, "perDrawer": 1},
  "D": {"y": 2, "perDrawer": 1},
  "C": {"y": 3, "perDrawer": 1},
  "B": {"y": 4, "perDrawer": 2},
  "A": {"y": 5, "perDrawer": 5}
}
```

Each key is a row letter. Fields:
- `y` — zero-based row index in the WLED 2D matrix
- `perDrawer` — number of LED columns per drawer (width of the highlight segment)
- `host` *(optional)* — override the WLED device IP for rows on a different module

### Adding a second WLED module

When adding a new module for rows G–L above the existing cabinet:
1. Add new row entries to the Row Configuration JSON with their `y` values and the new device's `host`
2. Name the new storage locations with the new letters (e.g. `G01`–`G60`)
3. No code changes needed

### Storage Location form — WLED tab

| Field | Description |
|-------|-------------|
| Controller Host | IP/hostname of the WLED device for this cabinet branch. Leave empty to inherit from parent or use the system default |
| LED Start Index | (Optional) Manual override: first LED column for this location |
| LED End Index | (Optional) Manual override: last LED column for this location (inclusive) |

The **manual override** (`LED Start` + `LED End`) takes priority over the name-based auto-detection. It uses y=0, height=1 and is intended for non-standard layouts or legacy configurations.

## Troubleshooting

**Drawer doesn't light up:**
1. Check the storage location name follows `[Letter][Number]` format exactly (no spaces, no extra characters)
2. Confirm the row letter exists in the Row Configuration JSON
3. Verify the WLED device is reachable: `curl http://192.168.1.37/json/state`
4. Check worker logs: `docker compose logs -f partdb-worker` — look for `Cannot resolve WLED segment` or `WLED HTTP request failed`

**Wrong drawer lights up:**
- Confirm column numbering: column 1 = left-most column (x=0). So `E35` → x=34 (0-indexed).
- Check `perDrawer` in the Row Configuration matches the physical layout.

**Entire panel lights up:**
- This happened historically when segment 0 (the base layer) was targeted instead of segment 1 (ELECTRODRAWER). The handler always targets segment id=1. If this recurs, verify the WLED firmware still has "ELECTRODRAWER" as segment 1.
