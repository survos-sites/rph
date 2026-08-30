# Role Playhouse

A Symfony 8.1 application for importing, reading, casting, and rehearsing scripts. The first vertical slice imports Fountain files into a structured script, scene, character, and element graph and renders them with Tabler.

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

No scripts or imported data are committed. The legacy Symfony 5 implementation remains in `~/sites/rph-5` as behavioral reference.

## Next candidates

- character selection and role-specific rehearsal views;
- casts, teams, and productions;
- a teleprompter/rehearsal mode using small AssetMapper controllers;
- import from Final Draft XML;
- Bard integration without duplicating the Shakespeare corpus.
