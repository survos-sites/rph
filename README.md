# Role Playhouse

A Symfony 8.1 application for importing, reading, casting, and rehearsing scripts. It imports Fountain and desktop Celtx projects, preserves production metadata, and presents a static shotlist with keyboard controls, action overlays, and dialogue subtitles.

The next direction is **Cine-AI with a richer Symfony-owned shot corpus**, followed by data-driven Unity playback and avatar creation. Read the detailed [scene-player handoff](docs/scene-player-handoff.md) before resuming.

## Local setup

The app uses the shared Survos PostgreSQL service on port 5434, database `rph`.

```bash
composer install
php bin/console doctrine:database:create
php bin/console doctrine:migrations:migrate
php bin/console app:load path/to/script.fountain
symfony server:start -d
```

You can also place local `.fountain` or `.fount` files in the ignored `data/scripts/` directory and run `php bin/console app:load`.

Recovered demo inputs and their provenance are maintained separately; imported database records and generated audio are not reconstructed by a source checkout alone. The legacy Symfony 5 implementation remains in `~/sites/rph-5` as behavioral reference.

## Next candidates

- character selection and role-specific rehearsal views;
- casts, teams, and productions;
- a teleprompter/rehearsal mode using small AssetMapper controllers;
- import from Final Draft XML;
- Bard integration without duplicating the Shakespeare corpus.
