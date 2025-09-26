# Importing shared blocks

Twill 3.{{< version >}} introduces a more modular block registry and a first-party importer command that makes it easier to reuse
blocks across projects.

## Modular block registration

The block registry now lives inside `A17\Twill\Services\Blocks\BlockRegistry`. It is resolved as a singleton and orchestrates
all block directories, dynamic repeaters and component-based blocks in one place. This makes it possible to register additional
directories at runtime without reloading the application. If you previously manipulated static properties on `TwillBlocks`, you can
now call the higher-level helpers provided by the facade:

```php
TwillBlocks::registerSourceDirectory(resource_path('views/twill/marketing'), \A17\Twill\Services\Blocks\Block::TYPE_BLOCK, 'app');
TwillBlocks::registerPackageRepeatersDirectory($packagePath, 'Vendor\\Package\\Blocks');
```

The registry will lazily read the directories and merge their block definitions only when they are needed, which keeps bootstrapping
fast even when many block packs are available.

## Importing blocks from archives or folders

Blocks can now be shared by zipping (or sharing directly) a directory that contains a `blocks` folder or a `views/twill/blocks`
structure. Developers can import those bundles using the new Artisan command:

```bash
php artisan twill:block:import blocks/my-team-package.zip
```

The command accepts either a directory or a `.zip` archive. By default, existing block views are not overwritten; pass `--force`
if you want to replace local files. During the import the command reports which files were copied or skipped and surfaces any
warnings (for example, empty directories).

If the bundle contains a `twill-block.json` manifest the importer reads it to locate view directories and exposes suggested
configuration additions. Those configuration snippets are printed as JSON so they can be merged into `config/twill.php` manually.

This workflow makes it straightforward to share curated block libraries between projects or teams without manually copying files.
