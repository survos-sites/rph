# RPH MCP

Symfony MCP bundle exposes `https://rph.wip/mcp`, or STDIO:

    php /Users/tac/sites/rph/bin/console mcp:server default

Tools: list_works, set_work, get_scenes, get_longest_scene,
get_character_lines, get_screenplay, preview_work, revise_line.

Select `xmas` with set_work. Selection belongs to the MCP session; every query
also accepts an explicit work for stateless callers. Character queries return
all dialogue with context and stable line IDs, without vector search.

preview_work returns an MCP App at ui://rph/preview. Omit scene for the entire
movie; pass scene="03" for the contiguous interval covering scene pictures.
The widget embeds the existing live OTIO/Remotion player. No MP4 is encoded.
The scene view starts at its interval and pauses at its end; the underlying
scrubber still exposes the full movie. Existing inaccurate scene assignments
can affect the range. Local rph.wip and its media must be reachable from the
MCP Apps client; registering the server in a client is separate from installing
this bundle. Host iframe/CSP support must also permit the local origin.

Queries use WorkCatalog / SqliteWorkCatalog. Production, review, OTIO and widget
read `data/catalogs/{work}.sqlite` via WorkingCatalog. JSON directories are frozen
import sources, not working data. `app:catalog:import` imports once and refuses to
overwrite existing catalogs. Legacy JSON-writing render commands are disabled.

`revise_line` requires an expected revision. It updates action text directly;
for dialogue it generates a local voice candidate before atomically updating
text, clip label and selected asset version. Existing cut timing is preserved;
compression over 1.25x is rejected instead of truncating speech. This is a first
synchronous editing operation, not the final async voice workflow. Revisions are
returned by query tools. SQLite migration was verified with exact OTIO equality
before editorial corrections. No JSON files are rewritten by editing.

Local development currently requires the sibling `../mono/lib/omc` Composer path
repository containing WorkingCatalog; release that library before deployment.

Validation: tests/Production, tests/Omc; STDIO initialize/list/call/resource
read and HTTP initialize/set_work/get_character_lines exercised locally.
