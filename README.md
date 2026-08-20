# iNeed CHIM

CHIM support is **NPC-only**. The player is human and roleplays hunger or thirst in chat. This package marks followers and can trigger a spoken line when they need food or water.

iNeed does not give followers a 0-110 meter. It uses two factions as markers:

- `_SNHungryFaction` when they need food and have none
- `_SNThirstyFaction` when they need a drink and have none

Follower Needs must be enabled in the iNeed MCM (`Default` or `Simulated`).

## What players install

Two pieces ship together:

1. **Skyrim patch** (`iNeed-CHIM-Patch.zip`) — compiled Papyrus, `CHIM/ineed_actions.csv`, and a bundled server plugin under `CHIM/server-plugins/CHIM-iNeed/`.
2. **CHIM server plugin** (`CHIM-iNeed.tar.gz`) — for the CHIM plugin manager, or auto-installed from the Skyrim zip when the game starts.

Load the Skyrim patch **below** `iNeed - Food, Water and Sleep` and `AIAgent`.

## Triggers and markers

| Piece | When | What CHIM sees |
| --- | --- | --- |
| **Marker** | NPC has no food or water | `{Name} is hungry and has no food.` stays in recent context until they eat/drink |
| **Trigger** | Marker is first set, or Simulated mode starts a hunger/thirst cycle | NPC speaks one short in-character line |
| **Clear** | They eat or drink | `{Name} is no longer hungry.` |

If they already have food and eat it, nothing is sent to CHIM.

## Build release archives

```powershell
.\scripts\build-release.ps1
```

Optional, once the GitHub repo exists:

```powershell
.\scripts\build-release.ps1 -GitHubRepo "hey-danielx/CHIM-iNeed"
```

Output in `release/`:

- `CHIM-iNeed.tar.gz` / `CHIM-iNeed.tar` — upload as the GitHub release assets the plugin manager downloads
- `iNeed-CHIM-Patch.zip` — Skyrim/MO2/Nexus package
- `CHIM-iNeed/1.0.0.dwpkg` — bundled inside the Skyrim zip
- `github-src/` — clean tree to push (plugin files at repo root, same layout as CHIM-Custom)

The Skyrim zip also copies `ineed_actions.csv` and the `.dwpkg` into your MO2 mod `iNeed-CHIM patch`.

## GitHub release

1. Create a public repo named `CHIM-iNeed`.
2. Push `release/github-src`.
3. Create a release tagged `1.0.0` and attach `CHIM-iNeed.tar.gz`, `CHIM-iNeed.tar`, and `iNeed-CHIM-Patch.zip`.
4. Asset names must match exactly. The installer looks for `CHIM-iNeed.tar.gz`.

To appear in CHIM's built-in plugin list, submit a PR to AIAgent `ui/data/plugin_repository.json`. The bundled `.dwpkg` already installs the plugin when the player loads a save, so that PR is optional.

## Requirements

iNeed, SKSE, CHIM (`AIAgent.esp`).
