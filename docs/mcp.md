# RPH MCP

Symfony MCP bundle exposes `https://rph.wip/mcp`, or STDIO:

    php /Users/tac/sites/rph/bin/console mcp:server default

Tools: list_works, set_work, get_scenes, get_longest_scene,
get_character_lines, get_screenplay, preview_work.

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

Queries use WorkCatalog, currently JsonWorkCatalog. Replace that adapter with
the relational working catalog when its API is ready. PreviewModel still uses
the existing OMC reader and must migrate alongside the renderer. No mutation
tools are exposed yet. Do not treat this as completion of the SQLite migration.

Validation: tests/Production, tests/Omc; STDIO initialize/list/call/resource
read and HTTP initialize/set_work/get_character_lines exercised locally.
