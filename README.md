# Composer Installers Dependency Search

An extension for [composer/installers](https://github.com/composer/installers)
which allows project dependencies to define installer types, and the install
locations for those types.

## [Composer Installers](https://github.com/composer/installers) Support

This project doesn't depend on the Composer Installers project, and it will not
be included as a dependency of this project. This project can be used completely
independently and it will support any custom installer path defined in a package
required by the project.

## How to Use

To use this project, require it as part of your composer project or library.

```
composer require roygoldman/composer-installers-discovery
```

Once this project is required, then any module installs will respect the
installer configuration of the other dependencies. Any project defining the
`installer-paths` key it the extra section of its `composer.json` will be
included in the package.

To add this to you project, simply defined this section in your project.

```
  "extra": {
    "installer-paths": {
      ...
      "path/to/libraries/{$name}/": ["type:library"]
      ...
    }
  }
```

## Limitations

There are some limitations to this module use case. Package discovery iterates
over each packages dependencies in the order they are defined in the `requires`
block of the package. This means that if multiple projects define path mapping
for the same package type, only the path for the first project will be used.

Additionally, if a package defines its own mappings, they will override the
mappings of projects which are required by them. However, if the same package
is required twice, and the high priority package isn't otherwise overridden, it
will be used instead of the dependent package.

As a result of these two limitations, the order of packages can directly effect
the mapping of the installer paths. If this is an issue, please file an issue
on github, so I can find a good solution for your use case.

### Overriding paths of dependencies

A root project is the highest authority on installer paths. This means that any
installer paths defined in the root package, will be used instead of those
defined by the dependencies. This allows a user to opt out of a dependency's
installer paths, or replace them.

## Support

If you have any questions, comments, or feedback on this module, please open an
issue on GitHub. I would like to solve any compatibility and use case issues as
well as incorporate new features into this project where its logical. If you
could spend some time debugging and possibly fixing issues, its appreciated and
pull requests are always welcome!
