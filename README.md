# Flex Inline

Composer plugin for Symfony Flex to use recipes in composer.json

## Usage

To use inline recipes add `kwivix/flexinline` to composer.json of your project.

## Create recipes

Inside the composer.json add under [extra](https://getcomposer.org/doc/04-schema.md#extra) the key `recipe-manifest` with a JSON-object. The manifest supports the same [flex configurators](https://github.com/symfony/recipes#configurators) as the official flex. Except for `copy-from-recipe`, since it would be identical to `copy-from-package`.

``` json
{
    "name": "example/package",
    "type": "project",
    "extra": {
        "recipe-manifest": {
            "gitignore": [
                ".env"
            ],
            "copy-from-package": {
                "bin/check.php": "%BIN_DIR%/check.php"
            }
        }
    }
}
```
