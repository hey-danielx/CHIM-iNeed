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
| **Sticky marker** | NPC has no food or water | Stored on the server and shown on the actor profile until they eat/drink |
| **First comment** | Marker is first set | NPC speaks one short in-character line |
| **Player talk** | You start a conversation while they are still needy | Mention chance slider (plugin page, default 40%) |
| **Bored repeat** | Still needy after ~1 game hour, and not already talking | A CHIM `bored` idle about that need |
| **Clear** | They eat or drink | `{Name} is no longer hungry.` |

If they already have food and eat it, nothing is sent to CHIM.

## Build release archives

```powershell
.\scripts\build-release.ps1
```

Pass `-Mo2PatchPath` when building locally if you also want the generated `ineed_actions.csv` and `.dwpkg` copied into an existing MO2 mod folder:

```powershell
.\scripts\build-release.ps1 -Mo2PatchPath "E:\path\to\mods\iNeed-CHIM patch"
```

Output in `release/`:

- `CHIM-iNeed.tar.gz` / `CHIM-iNeed.tar` — GitHub release assets the plugin manager downloads
- `iNeed-CHIM-Patch.zip` — Skyrim/MO2/Nexus package
- `CHIM-iNeed/<version>.dwpkg` — bundled inside the Skyrim zip
- `github-src/` — clean tree to push (plugin files at repo root, same layout as CHIM-Custom)

## GitHub release

1. Update the version in `manifest.json` and `dwemer-package.json`, then push the change.
2. Create and publish a GitHub release whose tag matches that version, such as `1.0.1`.
3. The **Package release assets** workflow builds and attaches `CHIM-iNeed.tar.gz`, `CHIM-iNeed.tar`, and `iNeed-CHIM-Patch.zip` automatically.

You can also run the workflow manually before publishing a release. The three packages will be available together as a workflow artifact for inspection.

Asset names must match exactly. The installer looks for `CHIM-iNeed.tar.gz`.

To appear in CHIM's built-in plugin list, submit a PR to AIAgent `ui/data/plugin_repository.json`. The bundled `.dwpkg` already installs the plugin when the player loads a save, so that PR is optional.

## Requirements

iNeed, SKSE, CHIM (`AIAgent.esp`).
